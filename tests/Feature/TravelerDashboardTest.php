<?php

namespace Tests\Feature;

use App\Enums\PackageStatus;
use App\Enums\PaymentStatus;
use App\Models\Agency;
use App\Models\AgencyDocument;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Inquiry;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\Review;
use App\Models\User;
use App\Notifications\AgencyActivityNotification;
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
        $response->assertSee('data-remove-saved-agency', false);
    }

    public function test_a_traveler_can_view_only_their_own_bookings(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $otherTraveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'North Star Travel']);
        $package = Package::factory()->create([
            'agency_id' => $agency->id,
            'title' => 'Northern Lights Escape',
            'status' => PackageStatus::PUBLISHED,
        ]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2026-12-10',
            'capacity' => 12,
            'status' => 'active',
        ]);

        foreach ([
            ['traveler' => $traveler, 'reference' => 'TC-2026-100001'],
            ['traveler' => $otherTraveler, 'reference' => 'TC-2026-100002'],
        ] as $bookingData) {
            Booking::create([
                'agency_id' => $agency->id,
                'package_id' => $package->id,
                'package_availability_id' => $availability->id,
                'user_id' => $bookingData['traveler']->id,
                'booking_reference' => $bookingData['reference'],
                'status' => 'pending',
                'payment_status' => PaymentStatus::UNPAID,
                'traveler_count' => 2,
                'unit_price' => 1200,
                'total_amount' => 2400,
                'currency' => 'USD',
                'expires_at' => now()->addHour(),
            ]);
        }

        $response = $this->actingAs($traveler)->get(route('account.bookings', ['lang' => 'en']));

        $response->assertOk()
            ->assertSee('TC-2026-100001')
            ->assertSee('Northern Lights Escape')
            ->assertDontSee('TC-2026-100002')
            ->assertSee('data-booking-details-open', false)
            ->assertSee('data-booking-details-dialog', false)
            ->assertSee('href="'.route('account.bookings', ['lang' => 'en']).'"', false);
    }

    public function test_an_agency_owner_cannot_access_the_traveler_booking_list(): void
    {
        $agency = Agency::factory()->create();

        $response = $this->actingAs($agency->owner)->get(route('account.bookings', ['lang' => 'en']));

        $response->assertForbidden();
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
        $country = Country::factory()->create(['name' => 'Japan']);
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
        $country = Country::factory()->create(['name' => 'Japan']);

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

    public function test_agency_profile_shows_all_published_packages_and_a_booking_link(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages']);
        $bookablePackage = Package::factory()->create([
            'agency_id' => $agency->id,
            'title' => 'Alpine Lakes Journey',
            'destination' => 'Switzerland',
            'status' => PackageStatus::PUBLISHED,
        ]);
        PackageAvailability::create([
            'package_id' => $bookablePackage->id,
            'departure_date' => '2026-10-10',
            'capacity' => 12,
            'status' => 'active',
        ]);
        Package::factory()->create([
            'agency_id' => $agency->id,
            'title' => 'Published Without Dates',
            'status' => PackageStatus::PUBLISHED,
        ]);
        Package::factory()->create([
            'agency_id' => $agency->id,
            'title' => 'Private Draft Journey',
            'status' => PackageStatus::DRAFT,
        ]);

        $response = $this->actingAs($traveler)->get(route('agency.show', ['slug' => 'atlas-horizon-voyages', 'lang' => 'en']));

        $response->assertOk()
            ->assertSee('Alpine Lakes Journey')
            ->assertSee('Published Without Dates')
            ->assertDontSee('Private Draft Journey')
            ->assertSee('data-booking-modal-open', false)
            ->assertSee('data-package-booking-dialog', false)
            ->assertSee(route('bookings.store', ['package' => $bookablePackage->id, 'lang' => 'en']));

        $bookingPage = $this->actingAs($traveler)->get(route('bookings.create', ['package' => $bookablePackage->id, 'lang' => 'en']));

        $bookingPage->assertOk()
            ->assertSee('Alpine Lakes Journey')
            ->assertSee(route('agency.show', ['slug' => 'atlas-horizon-voyages', 'lang' => 'en']));
    }

    public function test_booking_modal_reopens_with_the_selected_locale_after_validation_errors(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages']);
        $package = Package::factory()->create([
            'agency_id' => $agency->id,
            'title' => 'French Coast Retreat',
            'status' => PackageStatus::PUBLISHED,
        ]);
        PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2026-11-10',
            'capacity' => 8,
            'status' => 'active',
        ]);
        $profileUrl = route('agency.show', ['slug' => 'atlas-horizon-voyages', 'lang' => 'fr']);

        $response = $this->actingAs($traveler)
            ->from($profileUrl)
            ->post(route('bookings.store', ['package' => $package->id, 'lang' => 'fr']), [
                'booking_modal_package' => $package->id,
                'traveler_count' => 1,
            ]);

        $response->assertRedirect($profileUrl)
            ->assertSessionHasErrors('package_availability_id');

        $profile = $this->get($profileUrl);
        $profile->assertOk()
            ->assertSee('data-booking-modal-reopen', false)
            ->assertSee(route('bookings.store', ['package' => $package->id, 'lang' => 'fr']));
    }

    public function test_successful_profile_modal_booking_returns_to_the_agency_with_a_confirmation_dialog(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages']);
        $package = Package::factory()->create([
            'agency_id' => $agency->id,
            'title' => 'French Coast Retreat',
            'status' => PackageStatus::PUBLISHED,
        ]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2026-11-10',
            'capacity' => 8,
            'status' => 'active',
        ]);
        $profileUrl = route('agency.show', ['slug' => 'atlas-horizon-voyages', 'lang' => 'fr']);

        $response = $this->actingAs($traveler)
            ->from($profileUrl)
            ->post(route('bookings.store', ['package' => $package->id, 'lang' => 'fr']), [
                'booking_modal_package' => $package->id,
                'package_availability_id' => $availability->id,
                'traveler_count' => 2,
                'notes' => 'Window seats if available.',
            ]);

        $response->assertRedirect($profileUrl)
            ->assertSessionHas('booking_success', fn (array $bookingSuccess): bool => $bookingSuccess['package_id'] === $package->id
                && $bookingSuccess['package_title'] === 'French Coast Retreat');
        $this->assertDatabaseHas('bookings', [
            'user_id' => $traveler->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'traveler_count' => 2,
            'notes' => 'Window seats if available.',
        ]);

        $profile = $this->get($profileUrl);
        $profile->assertOk()
            ->assertSee('data-booking-success-dialog', false)
            ->assertSee('French Coast Retreat')
            ->assertSee(__('messages.my_bookings', [], 'fr'));
    }

    public function test_a_traveler_can_delete_their_own_activity_notification(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $otherTraveler = User::factory()->create(['role' => 'traveler']);
        $traveler->notify(new AgencyActivityNotification('Agency reply', 'Your agency replied.', 'inquiry_reply'));
        $otherTraveler->notify(new AgencyActivityNotification('Agency reply', 'A different traveler received a reply.', 'inquiry_reply'));
        $notification = $traveler->notifications()->firstOrFail();
        $otherNotification = $otherTraveler->notifications()->firstOrFail();

        $response = $this->actingAs($traveler)->delete(route('account.notifications.destroy', [
            'notification' => $notification->id,
            'lang' => 'en',
        ]));

        $response->assertRedirect(route('account.activity', ['lang' => 'en']));
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
        $this->assertDatabaseHas('notifications', ['id' => $otherNotification->id]);
    }

    public function test_a_traveler_cannot_delete_another_users_activity_notification(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $otherTraveler = User::factory()->create(['role' => 'traveler']);
        $otherTraveler->notify(new AgencyActivityNotification('Agency reply', 'A different traveler received a reply.', 'inquiry_reply'));
        $notification = $otherTraveler->notifications()->firstOrFail();

        $response = $this->actingAs($traveler)->delete(route('account.notifications.destroy', [
            'notification' => $notification->id,
            'lang' => 'en',
        ]));

        $response->assertNotFound();
        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
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

    public function test_agency_inquiry_form_renders_human_readable_labels(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages', 'verification_status' => 'verified']);
        AgencyDocument::factory()->create(['agency_id' => $agency->id, 'status' => 'approved']);

        $response = $this->actingAs($traveler)->get(route('agency.show', ['slug' => 'atlas-horizon-voyages']));

        $response->assertOk();
        $response->assertSee('Subject');
        $response->assertSee('Message');
        $response->assertDontSee('messages.subject');
        $response->assertDontSee('messages.message');
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

    public function test_agency_owner_notification_count_decrements_after_replying_to_an_inquiry(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages', 'verification_status' => 'verified']);
        AgencyDocument::factory()->create(['agency_id' => $agency->id, 'status' => 'approved']);

        $this->actingAs($traveler)->post(route('inquiries.store', ['slug' => 'atlas-horizon-voyages']), [
            'subject' => 'Travel planning question',
            'message' => 'Could you share a suitable 7-day trip for my family?',
        ]);

        $this->assertSame(1, $agency->owner->fresh()->unreadNotifications()->count());

        $inquiry = $agency->fresh()->inquiries()->first();

        $response = $this->actingAs($agency->owner)->post(route('agency.inquiries.reply', ['inquiry' => $inquiry->id]), [
            'reply_message' => 'Yes, we can prepare a family-friendly itinerary for you with local guidance and a flexible budget.',
        ]);

        $response->assertRedirect();
        $this->assertSame(0, $agency->owner->fresh()->unreadNotifications()->count());
    }

    public function test_agency_owner_can_close_a_finished_inquiry(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages', 'verification_status' => 'verified']);
        AgencyDocument::factory()->create(['agency_id' => $agency->id, 'status' => 'approved']);

        $this->actingAs($traveler)->post(route('inquiries.store', ['slug' => 'atlas-horizon-voyages']), [
            'subject' => 'Travel planning question',
            'message' => 'Could you share a suitable 7-day trip for my family?',
        ]);

        $inquiry = $agency->fresh()->inquiries()->first();

        $this->actingAs($agency->owner)->post(route('agency.inquiries.reply', ['inquiry' => $inquiry->id]), [
            'reply_message' => 'Yes, we can prepare a family-friendly itinerary for you with local guidance and a flexible budget.',
        ]);

        $response = $this->actingAs($agency->owner)->post(route('agency.inquiries.close', ['inquiry' => $inquiry->id]));

        $response->assertRedirect();
        $this->assertSame('closed', $inquiry->fresh()->status->value);
    }

    public function test_traveler_sees_the_agency_reply_in_their_activity_feed(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages', 'verification_status' => 'verified']);
        AgencyDocument::factory()->create(['agency_id' => $agency->id, 'status' => 'approved']);

        $this->actingAs($traveler)->post(route('inquiries.store', ['slug' => 'atlas-horizon-voyages']), [
            'subject' => 'Travel planning question',
            'message' => 'Could you share a suitable 7-day trip for my family?',
        ]);

        $inquiry = $agency->fresh()->inquiries()->first();

        $this->actingAs($agency->owner)->post(route('agency.inquiries.reply', ['inquiry' => $inquiry->id]), [
            'reply_message' => 'Yes, we can prepare a family-friendly itinerary for you with local guidance and a flexible budget.',
        ]);

        $response = $this->actingAs($traveler)->get(route('account.activity'));

        $response->assertOk();
        $response->assertSee('Yes, we can prepare a family-friendly itinerary');
        $response->assertSee('Responded');
        $response->assertSee('data-notification-delete-icon', false);
        $response->assertSee(route('account.notifications.destroy', ['notification' => $traveler->notifications()->firstOrFail()->id, 'lang' => 'en']));
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
        $response->assertSee('data-remove-saved-agency', false);
    }
}
