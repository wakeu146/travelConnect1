<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\ContentReport;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\Review;
use App\Models\TrustScoreLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminTrustScoreManagementTest extends TestCase
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

    public function test_overview_shows_the_current_score_and_matching_recorded_factors(): void
    {
        $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create(['trust_score' => 90]);
        $calculatedAt = CarbonImmutable::parse('2026-10-28 08:31:00', config('app.timezone'));

        Review::factory()
            ->count(2)
            ->sequence(['rating' => 4], ['rating' => 5])
            ->create([
                'agency_id' => $agency->id,
                'status' => 'published',
                'is_hidden' => false,
            ]);

        TrustScoreLog::factory()->for($agency)->create([
            'score' => 90,
            'factors' => [
                'average_published_rating' => 4.5,
                'published_review_count' => 2,
                'calculation' => 'average_rating_x_20',
            ],
            'calculated_at' => $calculatedAt,
        ]);

        $this->get(route('admin.dashboard.page', ['page' => 'trust_score_overview', 'lang' => 'en']))
            ->assertSee('data-admin-trust-score-overview', false)
            ->assertSee('<table', false)
            ->assertSee('data-trust-score-agency="'.$agency->id.'"', false)
            ->assertSee($agency->company_name)
            ->assertSee('4.50 / 5')
            ->assertSee(__('messages.trust_scores.published_review_count', [], 'en'))
            ->assertSee(__('messages.trust_scores.average_rating_x_20', [], 'en'))
            ->assertSee('datetime="'.$calculatedAt->toIso8601String().'"', false)
            ->assertDontSee(__('messages.admin_nav.page_in_preparation', [], 'en'));
    }

    public function test_overview_does_not_show_stale_calculation_factors_as_current(): void
    {
        $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create(['trust_score' => 90]);
        $calculatedAt = CarbonImmutable::parse('2026-10-27 08:31:00', config('app.timezone'));

        TrustScoreLog::factory()->for($agency)->create([
            'score' => 85,
            'factors' => [
                'average_published_rating' => 4.25,
                'published_review_count' => 2,
                'calculation' => 'average_rating_x_20',
            ],
            'calculated_at' => $calculatedAt,
        ]);

        $this->get(route('admin.dashboard.page', ['page' => 'trust_score_overview', 'lang' => 'en']))
            ->assertSee(__('messages.trust_scores.calculation_mismatch', [
                'score' => 85,
                'date' => $calculatedAt->locale('en')->isoFormat('LLL'),
            ], 'en'))
            ->assertDontSee('4.25 / 5')
            ->assertDontSee(__('messages.trust_scores.average_rating_x_20', [], 'en'));
    }

    public function test_attention_queue_links_active_reports_to_reports_searchable_by_agency(): void
    {
        $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create([
            'company_name' => 'Northwind Tours',
            'operating_status' => 'active',
        ]);
        $review = Review::factory()->create([
            'agency_id' => $agency->id,
            'status' => 'flagged',
            'comment' => 'The listed price does not match the advertised price.',
        ]);
        $report = ContentReport::query()->create([
            'review_id' => $review->id,
            'reporter_id' => User::factory()->create()->id,
            'reason' => 'This review includes misleading pricing.',
            'status' => 'investigating',
        ]);
        $traveler = User::factory()->create();
        $package = Package::factory()->for($agency)->create();
        $availability = PackageAvailability::query()->create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 5,
        ]);
        $booking = Booking::query()->create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'booking_reference' => 'TC-TRUST-ATTENTION',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'traveler_count' => 1,
            'unit_price' => 100,
            'total_amount' => 100,
            'currency' => 'XAF',
        ]);
        $complaint = $traveler->travelerIssues()->create([
            'booking_id' => $booking->id,
            'type' => 'complaint',
            'subject' => 'The agency did not provide the booked transfer.',
            'description' => 'The traveler reports that the agreed transfer was not provided.',
            'status' => 'open',
        ]);
        $verificationUrl = route('admin.agencies.show', [
            'agency' => $agency->id,
            'queue' => 'verification_pending',
            'lang' => 'en',
        ]);
        $reviewsUrl = route('admin.dashboard.page', [
            'page' => 'reviews_ratings',
            'search' => $agency->company_name,
            'lang' => 'en',
        ]);
        $reportsUrl = route('admin.dashboard.page', [
            'page' => 'reported_content',
            'search' => $agency->company_name,
            'lang' => 'en',
        ]);
        $complaintsUrl = route('admin.dashboard.page', [
            'page' => 'user_agency_complaints',
            'search' => $agency->company_name,
            'lang' => 'en',
        ]);
        $bookingsUrl = route('admin.agencies.show', [
            'agency' => $agency->id,
            'lang' => 'en',
        ]).'#agency-bookings';

        $this->get(route('admin.dashboard.page', ['page' => 'agencies_requiring_attention', 'lang' => 'en']))
            ->assertSee('data-admin-agencies-requiring-attention', false)
            ->assertSee('<table', false)
            ->assertSee(__('messages.trust_scores.verification_attention', [
                'status' => __('messages.admin_agencies.verification_statuses.pending', [], 'en'),
            ], 'en'))
            ->assertSee(__('messages.trust_scores.flagged_reviews_attention', ['count' => 1], 'en'))
            ->assertSee(__('messages.trust_scores.active_reports_attention', ['count' => 1], 'en'))
            ->assertSee(__('messages.trust_scores.active_complaints_attention', ['count' => 1], 'en'))
            ->assertSee(__('messages.trust_scores.open_verification', [], 'en'))
            ->assertSee(__('messages.trust_scores.open_reviews', [], 'en'))
            ->assertSee(__('messages.trust_scores.view_reports', [], 'en'))
            ->assertSee(__('messages.trust_scores.open_complaints', [], 'en'))
            ->assertSee(__('messages.trust_scores.open_bookings', [], 'en'))
            ->assertSee($verificationUrl)
            ->assertSee($reviewsUrl)
            ->assertSee($reportsUrl)
            ->assertSee($complaintsUrl)
            ->assertSee($bookingsUrl);

        $this->get($reviewsUrl)
            ->assertSee('<table', false)
            ->assertSee($agency->company_name);

        $this->get(route('admin.reviews.show', [
            'review' => $review->id,
            'lang' => 'en',
            'search' => $agency->company_name,
        ]))
            ->assertSee('data-admin-review-detail', false)
            ->assertSee($review->comment);

        $this->get($reportsUrl)
            ->assertSee('data-admin-reported-content', false)
            ->assertSee($agency->company_name);

        $this->get(route('admin.reports.show', [
            'report' => $report->id,
            'lang' => 'en',
            'search' => $agency->company_name,
        ]))
            ->assertSee('data-admin-report-detail', false)
            ->assertSee($report->reason)
            ->assertSee($agency->company_name);

        $this->get($complaintsUrl)
            ->assertSee('data-admin-complaints', false)
            ->assertSee($complaint->subject);

        $this->get(route('admin.agencies.show', ['agency' => $agency->id, 'lang' => 'en']))
            ->assertSee('id="agency-bookings"', false)
            ->assertSee($booking->booking_reference);
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
