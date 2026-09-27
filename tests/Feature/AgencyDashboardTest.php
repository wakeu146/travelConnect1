<?php

namespace Tests\Feature;

use App\Enums\UserRole;
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

    public function test_a_traveler_login_redirects_to_the_traveler_dashboard(): void
    {
        $traveler = User::factory()->create([
            'email' => 'traveler-login@example.com',
            'role' => UserRole::TRAVELER,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $traveler->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('auth.success'));
        $response->assertSessionHas('success_redirect', route('dashboard'));
    }

    public function test_an_agency_owner_login_redirects_to_the_agency_dashboard(): void
    {
        $agency = Agency::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $agency->owner->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('auth.success'));
        $response->assertSessionHas('success_redirect', route('agency.dashboard'));
    }

    public function test_an_admin_login_keeps_the_default_dashboard_behavior(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin-login@example.com',
            'role' => UserRole::ADMIN,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('auth.success'));
        $response->assertSessionHas('success_redirect', route('dashboard'));
    }

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

    public function test_agency_dashboard_uses_a_persistent_workspace_shell(): void
    {
        $agency = Agency::factory()->create(['company_name' => 'North Star Travel']);

        $response = $this->actingAs($agency->owner)->get(route('agency.dashboard'));

        $response->assertOk();
        $response->assertSee('data-agency-shell', false);
        $response->assertSee('id="agency-main-content"', false);
        $response->assertSee('data-agency-sidebar', false);
        $response->assertSee('data-agency-topbar', false);
        $response->assertSee('data-agency-search', false);
        $response->assertSee('data-agency-search-submit', false);
        $response->assertSee('data-agency-language-switcher class="flex shrink-0', false);
        $response->assertSee('>EN</a>', false);
        $response->assertSee('>FR</a>', false);
        $response->assertSee('title="'.__('messages.overview').'"', false);
        $response->assertDontSee('data-agency-theme class=', false);
    }

    public function test_agency_profile_icon_keeps_the_selected_french_locale(): void
    {
        $agency = Agency::factory()->create(['company_name' => 'North Star Travel']);
        $profileUrl = route('agency.profile', ['lang' => 'fr']);

        $dashboard = $this->actingAs($agency->owner)->get(route('agency.dashboard', ['lang' => 'fr']));

        $dashboard->assertOk();
        $dashboard->assertSee('data-agency-header-profile data-agency-nav-link', false);
        $dashboard->assertSee('href="'.$profileUrl.'"', false);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')->get($profileUrl)
            ->assertOk()
            ->assertSee('id="agency-main-content"', false)
            ->assertSee('href="'.$profileUrl.'"', false)
            ->assertSee(__('messages.agency_profile', [], 'fr'));
    }

    public function test_agency_shell_shows_the_real_logo_name_and_verification_state(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('agency-logos/atlas.png', UploadedFile::fake()->image('atlas.png')->get());
        $agency = Agency::factory()->create([
            'company_name' => 'Atlas Horizon Voyages',
            'logo_path' => 'agency-logos/atlas.png',
            'verification_status' => 'verified',
        ]);

        $response = $this->actingAs($agency->owner)->get(route('agency.dashboard'));

        $response->assertOk();
        $response->assertSee('storage/agency-logos/atlas.png');
        $response->assertSee('Atlas Horizon Voyages');
        $response->assertSee(__('messages.agency_verification_status_verified'));
        $response->assertDontSee(__('messages.public_profile'));
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
        $logoUrl = $agency->logoUrl();
        $coverUrl = $agency->coverImageUrl();
        $this->assertStringStartsWith(Storage::disk('public')->url($agency->logo_path).'?v=', $logoUrl);
        $this->assertStringStartsWith(Storage::disk('public')->url($agency->cover_image_path).'?v=', $coverUrl);

        $profile = $this->actingAs($agency->owner)->get(route('agency.profile'));
        $profile->assertOk()->assertSee('src="'.$logoUrl.'"', false)->assertSee('src="'.$coverUrl.'"', false);

        $public = $this->actingAs($agency->owner)->get(route('agency.show', ['slug' => 'summit-sea-travel']));
        $public->assertOk()->assertSee('Journeys planned around thoughtful local knowledge.');
        $public->assertSee('src="'.$coverUrl.'"', false);
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

    public function test_agency_owner_can_replace_logo_and_remove_cover_without_orphaning_files(): void
    {
        Storage::fake('public');
        $oldLogo = UploadedFile::fake()->image('old-logo.jpg')->store('agency-logos/old', 'public');
        $oldCover = UploadedFile::fake()->image('old-cover.jpg')->store('agency-covers/old', 'public');
        $agency = Agency::factory()->create(['logo_path' => $oldLogo, 'cover_image_path' => $oldCover]);

        $response = $this->actingAs($agency->owner)->put(route('agency.settings.update'), [
            'name' => $agency->owner->name,
            'email' => $agency->owner->email,
            'company_name' => $agency->company_name,
            'logo' => UploadedFile::fake()->image('new-logo.webp'),
            'remove_cover_image' => '1',
        ]);

        $response->assertRedirect();
        $agency->refresh();
        $this->assertNotSame($oldLogo, $agency->logo_path);
        $this->assertTrue(Storage::disk('public')->exists($agency->logo_path));
        $this->assertFalse(Storage::disk('public')->exists($oldLogo));
        $this->assertNull($agency->cover_image_path);
        $this->assertFalse(Storage::disk('public')->exists($oldCover));
        $this->assertStringStartsWith(Storage::disk('public')->url($agency->logo_path).'?v=', $agency->logoUrl());
        $this->assertNull($agency->coverImageUrl());
    }
}
