<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentTransactionType;
use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingPaymentTransaction;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class AdminAlertsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            AdminSecondFactor::class,
            Mockery::mock(AdminSecondFactor::class),
        );
    }

    public function test_verified_admin_sees_real_alerts_and_can_filter_the_queue(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create(['company_name' => 'Blue Horizon Travel']);
        $traveler = User::factory()->create();
        Review::factory()->for($agency)->for($traveler)->create([
            'comment' => 'Flagged review that should not match the agency filter.',
            'status' => ReviewStatus::FLAGGED,
        ]);
        $package = Package::factory()->for($agency)->create();
        $availability = PackageAvailability::query()->create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 10,
        ]);
        $booking = Booking::query()->create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'booking_reference' => 'TC-ALERT-BOOKING',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'traveler_count' => 1,
            'unit_price' => 100,
            'total_amount' => 100,
            'currency' => 'XAF',
        ]);
        BookingPaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'payment_reference' => 'payment-ref-test',
            'provider' => 'test-provider',
            'type' => PaymentTransactionType::CHARGE,
            'status' => PaymentTransactionStatus::FAILED,
            'amount' => 100,
            'currency' => 'XAF',
            'failure_reason' => 'Provider declined payment',
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => 'job-visible-id',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => 'PRIVATE_JOB_PAYLOAD',
            'exception' => 'PRIVATE_EXCEPTION_STACK',
            'failed_at' => now(),
        ]);
        DB::table('admin_login_events')->insert([
            'user_id' => $admin->id,
            'email' => $admin->email,
            'ip_address' => '192.0.2.10',
            'outcome' => 'blocked',
            'failed_step' => 'password_lockout',
            'created_at' => now(),
        ]);

        $allAlertsResponse = $this->get(route('admin.dashboard.page', ['page' => 'alerts']));
        $allAlertsResponse->assertSee('Blue Horizon Travel')
            ->assertSee('payment-ref-test')
            ->assertSee('job-visible-id')
            ->assertSee($admin->email)
            ->assertDontSee('PRIVATE_JOB_PAYLOAD')
            ->assertDontSee('PRIVATE_EXCEPTION_STACK');

        $this->get(route('admin.dashboard.page', [
            'page' => 'alerts',
            'source' => 'agency',
            'source_id' => $agency->id,
        ]))->assertSee(__('messages.admin_alerts.reasons.agency_verification'));

        $paymentTransaction = BookingPaymentTransaction::query()->firstOrFail();
        $this->get(route('admin.dashboard.page', [
            'page' => 'alerts',
            'source' => 'failed_payment',
            'source_id' => $paymentTransaction->id,
        ]))->assertSee(__('messages.admin_alerts.reason_heading'))
            ->assertSee('Provider declined payment');

        $response = $this->get(route('admin.dashboard.page', [
            'page' => 'alerts',
            'category' => 'agency_verification',
            'search' => 'Blue Horizon',
        ]));

        $response->assertSee('data-admin-alerts', false)
            ->assertSee('Blue Horizon Travel')
            ->assertDontSee('Flagged review that should not match');

        $blockedEvent = DB::table('admin_login_events')->where('outcome', 'blocked')->first();
        $this->get(route('admin.dashboard.page', [
            'page' => 'alerts',
            'source' => 'admin_login_event',
            'source_id' => $blockedEvent->id,
        ]))->assertSee('data-alert-severity="high"', false)
            ->assertSee(__('messages.admin_alerts.reasons.suspicious_activity', [
                'outcome' => __('messages.admin_alerts.login_outcomes.blocked'),
                'step' => __('messages.admin_alerts.login_steps.password_lockout'),
            ]));
    }

    public function test_alert_detail_escapes_source_content_and_does_not_expose_job_payloads(): void
    {
        $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create();
        $traveler = User::factory()->create();
        $review = Review::factory()->for($agency)->for($traveler)->create([
            'comment' => "<script>alert('xss')</script>",
            'status' => ReviewStatus::FLAGGED,
        ]);
        $failedJobId = DB::table('failed_jobs')->insertGetId([
            'uuid' => 'job-safe-id',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => 'PRIVATE_JOB_PAYLOAD',
            'exception' => "RuntimeException: Mail delivery failed\n at PRIVATE_EXCEPTION_STACK",
            'failed_at' => now(),
        ]);

        $reviewResponse = $this->get(route('admin.dashboard.page', [
            'page' => 'alerts',
            'source' => 'review',
            'source_id' => $review->id,
        ]));

        $reviewResponse->assertSee('data-alert-detail', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee("<script>alert('xss')</script>", false);

        $jobResponse = $this->get(route('admin.dashboard.page', [
            'page' => 'alerts',
            'source' => 'failed_job',
            'source_id' => $failedJobId,
        ]));

        $jobResponse->assertSee('job-safe-id')
            ->assertSee(__('messages.admin_alerts.reason_heading'))
            ->assertSee('RuntimeException: Mail delivery failed')
            ->assertDontSee('PRIVATE_JOB_PAYLOAD')
            ->assertDontSee('PRIVATE_EXCEPTION_STACK');

        $this->get(route('admin.dashboard.page', [
            'page' => 'alerts',
            'source' => 'review',
            'source_id' => $review->id,
        ]))->assertSee(__('messages.admin_alerts.reasons.reported_review'));
    }

    public function test_acknowledging_and_resolving_an_alert_are_audited_without_changing_the_source(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create([
            'verification_status' => VerificationStatus::PENDING,
        ]);

        $this->post(route('admin.alerts.acknowledge', [
            'source' => 'agency',
            'sourceId' => $agency->id,
        ]))->assertRedirect();

        $this->assertDatabaseHas('admin_alerts', [
            'source_type' => 'agency',
            'source_id' => $agency->id,
            'status' => 'acknowledged',
            'acknowledged_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('admin_alert_actions', [
            'action' => 'acknowledge',
            'status_before' => 'open',
            'status_after' => 'acknowledged',
            'actor_id' => $admin->id,
        ]);

        $this->post(route('admin.alerts.acknowledge', [
            'source' => 'agency',
            'sourceId' => $agency->id,
        ]))->assertSessionHasErrors([
            'status' => __('messages.admin_alerts.invalid_transition'),
        ]);

        $this->post(route('admin.alerts.resolve', [
            'source' => 'agency',
            'sourceId' => $agency->id,
        ]))->assertRedirect();

        $this->assertDatabaseHas('admin_alerts', [
            'source_type' => 'agency',
            'source_id' => $agency->id,
            'status' => 'resolved',
            'resolved_by' => $admin->id,
        ]);
        $this->assertDatabaseCount('admin_alert_actions', 2);
        $this->assertDatabaseHas('admin_alert_actions', [
            'action' => 'resolve',
            'status_before' => 'acknowledged',
            'status_after' => 'resolved',
            'actor_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('agencies', [
            'id' => $agency->id,
            'verification_status' => VerificationStatus::PENDING->value,
        ]);
    }

    public function test_only_a_verified_admin_can_change_an_alert_status(): void
    {
        $agency = Agency::factory()->create();

        $this->post(route('admin.alerts.resolve', [
            'source' => 'agency',
            'sourceId' => $agency->id,
        ]))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->post(route('admin.alerts.resolve', [
                'source' => 'agency',
                'sourceId' => $agency->id,
            ]))
            ->assertForbidden();

        $this->assertDatabaseCount('admin_alerts', 0);
        $this->assertDatabaseCount('admin_alert_actions', 0);
    }

    private function actAsVerifiedAdmin(): User
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $this->actingAs($admin)->withSession([
            'admin_login.verified_user_id' => $admin->id,
            'admin_login.session_version' => $admin->admin_session_version,
            'admin_login.verified_at' => now()->timestamp,
            'admin_login.last_activity' => now()->timestamp,
        ]);

        return $admin;
    }
}
