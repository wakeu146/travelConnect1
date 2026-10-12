<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Mail\AdminPasswordResetNoticeMail;
use App\Models\User;
use App\Notifications\PasswordResetCode;
use App\Services\AdminAuthenticationService;
use App\Services\AdminLoginSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthController extends Controller
{
    public function updateProfilePhoto(Request $request): RedirectResponse
    {
        $request->validate(['profile_photo' => ['required', 'image', 'max:2048']]);
        $user = $request->user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->update(['profile_photo_path' => $request->file('profile_photo')->store('profile-photos', 'public')]);

        return back()->with('status', 'Profile photo updated.');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+237\s?[0-9\s-]{9,15}$/'],
        ]);

        if (! empty($data['phone'])) {
            $digits = preg_replace('/\D+/', '', $data['phone']);
            if (preg_match('/^237\d{9,10}$/', $digits)) {
                $data['phone'] = '+237 '.trim(chunk_split(substr($digits, 3), 3, ' '));
            }
        }

        $user->update($data);

        return back()->with('status', 'Profile information updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('password_status', __('messages.password_updated_successfully'));
    }

    public function registerTraveler(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'terms' => ['accepted'],
        ], [
            'email.unique' => 'An account with this email already exists. Please use another email or log in.',
            'password.min' => 'Your password must be at least 6 characters.',
            'password.confirmed' => 'Your passwords do not match.',
            'terms.accepted' => 'You must accept the terms to create an account.',
        ]);

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->forceFill(['role' => UserRole::TRAVELER])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('auth.success')->with([
            'message' => 'Registration successful',
            'success_redirect' => route('login'),
        ]);
    }

    public function registerAgency(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'agency_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'terms' => ['accepted'],
        ], [
            'email.unique' => 'An account with this email already exists. Please use another email or log in.',
            'password.min' => 'Your password must be at least 6 characters.',
            'password.confirmed' => 'Your passwords do not match.',
            'terms.accepted' => 'You must accept the terms to create an account.',
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $user->forceFill(['role' => UserRole::AGENCY_OWNER])->save();

            $user->agency()->create([
                'company_name' => $data['agency_name'],
                'description' => '',
                'license_number' => '',
                'email' => $data['email'],
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('auth.success')->with([
            'message' => 'Registration successful',
            'success_redirect' => route('login'),
        ]);
    }

    public function login(
        Request $request,
        AdminAuthenticationService $adminAuthentication,
        AdminLoginSecurityService $adminSecurity,
    ): RedirectResponse {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $credentials['email'] = Str::lower($credentials['email']);
        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user?->isAdmin() && $adminSecurity->isPasswordLockedOut($user)) {
            $adminSecurity->recordPasswordLockoutAttempt($user, $credentials['email'], $request);

            return back()->withErrors(['email' => __('messages.credentials_invalid')])->onlyInput('email');
        }

        if (! Auth::validate($credentials)) {
            $adminSecurity->recordFailedPasswordAttempt($user, $credentials['email'], $request);

            return back()->withErrors(['email' => __('messages.credentials_invalid')])->onlyInput('email');
        }

        if ($user?->isAdmin()) {
            try {
                $adminAuthentication->begin($user, $request);
            } catch (TransportExceptionInterface $exception) {
                Log::warning('Admin login code email could not be delivered.', [
                    'admin_id' => $user->id,
                    'exception' => $exception,
                ]);

                return back()->withErrors(['email' => __('messages.admin_login_code_delivery_failed')])->onlyInput('email');
            }

            return redirect()->route('admin.login.verify', ['lang' => app()->getLocale()])
                ->with('status', $adminAuthentication->adminUsesAuthenticator($user)
                    ? __('messages.admin_totp_login_required')
                    : __('messages.admin_login_code_sent'));
        }

        Auth::login($user, $request->boolean('remember'));
        $successRedirect = route('dashboard');

        if ($user->isAgencyOwner()) {
            $successRedirect = route('agency.dashboard');
        }

        $request->session()->regenerate();

        return redirect()->route('auth.success')->with([
            'message' => 'Login successful',
            'success_redirect' => $successRedirect,
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $email = Str::lower($request->validate(['email' => ['required', 'email']])['email']);
        $user = User::where('email', $email)->first();
        $neutralMessage = __('messages.password_reset_neutral');

        if ($user?->isAdmin()) {
            try {
                Mail::to($user->email)->send(new AdminPasswordResetNoticeMail($user->name));
            } catch (TransportExceptionInterface $exception) {
                Log::warning('Admin password reset notice email could not be delivered.', [
                    'admin_id' => $user->id,
                    'exception' => $exception,
                ]);
            }

            Log::notice('Self-service password reset requested for an admin account.', [
                'admin_id' => $user->id,
                'ip_address' => $request->ip(),
            ]);

            $request->session()->put('password_reset_email', $email);

            return redirect()->route('password.code')->with('status', $neutralMessage);
        }

        if (! $user) {
            $this->performDummyResetWork();
            $request->session()->put('password_reset_email', $email);

            return redirect()->route('password.code')->with('status', $neutralMessage);
        }

        $code = (string) random_int(100000, 999999);
        DB::table('password_reset_codes')->updateOrInsert(
            ['email' => $email],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        try {
            $user->notify(new PasswordResetCode($code));
        } catch (TransportExceptionInterface $exception) {
            DB::table('password_reset_codes')->where('email', $email)->delete();
            Log::error('Password reset code email could not be sent.', [
                'email' => $email,
                'exception' => $exception,
            ]);

            return back()->with('status', $neutralMessage);
        }

        $request->session()->put('password_reset_email', $email);

        return redirect()->route('password.code')->with('status', $neutralMessage);
    }

    public function showCodeForm(Request $request): View
    {
        return view('auth.verify-reset-code', ['email' => session('password_reset_email')]);
    }

    public function verifyResetCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);
        $email = Str::lower($data['email']);
        $user = User::query()->where('email', $email)->first();
        if ($user?->isAdmin()) {
            return back()->withErrors(['code' => 'This verification code is invalid or expired.'])->withInput();
        }

        $resetCode = DB::table('password_reset_codes')->where('email', $email)->first();

        if (! $resetCode || now()->greaterThan($resetCode->expires_at) || $resetCode->attempts >= 5) {
            return back()->withErrors(['code' => 'This verification code is invalid or expired.'])->withInput();
        }

        if (! Hash::check($data['code'], $resetCode->code_hash)) {
            DB::table('password_reset_codes')->where('email', $email)->increment('attempts');

            return back()->withErrors(['code' => 'This verification code is incorrect.'])->withInput();
        }

        DB::table('password_reset_codes')->where('email', $email)->delete();
        $request->session()->put('password_reset_verified_email', $email);

        return redirect()->route('password.reset');
    }

    public function showResetForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('password_reset_verified_email')) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password');
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $email = $request->session()->get('password_reset_verified_email');
        if (! $email) {
            return redirect()->route('password.request');
        }

        if (User::query()->where('email', $email)->where('role', UserRole::ADMIN)->exists()) {
            $request->session()->forget(['password_reset_email', 'password_reset_verified_email']);

            return redirect()->route('password.request')->with('status', __('messages.password_reset_neutral'));
        }

        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'confirmed']]);
        $user = User::where('email', $email)->first();

        if (! $user) {
            return redirect()->route('password.request')->withErrors(['email' => 'We could not find that account.']);
        }

        $user->forceFill([
            'password' => $data['password'],
            'remember_token' => Str::random(60),
        ])->save();
        $request->session()->forget(['password_reset_email', 'password_reset_verified_email']);

        return redirect()->route('login')->with('status', 'Your password has been reset.');
    }

    private function performDummyResetWork(): void
    {
        Hash::make(Str::random(32));
    }
}
