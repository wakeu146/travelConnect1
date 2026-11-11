<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyDocument;
use App\Models\Country;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyDiscoveryExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_discovery_shows_all_verified_agencies_in_trust_score_order(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $this->createDiscoverableAgency('Highest Trust Agency', ['trust_score' => 96]);
        $this->createDiscoverableAgency('Second Trust Agency', ['trust_score' => 84]);
        $this->createDiscoverableAgency('Third Trust Agency', ['trust_score' => 71]);
        $this->createDiscoverableAgency('Other Directory Agency', ['trust_score' => 60]);
        Agency::factory()->create([
            'company_name' => 'Unverified High Score Agency',
            'trust_score' => 100,
        ]);

        $response = $this->actingAs($traveler)->get(route('discover'));

        $response->assertOk()
            ->assertSeeInOrder([
                __('messages.agency_discovery.recent_title'),
                __('messages.agency_discovery.featured_title'),
                'Highest Trust Agency',
                'Second Trust Agency',
                'Third Trust Agency',
                'Other Directory Agency',
            ])
            ->assertDontSee('Unverified High Score Agency')
            ->assertSee('data-discovery-recent class="hidden gap-5"', false)
            ->assertDontSee('data-discovery-recent-empty', false);
    }

    public function test_search_mode_starts_blank_and_shows_matches_for_a_single_character_query(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $this->createDiscoverableAgency('Alpine Journeys');

        $blankResponse = $this->actingAs($traveler)->get(route('account.discover', ['search_mode' => 1]));

        $blankResponse->assertOk()
            ->assertSee('data-search-mode="true"', false)
            ->assertSee('data-discovery-hero class="hidden border-b', false)
            ->assertSee('data-discovery-recent class="hidden gap-5"', false)
            ->assertSee('data-discovery-popular class="hidden gap-5"', false)
            ->assertSee('data-discovery-featured class="hidden gap-5"', false)
            ->assertSee('class="hidden gap-5" data-discovery-results-section aria-busy="false"', false);

        $searchResponse = $this->actingAs($traveler)->get(route('account.discover', [
            'search_mode' => 1,
            'search' => 'A',
        ]));

        $searchResponse->assertOk()
            ->assertSee('Alpine Journeys')
            ->assertSee('class="grid gap-5" data-discovery-results-section aria-busy="false"', false);
        $this->assertDatabaseCount('recent_agencies', 0);
    }

    public function test_popular_section_requires_a_published_rating_of_at_least_four_even_when_stored_trust_scores_are_high(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $fivePointAgency = $this->createDiscoverableAgency('Five Point Agency', ['trust_score' => 100]);
        $fourPointAgency = $this->createDiscoverableAgency('Four Point Agency', ['trust_score' => 80]);
        $belowFourPointAgency = $this->createDiscoverableAgency('Below Four Point Agency', ['trust_score' => 80]);
        $this->createDiscoverableAgency('No Rating Agency', ['trust_score' => 100]);

        foreach ([
            [$fivePointAgency, [5]],
            [$fourPointAgency, [4]],
            [$belowFourPointAgency, [3, 4, 4]],
        ] as [$agency, $ratings]) {
            foreach ($ratings as $rating) {
                Review::factory()->create([
                    'agency_id' => $agency->id,
                    'user_id' => User::factory()->create(['role' => 'traveler'])->id,
                    'rating' => $rating,
                    'status' => 'published',
                    'is_hidden' => false,
                ]);
            }
        }

        $response = $this->actingAs($traveler)->get(route('discover'));

        $response->assertOk()
            ->assertSeeInOrder([
                __('messages.agency_discovery.recent_title'),
                __('messages.agency_discovery.popular_title'),
                __('messages.agency_discovery.featured_title'),
            ])
            ->assertSee('3.7')
            ->assertViewHas(
                'popularAgencyCards',
                fn (array $cards): bool => array_column($cards, 'name') === [
                    'Five Point Agency',
                    'Four Point Agency',
                ],
            );
    }

    public function test_discovery_uses_the_dashboard_search_bar_without_page_level_search_controls(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);

        $response = $this->actingAs($traveler)->get(route('account.discover', ['lang' => 'fr']));

        $response->assertOk()
            ->assertSee(__('messages.search_agencies_or_destinations'))
            ->assertSee('action="'.route('account.discover', ['lang' => 'fr']).'"', false)
            ->assertDontSee('agency-discovery-form')
            ->assertDontSee('agency-discovery-location')
            ->assertDontSee('Trouver des agences près d’un lieu')
            ->assertSee('Agences consultées récemment');

        $this->assertSame(1, substr_count($response->getContent(), 'name="search"'));
    }

    public function test_search_matches_agency_name_and_the_address_location_together(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $matchingAgency = $this->createDiscoverableAgency('Global Journey Co', [
            'address' => 'Yaoundé, Cameroon',
            'email' => 'private@example.test',
            'phone' => 'private-phone',
            'license_number' => 'private-license',
        ]);
        $otherCity = $this->createDiscoverableAgency('Global Routes', ['address' => 'Douala, Cameroon']);
        $otherName = $this->createDiscoverableAgency('Cameroon Journey Co', ['address' => 'Yaoundé, Cameroon']);
        $matchingAgency->services()->attach(Service::factory()->create(['name' => 'Family tours']));
        Review::factory()->create([
            'agency_id' => $matchingAgency->id,
            'rating' => 5,
            'status' => 'published',
            'is_hidden' => false,
        ]);

        $response = $this->actingAs($traveler)->getJson(route('discover.search', [
            'search' => 'gLoBaL',
            'location' => 'Yaoundé',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Global Journey Co')
            ->assertJsonPath('data.0.location', 'Yaoundé, Cameroon')
            ->assertJsonPath('data.0.verified', true)
            ->assertJsonPath('data.0.rating', '5.0')
            ->assertJsonPath('data.0.review_count', 1)
            ->assertJsonPath('data.0.services.0', 'Family tours');
        $this->assertSame(
            [
                'id',
                'slug',
                'name',
                'location',
                'logo_url',
                'cover_url',
                'verified',
                'rating',
                'review_count',
                'destinations',
                'services',
                'saved',
                'selected',
                'profile_url',
                'favorite_url',
                'select_url',
                'remove_recent_url',
            ],
            array_keys($response->json('data.0')),
        );
        $response->assertJsonMissing(['email' => 'private@example.test'])
            ->assertJsonMissing(['phone' => 'private-phone'])
            ->assertJsonMissing(['license_number' => 'private-license']);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('recent_agencies', 0);

        $cityResponse = $this->actingAs($traveler)->getJson(route('discover.search', [
            'search' => 'Yaoundé',
        ]));
        $cityResponse->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Global Journey Co'])
            ->assertJsonFragment(['name' => $otherName->company_name])
            ->assertJsonMissing(['name' => $otherCity->company_name]);
    }

    public function test_search_matches_destinations_and_supports_single_character_queries(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = $this->createDiscoverableAgency('Alpine Journeys');
        $agency->countries()->attach(Country::factory()->create(['name' => 'Japan']));

        $countryResponse = $this->actingAs($traveler)->getJson(route('discover.search', ['search' => 'Japan']));
        $singleCharacterResponse = $this->actingAs($traveler)->getJson(route('discover.search', ['search' => 'A']));
        $htmlResponse = $this->actingAs($traveler)->get(
            route('discover.search', ['search' => 'A']),
            ['Accept' => 'text/html'],
        );

        $countryResponse->assertOk()
            ->assertJsonPath('data.0.name', 'Alpine Journeys')
            ->assertJsonPath('data.0.destinations.0', 'Japan');
        $singleCharacterResponse->assertJsonPath('data.0.name', 'Alpine Journeys');
        $htmlResponse->assertOk()
            ->assertSee('data-discovery-card', false)
            ->assertSee('Alpine Journeys');
    }

    public function test_location_search_excludes_unverified_and_suspended_agencies(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $eligibleAgency = $this->createDiscoverableAgency('Open Yaounde Travel', ['address' => 'Yaoundé, Cameroon']);
        $eligibleAgency->countries()->attach(Country::factory()->create(['name' => 'Japan']));
        $unverifiedAgency = Agency::factory()->create(['company_name' => 'Unverified Yaounde Travel', 'address' => 'Yaoundé, Cameroon']);
        $suspendedAgency = $this->createDiscoverableAgency('Suspended Yaounde Travel', [
            'address' => 'Yaoundé, Cameroon',
            'operating_status' => 'suspended',
        ]);
        AgencyDocument::factory()->create(['agency_id' => $unverifiedAgency->id, 'status' => 'approved']);

        $response = $this->actingAs($traveler)->getJson(route('discover.search', ['location' => 'Yaoundé']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Open Yaounde Travel')
            ->assertJsonMissing(['name' => 'Unverified Yaounde Travel'])
            ->assertJsonMissing(['name' => 'Suspended Yaounde Travel']);

        $countryResponse = $this->actingAs($traveler)->getJson(route('discover.search', ['location' => 'Cameroon']));
        $countryResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Open Yaounde Travel')
            ->assertJsonMissingPath('data.0.distance');

        $destinationResponse = $this->actingAs($traveler)->getJson(route('discover.search', ['location' => 'Japan']));
        $destinationResponse->assertOk()->assertExactJson([
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'has_more' => false,
            ],
        ]);

        $unmatchedLocationResponse = $this->actingAs($traveler)->getJson(route('discover.search', [
            'location' => 'Bafoussam',
        ]));
        $unmatchedLocationResponse->assertOk()
            ->assertExactJson([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'has_more' => false,
                ],
            ]);
    }

    public function test_opened_agencies_are_recent_deduplicated_and_private_to_each_traveler(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $anotherTraveler = User::factory()->create(['role' => 'traveler']);
        foreach (range(1, 3) as $number) {
            $this->createDiscoverableAgency(sprintf('Featured Travel %d', $number), [
                'trust_score' => 90 + $number,
            ]);
        }
        $agency = $this->createDiscoverableAgency('Recently Viewed Travel', ['trust_score' => 1]);

        $this->actingAs($traveler)->get(route('agency.show', ['slug' => 'recently-viewed-travel']));
        $this->actingAs($traveler)->get(route('agency.show', ['slug' => 'recently-viewed-travel']));

        $this->assertDatabaseCount('recent_agencies', 1);
        $this->assertDatabaseHas('recent_agencies', [
            'user_id' => $traveler->id,
            'agency_id' => $agency->id,
        ]);

        $travelerResponse = $this->actingAs($traveler)->get(route('account.discover'));
        $anotherTravelerResponse = $this->actingAs($anotherTraveler)->get(route('account.discover'));

        $travelerResponse->assertSee('data-discovery-recent class="grid gap-5"', false)
            ->assertSee('Recently Viewed Travel');
        $anotherTravelerResponse->assertSee('data-discovery-recent class="hidden gap-5"', false)
            ->assertSee('Recently Viewed Travel');
        $this->assertDatabaseMissing('recent_agencies', [
            'user_id' => $anotherTraveler->id,
            'agency_id' => $agency->id,
        ]);

        $this->actingAs($anotherTraveler)
            ->deleteJson(route('discover.recent.destroy', ['agency' => $agency->id]))
            ->assertNoContent();
        $this->assertDatabaseHas('recent_agencies', [
            'user_id' => $traveler->id,
            'agency_id' => $agency->id,
        ]);
    }

    public function test_travelers_can_remove_one_recent_agency_and_clear_only_their_own_history(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $anotherTraveler = User::factory()->create(['role' => 'traveler']);
        $firstAgency = $this->createDiscoverableAgency('First Recent Travel');
        $secondAgency = $this->createDiscoverableAgency('Second Recent Travel');
        $traveler->recentAgencies()->createMany([
            ['agency_id' => $firstAgency->id, 'last_viewed_at' => now()],
            ['agency_id' => $secondAgency->id, 'last_viewed_at' => now()],
        ]);
        $anotherTraveler->recentAgencies()->create([
            'agency_id' => $firstAgency->id,
            'last_viewed_at' => now(),
        ]);

        $this->actingAs($traveler)
            ->deleteJson(route('discover.recent.destroy', ['agency' => $firstAgency->id]))
            ->assertNoContent();
        $this->assertDatabaseMissing('recent_agencies', [
            'user_id' => $traveler->id,
            'agency_id' => $firstAgency->id,
        ]);
        $this->assertDatabaseHas('recent_agencies', [
            'user_id' => $traveler->id,
            'agency_id' => $secondAgency->id,
        ]);
        $this->assertDatabaseHas('recent_agencies', [
            'user_id' => $anotherTraveler->id,
            'agency_id' => $firstAgency->id,
        ]);

        $this->actingAs($traveler)->deleteJson(route('discover.recent.clear'))->assertNoContent();
        $this->assertDatabaseMissing('recent_agencies', ['user_id' => $traveler->id]);
        $this->assertDatabaseHas('recent_agencies', [
            'user_id' => $anotherTraveler->id,
            'agency_id' => $firstAgency->id,
        ]);
    }

    public function test_search_results_are_not_recorded_as_recently_viewed_agencies(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);

        foreach (range(1, 13) as $number) {
            $this->createDiscoverableAgency(sprintf('Recent History Agency %02d', $number));
        }

        $response = $this->actingAs($traveler)->getJson(route('discover.search', [
            'search' => 'Recent History Agency',
        ]));

        $response->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.has_more', true);
        $this->assertDatabaseCount('recent_agencies', 0);
    }

    public function test_only_travelers_can_use_discovery_search_and_history_actions(): void
    {
        $agencyOwner = User::factory()->create(['role' => 'agency_owner']);

        $this->actingAs($agencyOwner)
            ->getJson(route('discover.search', ['search' => 'Travel']))
            ->assertForbidden();
        $this->actingAs($agencyOwner)
            ->deleteJson(route('discover.recent.clear'))
            ->assertForbidden();
    }

    public function test_traveler_can_select_a_verified_agency_and_replace_the_previous_selection(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $previousAgency = $this->createDiscoverableAgency('Previous Selected Agency');
        $selectedAgency = $this->createDiscoverableAgency('New Selected Agency');
        $traveler->selectedAgency()->associate($previousAgency);
        $traveler->save();

        $response = $this->actingAs($traveler)
            ->postJson(route('discover.selection.store', ['agency' => $selectedAgency->id]));

        $response->assertOk()->assertExactJson([
            'selected_agency_id' => $selectedAgency->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $traveler->id,
            'selected_agency_id' => $selectedAgency->id,
        ]);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_discovery_agency_cards_do_not_show_a_select_agency_button(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = $this->createDiscoverableAgency('Persisted Selection Agency');

        $response = $this->actingAs($traveler)->get(route('discover'));

        $response->assertOk()
            ->assertSee($agency->company_name)
            ->assertDontSee('data-select-agency', false)
            ->assertDontSee(__('messages.agency_discovery.select_agency'));
    }

    public function test_non_travelers_cannot_select_an_agency(): void
    {
        $agencyOwner = User::factory()->create(['role' => 'agency_owner']);
        $agency = $this->createDiscoverableAgency('Restricted Selection Agency');

        $this->actingAs($agencyOwner)
            ->postJson(route('discover.selection.store', ['agency' => $agency->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $agencyOwner->id,
            'selected_agency_id' => null,
        ]);
    }

    public function test_travelers_cannot_select_an_ineligible_agency(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);
        $agency = Agency::factory()->create([
            'company_name' => 'Unverified Selection Agency',
            'verification_status' => 'pending',
        ]);

        $this->actingAs($traveler)
            ->postJson(route('discover.selection.store', ['agency' => $agency->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id' => $traveler->id,
            'selected_agency_id' => null,
        ]);
    }

    private function createDiscoverableAgency(string $companyName, array $attributes = []): Agency
    {
        $agency = Agency::factory()->create(array_merge([
            'company_name' => $companyName,
            'verification_status' => 'verified',
            'operating_status' => 'active',
        ], $attributes));

        AgencyDocument::factory()->create([
            'agency_id' => $agency->id,
            'status' => 'approved',
        ]);

        return $agency;
    }
}
