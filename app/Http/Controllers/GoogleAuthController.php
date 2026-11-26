<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\IntegrationFailure;
use App\Models\User;
use App\Services\AdminOperationalNotificationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    private const AGENCY_SIGNUP_INTENT = 'google_agency_signup_intent_at';

    private const PENDING_AGENCY_REGISTRATION = 'pending_google_agency_registration';

    private const AGENCY_REGISTRATION_TTL_SECONDS = 900;

    public function redirect(): RedirectResponse
    {
        try {
            return $this->googleRedirectResponse();
        } catch (Throwable $exception) {
            $this->recordGoogleFailure('sign_in_redirect', $exception);

            return $this->redirectToLoginWithError('Google sign-in is temporarily unavailable. Please try again.');
        }
    }

    public function agencyRedirect(Request $request): RedirectResponse
    {
        $request->session()->forget(self::PENDING_AGENCY_REGISTRATION);
        $request->session()->put(self::AGENCY_SIGNUP_INTENT, now()->timestamp);

        try {
            return $this->googleRedirectResponse();
        } catch (Throwable $exception) {
            $this->recordGoogleFailure('agency_signup_redirect', $exception);
            $request->session()->forget(self::AGENCY_SIGNUP_INTENT);

            return redirect()->route('register.agency')->withErrors([
                'email' => 'Google signup is temporarily unavailable. Please complete the form or try again.',
            ]);
        }
    }

    public function showAgencyRegistration(Request $request): View|RedirectResponse
    {
        $pendingRegistration = null;

        if ($request->session()->has(self::PENDING_AGENCY_REGISTRATION)) {
            $pendingRegistration = $this->pendingAgencyRegistration($request);

            if (! $pendingRegistration) {
                return redirect()->route('register.agency')->withErrors([
                    'email' => 'Your Google signup session expired. Please start again.',
                ]);
            }
        }

        return view('auth.register', [
            'pendingGoogleAgencyRegistration' => $pendingRegistration,
        ]);
    }

    public function completeAgencyRegistration(
        Request $request,
        AdminOperationalNotificationService $adminNotifications,
    ): RedirectResponse {
        $pendingRegistration = $this->pendingAgencyRegistration($request);

        if (! $pendingRegistration) {
            return redirect()->route('register.agency')->withErrors([
                'email' => 'Your Google signup session expired. Please start again.',
            ]);
        }

        $data = $request->validate([
            'agency_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'terms' => ['accepted'],
        ], [
            'password.min' => 'Your password must be at least 6 characters.',
            'password.confirmed' => 'Your passwords do not match.',
            'terms.accepted' => 'You must accept the terms to create an account.',
        ]);

        if ($this->googleAgencyIdentityExists($pendingRegistration)) {
            return $this->agencySignupConflict();
        }

        try {
            $user = DB::transaction(function () use ($data, $pendingRegistration, $adminNotifications): User {
                $user = new User([
                    'name' => $pendingRegistration['name'],
                    'email' => $pendingRegistration['email'],
                    'password' => $data['password'],
                    'google_id' => $pendingRegistration['google_id'],
                ]);
                $user->forceFill(['role' => UserRole::AGENCY_OWNER]);
                $user->email_verified_at = Carbon::now();
                $user->save();

                $agency = $user->agency()->create([
                    'company_name' => $data['agency_name'],
                    'description' => '',
                    'license_number' => '',
                    'email' => $pendingRegistration['email'],
                ]);

                DB::afterCommit(fn () => $adminNotifications->agencyRegistered($agency));

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->agencySignupConflict();
        }

        $request->session()->forget(self::PENDING_AGENCY_REGISTRATION);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('auth.success')->with([
            'message' => 'Registration successful',
            'success_redirect' => route('login'),
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->session()->has(self::AGENCY_SIGNUP_INTENT)) {
            $intentTimestamp = $request->session()->pull(self::AGENCY_SIGNUP_INTENT);
            $request->session()->forget(self::PENDING_AGENCY_REGISTRATION);

            if (! $this->isFreshSessionTimestamp($intentTimestamp)) {
                return redirect()->route('register.agency')->withErrors([
                    'email' => 'Your Google signup session expired. Please start again.',
                ]);
            }

            if ($request->query->has('error')) {
                return redirect()->route('register.agency')->withErrors([
                    'email' => 'Google signup was cancelled. You can complete the form or try again.',
                ]);
            }

            return $this->handleAgencyCallback($request);
        }

        if ($request->query->has('error')) {
            return $this->redirectToLoginWithError('Google sign-in was cancelled. You can try again or use your password.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            $this->recordGoogleFailure('sign_in_callback', $exception);
            $safeMessage = preg_replace('/https?:\/\/[^\s]+/i', '[URL redacted]', $exception->getMessage());
            $safeMessage = preg_replace(
                '/\b(code|state|error|access_token|refresh_token|id_token|client_secret|client_id|password|authorization|token)\b([\'"]?\s*[:=]\s*[\'"]?)[^,\s;&\'"]+/i',
                '$1$2[redacted]',
                $safeMessage
            );

            Log::warning('Google OAuth callback verification failed.', [
                'exception_class' => $exception::class,
                'exception_message' => $safeMessage,
                'exception_file' => $exception->getFile(),
                'exception_line' => $exception->getLine(),
                'has_code' => $request->query->has('code'),
                'has_state' => $request->query->has('state'),
                'has_error' => $request->query->has('error'),
            ]);

            return $this->redirectToLoginWithError('We could not verify your Google sign-in. Please try again.');
        }

        $identity = [
            'google_id' => $googleUser->getId(),
            'email' => $googleUser->getEmail(),
            'name' => $googleUser->getName(),
        ];
        $googleData = $googleUser instanceof AbstractUser ? $googleUser->getRaw() : [];
        $emailVerified = $this->googleEmailIsVerified($googleData);

        $validator = Validator::make($identity, [
            'google_id' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:191'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->redirectToLoginWithError('Google did not provide the account information required to sign in.');
        }

        $googleId = $identity['google_id'];
        $email = Str::lower(trim($identity['email']));
        $authoritativeEmail = $this->hasAuthoritativeGoogleEmail($email, $googleData, $emailVerified);

        $user = User::query()->where('google_id', $googleId)->first();

        if ($user) {
            return $this->authenticate($request, $user, 'Login successful');
        }

        if ($user = $this->findUserByEmail($email)) {
            return $this->linkGoogleAccount($request, $user, $googleId, $authoritativeEmail);
        }

        try {
            $user = new User([
                'name' => trim($identity['name']),
                'email' => $email,
                'password' => Str::random(64),
                'google_id' => $googleId,
            ]);
            $user->forceFill(['role' => UserRole::TRAVELER]);
            $user->email_verified_at = $emailVerified ? now() : null;
            $user->save();
        } catch (UniqueConstraintViolationException) {
            $user = User::query()->where('google_id', $googleId)->first();

            if ($user) {
                return $this->authenticate($request, $user, 'Login successful');
            }

            if ($user = $this->findUserByEmail($email)) {
                return $this->linkGoogleAccount($request, $user, $googleId, $authoritativeEmail);
            }

            return $this->redirectToLoginWithError('Google sign-in could not be completed. Please try again.');
        }

        return $this->authenticate($request, $user, 'Registration successful');
    }

    private function handleAgencyCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            $this->recordGoogleFailure('agency_signup_callback', $exception);

            return redirect()->route('register.agency')->withErrors([
                'email' => 'We could not verify your Google account. Please try again.',
            ]);
        }

        $googleData = $googleUser instanceof AbstractUser ? $googleUser->getRaw() : [];
        $identity = [
            'google_id' => $googleUser->getId(),
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
        ];
        $validator = Validator::make($identity, [
            'google_id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:191'],
        ]);

        if ($validator->fails() || ! $this->googleEmailIsVerified($googleData)) {
            return redirect()->route('register.agency')->withErrors([
                'email' => 'Google must provide a valid, verified email address for agency signup.',
            ]);
        }

        $identity['google_id'] = trim($identity['google_id']);
        $identity['name'] = trim($identity['name']);
        $identity['email'] = Str::lower(trim($identity['email']));

        if ($identity['google_id'] === '' || $identity['name'] === '' || $this->googleAgencyIdentityExists($identity)) {
            return $this->agencySignupConflict();
        }

        $request->session()->put(self::PENDING_AGENCY_REGISTRATION, [
            'google_id' => $identity['google_id'],
            'name' => $identity['name'],
            'email' => $identity['email'],
            'email_verified' => true,
            'created_at' => now()->timestamp,
        ]);

        return redirect()->route('register.agency');
    }

    /** @return array{google_id: string, name: string, email: string, email_verified: true, created_at: int}|null */
    private function pendingAgencyRegistration(Request $request): ?array
    {
        $pendingRegistration = $request->session()->get(self::PENDING_AGENCY_REGISTRATION);

        if (! is_array($pendingRegistration)) {
            return null;
        }

        $googleId = $pendingRegistration['google_id'] ?? null;
        $name = $pendingRegistration['name'] ?? null;
        $email = $pendingRegistration['email'] ?? null;
        $createdAt = $pendingRegistration['created_at'] ?? null;

        if (! is_string($googleId)
            || trim($googleId) === ''
            || Str::length($googleId) > 255
            || ! is_string($name)
            || trim($name) === ''
            || Str::length($name) > 255
            || ! is_string($email)
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || Str::length($email) > 191
            || ($pendingRegistration['email_verified'] ?? false) !== true
            || ! $this->isFreshSessionTimestamp($createdAt)) {
            $request->session()->forget(self::PENDING_AGENCY_REGISTRATION);

            return null;
        }

        return [
            'google_id' => trim($googleId),
            'name' => trim($name),
            'email' => Str::lower(trim($email)),
            'email_verified' => true,
            'created_at' => (int) $createdAt,
        ];
    }

    private function isFreshSessionTimestamp(mixed $timestamp): bool
    {
        if (! is_int($timestamp) && (! is_string($timestamp) || ! ctype_digit($timestamp))) {
            return false;
        }

        $age = now()->timestamp - (int) $timestamp;

        return $age >= 0 && $age <= self::AGENCY_REGISTRATION_TTL_SECONDS;
    }

    /** @param array{google_id: string, email: string} $identity */
    private function googleAgencyIdentityExists(array $identity): bool
    {
        return User::withTrashed()
            ->where(function ($query) use ($identity): void {
                $query
                    ->whereRaw('LOWER(email) = ?', [Str::lower($identity['email'])])
                    ->orWhere('google_id', $identity['google_id']);
            })
            ->exists();
    }

    private function agencySignupConflict(): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'email' => 'A TravelConnect account already uses this email or Google account. Please sign in instead.',
        ]);
    }

    private function googleEmailIsVerified(array $googleData): bool
    {
        $emailVerified = $googleData['email_verified'] ?? false;

        return filter_var(is_scalar($emailVerified) ? $emailVerified : false, FILTER_VALIDATE_BOOLEAN);
    }

    private function hasAuthoritativeGoogleEmail(string $email, array $googleData, bool $emailVerified): bool
    {
        if (Str::endsWith($email, '@gmail.com')) {
            return true;
        }

        $hostedDomain = $googleData['hd'] ?? null;

        return $emailVerified
            && is_string($hostedDomain)
            && filter_var($hostedDomain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
            && Str::lower($hostedDomain) === Str::afterLast($email, '@');
    }

    private function linkGoogleAccount(
        Request $request,
        User $user,
        string $googleId,
        bool $authoritativeEmail
    ): RedirectResponse {
        if ($user->isAdmin()) {
            return $this->rejectAdminGoogleSignIn();
        }

        if ($user->google_id !== null) {
            if ($user->google_id === $googleId) {
                return $this->authenticate($request, $user, 'Login successful');
            }

            return $this->redirectToLoginWithError(
                'This account is already linked to a different Google account. Sign in with your password instead.'
            );
        }

        if (! $authoritativeEmail) {
            return $this->redirectToLoginWithError(
                'We could not safely link this Google account. Sign in with your existing password and explicitly link Google.'
            );
        }

        try {
            $user->google_id = $googleId;
            $user->save();
        } catch (UniqueConstraintViolationException) {
            return $this->redirectToLoginWithError(
                'This Google account is already connected to a different TravelConnect account. Sign in to the account already linked to Google.'
            );
        }

        return $this->authenticate($request, $user, 'Login successful');
    }

    private function findUserByEmail(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    private function accountAlreadyExists(): RedirectResponse
    {
        return $this->redirectToLoginWithError(
            'An account with this email already exists. Sign in with your password. Connecting this account to Google requires an explicit account-linking flow, which is not available yet.'
        );
    }

    private function authenticate(Request $request, User $user, string $message): RedirectResponse
    {
        if ($user->isAdmin()) {
            return $this->rejectAdminGoogleSignIn();
        }

        Auth::login($user);
        $request->session()->regenerate();

        $successRedirect = $user->isAgencyOwner() ? route('agency.dashboard') : route('dashboard');

        return redirect()->route('auth.success')->with([
            'message' => $message,
            'success_redirect' => $successRedirect,
        ]);
    }

    private function rejectAdminGoogleSignIn(): RedirectResponse
    {
        return $this->redirectToLoginWithError(__('messages.admin_google_signin_requires_password'));
    }

    private function redirectToLoginWithError(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }

    private function googleRedirectResponse(): RedirectResponse
    {
        $response = Socialite::driver('google')->redirect();
        $googleUrl = $response->getTargetUrl();
        $response->setContent(view('auth.google-redirect', compact('googleUrl'))->render());
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function recordGoogleFailure(string $operation, Throwable $exception): void
    {
        IntegrationFailure::query()->create([
            'integration' => 'google_oauth',
            'operation' => $operation,
            'safe_message' => __('messages.admin_alerts.integration_messages.google_oauth_failure'),
            'exception_class' => $exception::class,
        ]);
    }
}
