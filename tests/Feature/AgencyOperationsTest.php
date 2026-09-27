<?php

namespace Tests\Feature;

use App\Enums\PackageStatus;
use App\Enums\PaymentStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_travelers_cannot_access_agency_operations_modules(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);

        foreach ([
            'agency.customers.index',
            'agency.reviews.index',
            'agency.payments.index',
            'agency.analytics.index',
        ] as $routeName) {
            $this->actingAs($traveler)
                ->get(route($routeName))
                ->assertRedirect(route('dashboard', ['lang' => 'en']));
        }
    }

    public function test_agency_operations_views_are_database_backed_and_tenant_scoped(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $traveler = User::factory()->create(['name' => 'Agency Customer', 'email' => 'customer@example.test', 'role' => 'traveler']);
        $otherTraveler = User::factory()->create(['name' => 'Other Customer', 'email' => 'other@example.test', 'role' => 'traveler']);
        $package = Package::factory()->create(['agency_id' => $agency->id, 'title' => 'Coastal Passage', 'status' => PackageStatus::PUBLISHED]);
        $otherPackage = Package::factory()->create(['agency_id' => $otherAgency->id, 'title' => 'Private Other Tour', 'status' => PackageStatus::PUBLISHED]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 8,
            'status' => 'active',
        ]);
        $otherAvailability = PackageAvailability::create([
            'package_id' => $otherPackage->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 8,
            'status' => 'active',
        ]);

        $this->createBooking($agency, $package, $availability, $traveler, 'TC-OPS-000001');
        $this->createBooking($otherAgency, $otherPackage, $otherAvailability, $otherTraveler, 'TC-OPS-000002');
        Review::create(['agency_id' => $agency->id, 'user_id' => $traveler->id, 'rating' => 5, 'comment' => 'Excellent local planning', 'status' => 'published']);
        Review::create(['agency_id' => $otherAgency->id, 'user_id' => $otherTraveler->id, 'rating' => 1, 'comment' => 'Other agency private review', 'status' => 'published']);

        $this->actingAs($agency->owner)
            ->get(route('agency.customers.index'))
            ->assertOk()
            ->assertSee('Agency Customer')
            ->assertDontSee('Other Customer');

        $this->actingAs($agency->owner)
            ->get(route('agency.availability.index'))
            ->assertOk()
            ->assertSee('Coastal Passage')
            ->assertSee(__('messages.reserved_travelers'))
            ->assertSee('2')
            ->assertSee('25%')
            ->assertSee(route('agency.bookings.index', ['availability_id' => $availability->id, 'lang' => 'en']));

        $this->actingAs($agency->owner)
            ->get(route('agency.reviews.index'))
            ->assertOk()
            ->assertSee('Excellent local planning')
            ->assertDontSee('Other agency private review');

        $payments = $this->actingAs($agency->owner)->get(route('agency.payments.index'));
        $payments->assertOk()
            ->assertSee('TC-OPS-000001')
            ->assertDontSee('TC-OPS-000002')
            ->assertSee('Coastal Passage')
            ->assertSee('scope="col" class="px-4 py-3 text-right"', false);

        $bookingList = $this->actingAs($agency->owner)->get(route('agency.bookings.index'));
        $bookingList->assertOk()
            ->assertSee('grid gap-4 sm:grid-cols-2 xl:grid-cols-12 xl:items-end', false)
            ->assertSee('Coastal Passage')
            ->assertSee('Agency Customer')
            ->assertSee(__('messages.travelers_count', ['count' => 2]));

        $this->actingAs($agency->owner)
            ->get(route('agency.analytics.index'))
            ->assertOk()
            ->assertSee('Business performance')
            ->assertSee('1');
    }

    private function createBooking(Agency $agency, Package $package, PackageAvailability $availability, User $traveler, string $reference): void
    {
        Booking::create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'booking_reference' => $reference,
            'status' => 'confirmed',
            'payment_status' => PaymentStatus::PAID->value,
            'traveler_count' => 2,
            'unit_price' => 125,
            'total_amount' => 250,
            'currency' => 'USD',
            'expires_at' => now()->addHour(),
        ]);
    }
}