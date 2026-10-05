<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PackageStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentTransactionType;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PaymentSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
        $this->assertSame(1, $agency->owner->notifications()->whereJsonContains('data->type', 'booking')->count());
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
            'currency' => 'XAF',
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
            'currency' => 'XAF',
            'booking_reference' => 'TC-2026-000100',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($agency->owner)
            ->patch(route('agency.bookings.confirm', ['booking' => $booking->id]))
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status->value);
        $this->assertSame(PaymentStatus::PAID, $booking->payment_status);
        $this->assertSame(1, $traveler->notifications()->whereJsonContains('data->type', 'booking')->count());

        $this->actingAs($agency->owner)
            ->patch(route('agency.bookings.complete', ['booking' => $booking->id]))
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame('completed', $booking->status->value);
        $this->assertSame(2, $traveler->notifications()->whereJsonContains('data->type', 'booking')->count());
    }

    public function test_booking_payment_status_tracks_verified_charges_and_refunds(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id, 'price_from' => 200]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 5,
            'status' => 'active',
        ]);
        $booking = $this->createBooking($agency, $package, $availability, User::factory()->create(['role' => 'traveler']), 'TC-PAYMENT-000001', [
            'unit_price' => 200,
            'total_amount' => 200,
        ]);
        $settlementService = new PaymentSettlementService;

        $firstCharge = $settlementService->record(
            $booking,
            'test-provider',
            PaymentTransactionType::CHARGE,
            PaymentTransactionStatus::SUCCEEDED,
            '80.00',
            'XAF',
            'provider-charge-001',
            'mobile_money'
        );
        $booking->refresh();
        $this->assertSame(PaymentStatus::PARTIAL, $booking->payment_status);

        $duplicateCharge = $settlementService->record(
            $booking,
            'test-provider',
            PaymentTransactionType::CHARGE,
            PaymentTransactionStatus::SUCCEEDED,
            '80.00',
            'XAF',
            'provider-charge-001',
            'mobile_money'
        );
        $this->assertSame($firstCharge->id, $duplicateCharge->id);
        $this->assertSame(1, $booking->paymentTransactions()->count());

        $settlementService->record(
            $booking,
            'test-provider',
            PaymentTransactionType::CHARGE,
            PaymentTransactionStatus::SUCCEEDED,
            '120.00',
            'XAF',
            'provider-charge-002',
            'mobile_money'
        );
        $this->assertSame(PaymentStatus::PAID, $booking->fresh()->payment_status);

        $settlementService->record(
            $booking,
            'test-provider',
            PaymentTransactionType::REFUND,
            PaymentTransactionStatus::SUCCEEDED,
            '50.00',
            'XAF',
            'provider-refund-001',
            'mobile_money'
        );
        $this->assertSame(PaymentStatus::PARTIAL, $booking->fresh()->payment_status);

        $settlementService->record(
            $booking,
            'test-provider',
            PaymentTransactionType::REFUND,
            PaymentTransactionStatus::SUCCEEDED,
            '150.00',
            'XAF',
            'provider-refund-002',
            'mobile_money'
        );
        $this->assertSame(PaymentStatus::REFUNDED, $booking->fresh()->payment_status);
    }

    public function test_successful_refunds_cannot_exceed_successful_charges(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id, 'price_from' => 100]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 5,
            'status' => 'active',
        ]);
        $booking = $this->createBooking($agency, $package, $availability, User::factory()->create(['role' => 'traveler']), 'TC-REFUND-000001', [
            'unit_price' => 100,
            'total_amount' => 100,
        ]);
        $settlementService = new PaymentSettlementService;
        $settlementService->record($booking, 'test-provider', PaymentTransactionType::CHARGE, PaymentTransactionStatus::SUCCEEDED, '100.00', 'XAF', 'charge-full');

        try {
            $settlementService->record($booking, 'test-provider', PaymentTransactionType::REFUND, PaymentTransactionStatus::SUCCEEDED, '100.01', 'XAF', 'refund-over');
            $this->fail('An over-refund should be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Successful refunds cannot exceed successful charges.', $exception->getMessage());
        }

        $this->assertSame(1, $booking->paymentTransactions()->count());
        $this->assertSame(PaymentStatus::PAID, $booking->fresh()->payment_status);
    }

    public function test_legacy_paid_flag_without_a_successful_transaction_is_not_treated_as_paid(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id, 'price_from' => 100]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 5,
            'status' => 'active',
        ]);
        $booking = $this->createBooking(
            $agency,
            $package,
            $availability,
            User::factory()->create(['role' => 'traveler']),
            'TC-LEGACY-PAID-000001',
            ['payment_status' => PaymentStatus::PAID->value]
        );

        $bookingWithPaymentSummary = Booking::query()->withPaymentSummary()->findOrFail($booking->id);

        $this->assertSame(PaymentStatus::UNPAID, $bookingWithPaymentSummary->effectivePaymentStatus());
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
            'currency' => 'XAF',
            'booking_reference' => 'TC-2026-000200',
            'expires_at' => now()->subMinute(),
        ]);

        $service = new BookingService;
        $service->expirePendingBookings();

        $booking->refresh();
        $this->assertSame('expired', $booking->status->value);
    }

    public function test_agency_cannot_confirm_an_expired_pending_booking(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id, 'status' => PackageStatus::PUBLISHED]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 5,
            'status' => 'active',
        ]);
        $traveler = User::factory()->create(['role' => 'traveler']);
        $booking = $this->createBooking($agency, $package, $availability, $traveler, 'TC-EXPIRED-000001', [
            'expires_at' => now()->subMinute(),
        ]);

        $detail = $this->actingAs($agency->owner)->get(route('agency.bookings.show', ['booking' => $booking->id]));
        $detail->assertOk()
            ->assertSee(__('messages.booking_expired_notice'))
            ->assertSee(__('messages.booking_expiry_deadline'))
            ->assertDontSee(__('messages.timeline_expired'))
            ->assertDontSee(route('agency.bookings.confirm', ['booking' => $booking->id]), false)
            ->assertDontSee(route('agency.bookings.reject', ['booking' => $booking->id]), false);

        $response = $this->actingAs($agency->owner)
            ->patch(route('agency.bookings.confirm', ['booking' => $booking->id]));

        $response->assertRedirect(route('agency.bookings.show', ['booking' => $booking->id]))
            ->assertSessionHasErrors('status');
        $this->assertSame('expired', $booking->fresh()->status->value);
        $this->assertSame(1, $traveler->notifications()->whereJsonContains('data->type', 'booking')->count());
    }

    public function test_rejected_and_agency_cancelled_bookings_notify_the_traveler(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 8,
            'status' => 'active',
        ]);
        $rejectedTraveler = User::factory()->create(['role' => 'traveler']);
        $rejectedBooking = $this->createBooking($agency, $package, $availability, $rejectedTraveler, 'TC-REJECT-NOTICE');
        $cancelledTraveler = User::factory()->create(['role' => 'traveler']);
        $cancelledBooking = $this->createBooking($agency, $package, $availability, $cancelledTraveler, 'TC-CANCEL-NOTICE');

        $this->actingAs($agency->owner)
            ->patch(route('agency.bookings.reject', ['booking' => $rejectedBooking->id]))
            ->assertRedirect();
        $this->actingAs($agency->owner)
            ->patch(route('agency.bookings.cancel', ['booking' => $cancelledBooking->id]))
            ->assertRedirect();

        $this->assertSame(1, $rejectedTraveler->notifications()->whereJsonContains('data->type', 'booking')->count());
        $this->assertSame(1, $cancelledTraveler->notifications()->whereJsonContains('data->type', 'booking')->count());
    }

    public function test_traveler_cancellation_notifies_the_agency_owner(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 8,
            'status' => 'active',
        ]);
        $traveler = User::factory()->create(['role' => 'traveler', 'name' => 'Taylor Traveler']);
        $booking = $this->createBooking($agency, $package, $availability, $traveler, 'TC-TRAVELER-CANCEL');

        $response = $this->actingAs($traveler)->post(route('bookings.cancel', ['booking' => $booking->id]));

        $response->assertRedirect();
        $this->assertSame('cancelled', $booking->fresh()->status->value);
        $this->assertSame(1, $agency->owner->notifications()->whereJsonContains('data->type', 'booking')->count());
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
        $service = new BookingService;

        foreach ([
            ['status' => 'pending', 'traveler_count' => 1, 'expires_at' => now()->addHour()],
            ['status' => 'confirmed', 'traveler_count' => 2, 'expires_at' => now()->addHour()],
            ['status' => 'pending', 'traveler_count' => 3, 'expires_at' => now()->subMinute()],
            ['status' => 'cancelled', 'traveler_count' => 4, 'expires_at' => now()->addHour()],
            ['status' => 'confirmed', 'traveler_count' => 1, 'expires_at' => now()->subMinute()],
            ['status' => 'completed', 'traveler_count' => 1, 'expires_at' => now()->subMinute()],
        ] as $index => $attributes) {
            Booking::create(array_merge($attributes, [
                'agency_id' => $agency->id,
                'package_id' => $package->id,
                'package_availability_id' => $availability->id,
                'user_id' => User::factory()->create(['role' => 'traveler'])->id,
                'payment_status' => PaymentStatus::UNPAID->value,
                'unit_price' => 100,
                'total_amount' => 100 * $attributes['traveler_count'],
                'currency' => 'XAF',
                'booking_reference' => 'TC-2026-'.str_pad((string) ($index + 800), 6, '0', STR_PAD_LEFT),
            ]));
        }

        $snapshot = $service->capacitySnapshots([$availability])->get($availability->id);

        $this->assertSame(3, $snapshot['booking_count']);
        $this->assertSame(4, $snapshot['reserved_travelers']);
        $this->assertSame(1, $snapshot['pending_travelers']);
        $this->assertSame(3, $snapshot['confirmed_travelers']);
        $this->assertSame(1, $snapshot['completed_travelers']);
        $this->assertSame(1, $snapshot['remaining_capacity']);
        $this->assertSame(80, $snapshot['occupancy_percent']);
        $this->assertFalse($snapshot['sold_out']);
        $this->assertTrue($snapshot['near_capacity']);
        $this->assertSame(1, $service->getAvailableCapacity($availability));

        $fullAvailability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2027-01-01',
            'capacity' => 2,
            'status' => 'active',
        ]);
        $this->createBooking($agency, $package, $fullAvailability, User::factory()->create(['role' => 'traveler']), 'TC-FULL-000001', [
            'status' => 'confirmed',
            'traveler_count' => 2,
            'expires_at' => now()->subHour(),
        ]);
        $fullSnapshot = $service->capacitySnapshots([$fullAvailability])->get($fullAvailability->id);

        $this->assertSame(100, $fullSnapshot['occupancy_percent']);
        $this->assertSame(0, $fullSnapshot['remaining_capacity']);
        $this->assertTrue($fullSnapshot['sold_out']);
        $this->assertFalse($fullSnapshot['near_capacity']);
    }

    public function test_departures_on_or_before_today_are_expired_but_future_departures_stay_active(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id]);
        $pastDeparture = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => today()->subDay()->toDateString(),
            'capacity' => 10,
            'status' => 'active',
        ]);
        $departureToday = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => today()->toDateString(),
            'capacity' => 10,
            'status' => 'active',
        ]);
        $futureDeparture = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => today()->addDay()->toDateString(),
            'capacity' => 10,
            'status' => 'active',
        ]);

        $expiredCount = (new BookingService)->expirePastDepartures();

        $this->assertSame(2, $expiredCount);
        $this->assertSame('expired', $pastDeparture->fresh()->status->value);
        $this->assertSame('expired', $departureToday->fresh()->status->value);
        $this->assertSame('active', $futureDeparture->fresh()->status->value);
    }

    public function test_a_traveler_cannot_book_a_departure_on_or_before_today(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id, 'status' => PackageStatus::PUBLISHED]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => today()->toDateString(),
            'capacity' => 10,
            'status' => 'active',
        ]);
        $traveler = User::factory()->create(['role' => 'traveler']);

        $this->expectException(\RuntimeException::class);
        (new BookingService)->createBooking($traveler, $package, $availability, 1);
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
            'currency' => 'XAF',
            'booking_reference' => 'TC-2026-000300',
            'expires_at' => now()->addHour(),
        ]);

        $service = new BookingService;

        $this->expectException(\RuntimeException::class);
        $service->transitionStatus($booking, BookingStatus::CONFIRMED);
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
        $this->createBooking($agency, $package, $availability, User::factory()->create(['role' => 'traveler']), 'TC-AVAIL-000001', [
            'status' => 'pending',
            'traveler_count' => 2,
            'expires_at' => now()->addHour(),
        ]);
        $this->createBooking($agency, $package, $availability, User::factory()->create(['role' => 'traveler']), 'TC-AVAIL-000002', [
            'status' => 'confirmed',
            'payment_status' => PaymentStatus::PAID->value,
            'traveler_count' => 3,
            'expires_at' => now()->subHour(),
        ]);
        $this->createBooking($agency, $package, $availability, User::factory()->create(['role' => 'traveler']), 'TC-AVAIL-000003', [
            'status' => 'completed',
            'payment_status' => PaymentStatus::PAID->value,
            'traveler_count' => 4,
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($agency->owner)
            ->get(route('agency.availability.index'))
            ->assertOk()
            ->assertSee('Marrakech Discovery')
            ->assertSee('2026-11-10')
            ->assertSee(__('messages.confirmed_travelers'))
            ->assertSee(__('messages.pending_travelers'))
            ->assertSee(__('messages.completed_travelers'))
            ->assertSee('>4</dd>', false)
            ->assertSee('42%');
    }

    public function test_agency_availability_page_expires_departures_that_have_reached_today(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id, 'status' => PackageStatus::PUBLISHED]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => today()->toDateString(),
            'capacity' => 12,
            'status' => 'active',
        ]);

        $response = $this->actingAs($agency->owner)->get(route('agency.availability.index'));

        $response->assertOk()->assertSee(__('messages.expired'));
        $this->assertSame('expired', $availability->fresh()->status->value);
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
            'currency' => 'XAF',
            'booking_reference' => 'TC-2026-001234',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($agency->owner)
            ->get(route('agency.bookings.show', ['booking' => $booking->id]))
            ->assertOk()
            ->assertSee('TC-2026-001234')
            ->assertSee('Sam Traveler');
    }

    public function test_agency_booking_kpis_and_combined_filters_use_real_owner_scoped_data(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $package = Package::factory()->create([
            'agency_id' => $agency->id,
            'title' => 'Alpine Discovery',
            'destination' => 'Switzerland',
        ]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => now()->addDays(30)->toDateString(),
            'capacity' => 12,
            'status' => 'active',
        ]);
        $activeTraveler = User::factory()->create(['role' => 'traveler', 'name' => 'Alina Active']);
        $this->createBooking($agency, $package, $availability, $activeTraveler, 'TC-KPI-000001', [
            'traveler_count' => 2,
            'created_at' => now()->subDays(3),
        ]);
        $this->createBooking($agency, $package, $availability, User::factory()->create(['role' => 'traveler', 'name' => 'Deadline Passed']), 'TC-KPI-000002', [
            'traveler_count' => 4,
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subDays(2),
        ]);
        $this->createBooking($agency, $package, $availability, User::factory()->create(['role' => 'traveler']), 'TC-KPI-000003', [
            'status' => 'confirmed',
            'payment_status' => PaymentStatus::PAID->value,
            'traveler_count' => 1,
            'expires_at' => now()->subHour(),
            'created_at' => now()->subDays(2),
        ]);
        $this->createBooking($agency, $package, $availability, User::factory()->create(['role' => 'traveler']), 'TC-KPI-000004', [
            'status' => 'cancelled',
            'payment_status' => PaymentStatus::REFUNDED->value,
            'created_at' => now()->subDay(),
        ]);
        $this->createBooking($agency, $package, $availability, User::factory()->create(['role' => 'traveler']), 'TC-KPI-000005', [
            'status' => 'rejected',
            'created_at' => now()->subDay(),
        ]);
        $otherPackage = Package::factory()->create(['agency_id' => $otherAgency->id]);
        $otherAvailability = PackageAvailability::create([
            'package_id' => $otherPackage->id,
            'departure_date' => now()->addDays(30)->toDateString(),
            'capacity' => 20,
            'status' => 'active',
        ]);
        $this->createBooking($otherAgency, $otherPackage, $otherAvailability, $activeTraveler, 'TC-KPI-OTHER');

        $response = $this->actingAs($agency->owner)->get(route('agency.bookings.index', [
            'search' => 'Alina Active',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'package_id' => $package->id,
            'upcoming' => '1',
        ]));

        $response->assertOk()
            ->assertSee('TC-KPI-000001')
            ->assertDontSee('TC-KPI-000002')
            ->assertDontSee('TC-KPI-OTHER')
            ->assertViewHas('totalBookingCount', 5)
            ->assertViewHas('upcomingConfirmedCount', 1)
            ->assertViewHas('upcomingTravelerCount', 3)
            ->assertViewHas('cancelledRejectedCount', 2);
    }

    public function test_agency_booking_pagination_preserves_active_filters(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 50,
            'status' => 'active',
        ]);

        foreach (range(1, 21) as $number) {
            $this->createBooking(
                $agency,
                $package,
                $availability,
                User::factory()->create(['role' => 'traveler']),
                sprintf('TC-PAGE-%06d', $number)
            );
        }

        $firstPage = $this->actingAs($agency->owner)->get(route('agency.bookings.index', ['status' => 'pending']));
        $secondPage = $this->actingAs($agency->owner)->get(route('agency.bookings.index', ['status' => 'pending', 'page' => 2]));

        $firstPage->assertOk()
            ->assertSee('TC-PAGE-000021')
            ->assertDontSee('TC-PAGE-000001')
            ->assertSee('status=pending');
        $secondPage->assertOk()
            ->assertSee('TC-PAGE-000001')
            ->assertDontSee('TC-PAGE-000021');
    }

    public function test_agency_booking_created_and_departure_date_ranges_work_together(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id]);
        $matchingAvailability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2026-11-01',
            'capacity' => 10,
            'status' => 'active',
        ]);
        $outsideAvailability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2026-12-01',
            'capacity' => 10,
            'status' => 'active',
        ]);
        $matchingBooking = $this->createBooking(
            $agency,
            $package,
            $matchingAvailability,
            User::factory()->create(['role' => 'traveler']),
            'TC-RANGE-MATCH'
        );
        $matchingBooking->forceFill(['created_at' => '2026-09-29 12:00:00'])->save();
        $outsideDepartureBooking = $this->createBooking(
            $agency,
            $package,
            $outsideAvailability,
            User::factory()->create(['role' => 'traveler']),
            'TC-RANGE-DEPARTURE'
        );
        $outsideDepartureBooking->forceFill(['created_at' => '2026-09-29 12:00:00'])->save();
        $outsideCreatedBooking = $this->createBooking(
            $agency,
            $package,
            $matchingAvailability,
            User::factory()->create(['role' => 'traveler']),
            'TC-RANGE-CREATED'
        );
        $outsideCreatedBooking->forceFill(['created_at' => '2026-09-20 12:00:00'])->save();

        $createdDateResults = $this->actingAs($agency->owner)->get(route('agency.bookings.index', [
            'created_from' => '2026-09-28',
            'created_to' => '2026-09-30',
        ]));
        $departureDateResults = $this->actingAs($agency->owner)->get(route('agency.bookings.index', [
            'departure_from' => '2026-10-30',
            'departure_to' => '2026-11-03',
        ]));
        $exactDateResults = $this->actingAs($agency->owner)->get(route('agency.bookings.index', [
            'date' => '2026-09-29',
        ]));
        $combinedResults = $this->actingAs($agency->owner)->get(route('agency.bookings.index', [
            'created_from' => '2026-09-28',
            'created_to' => '2026-09-30',
            'departure_from' => '2026-10-30',
            'departure_to' => '2026-11-03',
        ]));

        $createdDateResults->assertOk()
            ->assertSee($matchingBooking->booking_reference)
            ->assertDontSee('TC-RANGE-CREATED');
        $departureDateResults->assertOk()
            ->assertSee($matchingBooking->booking_reference)
            ->assertDontSee('TC-RANGE-DEPARTURE');
        $exactDateResults->assertOk()
            ->assertSee($matchingBooking->booking_reference)
            ->assertDontSee('TC-RANGE-CREATED');
        $combinedResults->assertOk()
            ->assertSee($matchingBooking->booking_reference)
            ->assertDontSee('TC-RANGE-DEPARTURE')
            ->assertDontSee('TC-RANGE-CREATED');
    }

    public function test_booking_details_show_real_traveler_trip_capacity_and_same_agency_history(): void
    {
        Storage::fake('public');
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $package = Package::factory()->create([
            'agency_id' => $agency->id,
            'title' => 'Coastal Passage',
            'destination' => 'Madeira',
            'duration_days' => 6,
        ]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 8,
            'status' => 'active',
        ]);
        $traveler = User::factory()->create([
            'role' => 'traveler',
            'name' => 'Jamie Traveler',
            'email' => 'jamie@example.test',
            'phone' => '+237 600 000 001',
            'profile_photo_path' => 'missing/jamie.jpg',
        ]);
        $booking = $this->createBooking($agency, $package, $availability, $traveler, 'TC-DETAIL-000001', [
            'traveler_count' => 2,
            'unit_price' => 1250,
            'total_amount' => 2500,
            'currency' => 'XAF',
        ]);
        $this->createBooking($agency, $package, $availability, $traveler, 'TC-DETAIL-RELATED');
        $otherPackage = Package::factory()->create(['agency_id' => $otherAgency->id]);
        $otherAvailability = PackageAvailability::create([
            'package_id' => $otherPackage->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 8,
            'status' => 'active',
        ]);
        $this->createBooking($otherAgency, $otherPackage, $otherAvailability, $traveler, 'TC-DETAIL-PRIVATE');

        $response = $this->actingAs($agency->owner)->get(route('agency.bookings.show', ['booking' => $booking->id]));

        $response->assertOk()
            ->assertSee('Jamie Traveler')
            ->assertSee('jamie@example.test')
            ->assertSee('+237 600 000 001')
            ->assertSee('data-traveler-avatar-fallback', false)
            ->assertSee('Madeira')
            ->assertSee(__('messages.duration_days_label', ['count' => 6]))
            ->assertSee('XAF 1,250.00')
            ->assertSee('XAF 2,500.00')
            ->assertSee(__('messages.departure_capacity_context'))
            ->assertSee('TC-DETAIL-RELATED')
            ->assertDontSee('TC-DETAIL-PRIVATE');
    }

    public function test_booking_timeline_uses_recorded_lifecycle_timestamps(): void
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 8,
            'status' => 'active',
        ]);
        $booking = $this->createBooking(
            $agency,
            $package,
            $availability,
            User::factory()->create(['role' => 'traveler']),
            'TC-TIMELINE-000001',
            [
                'status' => 'confirmed',
                'payment_status' => PaymentStatus::PAID->value,
                'confirmed_at' => '2026-09-30 10:00:00',
                'expires_at' => '2026-09-30 09:00:00',
            ]
        );

        $response = $this->actingAs($agency->owner)->get(route('agency.bookings.show', ['booking' => $booking->id]));

        $response->assertOk()
            ->assertSee(__('messages.timeline_booking_created'))
            ->assertSee(__('messages.timeline_confirmed'))
            ->assertSee($booking->confirmed_at->toIso8601String())
            ->assertSee(__('messages.mark_complete'))
            ->assertDontSee(route('agency.bookings.confirm', ['booking' => $booking->id]), false);
    }

    public function test_an_agency_cannot_access_another_agencys_booking_or_booking_actions(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $otherAgency->id]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 5,
            'status' => 'active',
        ]);
        $booking = $this->createBooking(
            $otherAgency,
            $package,
            $availability,
            User::factory()->create(['role' => 'traveler']),
            'TC-CROSS-AGENCY-001'
        );

        $this->actingAs($agency->owner)
            ->get(route('agency.bookings.show', ['booking' => $booking->id]))
            ->assertForbidden();
        $this->actingAs($agency->owner)
            ->patch(route('agency.bookings.confirm', ['booking' => $booking->id]))
            ->assertForbidden();
        $this->actingAs($agency->owner)
            ->get(route('agency.bookings.index'))
            ->assertOk()
            ->assertDontSee('TC-CROSS-AGENCY-001');
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
            'currency' => 'XAF',
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
            'currency' => 'XAF',
            'booking_reference' => 'TC-2026-001236',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($otherTraveler)->get(route('bookings.show', ['booking' => $booking->id]))->assertForbidden();
        $this->actingAs($otherTraveler)->get(route('bookings.confirmation', ['booking' => $booking->id]))->assertForbidden();
    }

    private function createBooking(Agency $agency, Package $package, PackageAvailability $availability, User $traveler, string $reference, array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'booking_reference' => $reference,
            'status' => 'pending',
            'payment_status' => PaymentStatus::UNPAID->value,
            'traveler_count' => 1,
            'unit_price' => $package->price_from,
            'total_amount' => $package->price_from,
            'currency' => 'XAF',
            'expires_at' => now()->addHour(),
        ], $attributes));
    }
}
