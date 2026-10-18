<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminDashboardControllerTest extends TestCase
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

    public function test_unauthenticated_request_to_dashboard_redirects_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_open_dashboard_sections_or_pages(): void
    {
        $traveler = User::factory()->create();

        $this->actingAs($traveler)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->get(route('admin.dashboard.section', ['section' => 'platform_management']))
            ->assertForbidden();
    }

    public function test_verified_admin_can_render_all_navigation_destinations(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $this->actingAs($admin)->withSession([
            'admin_login.verified_user_id' => $admin->id,
            'admin_login.session_version' => $admin->admin_session_version,
            'admin_login.verified_at' => now()->timestamp,
            'admin_login.last_activity' => now()->timestamp,
        ]);

        $sections = config('admin_dashboard.sections');

        foreach ($sections as $sectionKey => $section) {
            $sectionUrl = $sectionKey === 'main'
                ? route('admin.dashboard', ['lang' => 'en'])
                : route('admin.dashboard.section', ['section' => $sectionKey, 'lang' => 'en']);

            $this->get($sectionUrl)
                ->assertOk()
                ->assertSee(__('messages.admin_nav.sections.'.$sectionKey))
                ->assertSee(__('messages.admin_nav.page_in_preparation'))
                ->assertSee('aria-current="page"', false)
                ->assertSee(__('messages.admin_nav.switch_language_to_english'))
                ->assertSee(__('messages.admin_nav.switch_language_to_french'))
                ->assertSee('data-admin-locale="en"', false)
                ->assertSee('data-admin-locale="fr"', false);

            foreach ($section['groups'] as $group) {
                foreach ($group['pages'] ?? [$group['page']] as $page) {
                    $this->get(route('admin.dashboard.page', ['page' => $page, 'lang' => 'en']))
                        ->assertOk()
                        ->assertSee(__('messages.admin_nav.pages.'.$page))
                        ->assertSee(__('messages.admin_nav.sections.'.$sectionKey))
                        ->assertSee('aria-current="page"', false);
                }
            }
        }

        $this->get(route('admin.dashboard.page', ['page' => 'overview', 'lang' => 'fr']))
            ->assertOk()
            ->assertSee('<html lang="fr">', false)
            ->assertSee(__('messages.admin_nav.pages.overview', [], 'fr'))
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('data-admin-locale="en"', false)
            ->assertSee('data-admin-locale="fr"', false);
    }

    public function test_unknown_dashboard_sections_and_pages_return_not_found(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $this->actingAs($admin)->withSession([
            'admin_login.verified_user_id' => $admin->id,
            'admin_login.session_version' => $admin->admin_session_version,
            'admin_login.verified_at' => now()->timestamp,
            'admin_login.last_activity' => now()->timestamp,
        ]);

        $this->get(route('admin.dashboard.section', ['section' => 'unknown']))
            ->assertNotFound();

        $this->get(route('admin.dashboard.page', ['page' => 'unknown']))
            ->assertNotFound();
    }
}
