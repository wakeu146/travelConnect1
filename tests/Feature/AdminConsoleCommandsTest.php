<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AdminLoginEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_create_prompts_for_a_separate_admin_identity_and_password(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion(__('messages.admin_name_prompt'), 'Console Admin')
            ->expectsQuestion(__('messages.admin_email_prompt'), 'softwareengdev3@gmail.com')
            ->expectsQuestion(__('messages.admin_password_prompt'), 'new-admin-password-123')
            ->expectsQuestion(__('messages.admin_password_confirm_prompt'), 'new-admin-password-123')
            ->assertExitCode(0);

        $admin = User::query()->where('email', 'softwareengdev3@gmail.com')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(Hash::check('new-admin-password-123', $admin->password));
    }

    public function test_admin_reset_rotates_credentials_and_revokes_sessions_challenges_and_devices(): void
    {
        config(['session.driver' => 'database']);
        $admin = User::factory()->withRole(UserRole::ADMIN)->create(['email' => 'reset-admin@example.test']);
        $originalSessionVersion = $admin->admin_session_version;
        $admin->adminLoginChallenges()->create([
            'session_hash' => hash('sha256', 'pending'),
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);
        $admin->trustedAdminDevices()->create([
            'token_hash' => hash('sha256', 'trusted-device-token'),
            'user_agent' => 'Test Browser',
        ]);
        $admin->adminTotpCredential()->create([
            'secret' => 'ABCDEFGHABCDEFGHABCDEFGHABCDEFGH',
            'recovery_code_hashes' => [Hash::make('ABCD-EFGH-IJKL')],
            'verified_at' => now(),
        ]);
        DB::table('sessions')->insert([
            'id' => 'admin-session-to-revoke',
            'user_id' => $admin->id,
            'ip_address' => '192.0.2.5',
            'user_agent' => 'Test Browser',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->artisan('admin:reset')
            ->expectsQuestion(__('messages.admin_reset_target_prompt'), $admin->email)
            ->expectsQuestion(__('messages.admin_email_prompt'), 'reset-admin-new@example.test')
            ->expectsQuestion(__('messages.admin_password_prompt'), 'reset-admin-password-123')
            ->expectsQuestion(__('messages.admin_password_confirm_prompt'), 'reset-admin-password-123')
            ->assertExitCode(0);

        $admin->refresh();
        $this->assertSame('reset-admin-new@example.test', $admin->email);
        $this->assertTrue(Hash::check('reset-admin-password-123', $admin->password));
        $this->assertGreaterThan($originalSessionVersion, $admin->admin_session_version);
        $this->assertDatabaseMissing('admin_login_challenges', ['user_id' => $admin->id]);
        $this->assertDatabaseMissing('admin_totp_credentials', ['user_id' => $admin->id]);
        $this->assertDatabaseMissing('trusted_admin_devices', ['user_id' => $admin->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'admin-session-to-revoke']);
        $this->assertDatabaseHas('admin_login_events', [
            'user_id' => $admin->id,
            'failed_step' => 'admin_reset',
        ]);
    }

    public function test_admin_login_event_prune_uses_configured_retention_period(): void
    {
        config(['admin.login_event_retention_days' => 180]);
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $oldEvent = AdminLoginEvent::query()->create([
            'user_id' => $admin->id,
            'email' => $admin->email,
            'outcome' => 'password_failed',
            'created_at' => now()->subDays(181),
        ]);
        $recentEvent = AdminLoginEvent::query()->create([
            'user_id' => $admin->id,
            'email' => $admin->email,
            'outcome' => 'success',
            'created_at' => now()->subDays(179),
        ]);

        $this->artisan('admin:prune-login-events')->assertExitCode(0);

        $this->assertDatabaseMissing('admin_login_events', ['id' => $oldEvent->id]);
        $this->assertDatabaseHas('admin_login_events', ['id' => $recentEvent->id]);
    }
}
