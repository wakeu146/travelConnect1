<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_has_canonical_social_metadata_and_brand_schema(): void
    {
        config()->set('app.url', 'https://travelconnect.cm');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('rel="canonical" href="https://travelconnect.cm/"', false);
        $response->assertSee('property="og:site_name" content="TravelConnect"', false);
        $response->assertSee('type="application/ld+json"', false);
        $response->assertSee('href="'.route('sitemap').'"', false);
        $response->assertSee(__('messages.sitemap'));
        $response->assertSee('property="og:image" content="https://travelconnect.cm/images/logos/travelconnect1.png"', false);
        $response->assertSee('property="og:image:width" content="570"', false);
        $response->assertSee('property="og:image:height" content="217"', false);
        $response->assertSee('name="robots" content="index, follow"', false);
        $response->assertSee('rel="icon" type="image/png" href="https://travelconnect.cm/images/logos/travelconnect-square.png" sizes="256x256"', false);
        $response->assertSee('images/destinations/new-york.jpg', false);
    }

    public function test_home_json_ld_contains_valid_organization_and_website_data(): void
    {
        config()->set('app.url', 'https://travelconnect.cm');

        $response = $this->get('/');

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);

        $this->assertNotEmpty($matches[1] ?? null);

        $structuredData = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $types = array_column($structuredData['@graph'], '@type');

        $this->assertSame('https://schema.org', $structuredData['@context']);
        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);

        $organization = collect($structuredData['@graph'])->firstWhere('@type', 'Organization');

        $this->assertSame('https://travelconnect.cm/images/logos/travelconnect-square.png', $organization['logo']['url']);
        $this->assertSame('Cameroon', $organization['areaServed']['name']);
    }

    public function test_auth_pages_are_noindexed(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_robots_excludes_private_workspaces(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertSee('Disallow: /admin/', false);
        $response->assertSee('Disallow: /account/', false);
        $response->assertSee('Disallow: /agency/', false);
        $response->assertSee('Disallow: /login', false);
        $response->assertSee('Sitemap: https://travelconnect.cm/sitemap.xml', false);
    }

    public function test_sitemap_contains_public_routes_in_xml_format(): void
    {
        config()->set('app.url', 'https://travelconnect.cm');
        Agency::factory()->create([
            'company_name' => 'Cameroon Trips',
            'verification_status' => VerificationStatus::VERIFIED,
        ]);
        Agency::factory()->create([
            'company_name' => 'Pending Cameroon Agency',
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<loc>https://travelconnect.cm/</loc>', false);
        $response->assertSee('<loc>https://travelconnect.cm/how-it-works</loc>', false);
        $response->assertSee('<loc>https://travelconnect.cm/for-agencies</loc>', false);
        $response->assertSee('<loc>https://travelconnect.cm/agencies/cameroon-trips</loc>', false);
        $response->assertDontSee('pending-cameroon-agency');
    }

    public function test_verified_agency_profiles_are_publicly_indexable_without_private_contacts(): void
    {
        config()->set('app.url', 'https://travelconnect.cm');
        $agency = Agency::factory()->create([
            'company_name' => 'Cameroon Trips',
            'verification_status' => VerificationStatus::VERIFIED,
            'email' => 'private-contact@example.test',
            'phone' => '+237 600 000 111',
        ]);

        $response = $this->get(route('agency.show', ['slug' => 'cameroon-trips', 'lang' => 'en']));

        $response->assertOk()
            ->assertSee('<title>Cameroon Trips | Travel agency in Cameroon | TravelConnect</title>', false)
            ->assertSee('name="robots" content="index, follow"', false)
            ->assertSee('rel="canonical" href="https://travelconnect.cm/agencies/cameroon-trips"', false)
            ->assertSee($agency->description)
            ->assertDontSee($agency->email)
            ->assertDontSee($agency->phone);
    }

    public function test_unverified_agency_profiles_are_not_publicly_accessible(): void
    {
        $agency = Agency::factory()->create(['company_name' => 'Pending Cameroon Agency']);

        $this->get(route('agency.show', ['slug' => 'pending-cameroon-agency']))
            ->assertNotFound();
    }

    public function test_homepage_links_to_verified_agency_profiles(): void
    {
        $agency = Agency::factory()->create([
            'company_name' => 'Cameroon Trips',
            'verification_status' => VerificationStatus::VERIFIED,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee(route('agency.show', ['slug' => 'cameroon-trips', 'lang' => 'en']), false)
            ->assertSee($agency->company_name);
    }
}
