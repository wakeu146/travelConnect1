<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminBookingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(AdminSecondFactor::class, Mockery::mock(AdminSecondFactor::class));
    }

    public function test_admin_lists_real_bookings_using_search_and_composable_filters(): void
    {
        $admin = $this->actingAsVerifiedAdmin();
        $booking = $this->createBooking(
            'TC-ADMIN-BOOKING-001',
            'pending',
            'Sam Traveler',
            'Northwind Tours',
            'Coastal Escape',
        );
        $this->createBooking(
            'TC-ADMIN-BOOKING-002',
            'confirmed',
            'Another Traveler',
            'Other Tours',
            'Mountain Route',
        );

        $this->actingAs($admin)
            ->get(route('admin.bookings.index', [
                'lang' => 'en',
                'search' => 'Sam Traveler',
                'status' => 'pending',
                'agency' => 'Northwind',
                'package' => 'Coastal',
            ]))
            ->assertOk()
            ->assertSee('TC-ADMIN-BOOKING-001')
            ->assertDontSee('TC-ADMIN-BOOKING-002')
            ->assertSee('data-admin-bookings="all_bookings"', false);

        $this->assertModelExists($booking);
    }

    public function test_status_specific_page_cannot_be_broadened_by_query_parameters(): void
    {
        $admin = $this->actingAsVerifiedAdmin();
        $pending = $this->createBooking('TC-ADMIN-PENDING-001', 'pending');
        $confirmed = $this->createBooking('TC-ADMIN-CONFIRMED-001', 'confirmed');

        $this->actingAs($admin)
            ->get(route('admin.bookings.pending', ['lang' => 'en', 'status' => 'confirmed']))
            ->assertOk()
            ->assertSee($pending->booking_reference)
            ->assertDontSee($confirmed->booking_reference);
    }

    public function test_admin_confirmation_preserves_payment_state_and_booking_price_snapshot(): void
    {
        $admin = $this->actingAsVerifiedAdmin();
        $booking = $this->createBooking('TC-ADMIN-CONFIRM-001', 'pending', extras: [
            'traveler_count' => 2,
            'unit_price' => '321.00',
            'total_amount' => '642.00',
            'payment_status' => PaymentStatus::UNPAID,
        ]);
        $booking->package->update(['price_from' => '9999.00']);

        $this->actingAs($admin)
            ->patch(route('admin.bookings.status', ['booking' => $booking->id, 'lang' => 'en']), [
                'status' => 'confirmed',
                'reason' => 'Agency documentation was reviewed and verified.',
            ])
            ->assertRedirect(route('admin.bookings.show', ['booking' => $booking->id, 'lang' => 'en']));

        $booking->refresh();
        $this->assertSame(BookingStatus::CONFIRMED, $booking->status);
        $this->assertSame(PaymentStatus::UNPAID, $booking->payment_status);
        $this->assertSame('321.00', $booking->unit_price);
        $this->assertSame('642.00', $booking->total_amount);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'actor_id' => $admin->id,
            'event_type' => 'status_transition',
            'from_status' => 'pending',
            'to_status' => 'confirmed',
            'reason' => 'Agency documentation was reviewed and verified.',
        ]);
    }

    public function test_admin_cannot_request_an_invalid_transition_or_change_payment_status(): void
    {
        $admin = $this->actingAsVerifiedAdmin();
        $booking = $this->createBooking('TC-ADMIN-INVALID-001', 'pending');

        $this->actingAs($admin)
            ->patch(route('admin.bookings.status', ['booking' => $booking->id, 'lang' => 'en']), [
                'status' => 'completed',
                'payment_status' => 'paid',
                'reason' => 'Attempting an unsupported lifecycle change.',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(BookingStatus::PENDING, $booking->fresh()->status);
        $this->assertSame(PaymentStatus::UNPAID, $booking->fresh()->payment_status);
        $this->assertDatabaseMissing('booking_status_histories', [
            'booking_id' => $booking->id,
            'event_type' => 'status_transition',
        ]);
    }

    public function test_non_admin_cannot_view_or_export_booking_data(): void
    {
        $booking = $this->createBooking('TC-ADMIN-PRIVATE-001', 'pending');
        $traveler = $booking->traveler;

        $this->actingAs($traveler)
            ->get(route('admin.bookings.show', ['booking' => $booking->id]))
            ->assertForbidden();

        $this->actingAs($traveler)
            ->get(route('admin.bookings.export'))
            ->assertForbidden();
    }

    public function test_admin_opens_booking_details_with_dispute_history_loaded(): void
    {
        $this->actingAsVerifiedAdmin();
        $booking = $this->createBooking('TC-ADMIN-DETAIL-001', 'pending');

        $this->get(route('admin.bookings.show', ['booking' => $booking->id, 'lang' => 'en']))
            ->assertOk()
            ->assertSee($booking->booking_reference);
    }

    public function test_booking_export_is_filtered_and_escapes_spreadsheet_formulas(): void
    {
        $admin = $this->actingAsVerifiedAdmin();
        $formulaBooking = $this->createBooking(
            'TC-EXPORT-FORMULA-001',
            'pending',
            packageTitle: '=HYPERLINK("https://example.test")',
        );
        $this->createBooking('TC-EXPORT-OTHER-001', 'confirmed');

        $response = $this->actingAs($admin)->get(route('admin.bookings.export', [
            'lang' => 'en',
            'search' => $formulaBooking->booking_reference,
        ]));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringContainsString($formulaBooking->booking_reference, $content);
        $this->assertStringNotContainsString('TC-EXPORT-OTHER-001', $content);
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

    /**
     * @param  array<string, mixed>  $extras
     */
    private function createBooking(
        string $reference,
        string $status,
        string $travelerName = 'Test Traveler',
        string $agencyName = 'Test Travel Agency',
        string $packageTitle = 'Test Travel Package',
        array $extras = [],
    ): Booking {
        $agency = Agency::factory()->create(['company_name' => $agencyName]);
        $package = Package::factory()->for($agency)->create([
            'title' => $packageTitle,
            'status' => 'published',
            'price_from' => '450.00',
        ]);
        $availability = PackageAvailability::query()->create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonths(2)->toDateString(),
            'capacity' => 10,
            'status' => 'active',
        ]);
        $traveler = User::factory()->create([
            'name' => $travelerName,
            'role' => UserRole::TRAVELER,
        ]);

        return Booking::query()->create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'booking_reference' => $reference,
            'status' => $status,
            'payment_status' => PaymentStatus::UNPAID,
            'traveler_count' => 1,
            'unit_price' => '450.00',
            'total_amount' => '450.00',
            'currency' => 'XAF',
            'expires_at' => now()->addHour(),
            ...$extras,
        ]);
    }
}
