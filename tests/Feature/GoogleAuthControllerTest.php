<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_sign_in_uses_an_http_redirect_and_starts_a_session(): void
    {
        $googleUrl = 'https://accounts.google.com/oauth';
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(new RedirectResponse($googleUrl));
        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->get(route('google.redirect'));

        $response
            ->assertRedirect($googleUrl)
            ->assertDontSee('Redirecting to Google sign-in');

        $this->assertNotEmpty($response->headers->all('set-cookie'));
    }

    public function test_links_a_gmail_account_without_changing_existing_account_data(): void
    {
        $verifiedAt = now()->subDays(5);
        $passwordHash = Hash::make('existing-password');
        $user = User::factory()->create([
            'name' => 'Existing Agency Owner',
            'email' => 'owner@gmail.com',
            'email_verified_at' => $verifiedAt,
            'password' => $passwordHash,
            'phone' => '+15555550123',
            'role' => UserRole::AGENCY_OWNER,
            'google_id' => null,
        ]);
        $agency = Agency::factory()->create(['user_id' => $user->id]);
        $this->fakeGoogleUser([
            'id' => 'google-owner-id',
            'email' => 'OWNER@gmail.com',
            'email_verified' => false,
        ]);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('auth.success'));
        $this->assertAuthenticatedAs($user);
        $user = $user->fresh();
        $this->assertSame('google-owner-id', $user->google_id);
        $this->assertSame('Existing Agency Owner', $user->name);
        $this->assertSame('owner@gmail.com', $user->email);
        $this->assertSame('+15555550123', $user->phone);
        $this->assertSame($passwordHash, $user->password);
        $this->assertSame(UserRole::AGENCY_OWNER, $user->role);
        $this->assertSame($verifiedAt->timestamp, $user->email_verified_at->timestamp);
        $this->assertSame($agency->id, $user->agency->id);
        $this->assertSame(route('agency.dashboard'), session('success_redirect'));
    }

    public function test_links_a_verified_workspace_account_with_string_email_verified_claim(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@travel.example',
            'google_id' => null,
        ]);
        $this->fakeGoogleUser([
            'id' => 'workspace-google-id',
            'email' => 'OWNER@TRAVEL.EXAMPLE',
            'email_verified' => 'true',
            'hd' => 'travel.example',
        ]);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('auth.success'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('workspace-google-id', $user->fresh()->google_id);
    }

    public function test_refuses_to_link_an_unverified_non_gmail_account(): void
    {
        $user = User::factory()->create([
            'email' => 'person@example.net',
            'google_id' => null,
        ]);
        $this->fakeGoogleUser([
            'id' => 'unverified-google-id',
            'email' => 'person@example.net',
            'email_verified' => 'false',
        ]);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_refuses_to_link_a_workspace_identity_with_a_mismatched_hosted_domain(): void
    {
        $user = User::factory()->create([
            'email' => 'person@example.net',
            'google_id' => null,
        ]);
        $this->fakeGoogleUser([
            'id' => 'mismatched-workspace-id',
            'email' => 'person@example.net',
            'email_verified' => 'true',
            'hd' => 'other.example',
        ]);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_refuses_to_replace_an_existing_google_link(): void
    {
        $user = User::factory()->create([
            'email' => 'person@gmail.com',
            'google_id' => 'original-google-id',
        ]);
        $this->fakeGoogleUser([
            'id' => 'different-google-id',
            'email' => 'person@gmail.com',
            'email_verified' => true,
        ]);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame('original-google-id', $user->fresh()->google_id);
    }

    public function test_google_signin_does_not_bypass_admin_two_step_verification_for_a_linked_account(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create([
            'email' => 'admin@example.com',
            'google_id' => 'stable-admin-google-id',
        ]);
        $this->fakeGoogleUser([
            'id' => 'stable-admin-google-id',
            'email' => 'admin@example.com',
            'email_verified' => true,
        ]);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame('stable-admin-google-id', $admin->fresh()->google_id);
    }

    public function test_google_signin_does_not_link_an_admin_account_by_email(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create([
            'email' => 'admin@gmail.com',
            'google_id' => null,
        ]);
        $this->fakeGoogleUser([
            'id' => 'new-admin-google-id',
            'email' => 'admin@gmail.com',
            'email_verified' => true,
        ]);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull($admin->fresh()->google_id);
    }

    public function test_signs_in_an_already_linked_user_without_changing_existing_account_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Stored Name',
            'email' => 'old-address@example.net',
            'role' => UserRole::TRAVELER,
            'google_id' => 'stable-google-id',
        ]);
        $this->fakeGoogleUser([
            'id' => 'stable-google-id',
            'email' => 'current-address@gmail.com',
            'email_verified' => true,
        ]);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('auth.success'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('old-address@example.net', $user->fresh()->email);
        $this->assertSame('Stored Name', $user->fresh()->name);
        $this->assertSame(UserRole::TRAVELER, $user->fresh()->role);
        $this->assertSame('stable-google-id', $user->fresh()->google_id);
        $this->assertSame(route('dashboard'), session('success_redirect'));
    }

    public function test_google_callback_redirects_to_https_when_behind_a_tls_terminating_proxy(): void
    {
        $this->fakeGoogleUser([
            'id' => 'proxy-google-id',
            'email' => 'proxy-user@gmail.com',
            'email_verified' => true,
        ]);

        $response = $this->withServerVariables([
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get(route('google.callback'));

        $response->assertRedirect();
        $this->assertSame('https', parse_url($response->headers->get('Location'), PHP_URL_SCHEME));
        $this->assertAuthenticated();
    }

    public function test_creates_a_new_google_user_as_a_traveler_and_normalizes_email_verified(): void
    {
        $this->fakeGoogleUser([
            'id' => 'new-google-id',
            'email' => 'new.person@example.net',
            'email_verified' => 'true',
        ]);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('auth.success'));
        $user = User::query()->where('email', 'new.person@example.net')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('new-google-id', $user->google_id);
        $this->assertSame(UserRole::TRAVELER, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(1, User::query()->where('email', 'new.person@example.net')->count());
    }

    public function test_google_callback_failure_creates_a_safe_integration_alert(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')
            ->once()
            ->andThrow(new \RuntimeException('OAuth failed with access_token=secret-value'));
        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseHas('integration_failures', [
            'integration' => 'google_oauth',
            'operation' => 'sign_in_callback',
            'safe_message' => __('messages.admin_alerts.integration_messages.google_oauth_failure'),
            'exception_class' => \RuntimeException::class,
        ]);
        $this->assertDatabaseMissing('integration_failures', [
            'safe_message' => 'OAuth failed with access_token=secret-value',
        ]);
    }

    public function test_google_user_cancellation_does_not_create_an_integration_alert(): void
    {
        $this->get(route('google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('integration_failures', 0);
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
}
