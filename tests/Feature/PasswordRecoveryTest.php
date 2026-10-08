<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\AdminPasswordResetNoticeMail;
use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_recovery_page_has_an_underlined_back_to_login_link(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk()
            ->assertSee(__('messages.back_to_log_in'))
            ->assertSee('underline', 1);
    }

    public function test_it_sends_a_verification_code_for_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'traveler@example.com']);
        Notification::fake();

        $response = $this->post(route('password.email'), ['email' => $user->email]);

        $response->assertRedirect(route('password.code'));
        $this->assertDatabaseHas('password_reset_codes', ['email' => $user->email, 'attempts' => 0]);
        Notification::assertSentTo($user, PasswordResetCode::class);
    }

    public function test_admin_normal_and_unknown_reset_requests_have_the_same_neutral_response(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create(['email' => 'admin-reset@example.com']);
        $traveler = User::factory()->create(['email' => 'traveler-reset@example.com']);
        Mail::fake();
        Notification::fake();

        $responses = collect([
            $admin->email,
            $traveler->email,
            'unknown-reset@example.com',
        ])->map(fn (string $email) => $this->from(route('password.request'))->post(route('password.email'), ['email' => $email]));

        foreach ($responses as $response) {
            $response->assertRedirect(route('password.code'))
                ->assertSessionHas('status', __('messages.password_reset_neutral'));
        }

        Notification::assertSentTo($traveler, PasswordResetCode::class);
        Notification::assertNotSentTo($admin, PasswordResetCode::class);
        Mail::assertSent(AdminPasswordResetNoticeMail::class);
        $this->assertDatabaseMissing('password_reset_codes', ['email' => $admin->email]);
        $this->assertDatabaseHas('password_reset_codes', ['email' => $traveler->email]);
    }

    public function test_reset_link_requests_are_limited_per_email_and_per_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])
                ->post(route('password.email'), ['email' => 'same-reset@example.test'])
                ->assertRedirect(route('password.code'));
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.6'])
            ->post(route('password.email'), ['email' => 'same-reset@example.test'])
            ->assertStatus(429);

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
                ->post(route('password.email'), ['email' => 'reset-ip-'.$attempt.'@example.test'])
                ->assertRedirect(route('password.code'));
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->post(route('password.email'), ['email' => 'reset-ip-final@example.test'])
            ->assertStatus(429);
    }

    public function test_admin_password_reset_submission_is_rejected(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create(['email' => 'admin-reset-submit@example.com']);
        $originalPassword = $admin->password;

        $response = $this->withSession(['password_reset_verified_email' => $admin->email])
            ->post(route('password.update'), [
                'password' => 'another-password-123',
                'password_confirmation' => 'another-password-123',
            ]);

        $response->assertRedirect(route('password.request'))
            ->assertSessionHas('status', __('messages.password_reset_neutral'));
        $this->assertSame($originalPassword, $admin->fresh()->password);
    }

    public function test_an_invalid_code_cannot_continue_recovery(): void
    {
        $email = 'traveler@example.com';
        User::factory()->create(['email' => $email]);
        DB::table('password_reset_codes')->insert([
            'email' => $email,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->from(route('password.code'))->post(route('password.code.verify'), [
            'email' => $email,
            'code' => '654321',
        ]);

        $response->assertRedirect(route('password.code'));
        $response->assertSessionHasErrors('code');
        $this->assertDatabaseHas('password_reset_codes', ['email' => $email, 'attempts' => 1]);
    }

    public function test_a_valid_code_allows_the_user_to_set_a_new_password(): void
    {
        $email = 'traveler@example.com';
        $user = User::factory()->create(['email' => $email, 'password' => 'old-password']);
        DB::table('password_reset_codes')->insert([
            'email' => $email,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['password_reset_email' => $email])
            ->post(route('password.code.verify'), ['email' => $email, 'code' => '123456'])
            ->assertRedirect(route('password.reset'));

        $response = $this->post(route('password.update'), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('new-secure-password', $user->refresh()->password));
        $this->assertDatabaseMissing('password_reset_codes', ['email' => $email]);
    }
}
