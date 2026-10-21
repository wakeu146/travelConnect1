<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\BookingStatus;
use App\Enums\PackageStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\Review;
use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminDashboardControllerTest extends TestCase
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

    public function test_unauthenticated_request_to_dashboard_redirects_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_open_dashboard_sections_or_pages(): void
    {
        $traveler = User::factory()->create();

        $this->actingAs($traveler)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->get(route('admin.dashboard.section', ['section' => 'platform_management']))
            ->assertForbidden();
    }

    public function test_verified_admin_can_render_all_navigation_destinations(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $this->actingAs($admin)->withSession([
            'admin_login.verified_user_id' => $admin->id,
            'admin_login.session_version' => $admin->admin_session_version,
            'admin_login.verified_at' => now()->timestamp,
            'admin_login.last_activity' => now()->timestamp,
        ]);

        $sections = config('admin_dashboard.sections');

        foreach ($sections as $sectionKey => $section) {
            $sectionUrl = $sectionKey === 'main'
                ? route('admin.dashboard', ['lang' => 'en'])
                : route('admin.dashboard.section', ['section' => $sectionKey, 'lang' => 'en']);

            $sectionResponse = $this->get($sectionUrl);

            if ($sectionKey === 'main') {
                $sectionResponse->assertOk()
                    ->assertSee(__('messages.admin_nav.sections.'.$sectionKey))
                    ->assertSee('aria-current="page"', false)
                    ->assertSee(__('messages.admin_nav.switch_language_to_english'))
                    ->assertSee(__('messages.admin_nav.switch_language_to_french'))
                    ->assertSee('data-admin-locale="en"', false)
                    ->assertSee('data-admin-locale="fr"', false)
                    ->assertSee('data-admin-overview', false);
            } else {
                $sectionResponse->assertOk()
                    ->assertSee(__('messages.admin_nav.sections.'.$sectionKey))
                    ->assertSee('data-admin-section-hub="'.$sectionKey.'"', false)
                    ->assertSee(__('messages.admin_nav.section_hub_intros.'.$sectionKey))
                    ->assertSee('aria-current="page"', false)
                    ->assertSee(__('messages.admin_nav.switch_language_to_english'))
                    ->assertSee(__('messages.admin_nav.switch_language_to_french'))
                    ->assertSee('data-admin-locale="en"', false)
                    ->assertSee('data-admin-locale="fr"', false);

                foreach ($section['groups'] as $group) {
                    $groupPages = $group['pages'] ?? [$group['page']];
                    $groupKey = $group['label'] ?? $group['page'];
                    $groupLabel = isset($group['label'])
                        ? __('messages.admin_nav.groups.'.$group['label'])
                        : __('messages.admin_nav.pages.'.$group['page']);

                    $sectionResponse->assertSee('data-admin-section-card="'.$groupKey.'"', false)
                        ->assertSee($groupLabel)
                        ->assertSee(__('messages.admin_nav.section_hub_descriptions.'.$groupKey))
                        ->assertSee(route('admin.dashboard.page', [
                            'page' => $groupPages[0],
                            'lang' => 'en',
                        ]), false);
                }

                $frenchSectionResponse = $this->get(route('admin.dashboard.section', [
                    'section' => $sectionKey,
                    'lang' => 'fr',
                ]))
                    ->assertOk()
                    ->assertSee(__('messages.admin_nav.sections.'.$sectionKey, [], 'fr'))
                    ->assertSee('data-admin-section-hub="'.$sectionKey.'"', false)
                    ->assertSee(__('messages.admin_nav.section_hub_intros.'.$sectionKey, [], 'fr'));

                foreach ($section['groups'] as $group) {
                    $groupPages = $group['pages'] ?? [$group['page']];
                    $groupKey = $group['label'] ?? $group['page'];
                    $groupLabel = isset($group['label'])
                        ? __('messages.admin_nav.groups.'.$group['label'], [], 'fr')
                        : __('messages.admin_nav.pages.'.$group['page'], [], 'fr');

                    $frenchSectionResponse->assertSee('data-admin-section-card="'.$groupKey.'"', false)
                        ->assertSee($groupLabel)
                        ->assertSee(__('messages.admin_nav.section_hub_descriptions.'.$groupKey, [], 'fr'))
                        ->assertSee(route('admin.dashboard.page', [
                            'page' => $groupPages[0],
                            'lang' => 'fr',
                        ]), false);
                }

                if ($sectionKey === 'platform_management') {
                    $sectionResponse->assertDontSee('data-admin-agency-directory', false);
                }
            }

            foreach ($section['groups'] as $group) {
                foreach ($group['pages'] ?? [$group['page']] as $page) {
                    $pageResponse = $this->get(route('admin.dashboard.page', ['page' => $page, 'lang' => 'en']))
                        ->assertOk()
                        ->assertSee(__('messages.admin_nav.pages.'.$page))
                        ->assertSee(__('messages.admin_nav.sections.'.$sectionKey))
                        ->assertSee('aria-current="page"', false);

                    if ($page === 'overview') {
                        $pageResponse->assertSee('data-admin-overview', false);
                    } elseif ($page === 'alerts') {
                        $pageResponse->assertSee('data-admin-alerts', false)
                            ->assertSee('data-alerts-empty', false);
                    } elseif (in_array($page, [
                        'all_agencies',
                        'pending_verification',
                        'verified_agencies',
                        'unverified_agencies',
                        'suspended_agencies',
                    ], true)) {
                        $pageResponse->assertSee('data-admin-agency-directory', false);
                    } else {
                        $pageResponse->assertSee(__('messages.admin_nav.page_in_preparation'));
                    }
                }
            }
        }

        $this->get(route('admin.dashboard.page', ['page' => 'overview', 'lang' => 'fr']))
            ->assertOk()
            ->assertSee('<html lang="fr">', false)
            ->assertSee(__('messages.admin_nav.pages.overview', [], 'fr'))
            ->assertSee(__('messages.admin_overview.title', [], 'fr'))
            ->assertSee(__('messages.admin_overview.booking_status_title', [], 'fr'))
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('data-admin-locale="en"', false)
            ->assertSee('data-admin-locale="fr"', false);
    }

    public function test_overview_shows_current_counts_attention_items_and_recent_activity(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $this->actingAs($admin)->withSession([
            'admin_login.verified_user_id' => $admin->id,
            'admin_login.session_version' => $admin->admin_session_version,
            'admin_login.verified_at' => now()->timestamp,
            'admin_login.last_activity' => now()->timestamp,
        ]);

        $traveler = User::factory()->create(['name' => 'Alex Traveler']);
        $pendingAgency = Agency::factory()->create(['company_name' => 'North Star Travel']);
        $verifiedAgency = Agency::factory()->create([
            'company_name' => 'Coastal Travel Group',
            'verification_status' => VerificationStatus::VERIFIED,
        ]);
        Package::factory()->for($verifiedAgency)->create([
            'title' => 'Island Escape',
            'status' => PackageStatus::PUBLISHED,
        ]);
        Inquiry::factory()->for($pendingAgency)->for($traveler)->create([
            'subject' => 'Safari planning request',
            'status' => 'open',
        ]);
        Review::factory()->for($pendingAgency)->for($traveler)->create([
            'comment' => 'A pending review for moderation.',
            'status' => ReviewStatus::PENDING,
        ]);
        Review::factory()->for($pendingAgency)->for($traveler)->create([
            'comment' => 'A review reported for moderation.',
            'status' => ReviewStatus::FLAGGED,
        ]);
        $traveler->travelerIssues()->create([
            'type' => 'complaint',
            'subject' => 'Traveler support case',
            'description' => 'A traveler complaint requiring administrator follow-up.',
        ]);
        TravelRequest::factory()->for($traveler, 'traveler')->create([
            'title' => 'Family trip request',
        ]);

        $response = $this->get(route('admin.dashboard', ['lang' => 'en']));

        $response->assertSee('data-overview-metric-value="travelers">1</span>', false)
            ->assertSee('data-overview-metric-value="agencies">2</span>', false)
            ->assertSee('data-overview-metric-value="published-packages">1</span>', false)
            ->assertSee('data-overview-metric-value="bookings">0</span>', false)
            ->assertSee('data-agency-status-count="pending">1</span>', false)
            ->assertSee('data-agency-status-count="verified">1</span>', false)
            ->assertSee('data-agency-status-count="rejected">0</span>', false)
            ->assertSee('data-booking-status-empty', false)
            ->assertSee('data-attention-count="pending-verifications">1</span>', false)
            ->assertSee('data-attention-count="flagged-reviews">1</span>', false)
            ->assertSee('data-attention-count="open-traveler-issues">1</span>', false)
            ->assertSee('data-attention-count="open-inquiries">1</span>', false)
            ->assertSee('data-attention-count="pending-reviews">1</span>', false)
            ->assertSee('data-attention-count="open-travel-requests">1</span>', false)
            ->assertSee('data-activity-type="traveler"', false)
            ->assertSee('data-activity-type="agency"', false)
            ->assertSee('data-activity-type="package"', false)
            ->assertSee('data-activity-type="inquiry"', false)
            ->assertSee('data-activity-type="travel_request"', false)
            ->assertSee('data-activity-type="traveler_issue"', false)
            ->assertSee('Alex Traveler')
            ->assertSee('North Star Travel')
            ->assertSee('Island Escape')
            ->assertSee('Safari planning request')
            ->assertSee('Family trip request')
            ->assertSee('Traveler support case')
            ->assertSee(route('admin.dashboard.page', ['page' => 'pending_verification', 'lang' => 'en']), false)
            ->assertSee(route('admin.dashboard.page', ['page' => 'alerts', 'lang' => 'en']), false)
            ->assertSee(route('admin.dashboard.page', ['page' => 'traveler_inquiries', 'lang' => 'en']), false)
            ->assertSee(route('admin.dashboard.page', ['page' => 'reviews_ratings', 'lang' => 'en']), false)
            ->assertSee(route('admin.dashboard.page', ['page' => 'reported_content', 'lang' => 'en']), false)
            ->assertSee(route('admin.dashboard.page', ['page' => 'new_requests', 'lang' => 'en']), false);
    }

    public function test_overview_shows_zero_counts_and_empty_states_without_platform_activity(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $this->actingAs($admin)->withSession([
            'admin_login.verified_user_id' => $admin->id,
            'admin_login.session_version' => $admin->admin_session_version,
            'admin_login.verified_at' => now()->timestamp,
            'admin_login.last_activity' => now()->timestamp,
        ]);

        $this->get(route('admin.dashboard', ['lang' => 'en']))
            ->assertSee('data-overview-metric-value="travelers">0</span>', false)
            ->assertSee('data-overview-metric-value="agencies">0</span>', false)
            ->assertSee('data-overview-metric-value="published-packages">0</span>', false)
            ->assertSee('data-overview-metric-value="bookings">0</span>', false)
            ->assertSee('data-agency-status-empty', false)
            ->assertSee('data-booking-status-empty', false)
            ->assertSee('data-attention-empty', false)
            ->assertSee('data-activity-empty', false)
            ->assertSee(__('messages.admin_overview.no_attention_items'))
            ->assertSee(__('messages.admin_overview.no_recent_activity'));
    }

    public function test_overview_excludes_expired_pending_bookings_from_attention_count(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $this->actingAs($admin)->withSession([
            'admin_login.verified_user_id' => $admin->id,
            'admin_login.session_version' => $admin->admin_session_version,
            'admin_login.verified_at' => now()->timestamp,
            'admin_login.last_activity' => now()->timestamp,
        ]);

        $traveler = User::factory()->create();
        $agency = Agency::factory()->create();
        $package = Package::factory()->for($agency)->create();
        $availability = PackageAvailability::query()->create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 10,
        ]);
        $bookingDetails = [
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'status' => BookingStatus::PENDING,
            'traveler_count' => 1,
            'unit_price' => 100,
            'total_amount' => 100,
            'currency' => 'XAF',
        ];
        Booking::query()->create([
            ...$bookingDetails,
            'booking_reference' => 'TC-OVERVIEW-ACTIVE',
            'expires_at' => now()->addHour(),
        ]);
        Booking::query()->create([
            ...$bookingDetails,
            'booking_reference' => 'TC-OVERVIEW-EXPIRED',
            'expires_at' => now()->subMinute(),
        ]);
        Booking::query()->create([
            ...$bookingDetails,
            'booking_reference' => 'TC-OVERVIEW-CONFIRMED',
            'status' => BookingStatus::CONFIRMED,
            'expires_at' => null,
        ]);

        $this->get(route('admin.dashboard', ['lang' => 'en']))
            ->assertSee('data-overview-metric-value="bookings">3</span>', false)
            ->assertSee('data-booking-status-count="pending">2</span>', false)
            ->assertSee('data-booking-status-count="confirmed">1</span>', false)
            ->assertSee('data-booking-status-count="cancelled">0</span>', false)
            ->assertSee('data-attention-count="pending-bookings">1</span>', false);
    }

    public function test_unknown_dashboard_sections_and_pages_return_not_found(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $this->actingAs($admin)->withSession([
            'admin_login.verified_user_id' => $admin->id,
            'admin_login.session_version' => $admin->admin_session_version,
            'admin_login.verified_at' => now()->timestamp,
            'admin_login.last_activity' => now()->timestamp,
        ]);

        $this->get(route('admin.dashboard.section', ['section' => 'unknown']))
            ->assertNotFound();

        $this->get(route('admin.dashboard.page', ['page' => 'unknown']))
            ->assertNotFound();
    }
}
