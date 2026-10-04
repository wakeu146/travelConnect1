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
            ->assertSee(__('messages.confirmed_travelers'))
            ->assertSee('2')
            ->assertSee('25%')
            ->assertSee(route('agency.bookings.index', ['availability_id' => $availability->id, 'lang' => 'en']));

        $this->actingAs($agency->owner)
            ->get(route('agency.reviews.index'))
            ->assertOk()
            ->assertSee('Excellent local planning')
            ->assertSee('5.0')
            ->assertSee(trans_choice('messages.traveler_ratings', 1, ['count' => 1]))
            ->assertDontSee('Other agency private review');

        $payments = $this->actingAs($agency->owner)->get(route('agency.payments.index'));
        $payments->assertOk()
            ->assertSee('TC-OPS-000001')
            ->assertSee('XAF')
            ->assertDontSee('USD')
            ->assertDontSee('TC-OPS-000002')
            ->assertSee('Coastal Passage')
            ->assertSee('scope="col" class="px-4 py-3 text-right"', false);

        $bookingList = $this->actingAs($agency->owner)->get(route('agency.bookings.index'));
        $bookingList->assertOk()
            ->assertSee('min-w-[1180px]', false)
            ->assertSee('Coastal Passage')
            ->assertSee('Agency Customer')
            ->assertSee(__('messages.upcoming_confirmed_bookings'))
            ->assertSee(__('messages.upcoming_travelers'))
            ->assertSee('TC-OPS-000001')
            ->assertSee('2');

        $this->actingAs($agency->owner)
            ->get(route('agency.analytics.index'))
            ->assertOk()
            ->assertSee('Business performance')
            ->assertSee('1');
    }

    public function test_agency_can_publish_a_traveler_rating_and_recalculate_its_trust_score(): void
    {
        $agency = Agency::factory()->create(['trust_score' => 0]);
        $traveler = User::factory()->create(['role' => 'traveler']);
        $publishedTraveler = User::factory()->create(['role' => 'traveler']);
        $pendingTraveler = User::factory()->create(['role' => 'traveler']);
        Review::create([
            'agency_id' => $agency->id,
            'user_id' => $publishedTraveler->id,
            'rating' => 5,
            'comment' => 'A wonderful trip with excellent planning from start to finish.',
            'status' => 'published',
        ]);
        $otherPendingReview = Review::create([
            'agency_id' => $agency->id,
            'user_id' => $pendingTraveler->id,
            'rating' => 1,
            'comment' => 'This pending rating must not affect the trust score.',
            'status' => 'pending',
        ]);
        $review = Review::create([
            'agency_id' => $agency->id,
            'user_id' => $traveler->id,
            'rating' => 4,
            'comment' => 'A thoughtful and enjoyable trip with excellent support.',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($agency->owner)
            ->patch(route('agency.reviews.publish', ['review' => $review->id]));

        $response->assertRedirect();
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => 'published']);
        $this->assertDatabaseHas('agencies', ['id' => $agency->id, 'trust_score' => 90]);
        $this->assertDatabaseHas('reviews', ['id' => $otherPendingReview->id, 'status' => 'pending']);
        $this->assertDatabaseHas('trust_score_logs', [
            'agency_id' => $agency->id,
            'score' => 90,
        ]);
    }

    public function test_an_agency_cannot_publish_another_agencys_rating(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $traveler = User::factory()->create(['role' => 'traveler']);
        $review = Review::create([
            'agency_id' => $otherAgency->id,
            'user_id' => $traveler->id,
            'rating' => 5,
            'comment' => 'A thoughtful and enjoyable trip with excellent support.',
            'status' => 'pending',
        ]);

        $this->actingAs($agency->owner)
            ->patch(route('agency.reviews.publish', ['review' => $review->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => 'pending']);
    }

    public function test_agency_can_respond_to_its_published_review_without_mutating_the_review(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $traveler = User::factory()->create(['role' => 'traveler']);
        $review = Review::create([
            'agency_id' => $agency->id,
            'user_id' => $traveler->id,
            'rating' => 4,
            'comment' => 'The local planning made the trip feel easy and welcoming.',
            'status' => 'published',
        ]);
        $otherReview = Review::create([
            'agency_id' => $otherAgency->id,
            'user_id' => $traveler->id,
            'rating' => 3,
            'comment' => 'This review belongs to a different agency.',
            'status' => 'published',
        ]);

        $response = $this->actingAs($agency->owner)->post(route('agency.reviews.response', ['review' => $review->id]), [
            'agency_response' => 'Thank you for sharing your experience with our team.',
        ]);

        $response->assertRedirect();
        $review->refresh();
        $this->assertSame('Thank you for sharing your experience with our team.', $review->agency_response);
        $this->assertSame(4, $review->rating);
        $this->assertSame('The local planning made the trip feel easy and welcoming.', $review->comment);
        $this->assertNotNull($review->agency_responded_at);
        $this->assertSame(1, $traveler->notifications()->whereJsonContains('data->type', 'review_response')->count());

        $this->actingAs($agency->owner)
            ->post(route('agency.reviews.response', ['review' => $otherReview->id]), [
                'agency_response' => 'This must not be saved to another agency review.',
            ])
            ->assertNotFound();
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
            'currency' => 'XAF',
            'expires_at' => now()->addHour(),
        ]);
    }
}
