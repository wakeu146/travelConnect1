<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\EmailAdminSecondFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class EmailAdminSecondFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_delivery_failure_creates_a_safe_integration_alert_and_is_rethrown(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $failure = new RuntimeException('SMTP connection failed with password=secret-value');
        Mail::shouldReceive('to')
            ->once()
            ->with($admin->email)
            ->andThrow($failure);

        try {
            app(EmailAdminSecondFactor::class)->issue($admin, 'test-session-key');
            $this->fail('Expected the mail transport failure to be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure->getMessage(), $exception->getMessage());
        }

        $this->assertDatabaseHas('integration_failures', [
            'integration' => 'email',
            'operation' => 'admin_second_factor_delivery',
            'safe_message' => __('messages.admin_alerts.integration_messages.email_delivery_failure'),
            'exception_class' => RuntimeException::class,
        ]);
        $this->assertDatabaseMissing('integration_failures', [
            'safe_message' => $failure->getMessage(),
        ]);
        $this->assertDatabaseCount('admin_login_challenges', 1);
    }
}
