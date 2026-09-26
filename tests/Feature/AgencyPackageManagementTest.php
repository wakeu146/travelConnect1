<?php

namespace Tests\Feature;

use App\Enums\PackageStatus;
use App\Models\Agency;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\PackageImage;
use App\Models\PackageItineraryDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgencyPackageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_owner_can_view_own_packages_only(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        Package::factory()->create(['agency_id' => $agency->id, 'title' => 'Owned package']);
        Package::factory()->create(['agency_id' => $otherAgency->id, 'title' => 'Other package']);

        $response = $this->actingAs($agency->owner)->get(route('agency.packages.index'));

        $response->assertOk();
        $response->assertSee('Owned package');
        $response->assertDontSee('Other package');
    }

    public function test_traveler_cannot_access_agency_package_routes(): void
    {
        $agency = Agency::factory()->create();
        $traveler = User::factory()->create(['role' => 'traveler']);
        $package = Package::factory()->create(['agency_id' => $agency->id]);

        $this->actingAs($traveler)->get(route('agency.packages.index'))->assertRedirect(route('dashboard', ['lang' => 'en']));
        $this->actingAs($traveler)->get(route('agency.packages.edit', ['package' => $package->id]))->assertRedirect(route('dashboard', ['lang' => 'en']));
    }

    public function test_agency_owner_cannot_view_or_edit_another_agencys_package(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $packageB = Package::factory()->create(['agency_id' => $agencyB->id]);

        $this->actingAs($agencyA->owner)
            ->get(route('agency.packages.edit', ['package' => $packageB->id]))
            ->assertForbidden();

        $this->actingAs($agencyA->owner)
            ->put(route('agency.packages.update', ['package' => $packageB->id]), [
                'title' => 'Hijack attempt',
                'summary' => 'Bad attempt',
                'description' => 'This should fail',
                'destination' => 'Paris',
                'price_from' => 1200,
                'duration_days' => 5,
                'status' => PackageStatus::DRAFT->value,
            ])
            ->assertForbidden();
    }

    public function test_package_can_be_created_updated_and_soft_deleted(): void
    {
        $agency = Agency::factory()->create();

        $create = $this->actingAs($agency->owner)->post(route('agency.packages.store'), [
            'title' => 'Alpine Escape',
            'summary' => 'A scenic alpine journey',
            'description' => 'Beautiful trail and countryside.',
            'destination' => 'Switzerland',
            'price_from' => 1290,
            'duration_days' => 6,
            'status' => PackageStatus::DRAFT->value,
            'is_featured' => true,
        ]);

        $create->assertRedirect();
        $this->assertDatabaseHas('packages', ['title' => 'Alpine Escape', 'agency_id' => $agency->id, 'status' => PackageStatus::DRAFT->value]);

        $package = $agency->packages()->first();

        $update = $this->actingAs($agency->owner)->put(route('agency.packages.update', ['package' => $package->id]), [
            'title' => 'Alpine Escape Updated',
            'summary' => 'A refined alpine journey',
            'description' => 'Updated description',
            'destination' => 'Switzerland',
            'price_from' => 1490,
            'duration_days' => 7,
            'status' => PackageStatus::PUBLISHED->value,
            'is_featured' => false,
        ]);

        $update->assertRedirect();
        $package->refresh();
        $this->assertSame('Alpine Escape Updated', $package->title);
        $this->assertSame(PackageStatus::PUBLISHED->value, $package->status->value);
        $this->assertNotNull($package->published_at);

        $delete = $this->actingAs($agency->owner)->delete(route('agency.packages.destroy', ['package' => $package->id]));
        $delete->assertRedirect(route('agency.packages.index'));
        $this->assertSoftDeleted('packages', ['id' => $package->id]);
    }

    public function test_package_duplication_copies_content_and_images_but_not_departures_or_bookings(): void
    {
        Storage::fake('public');
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id, 'title' => 'Coastal Passage', 'status' => PackageStatus::PUBLISHED]);
        $imagePath = UploadedFile::fake()->image('coast.jpg')->store('package-images', 'public');
        $package->images()->create(['path' => $imagePath, 'caption' => 'Coast', 'is_primary' => true, 'sort_order' => 1]);
        $package->itineraryDays()->create(['day_number' => 1, 'title' => 'Arrival', 'description' => 'Check in']);
        PackageAvailability::create(['package_id' => $package->id, 'departure_date' => '2026-12-10', 'capacity' => 8, 'status' => 'active']);

        $response = $this->actingAs($agency->owner)->post(route('agency.packages.duplicate', ['package' => $package->id]));

        /** @var Package $duplicate */
        $duplicate = $agency->packages()->where('id', '!=', $package->id)->firstOrFail();
        $response->assertRedirect(route('agency.packages.edit', ['package' => $duplicate->id]));
        $this->assertSame('draft', $duplicate->status->value);
        $this->assertSame('Coastal Passage', $package->title);
        $this->assertSame('Copy of Coastal Passage', $duplicate->title);
        $this->assertCount(1, $duplicate->itineraryDays);
        $this->assertCount(1, $duplicate->images);
        $this->assertNotSame($imagePath, $duplicate->images->first()->path);
        $this->assertTrue(Storage::disk('public')->exists($imagePath));
        $this->assertTrue(Storage::disk('public')->exists($duplicate->images->first()->path));
        $this->assertDatabaseMissing('package_availability', ['package_id' => $duplicate->id]);
        $this->assertDatabaseMissing('bookings', ['package_id' => $duplicate->id]);
    }

    public function test_agency_owner_cannot_duplicate_another_agencys_package(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $otherPackage = Package::factory()->create(['agency_id' => $otherAgency->id]);

        $this->actingAs($agency->owner)
            ->post(route('agency.packages.duplicate', ['package' => $otherPackage->id]))
            ->assertForbidden();
    }

    public function test_package_images_require_valid_upload_and_primary_image_selection_is_enforced(): void
    {
        Storage::fake('public');
        $agency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id]);

        $invalid = $this->actingAs($agency->owner)->post(route('agency.packages.images.store', ['package' => $package->id]), [
            'image' => UploadedFile::fake()->create('bad.txt', 10, 'text/plain'),
        ]);
        $invalid->assertSessionHasErrors('image');

        $first = UploadedFile::fake()->image('one.jpg');
        $second = UploadedFile::fake()->image('two.jpg');

        $this->actingAs($agency->owner)->post(route('agency.packages.images.store', ['package' => $package->id]), [
            'image' => $first,
            'caption' => 'First image',
            'is_primary' => true,
        ])->assertRedirect();

        $this->actingAs($agency->owner)->post(route('agency.packages.images.store', ['package' => $package->id]), [
            'image' => $second,
            'caption' => 'Second image',
            'is_primary' => true,
        ])->assertRedirect();

        $this->actingAs($agency->owner)
            ->get(route('agency.packages.edit', ['package' => $package->id]))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($package->fresh()->images()->where('caption', 'Second image')->firstOrFail()->path));

        $this->assertDatabaseHas('package_images', ['package_id' => $package->id, 'caption' => 'First image', 'is_primary' => false]);
        $this->assertDatabaseHas('package_images', ['package_id' => $package->id, 'caption' => 'Second image', 'is_primary' => true]);

        $this->assertCount(1, $package->fresh()->images()->where('is_primary', true)->get());

        $image = $package->fresh()->images()->first();
        $this->actingAs($agency->owner)
            ->delete(route('agency.packages.images.destroy', ['package' => $package->id, 'image' => $image->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('package_images', ['id' => $image->id]);
    }

    public function test_package_itinerary_days_are_owned_and_ordered(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $package = Package::factory()->create(['agency_id' => $agency->id]);
        $otherPackage = Package::factory()->create(['agency_id' => $otherAgency->id]);

        $this->actingAs($agency->owner)->post(route('agency.packages.days.store', ['package' => $package->id]), [
            'title' => 'Day one',
            'description' => 'Arrival morning',
            'location' => 'Paris',
            'meals' => 'Breakfast',
            'overnight' => 'Hotel Stay',
        ])->assertRedirect();

        $day = $package->fresh()->itineraryDays()->first();
        $this->assertNotNull($day);

        $this->actingAs($agency->owner)->put(route('agency.packages.days.update', ['package' => $package->id, 'day' => $day->id]), [
            'title' => 'Day one updated',
            'description' => 'Arrival noon',
            'location' => 'Paris',
            'meals' => 'Brunch',
            'overnight' => 'Boutique stay',
        ])->assertRedirect();

        $this->actingAs($agency->owner)->delete(route('agency.packages.days.destroy', ['package' => $package->id, 'day' => $day->id]))->assertRedirect();

        $this->actingAs($agency->owner)
            ->post(route('agency.packages.days.store', ['package' => $package->id]), [
                'title' => 'Foreign day',
                'description' => 'Bad',
            ])
            ->assertRedirect();

        $this->actingAs($agency->owner)
            ->put(route('agency.packages.days.update', ['package' => $otherPackage->id, 'day' => $day->id]), [
                'title' => 'Hacked',
                'description' => 'Bad',
            ])
            ->assertNotFound();
    }

    public function test_agency_owner_can_reorder_itinerary_days_and_the_new_order_persists(): void
    {
        $owner = User::factory()->create(['role' => 'agency_owner']);
        $agency = Agency::create([
            'user_id' => $owner->id,
            'company_name' => 'Oceanic Journeys',
            'description' => 'Short description.',
            'license_number' => 'LIC-100',
            'verification_status' => 'pending',
            'trust_score' => 0,
            'address' => 'Paris',
            'phone' => '+33123456789',
            'email' => 'hello@oceanic.test',
            'website' => 'https://oceanic.test',
        ]);
        $package = Package::create([
            'agency_id' => $agency->id,
            'slug' => 'oceanic-package',
            'title' => 'Oceanic package',
            'summary' => 'A nice trip',
            'description' => 'Travel details',
            'destination' => 'Maldives',
            'price_from' => 1200,
            'duration_days' => 4,
            'status' => PackageStatus::DRAFT->value,
            'published_at' => null,
            'is_featured' => false,
        ]);

        $arrival = $package->itineraryDays()->create(['day_number' => 1, 'title' => 'Arrival', 'description' => 'Arrive']);
        $cityTour = $package->itineraryDays()->create(['day_number' => 2, 'title' => 'City Tour', 'description' => 'Explore']);
        $beach = $package->itineraryDays()->create(['day_number' => 3, 'title' => 'Beach', 'description' => 'Relax']);
        $departure = $package->itineraryDays()->create(['day_number' => 4, 'title' => 'Departure', 'description' => 'Leave']);

        $this->actingAs($owner)
            ->post(route('agency.packages.days.move', ['package' => $package->id, 'day' => $beach->id]), ['direction' => 'up'])
            ->assertRedirect();

        $package->refresh();
        $orderedTitles = $package->itineraryDays()->orderBy('day_number')->pluck('title')->all();
        $this->assertSame(['Arrival', 'Beach', 'City Tour', 'Departure'], $orderedTitles);
        $this->assertSame([1, 2, 3, 4], $package->itineraryDays()->orderBy('day_number')->pluck('day_number')->all());
    }

    public function test_agency_cannot_reorder_another_agencys_package_days_and_invalid_days_are_rejected(): void
    {
        $ownerA = User::factory()->create(['role' => 'agency_owner']);
        $ownerB = User::factory()->create(['role' => 'agency_owner']);
        $agencyA = Agency::create(['user_id' => $ownerA->id, 'company_name' => 'Agency A', 'description' => 'Short', 'license_number' => 'LIC-A', 'verification_status' => 'pending', 'trust_score' => 0, 'address' => 'Paris', 'phone' => '+331', 'email' => 'a@example.com', 'website' => 'https://a.test']);
        $agencyB = Agency::create(['user_id' => $ownerB->id, 'company_name' => 'Agency B', 'description' => 'Short', 'license_number' => 'LIC-B', 'verification_status' => 'pending', 'trust_score' => 0, 'address' => 'Rome', 'phone' => '+332', 'email' => 'b@example.com', 'website' => 'https://b.test']);
        $packageA = Package::create(['agency_id' => $agencyA->id, 'slug' => 'agency-a-package', 'title' => 'A package', 'summary' => 'A', 'description' => 'A', 'destination' => 'A', 'price_from' => 100, 'duration_days' => 2, 'status' => PackageStatus::DRAFT->value, 'published_at' => null, 'is_featured' => false]);
        $packageB = Package::create(['agency_id' => $agencyB->id, 'slug' => 'agency-b-package', 'title' => 'B package', 'summary' => 'B', 'description' => 'B', 'destination' => 'B', 'price_from' => 200, 'duration_days' => 2, 'status' => PackageStatus::DRAFT->value, 'published_at' => null, 'is_featured' => false]);
        $dayA = $packageA->itineraryDays()->create(['day_number' => 1, 'title' => 'Arrival', 'description' => 'A']);
        $dayB = $packageB->itineraryDays()->create(['day_number' => 1, 'title' => 'Arrival B', 'description' => 'B']);

        $this->actingAs($ownerA)
            ->post(route('agency.packages.days.move', ['package' => $packageB->id, 'day' => $dayB->id]), ['direction' => 'up'])
            ->assertForbidden();

        $this->actingAs($ownerA)
            ->post(route('agency.packages.days.move', ['package' => $packageA->id, 'day' => 999999]), ['direction' => 'up'])
            ->assertNotFound();

        $this->assertDatabaseHas('package_itinerary_days', ['id' => $dayA->id, 'day_number' => 1]);
        $this->assertDatabaseHas('package_itinerary_days', ['id' => $dayB->id, 'day_number' => 1]);
    }

    public function test_itinerary_reorder_validation_fails_without_partial_updates(): void
    {
        $owner = User::factory()->create(['role' => 'agency_owner']);
        $agency = Agency::create(['user_id' => $owner->id, 'company_name' => 'Acme', 'description' => 'Short', 'license_number' => 'LIC-C', 'verification_status' => 'pending', 'trust_score' => 0, 'address' => 'Berlin', 'phone' => '+333', 'email' => 'c@example.com', 'website' => 'https://c.test']);
        $package = Package::create(['agency_id' => $agency->id, 'slug' => 'acme-package', 'title' => 'Acme travel', 'summary' => 'Trip', 'description' => 'Trip', 'destination' => 'Berlin', 'price_from' => 100, 'duration_days' => 2, 'status' => PackageStatus::DRAFT->value, 'published_at' => null, 'is_featured' => false]);
        $dayOne = $package->itineraryDays()->create(['day_number' => 1, 'title' => 'Arrival', 'description' => 'A']);
        $dayTwo = $package->itineraryDays()->create(['day_number' => 2, 'title' => 'City', 'description' => 'B']);

        $beforeNumbers = $package->itineraryDays()->orderBy('day_number')->pluck('day_number')->all();

        $this->actingAs($owner)
            ->post(route('agency.packages.days.move', ['package' => $package->id, 'day' => $dayTwo->id]), ['direction' => 'sideways'])
            ->assertSessionHasErrors('direction');

        $afterNumbers = $package->fresh()->itineraryDays()->orderBy('day_number')->pluck('day_number')->all();
        $this->assertSame($beforeNumbers, $afterNumbers);
    }

    public function test_profile_update_requires_auth_and_valid_agency_owner_session(): void
    {
        $agency = Agency::factory()->create();
        $traveler = User::factory()->create(['role' => 'traveler']);

        $this->actingAs($agency->owner)->put(route('agency.settings.update'), [
            'name' => 'Updated owner',
            'email' => $agency->owner->email,
            'company_name' => 'Updated agency',
            'description' => 'Updated copy',
            'license_number' => 'LIC-123',
            'address' => 'Paris',
            'phone' => '+33 1 00 00 00 00',
            'agency_phone' => '+33 1 00 00 00 01',
            'agency_email' => 'agency@example.com',
            'website' => 'https://example.com',
        ])->assertRedirect();

        $this->actingAs($traveler)
            ->put(route('agency.settings.update'), [
                'name' => 'Fake owner',
                'email' => $traveler->email,
                'company_name' => 'Wrong agency',
                'description' => 'Incorrect',
                'license_number' => 'LIC-999',
            ])
            ->assertRedirect(route('dashboard', ['lang' => 'en']));
    }
}
