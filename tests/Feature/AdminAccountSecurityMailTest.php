<?php

namespace Tests\Feature;

use App\Mail\AdminAccountSecurityMail;
use Tests\TestCase;

class AdminAccountSecurityMailTest extends TestCase
{
    public function test_security_email_explains_session_revocation_and_escapes_the_recipient_name(): void
    {
        $mail = new AdminAccountSecurityMail(
            name: '<script>alert("xss")</script>',
            action: 'sessions_revoked',
            time: '2026-10-18 23:00 BST',
        );

        $html = $mail->render();

        $this->assertStringContainsString('Your sign-in sessions were ended', $html);
        $this->assertStringContainsString('all active sign-in sessions', $html);
        $this->assertStringContainsString('2026-10-18 23:00 BST', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
    }

    public function test_deactivation_email_explains_that_the_account_is_unavailable(): void
    {
        $html = (new AdminAccountSecurityMail(
            name: 'Taylor Traveler',
            action: 'account_deactivated',
            time: '2026-10-18 23:00 BST',
        ))->render();

        $this->assertStringContainsString('Your account has been deactivated', $html);
        $this->assertStringContainsString('will not be able to sign in', $html);
        $this->assertStringContainsString('contact TravelConnect support', $html);
    }

    public function test_restoration_email_confirms_access_is_available_again(): void
    {
        $html = (new AdminAccountSecurityMail(
            name: 'Taylor Traveler',
            action: 'account_restored',
            time: '2026-10-18 23:00 BST',
        ))->render();

        $this->assertStringContainsString('Your account has been restored', $html);
        $this->assertStringContainsString('You can now sign in again', $html);
        $this->assertStringContainsString('contact TravelConnect support', $html);
    }
}
