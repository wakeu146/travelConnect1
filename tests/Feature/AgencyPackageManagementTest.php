<?php

namespace Tests\Feature;

use App\Enums\PackageStatus;
use App\Models\Agency;
use App\Models\Country;
use App\Models\Package;
use App\Models\PackageAvailability;
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
        $response->assertSee('data-package-delete-icon', false);
    }

    public function test_traveler_cannot_access_agency_package_routes(): void
    {
        $agency = Agency::factory()->create();
        $traveler = User::factory()->create(['role' => 'traveler']);
        $package = Package::factory()->create(['agency_id' => $agency->id]);

        $this->actingAs($traveler)->get(route('agency.packages.index'))->assertRedirect(route('dashboard', ['lang' => 'en']));
        $this->actingAs($traveler)->get(route('agency.packages.edit', ['package' => $package->id]))->assertRedirect(route('dashboard', ['lang' => 'en']));
    }

    public function test_package_form_lists_only_destinations_assigned_to_the_agency(): void
    {
        $agency = Agency::factory()->create();
        $france = Country::factory()->create(['name' => 'France']);
        Country::factory()->create(['name' => 'Japan']);
        $agency->countries()->attach($france);

        $response = $this->actingAs($agency->owner)->get(route('agency.packages.create', ['lang' => 'en']));

        $response->assertOk()
            ->assertSee('<select name="destination"', false)
            ->assertSee('<option value="France"', false)
            ->assertSee('>France</option>', false)
            ->assertDontSee('<option value="Japan"', false);
    }

    public function test_agency_cannot_create_package_for_a_destination_it_does_not_serve(): void
    {
        $agency = Agency::factory()->create();
        Country::factory()->create(['name' => 'Japan']);

        $response = $this->actingAs($agency->owner)->post(route('agency.packages.store'), [
            'title' => 'Japan Discovery',
            'summary' => 'A thoughtful journey through Japan',
            'destination' => 'Japan',
            'price_from' => 900,
            'duration_days' => 8,
            'status' => PackageStatus::DRAFT->value,
        ]);

        $response->assertSessionHasErrors('destination');
        $this->assertDatabaseMissing('packages', ['agency_id' => $agency->id, 'title' => 'Japan Discovery']);
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
        $destination = Country::factory()->create(['name' => 'Switzerland']);
        $agency->countries()->attach($destination);

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
        PackageAvailability::create(['package_id' => $package->id, 'departure_date' => '2026-12-10', 'capacity' => 8, 'status' => 'active']);

        $response = $this->actingAs($agency->owner)->post(route('agency.packages.duplicate', ['package' => $package->id]));

        /** @var Package $duplicate */
        $duplicate = $agency->packages()->where('id', '!=', $package->id)->firstOrFail();
        $response->assertRedirect(route('agency.packages.edit', ['package' => $duplicate->id]));
        $this->assertSame('draft', $duplicate->status->value);
        $this->assertSame('Coastal Passage', $package->title);
        $this->assertSame('Copy of Coastal Passage', $duplicate->title);
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
            ->assertSee('data-package-image-delete-icon', false)
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

    public function test_verification_document_upload_redirects_to_profile_and_counts_toward_completion(): void
    {
        Storage::fake('public');
        $agency = Agency::factory()->create();
        $agency->update([
            'company_name' => 'Emma Travel',
            'description' => 'Travel company',
            'email' => 'hello@emmatravel.test',
            'phone' => '+33100000000',
            'address' => 'Paris',
            'website' => 'https://emmatravel.test',
            'logo_path' => 'agency-logos/logo.png',
            'cover_image_path' => 'agency-covers/cover.png',
        ]);
        $agency->countries()->sync([]);
        $agency->services()->sync([]);

        $response = $this->actingAs($agency->owner)
            ->post(route('agency.documents.store', ['lang' => 'en']), [
                'type' => 'license',
                'document' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
            ]);

        $response->assertRedirect(route('agency.profile', ['lang' => 'en']));
        $this->assertDatabaseHas('agency_documents', ['agency_id' => $agency->id, 'type' => 'license']);

        $dashboard = $this->actingAs($agency->owner)->get(route('agency.dashboard', ['lang' => 'en']));
        $dashboard->assertOk();
        $dashboard->assertSee('100%');
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
