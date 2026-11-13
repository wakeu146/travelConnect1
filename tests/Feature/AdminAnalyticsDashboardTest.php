<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\BookingStatus;
use App\Enums\PackageStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Package;
use App\Models\TravelRequest;
use App\Models\TravelRequestRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminAnalyticsDashboardTest extends TestCase
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

    public function test_agency_growth_chart_scales_to_data_and_shows_five_axis_ticks(): void
    {
        $this->travelTo('2026-11-10 12:00:00');
        $this->actingAsVerifiedAdmin();
        $firstOwner = User::factory()->create(['role' => UserRole::AGENCY_OWNER->value]);
        $secondOwner = User::factory()->create(['role' => UserRole::AGENCY_OWNER->value]);
        $firstAgency = Agency::factory()->for($firstOwner, 'owner')->create();
        $secondAgency = Agency::factory()->for($secondOwner, 'owner')->create();
        Agency::query()->whereKey($firstAgency->id)->update(['created_at' => '2026-11-08 10:00:00']);
        Agency::query()->whereKey($secondAgency->id)->update(['created_at' => '2026-11-09 10:00:00']);

        $response = $this->get(route('admin.dashboard.page', [
            'page' => 'agency_growth',
            'lang' => 'en',
            'created_from' => '2026-11-08',
            'created_to' => '2026-11-09',
        ]));

        $response
            ->assertOk()
            ->assertSee('data-analytics-chart="analytics-agency-growth"', false)
            ->assertSee('data-analytics-y-tick=', false)
            ->assertSee('data-analytics-chart-interaction', false)
            ->assertSee('minimumFractionDigits: 3', false)
            ->assertSee('maximumFractionDigits: 3', false);
        $this->assertSame(5, substr_count($response->getContent(), 'data-analytics-y-tick='));
    }

    public function test_user_growth_chart_scales_above_one_thousand_records(): void
    {
        $this->travelTo('2026-11-10 12:00:00');
        $this->actingAsVerifiedAdmin();
        User::factory()->count(1234)->create([
            'role' => UserRole::TRAVELER->value,
            'created_at' => '2026-11-08 10:00:00',
        ]);

        $response = $this->get(route('admin.dashboard.page', [
            'page' => 'user_growth',
            'lang' => 'en',
            'created_from' => '2026-11-08',
            'created_to' => '2026-11-08',
        ]));

        $response
            ->assertOk()
            ->assertSee('data-analytics-chart-maximum="2000"', false)
            ->assertSee('data-analytics-series="users" data-analytics-series-label="New accounts" data-analytics-series-values="1234"', false)
            ->assertSee('<text x="60" y="34" text-anchor="end" data-analytics-y-tick="2000">2K</text>', false)
            ->assertSee('<text x="60" y="89" text-anchor="end" data-analytics-y-tick="1500">1.5K</text>', false);
        $this->assertSame(5, substr_count($response->getContent(), 'data-analytics-y-tick='));
    }

    public function test_platform_growth_and_activity_charts_use_real_date_filtered_records(): void
    {
        $this->travelTo('2026-11-09 12:00:00');
        $this->actingAsVerifiedAdmin();
        $traveler = User::factory()->create([
            'role' => UserRole::TRAVELER->value,
            'created_at' => '2026-11-08 10:00:00',
        ]);
        $owner = User::factory()->create(['role' => UserRole::AGENCY_OWNER->value]);
        $agency = Agency::factory()->for($owner, 'owner')->create();
        Agency::query()->whereKey($agency->id)->update(['created_at' => '2026-11-08 10:00:00']);
        $package = Package::factory()->for($agency)->create([
            'destination' => 'Lakeside',
            'status' => PackageStatus::PUBLISHED,
            'published_at' => '2026-11-08 11:00:00',
        ]);
        $booking = Booking::query()->create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'user_id' => $traveler->id,
            'booking_reference' => 'ANALYTICS-ACTIVITY-001',
            'status' => BookingStatus::CANCELLED,
            'payment_status' => 'unpaid',
            'traveler_count' => 1,
            'unit_price' => '250.00',
            'total_amount' => '250.00',
            'currency' => 'XAF',
            'cancelled_at' => '2026-11-08 13:00:00',
        ]);
        Booking::query()->whereKey($booking->id)->update(['created_at' => '2026-11-08 12:00:00']);
        $travelRequest = TravelRequest::factory()->create(['created_at' => '2026-11-08 10:30:00']);
        $inquiry = Inquiry::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $traveler->id,
            'subject' => 'Trip details',
            'message' => 'Please share more details.',
            'status' => 'open',
        ]);
        Inquiry::query()->whereKey($inquiry->id)->update(['created_at' => '2026-11-08 11:30:00']);
        TravelRequestRecipient::query()->create([
            'travel_request_id' => $travelRequest->id,
            'agency_id' => $agency->id,
            'available_at' => '2026-11-08 10:30:00',
            'first_viewed_at' => '2026-11-08 12:30:00',
        ]);

        $this->get(route('admin.dashboard.page', [
            'page' => 'platform_performance',
            'lang' => 'en',
            'created_from' => '2026-11-08',
            'created_to' => '2026-11-08',
        ]))
            ->assertOk()
            ->assertSee('data-analytics-metric="new_users" data-analytics-value="1"', false)
            ->assertSee('data-analytics-metric="new_agencies" data-analytics-value="1"', false)
            ->assertSee('data-analytics-metric="bookings" data-analytics-value="1"', false)
            ->assertSee('data-analytics-metric="travel_requests" data-analytics-value="1"', false)
            ->assertSee('data-analytics-series="users" data-analytics-series-label="New accounts" data-analytics-series-values="1"', false)
            ->assertSee('data-analytics-series="cancellations" data-analytics-series-label="Cancellations" data-analytics-series-values="1"', false)
            ->assertSee('Lakeside');
    }

    public function test_custom_and_package_destinations_are_included_in_analytics(): void
    {
        $this->travelTo('2026-11-09 12:00:00');
        $this->actingAsVerifiedAdmin();
        $owner = User::factory()->create(['role' => UserRole::AGENCY_OWNER->value]);
        $traveler = User::factory()->create(['role' => UserRole::TRAVELER->value]);
        $agency = Agency::factory()->for($owner, 'owner')->create();
        $package = Package::factory()->for($agency)->create([
            'destination' => 'Lakeside',
            'status' => PackageStatus::PUBLISHED,
        ]);
        $packageBooking = Booking::query()->create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'user_id' => $traveler->id,
            'booking_reference' => 'ANALYTICS-PACKAGE-001',
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => 'unpaid',
            'traveler_count' => 1,
            'unit_price' => '250.00',
            'total_amount' => '250.00',
            'currency' => 'XAF',
        ]);
        Booking::query()->whereKey($packageBooking->id)->update(['created_at' => '2026-11-08 12:00:00']);
        $travelRequest = TravelRequest::factory()->create([
            'destination' => 'New York',
            'status' => 'converted',
        ]);
        $customBooking = Booking::query()->create([
            'agency_id' => $agency->id,
            'booking_type' => 'custom',
            'custom_request_id' => $travelRequest->id,
            'user_id' => $traveler->id,
            'booking_reference' => 'ANALYTICS-CUSTOM-001',
            'status' => BookingStatus::PENDING,
            'payment_status' => 'unpaid',
            'traveler_count' => 2,
            'unit_price' => '500.00',
            'total_amount' => '500.00',
            'currency' => 'XAF',
        ]);
        Booking::query()->whereKey($customBooking->id)->update(['created_at' => '2026-11-08 12:00:00']);

        $this->get(route('admin.dashboard.page', [
            'page' => 'platform_performance',
            'lang' => 'en',
            'created_from' => '2026-11-08',
            'created_to' => '2026-11-08',
        ]))
            ->assertOk()
            ->assertSee('Lakeside')
            ->assertSee('New York')
            ->assertSee('data-analytics-metric="bookings" data-analytics-value="2"', false);
    }

    public function test_analytics_exports_use_date_filtered_record_counts_and_are_authorized(): void
    {
        $this->travelTo('2026-11-09 12:00:00');
        $admin = $this->actingAsVerifiedAdmin();
        User::factory()->create([
            'role' => UserRole::TRAVELER->value,
            'created_at' => '2026-11-08 10:00:00',
        ]);

        $csvResponse = $this->actingAs($admin)->get(route('admin.analytics.export', [
            'lang' => 'en',
            'format' => 'csv',
            'created_from' => '2026-11-08',
            'created_to' => '2026-11-08',
        ]));
        $csvResponse->assertOk()->assertDownload('app-analytics-2026-11-08-2026-11-08.csv');
        $this->assertStringContainsString('2026-11-08,2026-11-08,1', $csvResponse->streamedContent());

        $jsonResponse = $this->actingAs($admin)->get(route('admin.analytics.export', [
            'lang' => 'en',
            'format' => 'json',
            'created_from' => '2026-11-08',
            'created_to' => '2026-11-08',
        ]));
        $jsonResponse->assertOk()->assertDownload('app-analytics-2026-11-08-2026-11-08.json');
        $jsonData = json_decode($jsonResponse->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $jsonData['daily'][0]['new_users']);

        $this->actingAs(User::factory()->withRole(UserRole::TRAVELER)->create())
            ->get(route('admin.analytics.export', ['lang' => 'en', 'format' => 'csv']))
            ->assertForbidden();
    }

    public function test_analytics_rejects_reversed_date_ranges(): void
    {
        $this->actingAsVerifiedAdmin();

        $this->get(route('admin.dashboard.page', [
            'page' => 'platform_performance',
            'lang' => 'en',
            'created_from' => '2026-11-10',
            'created_to' => '2026-11-08',
        ]))->assertSessionHasErrors('created_to');
    }

    public function test_analytics_pages_render_for_the_admin(): void
    {
        $this->actingAsVerifiedAdmin();

        foreach ([
            'platform_performance',
            'user_growth',
            'agency_growth',
            'booking_analytics',
            'popular_destinations',
            'package_performance',
            'trust_verification_statistics',
            'export_reports',
        ] as $page) {
            $this->get(route('admin.dashboard.page', ['page' => $page, 'lang' => 'en']))
                ->assertOk()
                ->assertSee('data-admin-analytics', false);
        }
    }

    private function actingAsVerifiedAdmin(): User
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
