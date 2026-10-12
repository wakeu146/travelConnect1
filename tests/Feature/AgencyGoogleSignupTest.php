<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class AgencyGoogleSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_google_entry_stores_intent_before_starting_socialite(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(new RedirectResponse('https://accounts.google.com/oauth'));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $response = $this->get(route('google.agency.redirect'));

        $response->assertRedirect('https://accounts.google.com/oauth');
        $response->assertSessionHas('google_agency_signup_intent_at', fn ($timestamp): bool => is_int($timestamp));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_normal_agency_form_keeps_password_registration_and_shows_agency_google_entry(): void
    {
        $response = $this->get(route('register.agency'));

        $response->assertSee('action="'.route('register.agency.store').'"', false);
        $response->assertSee('href="'.route('google.agency.redirect').'"', false);
        $response->assertSee('Sign up with Google');
        $response->assertSee('name="agency_name"', false);
        $response->assertSee('name="password"', false);
    }

    public function test_agency_callback_stages_verified_identity_without_creating_a_user(): void
    {
        $this->withSession(['google_agency_signup_intent_at' => now()->timestamp]);
        $this->fakeGoogleUser([
            'id' => 'agency-google-id',
            'name' => 'Google Agency Contact',
            'email' => 'agency@example.com',
            'email_verified' => 'true',
        ]);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('register.agency'));
        $response->assertSessionMissing('google_agency_signup_intent_at');
        $response->assertSessionHas('pending_google_agency_registration', function (array $pending): bool {
            return $pending['google_id'] === 'agency-google-id'
                && $pending['name'] === 'Google Agency Contact'
                && $pending['email'] === 'agency@example.com'
                && $pending['email_verified'] === true;
        });
        $this->assertDatabaseCount('users', 0);

        $form = $this->get(route('register.agency'));

        $form->assertSee('value="Google Agency Contact"', false);
        $form->assertSee('value="agency@example.com"', false);
        $form->assertSee('action="'.route('google.agency.complete').'"', false);
        $form->assertDontSee('name="google_id"', false);
        $form->assertDontSee('name="email"', false);
        $form->assertDontSee('name="name"', false);
    }

    public function test_completed_google_agency_signup_creates_owner_and_supports_password_and_google_login(): void
    {
        $this->withSession(['pending_google_agency_registration' => $this->pendingGoogleIdentity()]);

        $response = $this->post(route('google.agency.complete'), [
            'agency_name' => 'Summit Travel Co',
            'password' => 'chosen-password',
            'password_confirmation' => 'chosen-password',
            'terms' => '1',
            'name' => 'Untrusted Submitted Name',
            'email' => 'attacker@example.net',
            'google_id' => 'attacker-google-id',
            'role' => UserRole::ADMIN->value,
        ]);

        $response->assertRedirect(route('auth.success'));
        $this->assertAuthenticated();
        $this->assertDatabaseCount('users', 1);
        $user = User::query()->where('email', 'agency@example.com')->firstOrFail();
        $this->assertSame('agency-google-id', $user->google_id);
        $this->assertSame('Google Agency Contact', $user->name);
        $this->assertSame(UserRole::AGENCY_OWNER, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('chosen-password', $user->password));
        $this->assertDatabaseHas('agencies', [
            'user_id' => $user->id,
            'company_name' => 'Summit Travel Co',
            'email' => 'agency@example.com',
        ]);
        $this->assertInstanceOf(Agency::class, $user->fresh()->agency);
        $response->assertSessionMissing('pending_google_agency_registration');
        $this->assertSame(route('login'), session('success_redirect'));

        Auth::logout();
        $passwordLogin = $this->post(route('login.store'), [
            'email' => 'agency@example.com',
            'password' => 'chosen-password',
        ]);

        $passwordLogin->assertRedirect(route('auth.success'));
        $this->assertAuthenticatedAs($user);

        Auth::logout();
        $this->fakeGoogleUser([
            'id' => 'agency-google-id',
            'name' => 'Updated Provider Name',
            'email' => 'agency@example.com',
            'email_verified' => true,
        ]);

        $googleLogin = $this->get(route('google.callback'));

        $googleLogin->assertRedirect(route('auth.success'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::query()->count());
    }

    public function test_agency_signup_refuses_an_existing_email_without_changing_the_user(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'agency@example.com',
            'role' => UserRole::TRAVELER,
            'google_id' => null,
        ]);
        $this->withSession(['pending_google_agency_registration' => $this->pendingGoogleIdentity()]);

        $response = $this->post(route('google.agency.complete'), $this->validAgencySubmission());

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(UserRole::TRAVELER, $existingUser->fresh()->role);
        $this->assertNull($existingUser->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('agencies', 0);
    }

    public function test_agency_signup_refuses_a_google_id_owned_by_another_user(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'different@example.com',
            'role' => UserRole::TRAVELER,
            'google_id' => 'agency-google-id',
        ]);
        $this->withSession(['pending_google_agency_registration' => $this->pendingGoogleIdentity()]);

        $response = $this->post(route('google.agency.complete'), $this->validAgencySubmission());

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(UserRole::TRAVELER, $existingUser->fresh()->role);
        $this->assertSame('agency-google-id', $existingUser->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('agencies', 0);
    }

    public function test_expired_google_agency_registration_cannot_create_an_account(): void
    {
        $pendingRegistration = $this->pendingGoogleIdentity();
        $pendingRegistration['created_at'] = now()->subMinutes(16)->timestamp;
        $this->withSession(['pending_google_agency_registration' => $pendingRegistration]);

        $response = $this->post(route('google.agency.complete'), $this->validAgencySubmission());

        $response->assertRedirect(route('register.agency'));
        $response->assertSessionHasErrors('email');
        $response->assertSessionMissing('pending_google_agency_registration');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('agencies', 0);
    }

    public function test_unverified_google_identity_cannot_start_agency_registration(): void
    {
        $this->withSession(['google_agency_signup_intent_at' => now()->timestamp]);
        $this->fakeGoogleUser([
            'id' => 'unverified-google-id',
            'name' => 'Unverified Contact',
            'email' => 'unverified@example.com',
            'email_verified' => 'false',
        ]);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('register.agency'));
        $response->assertSessionHasErrors('email');
        $response->assertSessionMissing('google_agency_signup_intent_at');
        $response->assertSessionMissing('pending_google_agency_registration');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_cancellation_clears_agency_intent_and_returns_to_the_agency_form(): void
    {
        $this->withSession(['google_agency_signup_intent_at' => now()->timestamp]);

        $response = $this->get(route('google.callback').'?error=access_denied');

        $response->assertRedirect(route('register.agency'));
        $response->assertSessionHasErrors('email');
        $response->assertSessionMissing('google_agency_signup_intent_at');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_pending_identity_cannot_create_an_account(): void
    {
        $this->withSession(['pending_google_agency_registration' => [
            'google_id' => 'agency-google-id',
            'name' => 'Google Agency Contact',
            'email' => 'agency@example.com',
            'email_verified' => false,
            'created_at' => now()->timestamp,
        ]]);

        $response = $this->post(route('google.agency.complete'), $this->validAgencySubmission());

        $response->assertRedirect(route('register.agency'));
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('agencies', 0);
    }

    /** @param array<string, mixed> $attributes */
    private function fakeGoogleUser(array $attributes): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')
            ->once()
            ->andReturn(GoogleUser::fake($attributes));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);
    }

    /** @return array{google_id: string, name: string, email: string, email_verified: true, created_at: int} */
    private function pendingGoogleIdentity(): array
    {
        return [
            'google_id' => 'agency-google-id',
            'name' => 'Google Agency Contact',
            'email' => 'agency@example.com',
            'email_verified' => true,
            'created_at' => now()->timestamp,
        ];
    }

    /** @return array{agency_name: string, password: string, password_confirmation: string, terms: string} */
    private function validAgencySubmission(): array
    {
        return [
            'agency_name' => 'Summit Travel Co',
            'password' => 'chosen-password',
            'password_confirmation' => 'chosen-password',
            'terms' => '1',
        ];
    }
}
