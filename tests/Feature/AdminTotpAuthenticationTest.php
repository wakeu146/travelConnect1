<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\AdminLoginCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminTotpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unenrolled_admin_can_enroll_in_totp_after_email_verification(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        Mail::fake();

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.login.verify', ['lang' => 'en']));

        $emailCode = null;
        Mail::assertSent(AdminLoginCodeMail::class, function (AdminLoginCodeMail $mail) use (&$emailCode): bool {
            $emailCode = $mail->code;

            return true;
        });

        $this->post(route('admin.login.verify.submit'), ['code' => $emailCode])
            ->assertRedirect(route('admin.totp.setup'));
        $this->assertAuthenticatedAs($admin);

        $this->get(route('admin.totp.setup'))
            ->assertOk()
            ->assertSee('data:image/svg+xml;base64', false)
            ->assertSee(__('messages.admin_totp_manual_entry'))
            ->assertSee('placeholder="'.__('messages.admin_totp_code_placeholder').'"', false);

        $credential = $admin->adminTotpCredential()->firstOrFail();
        $authenticatorCode = app(Google2FA::class)->getCurrentOtp($credential->secret);
        $confirmResponse = $this->post(route('admin.totp.confirm'), ['code' => $authenticatorCode]);
        $confirmResponse->assertRedirect(route('admin.totp.setup'))
            ->assertSessionHas('admin_totp_recovery_codes', function (array $codes) use (&$recoveryCodes): bool {
                $recoveryCodes = $codes;

                return count($codes) === 10;
            });

        $this->assertIsArray($recoveryCodes);
        $this->assertCount(10, $recoveryCodes);
        $this->assertNotNull($credential->fresh()->verified_at);
        $this->assertCount(10, $credential->fresh()->recovery_code_hashes);
        $this->assertNotSame($credential->secret, $credential->getRawOriginal('secret'));

        $this->get(route('admin.totp.setup'))
            ->assertOk()
            ->assertSee($recoveryCodes[0]);
        $this->get(route('admin.totp.setup'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_enrolled_admin_uses_totp_and_cannot_replay_a_code(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $google2fa = app(Google2FA::class);
        $secret = $google2fa->generateSecretKey(32);
        $admin->adminTotpCredential()->create([
            'secret' => $secret,
            'recovery_code_hashes' => [],
            'verified_at' => now(),
        ]);
        Mail::fake();

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.login.verify', ['lang' => 'en']));
        Mail::assertNotSent(AdminLoginCodeMail::class);

        $this->get(route('admin.login.verify'))
            ->assertOk()
            ->assertSee('placeholder="'.__('messages.admin_totp_code_placeholder').'"', false)
            ->assertSee(__('messages.admin_totp_recovery_hint'))
            ->assertSee(__('messages.back_to_log_in'))
            ->assertSee('underline', 1)
            ->assertDontSee(__('messages.admin_resend_code'));

        $authenticatorCode = $google2fa->getCurrentOtp($secret);
        $this->post(route('admin.login.verify.submit'), ['code' => $authenticatorCode])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);

        $this->post(route('admin.logout'))->assertRedirect(route('home'));
        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $this->post(route('admin.login.verify.submit'), ['code' => $authenticatorCode])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertDatabaseHas('admin_login_challenges', [
            'user_id' => $admin->id,
            'attempts' => 1,
        ]);
    }

    public function test_totp_recovery_code_is_consumed_after_one_use(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $recoveryCode = 'ABCD-EFGH-IJKL';
        $admin->adminTotpCredential()->create([
            'secret' => $secret,
            'recovery_code_hashes' => [Hash::make('ABCDEFGHIJKL')],
            'verified_at' => now(),
        ]);

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $this->post(route('admin.login.verify.submit'), ['code' => $recoveryCode])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertSame([], $admin->adminTotpCredential()->firstOrFail()->recovery_code_hashes);

        $this->post(route('admin.logout'));
        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $this->post(route('admin.login.verify.submit'), ['code' => $recoveryCode])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }
}
