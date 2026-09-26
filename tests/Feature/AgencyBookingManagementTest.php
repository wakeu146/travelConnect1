<?php

namespace Tests\Feature;

use App\Enums\PackageStatus;
use App\Enums\PaymentStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyBookingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_can_create_availability_for_its_own_package(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id, 'status' => PackageStatus::PUBLISHED]);

        $response = $this->actingAs($agency->owner)->post(route('agency.availability.store'), [
            'package_id' => $package->id,
            'departure_date' => '2026-10-10',
            'capacity' => 20,
            'status' => 'active',
            'notes' => 'Early season departure',
        ]);

        $response->assertRedirect(route('agency.availability.index'));
        $this->assertDatabaseHas('package_availability', [
            'package_id' => $package->id,
            'capacity' => 20,
            'status' => 'active',
        ]);

        $createdAvailability = PackageAvailability::query()->where('package_id', $package->id)->first();
        $this->assertNotNull($createdAvailability);
        $this->assertSame('2026-10-10', $createdAvailability->departure_date->format('Y-m-d'));
    }

    public function test_agency_cannot_create_availability_for_another_agencys_package(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $packageB = Package::factory()->create(['agency_id' => $agencyB->id, 'status' => PackageStatus::PUBLISHED]);

        $this->actingAs($agencyA->owner)
            ->post(route('agency.availability.store'), [
                'package_id' => $packageB->id,
                'departure_date' => '2026-10-15',
                'capacity' => 10,
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_traveler_can_book_a_published_package_with_valid_capacity(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create([
            'agency_id' => $agency->id,
            'status' => PackageStatus::PUBLISHED,
            'price_from' => 1500,
        ]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2026-11-05',
            'capacity' => 10,
            'status' => 'active',
        ]);
        $traveler = User::factory()->create(['role' => 'traveler']);

        $response = $this->actingAs($traveler)->post(route('bookings.store', ['package' => $package->id]), [
            'package_availability_id' => $availability->id,
            'traveler_count' => 2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'user_id' => $traveler->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'traveler_count' => 2,
            'status' => 'pending',
            'payment_status' => PaymentStatus::UNPAID->value,
        ]);
    }

    public function test_over_capacity_booking_is_rejected_and_price_is_snapshotted(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id, 'status' => PackageStatus::PUBLISHED, 'price_from' => 2000]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2026-12-01',
            'capacity' => 3,
            'status' => 'active',
        ]);
        Booking::create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => User::factory()->create(['role' => 'traveler'])->id,
            'status' => 'confirmed',
            'payment_status' => PaymentStatus::UNPAID->value,
            'traveler_count' => 2,
            'unit_price' => 2000,
            'total_amount' => 4000,
            'currency' => 'USD',
            'booking_reference' => 'TC-2026-000001',
            'expires_at' => now()->addHour(),
        ]);
        $traveler = User::factory()->create(['role' => 'traveler']);

        $this->actingAs($traveler)
            ->post(route('bookings.store', ['package' => $package->id]), [
                'package_availability_id' => $availability->id,
                'traveler_count' => 2,
            ])
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing('bookings', ['user_id' => $traveler->id, 'package_id' => $package->id]);
    }

    public function test_agency_can_confirm_and_complete_booking_lifecycle(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id, 'status' => PackageStatus::PUBLISHED, 'price_from' => 1200]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2026-09-20',
            'capacity' => 10,
            'status' => 'active',
        ]);
        $traveler = User::factory()->create(['role' => 'traveler']);
        $booking = Booking::create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'status' => 'pending',
            'payment_status' => PaymentStatus::UNPAID->value,
            'traveler_count' => 1,
            'unit_price' => 1200,
            'total_amount' => 1200,
            'currency' => 'USD',
            'booking_reference' => 'TC-2026-000100',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($agency->owner)
            ->patch(route('agency.bookings.confirm', ['booking' => $booking->id]))
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status->value);

        $this->actingAs($agency->owner)
            ->patch(route('agency.bookings.complete', ['booking' => $booking->id]))
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame('completed', $booking->status->value);
    }

    public function test_expired_pending_bookings_are_marked_expired(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id, 'status' => PackageStatus::PUBLISHED]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2026-09-10',
            'capacity' => 5,
            'status' => 'active',
        ]);
        $traveler = User::factory()->create(['role' => 'traveler']);

        $booking = Booking::create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'status' => 'pending',
            'payment_status' => PaymentStatus::UNPAID->value,
            'traveler_count' => 1,
            'unit_price' => 150,
            'total_amount' => 150,
            'currency' => 'USD',
            'booking_reference' => 'TC-2026-000200',
            'expires_at' => now()->subMinute(),
        ]);

        $service = new \App\Services\BookingService();
        $service->expirePendingBookings();

        $booking->refresh();
        $this->assertSame('expired', $booking->status->value);
    }

    public function test_capacity_snapshot_counts_only_active_reservations_and_reports_occupancy(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2026-12-01',
            'capacity' => 5,
            'status' => 'active',
        ]);
        $service = new \App\Services\BookingService();

        foreach ([
            ['status' => 'pending', 'traveler_count' => 1, 'expires_at' => now()->addHour()],
            ['status' => 'confirmed', 'traveler_count' => 2, 'expires_at' => now()->addHour()],
            ['status' => 'pending', 'traveler_count' => 3, 'expires_at' => now()->subMinute()],
            ['status' => 'cancelled', 'traveler_count' => 4, 'expires_at' => now()->addHour()],
        ] as $index => $attributes) {
            Booking::create(array_merge($attributes, [
                'agency_id' => $agency->id,
                'package_id' => $package->id,
                'package_availability_id' => $availability->id,
                'user_id' => User::factory()->create(['role' => 'traveler'])->id,
                'payment_status' => PaymentStatus::UNPAID->value,
                'unit_price' => 100,
                'total_amount' => 100 * $attributes['traveler_count'],
                'currency' => 'USD',
                'booking_reference' => 'TC-2026-'.str_pad((string) ($index + 800), 6, '0', STR_PAD_LEFT),
            ]));
        }

        $snapshot = $service->capacitySnapshots([$availability])->get($availability->id);

        $this->assertSame(2, $snapshot['booking_count']);
        $this->assertSame(3, $snapshot['reserved_travelers']);
        $this->assertSame(2, $snapshot['remaining_capacity']);
        $this->assertSame(60, $snapshot['occupancy_percent']);
        $this->assertFalse($snapshot['sold_out']);
        $this->assertTrue($snapshot['near_capacity']);
        $this->assertSame(2, $service->getAvailableCapacity($availability));
    }

    public function test_invalid_booking_transition_is_rejected(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id, 'status' => PackageStatus::PUBLISHED]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2026-09-15',
            'capacity' => 8,
            'status' => 'active',
        ]);
        $traveler = User::factory()->create(['role' => 'traveler']);

        $booking = Booking::create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'status' => 'completed',
            'payment_status' => PaymentStatus::PAID->value,
            'traveler_count' => 1,
            'unit_price' => 200,
            'total_amount' => 200,
            'currency' => 'USD',
            'booking_reference' => 'TC-2026-000300',
            'expires_at' => now()->addHour(),
        ]);

        $service = new \App\Services\BookingService();

        $this->expectException(\RuntimeException::class);
        $service->transitionStatus($booking, \App\Enums\BookingStatus::CONFIRMED);
    }

    public function test_agency_availability_index_page_renders_for_the_current_agency(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id, 'status' => PackageStatus::PUBLISHED, 'title' => 'Marrakech Discovery']);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2026-11-10',
            'capacity' => 12,
            'status' => 'active',
        ]);

        $this->actingAs($agency->owner)
            ->get(route('agency.availability.index'))
            ->assertOk()
            ->assertSee('Marrakech Discovery')
            ->assertSee('2026-11-10');
    }

    public function test_agency_booking_detail_page_renders_for_the_current_agency(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id, 'status' => PackageStatus::PUBLISHED, 'title' => 'Cape Adventure']);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2026-12-02',
            'capacity' => 6,
            'status' => 'active',
        ]);
        $traveler = User::factory()->create(['role' => 'traveler', 'name' => 'Sam Traveler']);
        $booking = Booking::create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'status' => 'pending',
            'payment_status' => PaymentStatus::UNPAID->value,
            'traveler_count' => 2,
            'unit_price' => 1500,
            'total_amount' => 3000,
            'currency' => 'USD',
            'booking_reference' => 'TC-2026-001234',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($agency->owner)
            ->get(route('agency.bookings.show', ['booking' => $booking->id]))
            ->assertOk()
            ->assertSee('TC-2026-001234')
            ->assertSee('Sam Traveler');
    }

    public function test_traveler_booking_form_and_confirmation_pages_render_with_real_data(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create([
            'agency_id' => $agency->id,
            'status' => PackageStatus::PUBLISHED,
            'title' => 'Sahel Journey',
            'price_from' => 1500,
            'duration_days' => 6,
        ]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2026-10-12',
            'capacity' => 10,
            'status' => 'active',
        ]);
        $traveler = User::factory()->create(['role' => 'traveler', 'name' => 'Nadia']);

        $this->actingAs($traveler)
            ->get(route('bookings.create', ['package' => $package->id]))
            ->assertOk()
            ->assertSee('Sahel Journey')
            ->assertSee('Available departures');

        $booking = Booking::create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'status' => 'pending',
            'payment_status' => PaymentStatus::UNPAID->value,
            'traveler_count' => 2,
            'unit_price' => 1500,
            'total_amount' => 3000,
            'currency' => 'USD',
            'booking_reference' => 'TC-2026-001235',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($traveler)
            ->get(route('bookings.confirmation', ['booking' => $booking->id]))
            ->assertOk()
            ->assertSee('Booking request received')
            ->assertSee('TC-2026-001235');

        $this->actingAs($traveler)
            ->get(route('bookings.show', ['booking' => $booking->id]))
            ->assertOk()
            ->assertSee('Booking details');
    }

    public function test_unauthorized_users_cannot_access_booking_pages(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id, 'status' => PackageStatus::PUBLISHED]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2026-08-20',
            'capacity' => 8,
            'status' => 'active',
        ]);
        $traveler = User::factory()->create(['role' => 'traveler']);
        $otherTraveler = User::factory()->create(['role' => 'traveler']);
        $booking = Booking::create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'status' => 'pending',
            'payment_status' => PaymentStatus::UNPAID->value,
            'traveler_count' => 1,
            'unit_price' => 1200,
            'total_amount' => 1200,
            'currency' => 'USD',
            'booking_reference' => 'TC-2026-001236',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($otherTraveler)->get(route('bookings.show', ['booking' => $booking->id]))->assertForbidden();
        $this->actingAs($otherTraveler)->get(route('bookings.confirmation', ['booking' => $booking->id]))->assertForbidden();
    }
}
