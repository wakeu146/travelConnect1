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
use App\Models\IntegrationFailure;
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
        $agency = Agency::factory()->create([
            'company_name' => 'Blue Horizon Travel',
            'created_at' => now()->subHours(25),
        ]);
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
        foreach (range(1, 5) as $attempt) {
            DB::table('admin_login_events')->insert([
                'user_id' => $admin->id,
                'email' => $admin->email,
                'ip_address' => '192.0.2.10',
                'outcome' => 'password_failed',
                'failed_step' => 'password',
                'created_at' => now()->subSeconds(6 - $attempt),
            ]);
        }
        $integrationFailure = IntegrationFailure::query()->create([
            'integration' => 'google_oauth',
            'operation' => 'sign_in_callback',
            'safe_message' => __('messages.admin_alerts.integration_messages.google_oauth_failure'),
            'exception_class' => 'RuntimeException',
        ]);

        $allAlertsResponse = $this->get(route('admin.dashboard.page', ['page' => 'alerts']));
        $allAlertsResponse->assertSee('Blue Horizon Travel')
            ->assertSee('job-visible-id')
            ->assertSee($admin->email)
            ->assertSee('google_oauth')
            ->assertDontSee('payment-ref-test')
            ->assertDontSee('PRIVATE_JOB_PAYLOAD')
            ->assertDontSee('PRIVATE_EXCEPTION_STACK');

        $this->get(route('admin.dashboard.page', [
            'page' => 'alerts',
            'source' => 'agency',
            'source_id' => $agency->id,
        ]))->assertSee(__('messages.admin_alerts.reasons.agency_verification'));

        $this->get(route('admin.dashboard.page', [
            'page' => 'alerts',
            'source' => 'integration_failure',
            'source_id' => $integrationFailure->id,
        ]))->assertSee(__('messages.admin_alerts.reason_heading'))
            ->assertSee(__('messages.admin_alerts.integration_messages.google_oauth_failure'))
            ->assertSee('RuntimeException');

        $response = $this->get(route('admin.dashboard.page', [
            'page' => 'alerts',
            'category' => 'agency_verification',
            'search' => 'Blue Horizon',
        ]));

        $response->assertSee('data-admin-alerts', false)
            ->assertSee('Blue Horizon Travel')
            ->assertDontSee('Flagged review that should not match');

        $latestEvent = DB::table('admin_login_events')->orderByDesc('id')->first();
        $this->get(route('admin.dashboard.page', [
            'page' => 'alerts',
            'source' => 'login_burst',
            'source_id' => $latestEvent->id,
        ]))->assertSee('data-alert-severity="high"', false)
            ->assertSee(__('messages.admin_alerts.reasons.login_burst', [
                'count' => 5,
            ]));
    }

    public function test_only_verifications_older_than_twenty_four_hours_appear_as_alerts(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        Agency::factory()->create([
            'company_name' => 'Overdue verification',
            'verification_status' => VerificationStatus::PENDING,
            'created_at' => now()->subHours(25),
        ]);
        Agency::factory()->create([
            'company_name' => 'Recent verification',
            'verification_status' => VerificationStatus::PENDING,
            'created_at' => now()->subHours(23),
        ]);
        foreach (range(1, 4) as $attempt) {
            DB::table('admin_login_events')->insert([
                'user_id' => $admin->id,
                'email' => 'quiet-account@example.com',
                'ip_address' => '192.0.2.11',
                'outcome' => 'password_failed',
                'failed_step' => 'password',
                'created_at' => now()->subSeconds(5 - $attempt),
            ]);
        }
        foreach (range(1, 5) as $attempt) {
            DB::table('admin_login_events')->insert([
                'user_id' => $admin->id,
                'email' => 'email-delivery@example.com',
                'ip_address' => '192.0.2.12',
                'outcome' => 'blocked',
                'failed_step' => 'email_delivery',
                'created_at' => now()->subSeconds(6 - $attempt),
            ]);
        }

        $this->get(route('admin.dashboard.page', ['page' => 'alerts']))
            ->assertSee('Overdue verification')
            ->assertDontSee('Recent verification')
            ->assertDontSee('quiet-account@example.com')
            ->assertDontSee('email-delivery@example.com');
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
            'created_at' => now()->subHours(25),
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

    public function test_acknowledging_and_resolving_a_traveler_issue_updates_its_status_and_audit(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $traveler = User::factory()->create();
        $issue = $traveler->travelerIssues()->create([
            'type' => 'complaint',
            'subject' => 'Agency did not respond',
            'description' => 'I have not received any response from the agency.',
        ]);

        $this->get(route('admin.dashboard.page', [
            'page' => 'alerts',
            'source' => 'traveler_issue',
            'source_id' => $issue->id,
        ]))->assertSee(__('messages.admin_alerts.categories.complaint'))
            ->assertSee('I have not received any response from the agency.');

        $this->post(route('admin.alerts.acknowledge', [
            'source' => 'traveler_issue',
            'sourceId' => $issue->id,
        ]))->assertRedirect();

        $this->assertDatabaseHas('traveler_issues', [
            'id' => $issue->id,
            'status' => 'in_review',
        ]);
        $this->assertDatabaseHas('admin_alert_actions', [
            'admin_alert_id' => DB::table('admin_alerts')
                ->where('source_type', 'traveler_issue')
                ->where('source_id', $issue->id)
                ->value('id'),
            'action' => 'acknowledge',
            'actor_id' => $admin->id,
        ]);

        $this->post(route('admin.alerts.resolve', [
            'source' => 'traveler_issue',
            'sourceId' => $issue->id,
        ]))->assertRedirect();

        $this->assertDatabaseHas('traveler_issues', [
            'id' => $issue->id,
            'status' => 'resolved',
        ]);
        $this->assertDatabaseHas('admin_alert_actions', [
            'action' => 'resolve',
            'status_before' => 'acknowledged',
            'status_after' => 'resolved',
            'actor_id' => $admin->id,
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
