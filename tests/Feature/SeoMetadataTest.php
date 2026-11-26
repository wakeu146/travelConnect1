<?php

namespace Tests\Feature;

use Tests\TestCase;

class SeoMetadataTest extends TestCase
{
    public function test_public_home_has_canonical_social_metadata_and_brand_schema(): void
    {
        config()->set('app.url', 'https://travelconnect.cm');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('rel="canonical" href="https://travelconnect.cm/"', false);
        $response->assertSee('property="og:site_name" content="TravelConnect"', false);
        $response->assertSee('type="application/ld+json"', false);
        $response->assertSee('https://travelconnect.cm/images/logo.png', false);
        $response->assertSee('name="robots" content="index, follow"', false);
    }

    public function test_home_json_ld_contains_valid_organization_and_website_data(): void
    {
        $response = $this->get('/');

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);

        $this->assertNotEmpty($matches[1] ?? null);

        $structuredData = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $types = array_column($structuredData['@graph'], '@type');

        $this->assertSame('https://schema.org', $structuredData['@context']);
        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);
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
        $response->assertSee('Sitemap: https://travelconnect.cm/sitemap.xml', false);
    }
}
