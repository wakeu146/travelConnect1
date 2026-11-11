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
use App\Models\Service;
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

        $response->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
            ->assertSee(
                'href="'.route('account.bookings', ['lang' => 'en']).'" class="text-sm font-bold text-[#e76f51]">'.__('messages.view_all').'</a>',
                false,
            )
            ->assertSee('<div data-account-brand', false)
            ->assertDontSee('<a data-account-brand', false);
        $response->assertSee('Good to see you, Maya Chen.');
        $response->assertSee('North Star Travel');
        $response->assertSee('>1</p>', false);
        $response->assertSee('data-remove-saved-agency', false);
    }

    public function test_dashboard_shows_only_the_three_highest_trust_score_agencies_in_rank_order(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $this->createVerifiedAgency('High Trust Agency', 95);
        $this->createVerifiedAgency('Second Trust Agency', 82);
        $this->createVerifiedAgency('Third Trust Agency', 71);
        $this->createVerifiedAgency('Newest Low Trust Agency', 5);

        $response = $this->actingAs($traveler)->get(route('dashboard'));

        $response->assertOk()
            ->assertSeeInOrder(['High Trust Agency', 'Second Trust Agency', 'Third Trust Agency'])
            ->assertSee('Trust score: 95')
            ->assertSee('data-agency-cover-fallback', false)
            ->assertSee('data-agency-logo-fallback', false)
            ->assertDontSee('Newest Low Trust Agency');
    }

    public function test_dashboard_places_uploaded_cover_and_logo_in_stable_card_regions(): void
    {
        Storage::fake('public');
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = $this->createVerifiedAgency('Image Layout Agency', 99, [
            'logo_path' => 'agency-logos/1/logo.png',
            'cover_image_path' => 'agency-covers/1/cover.png',
        ]);
        Storage::disk('public')->put($agency->logo_path, 'logo image');
        Storage::disk('public')->put($agency->cover_image_path, 'cover image');

        $response = $this->actingAs($traveler)->get(route('dashboard'));

        $response->assertOk()->assertSeeInOrder([
            $agency->coverImageUrl(),
            $agency->logoUrl(),
        ]);
    }

    public function test_dashboard_orders_equal_trust_scores_by_agency_name(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $this->createVerifiedAgency('Zulu Journeys', 80);
        $this->createVerifiedAgency('Alpha Journeys', 80);

        $response = $this->actingAs($traveler)->get(route('dashboard'));

        $response->assertOk()->assertSeeInOrder(['Alpha Journeys', 'Zulu Journeys']);
    }

    public function test_dashboard_handles_fewer_than_three_verified_agencies(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $this->createVerifiedAgency('Only One Travel', 40);
        $this->createVerifiedAgency('Only Two Travel', 30);

        $response = $this->actingAs($traveler)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Only One Travel')
            ->assertSee('Only Two Travel')
            ->assertSee(__('messages.view_all_agencies'));
    }

    public function test_dashboard_shows_an_empty_state_when_no_verified_agencies_exist(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);

        $response = $this->actingAs($traveler)->get(route('dashboard'));

        $response->assertOk()->assertSee(__('messages.no_agencies_available'));
    }

    public function test_all_agencies_page_lists_database_agencies_and_hides_private_fields(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = $this->createVerifiedAgency('Publicly Listed Travel', 74, [
            'description' => 'Plans considered journeys around the world.',
            'address' => 'Douala, Cameroon',
            'email' => 'private-contact@example.test',
            'phone' => 'private-phone-value',
            'license_number' => 'private-license-value',
        ]);
        $agency->countries()->attach(Country::factory()->create(['name' => 'Iceland']));
        $agency->services()->attach(Service::factory()->create(['name' => 'Guided tours']));
        Review::factory()->create(['agency_id' => $agency->id, 'rating' => 5, 'status' => 'published']);
        Agency::factory()->create(['company_name' => 'Pending Agency']);

        $dashboard = $this->actingAs($traveler)->get(route('dashboard'));
        $response = $this->actingAs($traveler)->get(route('discover', ['search' => 'Publicly']));

        $dashboard->assertOk()
            ->assertSee(__('messages.view_all_agencies'))
            ->assertSee('Douala, Cameroon')
            ->assertSee(route('discover', ['lang' => 'en']));
        $response->assertOk()
            ->assertSee('Publicly Listed Travel')
            ->assertSee('Douala, Cameroon')
            ->assertSee('Iceland')
            ->assertSee('Guided tours')
            ->assertSee('5.0')
            ->assertSee('1 traveler review')
            ->assertSee('data-card-initials', false)
            ->assertSee('PL')
            ->assertDontSee('Pending Agency')
            ->assertDontSee('private-contact@example.test')
            ->assertDontSee('private-phone-value')
            ->assertDontSee('private-license-value');
    }

    public function test_discover_shows_uploaded_agency_images_and_static_images_when_no_upload_exists(): void
    {
        Storage::fake('public');
        $traveler = User::factory()->create(['role' => 'traveler']);
        $uploadedAgency = $this->createVerifiedAgency('Custom Image Agency', 90, [
            'logo_path' => 'agency-logos/custom/logo.png',
            'cover_image_path' => 'agency-covers/custom/cover.jpg',
        ]);
        Storage::disk('public')->put($uploadedAgency->logo_path, 'logo image');
        Storage::disk('public')->put($uploadedAgency->cover_image_path, 'cover image');
        $this->createVerifiedAgency('Atlas Horizon Voyages', 80);

        $uploadedResponse = $this->actingAs($traveler)->get(route('discover', ['search' => 'Custom Image']));
        $fallbackResponse = $this->actingAs($traveler)->get(route('discover', ['search' => 'Atlas Horizon']));

        $uploadedResponse->assertSee('Custom Image Agency')
            ->assertSee('src="/storage/agency-covers/custom/cover.jpg?v=', false)
            ->assertSee('src="/storage/agency-logos/custom/logo.png?v=', false);
        $fallbackResponse->assertSee('Atlas Horizon Voyages')
            ->assertSee('data-card-cover-fallback', false)
            ->assertSee('data-card-initials', false);
    }

    public function test_all_agencies_page_keeps_the_existing_authentication_boundary(): void
    {
        $response = $this->get(route('discover'));

        $response->assertRedirect(route('login'));
    }

    public function test_agency_search_results_are_paginated_and_preserve_search_filters(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);

        foreach (range(1, 13) as $number) {
            $this->createVerifiedAgency(sprintf('Directory Agency %02d', $number), 50);
        }

        $firstPage = $this->actingAs($traveler)->get(route('discover', ['search' => 'Directory']));
        $secondPage = $this->actingAs($traveler)->get(route('discover', ['search' => 'Directory', 'page' => 2]));
        $searchPage = $this->actingAs($traveler)->get(route('discover', ['search' => 'Directory Agency 13']));

        $firstPage->assertOk()
            ->assertSee('Directory Agency 01')
            ->assertDontSee('Directory Agency 13')
            ->assertSee('page=2');
        $secondPage->assertOk()
            ->assertSee('Directory Agency 13')
            ->assertDontSee('Directory Agency 01');
        $searchPage->assertOk()
            ->assertSee('Directory Agency 13')
            ->assertDontSee('Directory Agency 01');
    }

    private function createVerifiedAgency(string $companyName, int $trustScore, array $attributes = []): Agency
    {
        $agency = Agency::factory()->create(array_merge([
            'company_name' => $companyName,
            'verification_status' => 'verified',
            'trust_score' => $trustScore,
        ], $attributes));

        AgencyDocument::factory()->create([
            'agency_id' => $agency->id,
            'status' => 'approved',
        ]);

        return $agency;
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
                'currency' => 'XAF',
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
            ->assertSeeInOrder([
                'href="'.route('account.bookings', ['lang' => 'en']).'"',
                'href="'.route('account.custom-requests.create', ['lang' => 'en']).'"',
                __('messages.custom_requests.custom_request'),
            ], false)
            ->assertSee('href="'.route('account.custom-requests.index', ['lang' => 'en']).'"', false)
            ->assertSee('href="'.route('account.bookings', ['lang' => 'en']).'"', false);
    }

    public function test_an_agency_owner_cannot_access_the_traveler_booking_list(): void
    {
        $agency = Agency::factory()->create();

        $response = $this->actingAs($agency->owner)->get(route('account.bookings', ['lang' => 'en']));

        $response->assertForbidden();
    }

    public function test_a_traveler_can_create_a_booking_from_the_booking_page_and_pay_for_it(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler', 'name' => 'Nadia Stone']);
        $agency = $this->createVerifiedAgency('Blue Horizon Travel', 70, [
            'address' => 'Yaounde, Cameroon',
        ]);
        $country = Country::factory()->create(['name' => 'Iceland']);
        $agency->countries()->attach($country);
        $package = Package::factory()->create([
            'agency_id' => $agency->id,
            'title' => 'Iceland Escape',
            'destination' => $country->name,
            'status' => PackageStatus::PUBLISHED,
            'price_from' => 1200,
        ]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => '2026-11-20',
            'capacity' => 8,
            'status' => 'active',
        ]);

        $dashboard = $this->actingAs($traveler)->get(route('dashboard', ['lang' => 'en']));
        $dashboard->assertOk()->assertSee(route('account.bookings', ['lang' => 'en']));

        $page = $this->actingAs($traveler)->get(route('account.bookings', [
            'lang' => 'en',
            'country_id' => $country->id,
            'agency_id' => $agency->id,
            'package_id' => $package->id,
        ]));
        $page->assertOk()
            ->assertSee('Booking')
            ->assertSee('Book')
            ->assertSee('Yaounde, Cameroon')
            ->assertSee('XAF 1,200.00');

        $response = $this->actingAs($traveler)->post(route('account.bookings.store', ['lang' => 'en']), [
            'country_id' => $country->id,
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'traveler_count' => 2,
            'notes' => 'Need a window seat.',
        ]);

        $response->assertRedirect(route('account.bookings', ['lang' => 'en']));
        $this->assertDatabaseHas('bookings', [
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'user_id' => $traveler->id,
            'traveler_count' => 2,
            'payment_status' => PaymentStatus::PAID->value,
            'currency' => 'XAF',
        ]);
        $this->assertDatabaseHas('booking_payment_transactions', [
            'booking_id' => Booking::query()->where('user_id', $traveler->id)->first()->id,
            'type' => 'charge',
            'status' => 'succeeded',
            'currency' => 'XAF',
        ]);
        $this->assertSame(1, $agency->owner->notifications()->whereJsonContains('data->type', 'booking')->count());
    }

    public function test_booking_page_lists_only_packages_with_upcoming_departures_in_the_selected_country(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $japan = Country::factory()->create(['name' => 'Japan']);
        $france = Country::factory()->create(['name' => 'France']);
        $japanAgency = $this->createVerifiedAgency('Japan Journeys', 80);
        $japanAgency->countries()->attach($japan);
        $japanPackage = Package::factory()->create([
            'agency_id' => $japanAgency->id,
            'title' => 'Kyoto Spring Escape',
            'destination' => $japan->name,
            'status' => PackageStatus::PUBLISHED,
        ]);
        $japanAvailability = PackageAvailability::create([
            'package_id' => $japanPackage->id,
            'departure_date' => now()->addDays(10)->toDateString(),
            'capacity' => 10,
            'status' => 'active',
        ]);
        $secondJapanPackage = Package::factory()->create([
            'agency_id' => $japanAgency->id,
            'title' => 'Hokkaido Winter Route',
            'destination' => $japan->name,
            'status' => PackageStatus::PUBLISHED,
        ]);
        PackageAvailability::create([
            'package_id' => $secondJapanPackage->id,
            'departure_date' => now()->addDays(20)->toDateString(),
            'capacity' => 8,
            'status' => 'active',
        ]);
        $unavailableJapanPackage = Package::factory()->create([
            'agency_id' => $japanAgency->id,
            'title' => 'Japan Without Departures',
            'destination' => $japan->name,
            'status' => PackageStatus::PUBLISHED,
        ]);
        PackageAvailability::create([
            'package_id' => $unavailableJapanPackage->id,
            'departure_date' => now()->addDays(30)->toDateString(),
            'capacity' => 5,
            'status' => 'inactive',
        ]);
        $franceAgency = $this->createVerifiedAgency('France Journeys', 75);
        $franceAgency->countries()->attach($france);
        $francePackage = Package::factory()->create([
            'agency_id' => $franceAgency->id,
            'title' => 'Paris Weekend',
            'destination' => $france->name,
            'status' => PackageStatus::PUBLISHED,
        ]);
        PackageAvailability::create([
            'package_id' => $francePackage->id,
            'departure_date' => now()->addDays(10)->toDateString(),
            'capacity' => 5,
            'status' => 'active',
        ]);

        $response = $this->actingAs($traveler)->get(route('account.bookings', [
            'lang' => 'en',
            'country_id' => $japan->id,
        ]));

        $response->assertOk()
            ->assertSee('Japan Journeys')
            ->assertSee('Kyoto Spring Escape')
            ->assertSee('Hokkaido Winter Route')
            ->assertDontSee('Japan Without Departures')
            ->assertDontSee('France Journeys')
            ->assertDontSee('Paris Weekend')
            ->assertSee(route('account.bookings', [
                'lang' => 'en',
                'country_id' => $japan->id,
                'agency_id' => $japanAgency->id,
                'package_id' => $japanPackage->id,
            ]));

        $this->actingAs($traveler)
            ->post(route('account.bookings.store', ['lang' => 'en']), [
                'country_id' => $france->id,
                'agency_id' => $japanAgency->id,
                'package_id' => $japanPackage->id,
                'package_availability_id' => $japanAvailability->id,
                'traveler_count' => 1,
            ])
            ->assertNotFound();
        $this->assertDatabaseCount('bookings', 0);

        $selectedPackageResponse = $this->actingAs($traveler)->get(route('account.bookings', [
            'lang' => 'en',
            'country_id' => $japan->id,
            'agency_id' => $japanAgency->id,
            'package_id' => $japanPackage->id,
        ]));

        $selectedPackageResponse->assertOk()
            ->assertSee('name="country_id" value="'.$japan->id.'"', false)
            ->assertSee('name="package_id" value="'.$japanPackage->id.'"', false)
            ->assertSee('name="agency_id" value="'.$japanAgency->id.'"', false)
            ->assertSee('name="package_availability_id"', false);
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
        $agency = Agency::factory()->create([
            'company_name' => 'Atlas Horizon Voyages',
            'email' => 'direct-contact@atlas.test',
            'phone' => '+237 690 000 000',
        ]);
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
        $reachedDatePackage = Package::factory()->create([
            'agency_id' => $agency->id,
            'title' => 'Departure Reaching Today',
            'status' => PackageStatus::PUBLISHED,
        ]);
        PackageAvailability::create([
            'package_id' => $reachedDatePackage->id,
            'departure_date' => today()->toDateString(),
            'capacity' => 8,
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
            ->assertDontSee('Published Without Dates')
            ->assertDontSee('Departure Reaching Today')
            ->assertDontSee('Private Draft Journey')
            ->assertDontSee(today()->translatedFormat('d M Y'))
            ->assertSee('data-booking-modal-open', false)
            ->assertSee('data-package-booking-dialog', false)
            ->assertSee(route('bookings.store', ['package' => $bookablePackage->id, 'lang' => 'en']));

        $response->assertDontSee('direct-contact@atlas.test')
            ->assertDontSee('+237 690 000 000')
            ->assertSee(__('messages.booking_platform_guidance'))
            ->assertDontSee('mailto:direct-contact@atlas.test')
            ->assertDontSee('tel:+237690000000');

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
        $response->assertSee('data-scroll-to-top', false);
        $response->assertSee(__('messages.explore_destinations'));
        $response->assertDontSee('Atlas Horizon Voyages');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-scroll-to-top', false);
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
        $response->assertSee('4.0')
            ->assertSee('Basé sur 7 avis');
        $response->assertSee('Top review one');
        $response->assertSee('Top review five');
        $response->assertDontSee('Lower review one');
        $response->assertDontSee('Lower review two');
    }

    public function test_agency_profile_hides_preset_ratings_without_published_traveler_reviews(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);

        $response = $this->actingAs($traveler)->get(route('agency.show', ['slug' => 'atlas-horizon-voyages']));

        $response->assertOk()
            ->assertSee('0.0')
            ->assertDontSee('4.9')
            ->assertDontSee('38 avis');
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
        $booking = $this->createCompletedReviewBooking($agency, $traveler);

        $response = $this->actingAs($traveler)->post(route('reviews.store', ['slug' => 'atlas-horizon-voyages']), [
            'booking_id' => $booking->id,
            'rating' => 5,
            'comment' => 'The planning was thoughtful and the trip felt wonderfully easy.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reviews', [
            'agency_id' => $agency->id,
            'user_id' => $traveler->id,
            'booking_id' => $booking->id,
            'rating' => 5,
            'status' => 'pending',
        ]);
    }

    public function test_completed_booking_details_show_a_review_form_to_its_traveler(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages']);
        $booking = $this->createCompletedReviewBooking($agency, $traveler);

        $response = $this->actingAs($traveler)->get(route('bookings.show', ['booking' => $booking->id]));

        $response->assertOk()
            ->assertSee('data-booking-review-form', false)
            ->assertSee('name="booking_id" value="'.$booking->id.'"', false);
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

    public function test_a_traveler_can_review_each_completed_booking_once_even_with_an_older_review(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages']);
        Review::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $traveler->id,
            'booking_id' => null,
            'status' => 'pending',
        ]);
        $firstBooking = $this->createCompletedReviewBooking($agency, $traveler);
        $secondBooking = $this->createCompletedReviewBooking($agency, $traveler);

        $agencyProfile = $this->actingAs($traveler)->get(route('agency.show', ['slug' => 'atlas-horizon-voyages']));
        $agencyProfile->assertOk()
            ->assertSee('name="booking_id"', false)
            ->assertSee($firstBooking->booking_reference)
            ->assertSee($secondBooking->booking_reference);

        $first = $this->actingAs($traveler)->post(route('reviews.store', ['slug' => 'atlas-horizon-voyages']), [
            'booking_id' => $firstBooking->id,
            'rating' => 5,
            'comment' => 'The planning was thoughtful and the trip felt wonderfully easy.',
        ]);
        $first->assertRedirect();

        $second = $this->actingAs($traveler)->post(route('reviews.store', ['slug' => 'atlas-horizon-voyages']), [
            'booking_id' => $secondBooking->id,
            'rating' => 4,
            'comment' => 'A second completed booking deserves its own review.',
        ]);
        $second->assertRedirect();

        $duplicate = $this->actingAs($traveler)->post(route('reviews.store', ['slug' => 'atlas-horizon-voyages']), [
            'booking_id' => $firstBooking->id,
            'rating' => 3,
            'comment' => 'A second review for the same booking should be blocked.',
        ]);

        $duplicate->assertSessionHasErrors('booking_id');
        $this->assertSame(3, $agency->fresh()->reviews()->where('user_id', $traveler->id)->count());
    }

    public function test_a_traveler_cannot_review_an_agency_without_a_completed_booking(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages']);

        $response = $this->actingAs($traveler)->post(route('reviews.store', ['slug' => 'atlas-horizon-voyages']), [
            'rating' => 5,
            'comment' => 'The planning was thoughtful and the trip felt wonderfully easy.',
        ]);

        $response->assertSessionHasErrors('booking_id');
        $this->assertDatabaseMissing('reviews', ['user_id' => $traveler->id]);
    }

    public function test_a_traveler_cannot_use_another_agencys_completed_booking_for_a_review(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $reviewedAgency = Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages']);
        $bookingAgency = Agency::factory()->create();
        $booking = $this->createCompletedReviewBooking($bookingAgency, $traveler);

        $response = $this->actingAs($traveler)->post(route('reviews.store', ['slug' => 'atlas-horizon-voyages']), [
            'booking_id' => $booking->id,
            'rating' => 5,
            'comment' => 'The planning was thoughtful and the trip felt wonderfully easy.',
        ]);

        $response->assertSessionHasErrors('booking_id');
        $this->assertSame(0, $reviewedAgency->reviews()->count());
    }

    public function test_agency_profile_explains_when_a_traveler_has_no_completed_booking_to_review(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        Agency::factory()->create(['company_name' => 'Atlas Horizon Voyages']);

        $response = $this->actingAs($traveler)->get(route('agency.show', ['slug' => 'atlas-horizon-voyages']));

        $response->assertOk()
            ->assertSee(__('messages.no_completed_booking_for_review'))
            ->assertDontSee('name="booking_id"', false);
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

    public function test_booking_notifications_link_to_the_travelers_all_bookings_page(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $bookingDetailsUrl = route('bookings.show', ['booking' => 10, 'lang' => 'en']);
        $traveler->notify(new AgencyActivityNotification(
            'Booking update',
            'Your booking was confirmed.',
            'booking',
            $bookingDetailsUrl,
        ));

        $response = $this->actingAs($traveler)->get(route('account.activity', ['lang' => 'en']));

        $response->assertOk()
            ->assertSee(
                'href="'.route('account.bookings', ['lang' => 'en']).'" class="mt-3 inline-flex text-sm font-bold text-[#e76f51] hover:text-[#b94d35]">'.__('messages.view_all').'</a>',
                false,
            )
            ->assertDontSee('href="'.$bookingDetailsUrl.'"', false);
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

    private function createCompletedReviewBooking(Agency $agency, User $traveler): Booking
    {
        $package = Package::factory()->create(['agency_id' => $agency->id, 'status' => PackageStatus::PUBLISHED]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => now()->subDays(3)->toDateString(),
            'capacity' => 10,
            'status' => 'active',
        ]);

        return Booking::create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'booking_reference' => 'TC-REVIEW-'.str_pad((string) ($agency->bookings()->count() + 1), 6, '0', STR_PAD_LEFT),
            'status' => 'completed',
            'payment_status' => PaymentStatus::UNPAID,
            'traveler_count' => 1,
            'unit_price' => 100,
            'total_amount' => 100,
            'currency' => 'XAF',
            'completed_at' => now()->subDay(),
        ]);
    }
}
