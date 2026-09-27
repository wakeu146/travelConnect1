<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyDocument;
use App\Models\Inquiry;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TravelerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_traveler_sees_their_dashboard_data(): void
    {
        $traveler = User::factory()->create(['name' => 'Maya Chen', 'role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'North Star Travel']);

        $traveler->favoriteAgencies()->attach($agency);
        Inquiry::factory()->create(['user_id' => $traveler->id, 'agency_id' => $agency->id, 'status' => 'open']);
        Review::factory()->create(['user_id' => $traveler->id, 'agency_id' => $agency->id, 'status' => 'published']);

        $response = $this->actingAs($traveler)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Good to see you, Maya Chen.');
        $response->assertSee('North Star Travel');
        $response->assertSee('>1</p>', false);
    }

    public function test_an_authenticated_traveler_keeps_account_navigation_on_discovery(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);

        $response = $this->actingAs($traveler)->get(route('discover'));

        $response->assertOk();
        $response->assertSee(__('messages.overview'));
        $response->assertSee(__('messages.settings'));
        $response->assertDontSee(__('messages.create_account'));
    }

    public function test_agency_discovery_filters_by_destination(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $country = \App\Models\Country::factory()->create(['name' => 'Japan']);
        $matchingAgency = Agency::factory()->create(['company_name' => 'Japan Journey Co', 'verification_status' => 'verified']);
        $otherAgency = Agency::factory()->create(['company_name' => 'Alpine Routes', 'verification_status' => 'verified']);
        $matchingAgency->countries()->attach($country);
        AgencyDocument::factory()->create(['agency_id' => $matchingAgency->id, 'status' => 'approved']);
        AgencyDocument::factory()->create(['agency_id' => $otherAgency->id, 'status' => 'approved']);

        $response = $this->actingAs($traveler)->get(route('account.discover', ['search' => 'Japan']));

        $response->assertOk();
        $response->assertSee('Japan Journey Co');
        $response->assertDontSee('Alpine Routes');
    }

    public function test_country_search_returns_all_matching_agencies(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $country = \App\Models\Country::factory()->create(['name' => 'Japan']);

        foreach (['Japan Journey One', 'Japan Journey Two', 'Japan Journey Three', 'Japan Journey Four'] as $companyName) {
            $agency = Agency::factory()->create(['company_name' => $companyName, 'verification_status' => 'verified']);
            $agency->countries()->attach($country);
            AgencyDocument::factory()->create(['agency_id' => $agency->id, 'status' => 'approved']);
        }

        $response = $this->actingAs($traveler)->get(route('account.discover', ['search' => 'Japan']));

        $response->assertOk();
        foreach (['Japan Journey One', 'Japan Journey Two', 'Japan Journey Three', 'Japan Journey Four'] as $companyName) {
            $response->assertSee($companyName);
        }
    }

    public function test_blue_fern_agency_profile_is_available(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);

        $response = $this->actingAs($traveler)->get(route('agency.show', ['slug' => 'blue-fern-expeditions']));

        $response->assertOk();
        $response->assertSee('Blue Fern Expeditions');
    }

    public function test_guests_see_only_the_public_destination_homepage(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(__('messages.explore_destinations'));
        $response->assertDontSee('Atlas Horizon Voyages');
    }

    public function test_guests_cannot_open_agency_discovery(): void
    {
        $response = $this->get(route('account.discover'));

        $response->assertRedirect(route('login'));
    }

    public function test_agency_profile_only_shows_the_top_five_published_reviews(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages']);

        $topReviews = [
            ['rating' => 5, 'comment' => 'Top review one', 'user' => 'Alpha Traveler'],
            ['rating' => 5, 'comment' => 'Top review two', 'user' => 'Bravo Traveler'],
            ['rating' => 5, 'comment' => 'Top review three', 'user' => 'Charlie Traveler'],
            ['rating' => 4, 'comment' => 'Top review four', 'user' => 'Delta Traveler'],
            ['rating' => 4, 'comment' => 'Top review five', 'user' => 'Echo Traveler'],
            ['rating' => 3, 'comment' => 'Lower review one', 'user' => 'Foxtrot Traveler'],
            ['rating' => 2, 'comment' => 'Lower review two', 'user' => 'Golf Traveler'],
        ];

        foreach ($topReviews as $reviewData) {
            $user = User::factory()->create(['name' => $reviewData['user'], 'role' => 'traveler']);
            Review::factory()->create([
                'agency_id' => $agency->id,
                'user_id' => $user->id,
                'rating' => $reviewData['rating'],
                'comment' => $reviewData['comment'],
                'status' => 'published',
            ]);
        }

        $response = $this->actingAs($traveler)->get(route('agency.show', ['slug' => 'atlas-horizon-voyages']));

        $response->assertOk();
        $response->assertSee('Top review one');
        $response->assertSee('Top review five');
        $response->assertDontSee('Lower review one');
        $response->assertDontSee('Lower review two');
    }

    public function test_a_traveler_can_upload_a_profile_photo(): void
    {
        Storage::fake('public');
        $traveler = User::factory()->create(['role' => 'traveler']);

        $response = $this->actingAs($traveler)->post(route('settings.profile-photo'), [
            'profile_photo' => UploadedFile::fake()->image('traveler.jpg'),
        ]);

        $response->assertRedirect();
        $path = $traveler->refresh()->profile_photo_path;
        $this->assertNotNull($path);
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    public function test_account_pages_keep_the_authenticated_navigation_visible(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);

        foreach (['account.saved', 'account.activity', 'settings'] as $routeName) {
            $response = $this->actingAs($traveler)->get(route($routeName));

            $response->assertOk();
            $response->assertSee(__('messages.overview'));
            $response->assertSee(__('messages.settings'));
        }
    }

    public function test_settings_can_render_in_french(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);

        $response = $this->actingAs($traveler)->get(route('settings', ['lang' => 'fr']));

        $response->assertOk();
        $response->assertSee('Paramètres');
        $response->assertSee('Informations du profil');
        $response->assertSee('href="'.route('settings', ['lang' => 'en']).'"', false);
        $response->assertSee('href="'.route('settings', ['lang' => 'fr']).'"', false);
    }

    public function test_agency_discovery_and_profiles_keep_the_account_shell(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);

        $discovery = $this->actingAs($traveler)->get(route('account.discover'));
        $profile = $this->actingAs($traveler)->get(route('agency.show', ['slug' => 'atlas-horizon-voyages']));

        foreach ([$discovery, $profile] as $response) {
            $response->assertOk();
            $response->assertSee(__('messages.overview'));
            $response->assertSee(__('messages.settings'));
            $response->assertDontSee(__('messages.register_your_agency'));
        }
    }

    public function test_a_traveler_can_submit_a_review_for_an_agency(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages']);

        $response = $this->actingAs($traveler)->post(route('reviews.store', ['slug' => 'atlas-horizon-voyages']), [
            'rating' => 5,
            'comment' => 'The planning was thoughtful and the trip felt wonderfully easy.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reviews', [
            'agency_id' => $agency->id,
            'user_id' => $traveler->id,
            'rating' => 5,
            'status' => 'pending',
        ]);
    }

    public function test_a_traveler_can_save_and_unsave_an_agency(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages']);

        $save = $this->actingAs($traveler)->postJson(route('favorites.toggle', ['slug' => 'atlas-horizon-voyages']));
        $save->assertOk()->assertJson(['saved' => true]);
        $this->assertDatabaseHas('favorites', ['user_id' => $traveler->id, 'agency_id' => $agency->id]);

        $remove = $this->actingAs($traveler)->postJson(route('favorites.toggle', ['slug' => 'atlas-horizon-voyages']));
        $remove->assertOk()->assertJson(['saved' => false]);
        $this->assertDatabaseMissing('favorites', ['user_id' => $traveler->id, 'agency_id' => $agency->id]);
    }

    public function test_a_traveler_can_submit_an_inquiry_to_an_agency(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages', 'verification_status' => 'verified']);
        AgencyDocument::factory()->create(['agency_id' => $agency->id, 'status' => 'approved']);

        $response = $this->actingAs($traveler)->post(route('inquiries.store', ['slug' => 'atlas-horizon-voyages']), [
            'subject' => 'Family trip to Japan',
            'message' => 'I would like a suggestion for a family trip to Japan with a comfortable budget and local guidance.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inquiries', [
            'agency_id' => $agency->id,
            'user_id' => $traveler->id,
            'subject' => 'Family trip to Japan',
            'status' => 'open',
        ]);

        $agencyDashboard = $this->actingAs($agency->owner)->get(route('agency.dashboard'));
        $agencyDashboard->assertOk();
        $agencyDashboard->assertSee('Family trip to Japan');
    }

    public function test_discover_search_by_partial_agency_name_shows_the_real_agency_profile(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'Wakue Emmanuel Travel', 'verification_status' => 'verified']);
        AgencyDocument::factory()->create(['agency_id' => $agency->id, 'status' => 'approved']);

        $response = $this->actingAs($traveler)->get(route('account.discover', ['search' => 'wakeu']));

        $response->assertOk();
        $response->assertSee('Wakue Emmanuel Travel');
    }

    public function test_a_traveler_can_only_submit_one_review_per_agency(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages']);

        $first = $this->actingAs($traveler)->post(route('reviews.store', ['slug' => 'atlas-horizon-voyages']), [
            'rating' => 5,
            'comment' => 'The planning was thoughtful and the trip felt wonderfully easy.',
        ]);
        $first->assertRedirect();

        $second = $this->actingAs($traveler)->post(route('reviews.store', ['slug' => 'atlas-horizon-voyages']), [
            'rating' => 4,
            'comment' => 'A second review from the same traveler should be blocked.',
        ]);

        $second->assertSessionHasErrors('review');
        $this->assertSame(1, $agency->fresh()->reviews()->where('user_id', $traveler->id)->count());
    }

    public function test_agency_owner_sees_the_unread_notification_count_for_new_activity(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages', 'verification_status' => 'verified']);
        AgencyDocument::factory()->create(['agency_id' => $agency->id, 'status' => 'approved']);

        $this->actingAs($traveler)->post(route('inquiries.store', ['slug' => 'atlas-horizon-voyages']), [
            'subject' => 'Travel planning question',
            'message' => 'Could you share a suitable 7-day trip for my family?',
        ]);

        $this->assertSame(1, $agency->owner->fresh()->unreadNotifications()->count());
    }

    public function test_saved_agency_cards_show_the_uploaded_cover_image(): void
    {
        Storage::fake('public');
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create([
            'company_name' => 'Lumiere Routes',
            'cover_image_path' => 'agency-covers/lumiere-cover.jpg',
        ]);
        Storage::disk('public')->put($agency->cover_image_path, 'cover-content');

        $traveler->favoriteAgencies()->attach($agency);

        $response = $this->actingAs($traveler)->get(route('account.saved'));

        $response->assertOk();
        $response->assertSee('storage/agency-covers/lumiere-cover.jpg');
    }
}
