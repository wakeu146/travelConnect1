<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgencyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_agency_owner_can_view_their_dashboard(): void
    {
        $agency = Agency::factory()->create(['company_name' => 'North Star Travel']);
        $traveler = User::factory()->create(['role' => 'traveler']);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'user_id' => $traveler->id, 'status' => 'open']);
        Review::factory()->create(['agency_id' => $agency->id, 'user_id' => $traveler->id, 'status' => 'published', 'rating' => 5]);

        $response = $this->actingAs($agency->owner)->get(route('agency.dashboard'));

        $response->assertOk();
        $response->assertSee('North Star Travel');
        $response->assertSee(__('messages.agency_dashboard_intro'));
        $response->assertSee('Open inquiries');
        $response->assertSee('1');
    }

    public function test_a_traveler_is_redirected_away_from_the_agency_dashboard(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);

        $response = $this->actingAs($traveler)->get(route('agency.dashboard'));

        $response->assertRedirect(route('dashboard', ['lang' => 'en']));
    }

    public function test_an_agency_owner_only_sees_their_own_agency_data(): void
    {
        $ownedAgency = Agency::factory()->create(['company_name' => 'Owned Travel']);
        $otherAgency = Agency::factory()->create(['company_name' => 'Other Travel']);
        $traveler = User::factory()->create(['role' => 'traveler']);
        Inquiry::factory()->create(['agency_id' => $ownedAgency->id, 'user_id' => $traveler->id, 'subject' => 'Owned inquiry']);
        Inquiry::factory()->create(['agency_id' => $otherAgency->id, 'user_id' => $traveler->id, 'subject' => 'Other inquiry']);

        $response = $this->actingAs($ownedAgency->owner)->get(route('agency.dashboard'));

        $response->assertOk();
        $response->assertSee('Owned inquiry');
        $response->assertDontSee('Other inquiry');
        $response->assertSee('Owned Travel');
        $response->assertDontSee('Other Travel');
    }

    public function test_an_agency_owner_can_update_their_profile_and_public_profile_uses_it(): void
    {
        Storage::fake('public');
        $agency = Agency::factory()->create(['company_name' => 'Summit & Sea Travel', 'description' => 'Old description']);
        $country = \App\Models\Country::factory()->create(['name' => 'Japan']);
        $service = \App\Models\Service::factory()->create(['name' => 'Rail journeys']);

        $response = $this->actingAs($agency->owner)->put(route('agency.settings.update'), [
            'name' => 'Amina Cole',
            'email' => $agency->owner->email,
            'phone' => '+237 600 000 000',
            'company_name' => 'Summit & Sea Travel',
            'description' => 'Journeys planned around thoughtful local knowledge.',
            'license_number' => $agency->license_number,
            'address' => 'Douala',
            'agency_phone' => '+237 699 000 000',
            'agency_email' => 'hello@summit.test',
            'website' => 'https://summit.test',
            'countries' => [$country->id],
            'services' => [$service->id],
            'logo' => UploadedFile::fake()->image('logo.jpg'),
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $response->assertRedirect();
        $agency->refresh();
        $this->assertSame('Journeys planned around thoughtful local knowledge.', $agency->description);
        $this->assertNotNull($agency->logo_path);
        $this->assertNotNull($agency->cover_image_path);
        $this->assertTrue(Storage::disk('public')->exists($agency->logo_path));
        $this->assertTrue(Storage::disk('public')->exists($agency->cover_image_path));

        $public = $this->actingAs($agency->owner)->get(route('agency.show', ['slug' => 'summit-sea-travel']));
        $public->assertOk()->assertSee('Journeys planned around thoughtful local knowledge.');
        $public->assertSee('Japan');
        $public->assertSee('Rail journeys');
    }

    public function test_an_agency_owner_can_upload_a_document_and_publish_an_experience(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $agency = Agency::factory()->create();

        $document = $this->actingAs($agency->owner)->post(route('agency.documents.store'), [
            'type' => 'license',
            'document' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
        ]);
        $document->assertRedirect();

        $experience = $this->actingAs($agency->owner)->post(route('agency.experiences.store'), [
            'image' => UploadedFile::fake()->image('tokyo.jpg'),
            'destination' => 'Tokyo',
            'caption' => 'A city journey shaped by local insight.',
            'alt_text' => 'Travelers exploring Tokyo',
        ]);
        $experience->assertRedirect();

        $this->assertDatabaseHas('agency_documents', ['agency_id' => $agency->id, 'type' => 'license', 'status' => 'pending']);
        $this->assertDatabaseHas('agency_experiences', ['agency_id' => $agency->id, 'destination' => 'Tokyo']);
    }
}
