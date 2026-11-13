<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\PackageAvailabilityStatus;
use App\Enums\PackageStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Country;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\PackageModerationEvent;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class AdminTravelManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            AdminSecondFactor::class,
            Mockery::mock(AdminSecondFactor::class),
        );
    }

    public function test_travel_management_group_opens_the_package_directory_by_default(): void
    {
        $this->actAsVerifiedAdmin();

        $this->get(route('admin.dashboard.section', ['section' => 'platform_management', 'lang' => 'en']))
            ->assertOk()
            ->assertSee(route('admin.dashboard.page', ['page' => 'all_travel_packages', 'lang' => 'en']), false);

        $this->get(route('admin.dashboard.page', ['page' => 'all_travel_packages', 'lang' => 'en']))
            ->assertOk()
            ->assertSee('data-admin-package-directory', false)
            ->assertSee(__('messages.admin_travel.package_introduction'));
    }

    public function test_package_directory_searches_and_filters_existing_agency_owned_records(): void
    {
        $this->actAsVerifiedAdmin();
        $matchingPackage = Package::factory()->create([
            'title' => 'Mountain Discovery',
            'destination' => 'Mount Cameroon',
            'status' => PackageStatus::PUBLISHED,
            'published_at' => now(),
            'price_from' => 1250,
        ]);
        $otherPackage = Package::factory()->create([
            'title' => 'Island Escape',
            'destination' => 'Limbe',
            'status' => PackageStatus::DRAFT,
        ]);
        $archivedPackage = Package::factory()->create([
            'title' => 'Archived Coast Tour',
            'status' => PackageStatus::ARCHIVED,
        ]);
        PackageAvailability::query()->create([
            'package_id' => $matchingPackage->id,
            'departure_date' => today()->addDays(10),
            'capacity' => 4,
            'status' => PackageAvailabilityStatus::ACTIVE,
        ]);

        $this->get(route('admin.dashboard.page', [
            'page' => 'published_packages',
            'lang' => 'en',
            'search' => 'Mount Cameroon',
        ]))
            ->assertOk()
            ->assertSee($matchingPackage->title)
            ->assertSee($matchingPackage->agency->company_name)
            ->assertSee($matchingPackage->destination)
            ->assertSee('1,250.00 XAF')
            ->assertSee(__('messages.admin_travel.availability_count', ['count' => 1]))
            ->assertDontSee($otherPackage->title);

        $this->get(route('admin.dashboard.page', ['page' => 'draft_unpublished_packages', 'lang' => 'en']))
            ->assertOk()
            ->assertSee($otherPackage->title)
            ->assertSee($archivedPackage->title)
            ->assertDontSee($matchingPackage->title);
    }

    public function test_admin_can_restrict_and_restore_package_with_required_audit_reason_without_publishing(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $package = Package::factory()->create([
            'status' => PackageStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $this->post(route('admin.packages.moderation', ['package' => $package->id, 'lang' => 'en']), [
            'decision' => 'restrict',
            'reason' => 'Offer requires a policy review.',
        ])->assertRedirect(route('admin.packages.show', ['package' => $package->id, 'lang' => 'en']));

        $this->assertSame(PackageStatus::ARCHIVED, $package->fresh()->status);
        $this->assertNull($package->fresh()->published_at);
        $this->assertDatabaseHas('package_moderation_events', [
            'package_id' => $package->id,
            'actor_id' => $admin->id,
            'action' => 'restrict',
            'previous_status' => PackageStatus::PUBLISHED->value,
            'new_status' => PackageStatus::ARCHIVED->value,
            'reason' => 'Offer requires a policy review.',
        ]);

        $this->post(route('admin.packages.moderation', ['package' => $package->id, 'lang' => 'en']), [
            'decision' => 'restore',
            'reason' => 'The listing was reviewed and can return to the agency.',
        ])->assertRedirect(route('admin.packages.show', ['package' => $package->id, 'lang' => 'en']));

        $this->assertSame(PackageStatus::DRAFT, $package->fresh()->status);
        $this->assertNull($package->fresh()->published_at);
        $this->assertSame(2, PackageModerationEvent::query()->where('package_id', $package->id)->count());
    }

    public function test_package_moderation_allows_an_empty_reason_but_rejects_short_reasons_and_non_admins(): void
    {
        $package = Package::factory()->create([
            'status' => PackageStatus::PUBLISHED,
            'published_at' => now(),
        ]);
        $admin = $this->actAsVerifiedAdmin();

        $this->get(route('admin.packages.show', ['package' => $package->id, 'lang' => 'en']))
            ->assertSee(__('messages.admin_travel.moderation_reason'))
            ->assertDontSee('Reason (required)');

        $this->from(route('admin.packages.show', ['package' => $package->id, 'lang' => 'en']))
            ->post(route('admin.packages.moderation', ['package' => $package->id, 'lang' => 'en']), [
                'decision' => 'restrict',
                'reason' => 'short',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(PackageStatus::PUBLISHED, $package->fresh()->status);
        $this->assertDatabaseCount('package_moderation_events', 0);

        $this->post(route('admin.packages.moderation', ['package' => $package->id, 'lang' => 'en']), [
            'decision' => 'restrict',
        ])->assertRedirect(route('admin.packages.show', ['package' => $package->id, 'lang' => 'en']));

        $this->assertSame(PackageStatus::ARCHIVED, $package->fresh()->status);
        $this->assertDatabaseHas('package_moderation_events', [
            'package_id' => $package->id,
            'actor_id' => $admin->id,
            'action' => 'restrict',
            'reason' => null,
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.packages.moderation', ['package' => $package->id, 'lang' => 'en']), [
                'decision' => 'restrict',
                'reason' => 'A valid moderation reason.',
            ])
            ->assertForbidden();

        $this->actingAs($admin)->get(route('admin.dashboard.page', [
            'page' => 'reported_pending_review',
            'lang' => 'en',
        ]))
            ->assertOk()
            ->assertSee('data-package-reports-unavailable', false)
            ->assertDontSee($package->title);
    }

    public function test_package_directory_handles_missing_agency_owner_data_without_crashing(): void
    {
        $this->actAsVerifiedAdmin();

        $owner = User::factory()->create();
        $agency = Agency::factory()->create(['user_id' => $owner->id]);
        $package = Package::factory()->create([
            'agency_id' => $agency->id,
            'title' => 'Legacy package with missing owner',
            'destination' => 'Yaoundé',
            'status' => PackageStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        User::query()->whereKey($owner->id)->delete();

        $this->get(route('admin.dashboard.page', ['page' => 'published_packages', 'lang' => 'en']))
            ->assertOk()
            ->assertSee($package->title)
            ->assertSee(__('messages.not_available'));
    }

    public function test_country_catalogue_syncs_api_defaults_and_admins_can_add_or_remove_countries(): void
    {
        $this->actAsVerifiedAdmin();
        $this->fakeCountryApi();
        $agency = Agency::factory()->create();
        $country = Country::factory()->create(['name' => 'Cameroun', 'code' => 'CM']);
        $service = Service::factory()->create(['name' => 'Airport transfer']);
        $agency->countries()->attach($country);
        $agency->services()->attach($service);

        $this->get(route('admin.dashboard.page', ['page' => 'countries', 'lang' => 'en']))
            ->assertOk()
            ->assertSee('data-admin-travel-countries', false)
            ->assertSee('Cameroon')
            ->assertSee('CM')
            ->assertSee('France');

        $this->assertDatabaseHas('countries', ['name' => 'France', 'code' => 'FR', 'is_active' => true]);
        $this->delete(route('admin.catalogue.countries.destroy', ['country' => $country->id, 'lang' => 'en']))
            ->assertRedirect(route('admin.dashboard.page', ['page' => 'countries', 'lang' => 'en']));
        $this->assertDatabaseHas('countries', ['id' => $country->id, 'is_active' => false]);
        $this->assertTrue($agency->fresh()->countries->contains('id', $country->id));

        $this->post(route('admin.catalogue.countries.store', ['lang' => 'en']), [
            'country_id' => $country->id,
        ])
            ->assertRedirect(route('admin.dashboard.page', ['page' => 'countries', 'lang' => 'en']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('countries', ['id' => $country->id, 'is_active' => true]);

        $this->post(route('admin.catalogue.services.store', ['lang' => 'en']), [
            'name' => 'Guided tours',
        ])->assertRedirect(route('admin.dashboard.page', ['page' => 'travel_services', 'lang' => 'en']));
        $this->patch(route('admin.catalogue.services.update', ['service' => $service->id, 'lang' => 'en']), [
            'name' => 'Airport pickup',
        ])->assertRedirect(route('admin.dashboard.page', ['page' => 'travel_services', 'lang' => 'en']));
        $this->assertDatabaseHas('services', ['name' => 'Guided tours']);
        $this->assertDatabaseHas('services', ['id' => $service->id, 'name' => 'Airport pickup']);
        $this->assertTrue($agency->fresh()->services->contains('id', $service->id));

        $this->get(route('admin.dashboard.page', ['page' => 'travel_services', 'lang' => 'en']))
            ->assertOk()
            ->assertSee('Save changes')
            ->assertDontSee('messages.admin_travel.save_catalogue_entry');
    }

    public function test_country_catalogue_shows_saved_countries_when_api_is_unavailable(): void
    {
        $this->actAsVerifiedAdmin();
        Cache::forget('country-catalogue.restcountries');
        Http::preventStrayRequests();
        Http::fake([
            'https://restcountries.com/v3.1/all*' => Http::failedConnection(),
        ]);
        Country::factory()->create(['name' => 'France', 'code' => 'FR']);

        $this->get(route('admin.dashboard.page', ['page' => 'countries', 'lang' => 'en']))
            ->assertOk()
            ->assertSee(__('messages.admin_travel.country_api_unavailable'))
            ->assertSee('Andorra');
        $this->assertGreaterThanOrEqual(200, Country::query()->active()->count());
        $this->get(route('admin.dashboard.page', ['page' => 'countries', 'lang' => 'en', 'search' => 'France']))
            ->assertOk()
            ->assertSee('France');
    }

    public function test_country_catalogue_preserves_explicitly_disabled_countries_when_syncing_api_data(): void
    {
        $this->actAsVerifiedAdmin();
        Cache::forget('country-catalogue.restcountries');
        Cache::forget('country-catalogue.api-unavailable');
        Http::preventStrayRequests();
        Http::fake([
            'https://restcountries.com/v3.1/all*' => Http::response([
                ['name' => ['common' => 'Cameroon'], 'cca2' => 'CM'],
                ['name' => ['common' => 'France'], 'cca2' => 'FR'],
            ]),
        ]);
        $country = Country::factory()->create(['name' => 'Cameroon', 'code' => 'CM', 'is_active' => false]);

        $this->get(route('admin.dashboard.page', ['page' => 'countries', 'lang' => 'en']))
            ->assertOk();

        $this->assertDatabaseHas('countries', ['id' => $country->id, 'is_active' => false]);
        $this->assertDatabaseHas('countries', ['name' => 'France', 'code' => 'FR', 'is_active' => true]);
    }

    public function test_agency_profile_loads_default_countries_when_country_api_is_unavailable(): void
    {
        Cache::forget('country-catalogue.restcountries');
        Cache::forget('country-catalogue.api-unavailable');
        Http::preventStrayRequests();
        Http::fake([
            'https://restcountries.com/v3.1/all*' => Http::failedConnection(),
        ]);
        $agency = Agency::factory()->create();

        $this->actingAs($agency->owner)
            ->get(route('agency.profile', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Cameroon')
            ->assertSee('France')
            ->assertSee('Japan');
    }

    public function test_country_catalogue_explains_when_api_is_unavailable_and_no_countries_are_saved(): void
    {
        $this->actAsVerifiedAdmin();
        Cache::forget('country-catalogue.restcountries');
        Http::preventStrayRequests();
        Http::fake([
            'https://restcountries.com/v3.1/all*' => Http::response([], 503),
        ]);

        $this->get(route('admin.dashboard.page', ['page' => 'countries', 'lang' => 'en']))
            ->assertOk()
            ->assertSee(__('messages.admin_travel.country_api_unavailable'))
            ->assertSee('Andorra');
    }

    public function test_only_administrators_can_remove_a_country(): void
    {
        $country = Country::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.catalogue.countries.destroy', ['country' => $country->id, 'lang' => 'en']))
            ->assertForbidden();

        $this->assertDatabaseHas('countries', ['id' => $country->id, 'is_active' => true]);
    }

    public function test_destinations_are_browsed_from_package_values_without_creating_a_second_catalogue(): void
    {
        $this->actAsVerifiedAdmin();
        Package::factory()->count(2)->create(['destination' => 'Mount Cameroon']);

        $this->get(route('admin.dashboard.page', ['page' => 'destinations', 'lang' => 'en']))
            ->assertOk()
            ->assertSee('data-admin-travel-destinations', false)
            ->assertSee('Mount Cameroon')
            ->assertSee('2')
            ->assertSee('destination=Mount%20Cameroon');
    }

    private function actAsVerifiedAdmin(): User
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();

        $this->actingAs($admin)->withSession([
            'admin_login.verified_user_id' => $admin->id,
            'admin_login.session_version' => $admin->admin_session_version,
            'admin_login.verified_at' => now()->timestamp,
            'admin_login.last_activity' => now()->timestamp,
        ]);

        return $admin;
    }

    private function fakeCountryApi(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://restcountries.com/v3.1/all*' => Http::response([
                ['name' => ['common' => 'France'], 'cca2' => 'FR'],
                ['name' => ['common' => 'Cameroon'], 'cca2' => 'CM'],
                ['name' => ['common' => 'Japan'], 'cca2' => 'JP'],
            ]),
        ]);
    }
}
