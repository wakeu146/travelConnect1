<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\UserRole;
use App\Mail\AdminAccountSecurityMail;
use App\Mail\AgencyModerationMail;
use App\Models\AdminLoginEvent;
use App\Models\AdminUserActivity;
use App\Models\Agency;
use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
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

    public function test_users_pages_require_a_verified_administrator_session(): void
    {
        $this->get(route('admin.users.index'))
            ->assertRedirect(route('login'));

        $traveler = User::factory()->create();

        $this->actingAs($traveler)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_user_list_offers_direct_deletion_without_a_csv_export_route(): void
    {
        $this->verifiedAdmin();
        $traveler = User::factory()->create([
            'name' => 'Delete from list',
            'profile_photo_path' => 'profile-photos/delete-from-list.jpg',
        ]);

        $this->get(route('admin.users.index', ['lang' => 'en']))
            ->assertSee(route('admin.users.destroy', ['userId' => $traveler->id, 'lang' => 'en']), false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee(asset('storage/profile-photos/delete-from-list.jpg'), false)
            ->assertSee(__('messages.admin_users.profile_photo_alt', ['name' => $traveler->name]))
            ->assertSee(__('messages.admin_users.apply_to_selected'))
            ->assertSee('aria-label="'.__('messages.admin_users.tabs').'"', false)
            ->assertSee(route('admin.users.travelers', ['lang' => 'en']), false)
            ->assertSee(route('admin.users.agency-owners', ['lang' => 'en']), false)
            ->assertSee(route('admin.users.administrators', ['lang' => 'en']), false)
            ->assertSee(route('admin.users.suspended', ['lang' => 'en']), false)
            ->assertSee('aria-current="page"', false)
            ->assertSee(__('messages.admin_users.delete_user'))
            ->assertSee(__('messages.admin_users.delete_selected'))
            ->assertDontSee('name="reason"', false)
            ->assertDontSee('Export CSV')
            ->assertSee('data-user-status-cell="active"', false)
            ->assertSee('bg-emerald-50/80 dark:bg-emerald-950/30');

        $this->assertFalse(app('router')->has('admin.users.export'));
    }

    public function test_user_list_shows_online_status_for_all_account_roles_and_stale_users_as_offline(): void
    {
        $this->travelTo('2026-11-08 12:00:00');
        $this->verifiedAdmin();
        $onlineTraveler = User::factory()->create([
            'name' => 'Online Traveler',
            'role' => UserRole::TRAVELER,
        ]);
        $onlineTraveler->forceFill([
            'is_online' => true,
            'last_active_at' => now(),
        ])->save();
        $offlineTraveler = User::factory()->create([
            'name' => 'Offline Traveler',
            'role' => UserRole::TRAVELER,
        ]);
        $offlineTraveler->forceFill([
            'is_online' => true,
            'last_active_at' => now()->subMinutes(6),
        ])->save();
        $agency = Agency::factory()->create(['company_name' => 'Presence Agency']);

        $response = $this->get(route('admin.users.index', ['lang' => 'en']));

        $response->assertSee($onlineTraveler->name)
            ->assertSee($offlineTraveler->name)
            ->assertSee($agency->owner->name)
            ->assertSee(__('messages.admin_users.presence'))
            ->assertSee(__('messages.admin_users.online_now'))
            ->assertSee(__('messages.admin_users.offline'))
            ->assertSee('data-user-presence-cell="online"', false)
            ->assertSee('data-user-presence-cell="offline"', false)
            ->assertSee('h-2 w-2 rounded-full bg-emerald-500', false)
            ->assertSee('h-2 w-2 rounded-full bg-slate-400', false)
            ->assertSee('inline-flex items-center gap-2 text-xs admin-muted', false)
            ->assertDontSee('rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.12em]', false)
            ->assertSee('data-user-presence="online"', false)
            ->assertSee('data-user-presence="offline"', false)
            ->assertDontSee('admin-badge inline-flex items-center gap-1.5 border px-2 py-1 text-xs font-bold border-emerald-500/50', false)
            ->assertSee(__('messages.admin_users.last_active', [
                'time' => $offlineTraveler->last_active_at->locale('en')->diffForHumans(),
            ]));

        $this->get(route('admin.users.show', [
            'userId' => $offlineTraveler->id,
            'lang' => 'en',
        ]))
            ->assertSee('data-user-presence="offline"', false)
            ->assertSee(__('messages.admin_users.offline'))
            ->assertSee(__('messages.admin_users.last_active', [
                'time' => $offlineTraveler->last_active_at->locale('en')->diffForHumans(),
            ]));
    }

    public function test_administrator_can_permanently_delete_a_user_and_keep_the_list_context(): void
    {
        $this->verifiedAdmin();
        $traveler = User::factory()->create(['name' => 'Delete this traveler']);
        $filters = [
            'return_page' => 'travelers',
            'list_page' => 2,
            'search' => 'Delete this',
            'role' => UserRole::TRAVELER->value,
            'status' => 'active',
            'from' => '2026-01-01',
            'to' => '2026-01-31',
        ];

        $this->delete(route('admin.users.destroy', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]), $filters)
            ->assertRedirect(route('admin.users.travelers', [
                'lang' => 'en',
                'search' => 'Delete this',
                'role' => UserRole::TRAVELER->value,
                'status' => 'active',
                'from' => '2026-01-01',
                'to' => '2026-01-31',
                'page' => 2,
            ]))
            ->assertSessionHas('status', __('messages.admin_users.permanently_deleted'));

        $this->assertDatabaseMissing('users', ['id' => $traveler->id]);
    }

    public function test_permanently_deleting_an_agency_owner_also_removes_their_agency(): void
    {
        $this->verifiedAdmin();
        $agency = Agency::factory()->create(['company_name' => 'Agency to remove']);
        $owner = $agency->owner;
        Mail::fake();

        $this->delete(route('admin.users.destroy', [
            'userId' => $owner->id,
            'lang' => 'en',
        ]), ['return_page' => 'agency_owners'])
            ->assertRedirect(route('admin.users.agency-owners', ['lang' => 'en']))
            ->assertSessionHas('status', __('messages.admin_users.permanently_deleted'));

        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseMissing('agencies', ['id' => $agency->id]);
        Mail::assertSent(
            AgencyModerationMail::class,
            fn (AgencyModerationMail $mail): bool => $mail->hasTo($owner->email)
                && $mail->agencyName === $agency->company_name,
        );
    }

    public function test_administrator_accounts_cannot_be_permanently_deleted(): void
    {
        $this->verifiedAdmin();
        $targetAdmin = User::factory()->withRole(UserRole::ADMIN)->create();

        $this->get(route('admin.users.administrators', ['lang' => 'en']))
            ->assertDontSee('data-user-delete-form', false);

        $this->delete(route('admin.users.destroy', [
            'userId' => $targetAdmin->id,
            'lang' => 'en',
        ]), ['return_page' => 'administrators'])
            ->assertForbidden();

        $this->assertModelExists($targetAdmin);
    }

    public function test_non_administrator_cannot_permanently_delete_a_user(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->delete(route('admin.users.destroy', [
                'userId' => $target->id,
                'lang' => 'en',
            ]), ['return_page' => 'all_users'])
            ->assertForbidden();
        $this->post(route('admin.users.bulk', ['lang' => 'en']), [
            'user_ids' => [$target->id],
            'operation' => 'permanently_delete',
            'list' => 'all_users',
        ])->assertForbidden();

        $this->assertModelExists($target);
    }

    public function test_traveler_list_keeps_its_server_side_role_restriction(): void
    {
        $this->verifiedAdmin();
        $traveler = User::factory()->create(['name' => 'Real Traveler']);
        $administrator = User::factory()->withRole(UserRole::ADMIN)->create(['name' => 'Not a Traveler']);

        $response = $this->get(route('admin.users.travelers', [
            'lang' => 'en',
            'role' => UserRole::ADMIN->value,
        ]));

        $response->assertSee(__('messages.admin_users.empty_title'))
            ->assertDontSee($traveler->name)
            ->assertDontSee($administrator->name);
    }

    public function test_administrator_list_and_users_navigation_use_the_configured_pages(): void
    {
        $this->verifiedAdmin();
        $administrator = User::factory()->withRole(UserRole::ADMIN)->create([
            'name' => 'Listed Administrator',
        ]);
        $traveler = User::factory()->create(['name' => 'Excluded Traveler']);

        $response = $this->get(route('admin.users.administrators', ['lang' => 'fr']))
            ->assertSee('<html lang="fr">', false)
            ->assertSee(__('messages.admin_users.roles.admin', [], 'fr'))
            ->assertSee($administrator->name)
            ->assertDontSee($traveler->name);

        foreach (config('admin_dashboard.page_routes') as $routeName) {
            $response->assertSee(route($routeName, ['lang' => 'fr']), false);
        }

        $this->assertStringContainsString(
            'href="'.route('admin.dashboard.section', ['section' => 'platform_management', 'lang' => 'fr']).'" aria-label="'.__('messages.admin_nav.sections.platform_management', [], 'fr').'"',
            preg_replace('/\s+/', ' ', $response->getContent()) ?? $response->getContent(),
        );

        $this->get(route('admin.dashboard.section', ['section' => 'platform_management', 'lang' => 'fr']))
            ->assertSee(__('messages.admin_nav.sections.platform_management', [], 'fr'))
            ->assertSee('data-admin-section-card="users"', false)
            ->assertSee(route('admin.users.index', ['lang' => 'fr']), false)
            ->assertSee('data-admin-section-card="agencies"', false)
            ->assertDontSee(__('messages.admin_nav.page_in_preparation', [], 'fr'));
    }

    public function test_all_users_combines_search_role_status_and_registration_date_filters(): void
    {
        $this->verifiedAdmin();
        $matchingUser = User::factory()->create([
            'name' => 'Range Match Traveler',
            'role' => UserRole::TRAVELER,
            'created_at' => '2026-04-12 10:00:00',
        ]);
        $outsideRange = User::factory()->create([
            'name' => 'Range Match Outside',
            'role' => UserRole::TRAVELER,
            'created_at' => '2026-05-12 10:00:00',
        ]);
        $differentRole = User::factory()->withRole(UserRole::ADMIN)->create([
            'name' => 'Range Match Administrator',
            'created_at' => '2026-04-12 10:00:00',
        ]);

        $this->get(route('admin.users.index', [
            'lang' => 'en',
            'search' => '  Range   Match  ',
            'role' => UserRole::TRAVELER->value,
            'status' => 'active',
            'from' => '2026-04-01',
            'to' => '2026-04-30',
        ]))
            ->assertSee($matchingUser->name)
            ->assertDontSee($outsideRange->name)
            ->assertDontSee($differentRole->name)
            ->assertSee('Range Match');
    }

    public function test_invalid_user_filters_are_rejected(): void
    {
        $this->verifiedAdmin();

        $this->get(route('admin.users.index', [
            'lang' => 'en',
            'role' => 'super_admin',
        ]))
            ->assertSessionHasErrors('role');

        $this->get(route('admin.users.index', [
            'lang' => 'en',
            'from' => '2026-05-01',
            'to' => '2026-04-01',
        ]))
            ->assertSessionHasErrors('to');
    }

    public function test_agency_owner_list_shows_actual_agency_status_separately(): void
    {
        $this->verifiedAdmin();
        $owner = User::factory()->withRole(UserRole::AGENCY_OWNER)->create([
            'name' => 'Agency Account Owner',
        ]);
        $agency = Agency::factory()->for($owner, 'owner')->create([
            'company_name' => 'Harbor Travel',
            'verification_status' => 'verified',
        ]);
        $agency->delete();

        $this->get(route('admin.users.agency-owners', ['lang' => 'en']))
            ->assertSee($owner->name)
            ->assertSee('Harbor Travel')
            ->assertSee(__('messages.admin_users.verification.verified'))
            ->assertSee(__('messages.admin_users.agency_statuses.deactivated'))
            ->assertSee(__('messages.admin_users.statuses.active'));
    }

    public function test_deactivated_accounts_and_details_use_real_soft_delete_data(): void
    {
        $this->verifiedAdmin();
        $deactivatedUser = User::factory()->create(['name' => 'Deactivated Traveler']);
        $deactivatedUser->delete();
        $activeUser = User::factory()->create(['name' => 'Active Traveler']);

        $this->get(route('admin.users.suspended', ['lang' => 'en']))
            ->assertSee($deactivatedUser->name)
            ->assertDontSee($activeUser->name)
            ->assertSee(__('messages.admin_users.statuses.deactivated'))
            ->assertSee('data-user-status-cell="deactivated"', false)
            ->assertSee('bg-rose-50/80 dark:bg-rose-950/30');

        $this->get(route('admin.users.show', [
            'userId' => $deactivatedUser->id,
            'lang' => 'en',
            'return_page' => 'suspended_deactivated_accounts',
            'search' => 'Deactivated',
        ]))
            ->assertSee($deactivatedUser->name)
            ->assertSee(__('messages.admin_users.no_deactivation_audit'))
            ->assertSee(__('messages.admin_users.no_bookings'))
            ->assertSee(__('messages.admin_users.no_travel_requests'))
            ->assertSee(__('messages.admin_users.no_inquiries'))
            ->assertSee(__('messages.admin_users.no_reviews'))
            ->assertSee('search=Deactivated')
            ->assertDontSee('remember_token')
            ->assertDontSee($deactivatedUser->getRememberToken())
            ->assertDontSee($deactivatedUser->getAuthPassword());
    }

    public function test_user_profile_uses_agency_profile_layout_and_displays_the_users_photo_and_information(): void
    {
        $this->verifiedAdmin();
        $traveler = User::factory()->create([
            'name' => 'Profile Layout Traveler',
            'email' => 'profile-layout@example.test',
            'phone' => '+237 600 123 456',
            'profile_photo_path' => 'profile-photos/profile-layout.jpg',
            'email_verified_at' => '2026-06-15 10:30:00',
        ]);

        $this->get(route('admin.users.show', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]))
            ->assertSee('data-admin-user-profile', false)
            ->assertDontSee('data-user-cover', false)
            ->assertSee('data-user-profile-photo', false)
            ->assertSee(asset('storage/profile-photos/profile-layout.jpg'), false)
            ->assertSee(__('messages.admin_users.profile_photo_alt', ['name' => $traveler->name]))
            ->assertSee(__('messages.admin_users.account_information'))
            ->assertSee(__('messages.admin_users.account_status_information'))
            ->assertSee('<details class="grid gap-3">', false)
            ->assertSee(__('messages.admin_users.private_note_placeholder'))
            ->assertDontSee('name="reason"', false)
            ->assertSee($traveler->email)
            ->assertSee($traveler->phone)
            ->assertSee(__('messages.admin_users.email_verified'))
            ->assertSee(__('messages.admin_users.traveler_activity'));
    }

    public function test_admin_login_history_displays_localized_outcome_and_step_labels(): void
    {
        $this->verifiedAdmin();
        $administrator = User::factory()->withRole(UserRole::ADMIN)->create([
            'name' => 'Login History Admin',
        ]);
        AdminLoginEvent::query()->create([
            'user_id' => $administrator->id,
            'email' => $administrator->email,
            'outcome' => 'factor_required',
            'failed_step' => 'totp',
            'created_at' => now(),
        ]);
        AdminLoginEvent::query()->create([
            'user_id' => $administrator->id,
            'email' => $administrator->email,
            'outcome' => 'blocked',
            'failed_step' => 'idle_timeout',
            'created_at' => now()->subMinute(),
        ]);

        $this->get(route('admin.users.show', [
            'userId' => $administrator->id,
            'lang' => 'fr',
        ]))
            ->assertSee(__('messages.admin_users.login_outcomes.factor_required', [], 'fr'))
            ->assertSee(__('messages.admin_users.login_steps.totp', [], 'fr'))
            ->assertSee(__('messages.admin_users.login_outcomes.blocked', [], 'fr'))
            ->assertSee(__('messages.admin_users.login_steps.idle_timeout', [], 'fr'))
            ->assertDontSee('factor_required')
            ->assertDontSee('idle_timeout');
    }

    public function test_pagination_preserves_the_search_query(): void
    {
        $this->verifiedAdmin();

        User::factory()
            ->count(26)
            ->create([
                'name' => 'Pagination Traveler',
                'role' => UserRole::TRAVELER,
            ]);

        $this->get(route('admin.users.travelers', [
            'lang' => 'en',
            'search' => 'Pagination Traveler',
        ]))
            ->assertSee('page=2', false)
            ->assertSee('search=Pagination%20Traveler', false)
            ->assertSee(__('messages.admin_users.results', [
                'from' => 1,
                'to' => 25,
                'total' => 26,
            ]));
    }

    public function test_deactivation_revokes_database_sessions_and_records_an_automatic_reason_and_actor(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->verifiedAdmin();
        $traveler = User::factory()->create();
        Mail::fake();
        DB::table('sessions')->insert([
            'id' => 'traveler-session',
            'user_id' => $traveler->id,
            'ip_address' => '192.0.2.10',
            'user_agent' => 'Feature test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->post(route('admin.users.deactivate', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]));

        $response->assertRedirect(route('admin.users.show', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]));
        $this->assertSoftDeleted($traveler);
        $this->assertDatabaseMissing('sessions', ['id' => 'traveler-session']);
        $this->assertDatabaseHas('admin_user_activities', [
            'user_id' => $traveler->id,
            'actor_id' => $admin->id,
            'action' => 'deactivated',
            'outcome' => 'success',
            'reason' => __('messages.admin_users.automatic_reasons.deactivated', ['name' => $traveler->name]),
        ]);
        Mail::assertSent(AdminAccountSecurityMail::class, fn (AdminAccountSecurityMail $mail): bool => $mail->hasTo($traveler->email)
            && $mail->action === 'account_deactivated');
    }

    public function test_revoking_sessions_keeps_the_account_active_rotates_remember_token_and_sends_security_email(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->verifiedAdmin();
        $traveler = User::factory()->create(['remember_token' => 'original-remember-token']);
        Mail::fake();
        DB::table('sessions')->insert([
            'id' => 'traveler-session-to-revoke',
            'user_id' => $traveler->id,
            'ip_address' => '192.0.2.12',
            'user_agent' => 'Feature test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->post(route('admin.users.revoke-sessions', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]));

        $response->assertRedirect(route('admin.users.show', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]));
        $this->assertNotSoftDeleted($traveler);
        $this->assertDatabaseMissing('sessions', ['id' => 'traveler-session-to-revoke']);
        $this->assertDatabaseHas('admin_user_activities', [
            'user_id' => $traveler->id,
            'actor_id' => $admin->id,
            'action' => 'sessions_revoked',
            'outcome' => 'success',
            'reason' => __('messages.admin_users.automatic_reasons.sessions_revoked', ['name' => $traveler->name]),
        ]);
        $this->assertNotSame('original-remember-token', $traveler->refresh()->getRememberToken());
        Mail::assertSent(AdminAccountSecurityMail::class, fn (AdminAccountSecurityMail $mail): bool => $mail->hasTo($traveler->email)
            && $mail->action === 'sessions_revoked');
    }

    public function test_account_actions_generate_reasons_from_the_action_and_user_name(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->verifiedAdmin();
        $traveler = User::factory()->create(['remember_token' => 'original-remember-token']);
        Mail::fake();
        DB::table('sessions')->insert([
            'id' => 'traveler-session-without-reason',
            'user_id' => $traveler->id,
            'ip_address' => '192.0.2.15',
            'user_agent' => 'Feature test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->post(route('admin.users.revoke-sessions', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]))
            ->assertRedirect(route('admin.users.show', ['userId' => $traveler->id, 'lang' => 'en']));
        $this->assertDatabaseHas('admin_user_activities', [
            'user_id' => $traveler->id,
            'actor_id' => $admin->id,
            'action' => 'sessions_revoked',
            'reason' => __('messages.admin_users.automatic_reasons.sessions_revoked', ['name' => $traveler->name]),
        ]);

        $this->post(route('admin.users.deactivate', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]))
            ->assertRedirect(route('admin.users.show', ['userId' => $traveler->id, 'lang' => 'en']));
        $this->assertDatabaseHas('admin_user_activities', [
            'user_id' => $traveler->id,
            'actor_id' => $admin->id,
            'action' => 'deactivated',
            'reason' => __('messages.admin_users.automatic_reasons.deactivated', ['name' => $traveler->name]),
        ]);

        $this->post(route('admin.users.restore', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]))
            ->assertRedirect(route('admin.users.show', ['userId' => $traveler->id, 'lang' => 'en']));
        $this->assertDatabaseHas('admin_user_activities', [
            'user_id' => $traveler->id,
            'actor_id' => $admin->id,
            'action' => 'restored',
            'reason' => __('messages.admin_users.automatic_reasons.restored', ['name' => $traveler->name]),
        ]);
    }

    public function test_administrator_accounts_cannot_be_deactivated(): void
    {
        $this->verifiedAdmin();
        $targetAdmin = User::factory()->withRole(UserRole::ADMIN)->create();

        $this->post(route('admin.users.deactivate', [
            'userId' => $targetAdmin->id,
            'lang' => 'en',
        ]))
            ->assertForbidden();

        $this->assertModelExists($targetAdmin);
        $this->assertDatabaseMissing('admin_user_activities', [
            'user_id' => $targetAdmin->id,
            'action' => 'deactivated',
        ]);
    }

    public function test_deactivation_is_rejected_without_database_backed_sessions(): void
    {
        config(['session.driver' => 'array']);
        $this->verifiedAdmin();
        $traveler = User::factory()->create();

        $this->post(route('admin.users.deactivate', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]))
            ->assertSessionHasErrors('user');

        $this->assertModelExists($traveler);
        $this->assertDatabaseMissing('admin_user_activities', [
            'user_id' => $traveler->id,
            'action' => 'deactivated',
        ]);
    }

    public function test_session_revocation_is_rejected_without_database_backed_sessions(): void
    {
        config(['session.driver' => 'array']);
        $this->verifiedAdmin();
        $traveler = User::factory()->create(['remember_token' => 'original-remember-token']);
        Mail::fake();

        $this->post(route('admin.users.revoke-sessions', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]))
            ->assertSessionHasErrors('user');

        $this->assertModelExists($traveler);
        $this->assertSame('original-remember-token', $traveler->refresh()->getRememberToken());
        $this->assertDatabaseMissing('admin_user_activities', [
            'user_id' => $traveler->id,
            'action' => 'sessions_revoked',
        ]);
        Mail::assertNothingSent();
    }

    public function test_deactivation_rejects_invalid_date_filter_context_before_changing_the_account(): void
    {
        config(['session.driver' => 'database']);
        $this->verifiedAdmin();
        $traveler = User::factory()->create();

        $this->post(route('admin.users.deactivate', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]), [
            'from' => '2026-05-01',
            'to' => '2026-04-01',
        ])
            ->assertSessionHasErrors('to');

        $this->assertModelExists($traveler);
        $this->assertDatabaseMissing('admin_user_activities', [
            'user_id' => $traveler->id,
            'action' => 'deactivated',
        ]);
    }

    public function test_restoration_preserves_history_and_generates_a_reason_from_the_account_name(): void
    {
        $admin = $this->verifiedAdmin();
        $traveler = User::factory()->create();
        $traveler->delete();
        AdminUserActivity::query()->create([
            'user_id' => $traveler->id,
            'actor_id' => $admin->id,
            'action' => 'deactivated',
            'outcome' => 'success',
            'reason' => 'Previous incident review',
        ]);
        Mail::fake();

        $response = $this->post(route('admin.users.restore', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]));

        $response->assertRedirect(route('admin.users.show', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]))->assertSessionHas('status', __('messages.admin_users.restored_successfully'));
        $this->assertNotSoftDeleted($traveler);
        $this->assertDatabaseHas('admin_user_activities', [
            'user_id' => $traveler->id,
            'action' => 'deactivated',
            'reason' => 'Previous incident review',
        ]);
        $this->assertDatabaseHas('admin_user_activities', [
            'user_id' => $traveler->id,
            'actor_id' => $admin->id,
            'action' => 'restored',
            'reason' => __('messages.admin_users.automatic_reasons.restored', ['name' => $traveler->name]),
        ]);
        Mail::assertSent(AdminAccountSecurityMail::class, fn (AdminAccountSecurityMail $mail): bool => $mail->hasTo($traveler->email)
            && $mail->action === 'account_restored');
    }

    public function test_private_notes_are_recorded_and_rendered_as_escaped_administrator_history(): void
    {
        $admin = $this->verifiedAdmin();
        $traveler = User::factory()->create();
        $note = '<script>alert("private")</script>';

        $this->post(route('admin.users.notes.store', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]), ['note' => $note])
            ->assertRedirect(route('admin.users.show', [
                'userId' => $traveler->id,
                'lang' => 'en',
            ]));

        $this->assertDatabaseHas('admin_user_activities', [
            'user_id' => $traveler->id,
            'actor_id' => $admin->id,
            'action' => 'note_added',
            'note' => $note,
        ]);
        $this->get(route('admin.users.show', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]))
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee($note, false);
    }

    public function test_administrator_can_send_a_hashed_reset_code_to_an_unverified_traveler(): void
    {
        $admin = $this->verifiedAdmin();
        $traveler = User::factory()->unverified()->create();
        Notification::fake();

        $this->post(route('admin.users.password-reset', [
            'userId' => $traveler->id,
            'lang' => 'en',
        ]))
            ->assertRedirect(route('admin.users.show', [
                'userId' => $traveler->id,
                'lang' => 'en',
            ]));

        Notification::assertSentTo($traveler, PasswordResetCode::class, function (PasswordResetCode $notification) use ($traveler): bool {
            $resetCode = DB::table('password_reset_codes')->where('email', $traveler->email)->first();

            return $resetCode !== null
                && preg_match('/^\d{6}$/', $notification->code) === 1
                && Hash::check($notification->code, $resetCode->code_hash);
        });
        $this->assertDatabaseHas('admin_user_activities', [
            'user_id' => $traveler->id,
            'actor_id' => $admin->id,
            'action' => 'password_reset_requested',
            'outcome' => 'success',
        ]);
    }

    public function test_bulk_actions_only_process_selected_accounts_from_the_current_page(): void
    {
        config(['session.driver' => 'database']);
        $this->verifiedAdmin();
        $travelers = User::factory()->count(26)->create([
            'created_at' => '2026-01-01 12:00:00',
            'updated_at' => '2026-01-01 12:00:00',
        ]);
        $firstPageTraveler = $travelers->last();
        $secondPageTraveler = $travelers->first();

        $this->post(route('admin.users.bulk', ['lang' => 'en']), [
            'user_ids' => [$firstPageTraveler->id, $secondPageTraveler->id],
            'operation' => 'deactivate',
            'list' => 'travelers',
            'list_page' => 2,
        ])
            ->assertRedirect(route('admin.users.travelers', ['lang' => 'en', 'page' => 2]))
            ->assertSessionHas('status', __('messages.admin_users.bulk_result', [
                'processed' => 1,
                'failed' => 1,
            ]));

        $this->assertNotSoftDeleted($firstPageTraveler);
        $this->assertSoftDeleted($secondPageTraveler);
        $this->assertDatabaseHas('admin_user_activities', [
            'user_id' => $secondPageTraveler->id,
            'action' => 'deactivated',
            'reason' => __('messages.admin_users.automatic_reasons.deactivated', ['name' => $secondPageTraveler->name]),
        ]);
    }

    public function test_bulk_permanent_deletion_removes_selected_accounts_and_agency_data_and_logs_account_names(): void
    {
        $this->verifiedAdmin();
        $agency = Agency::factory()->create(['company_name' => 'Bulk deletion agency']);
        $owner = $agency->owner;
        $traveler = User::factory()->create(['name' => 'Bulk deletion traveler']);
        Mail::fake();
        Log::spy();

        $this->post(route('admin.users.bulk', ['lang' => 'en']), [
            'user_ids' => [$owner->id, $traveler->id],
            'operation' => 'permanently_delete',
            'list' => 'all_users',
            'list_page' => 1,
        ])
            ->assertRedirect(route('admin.users.index', ['lang' => 'en']))
            ->assertSessionHas('status', __('messages.admin_users.bulk_result', [
                'processed' => 2,
                'failed' => 0,
            ]));

        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseMissing('users', ['id' => $traveler->id]);
        $this->assertDatabaseMissing('agencies', ['id' => $agency->id]);
        Log::shouldHaveReceived('info')
            ->with('An administrator permanently deleted a user account.', Mockery::on(
                fn (array $context): bool => $context['user_id'] === $owner->id
                    && $context['reason'] === __('messages.admin_users.automatic_reasons.permanently_deleted', ['name' => $owner->name]),
            ))
            ->once();
        Log::shouldHaveReceived('info')
            ->with('An administrator permanently deleted a user account.', Mockery::on(
                fn (array $context): bool => $context['user_id'] === $traveler->id
                    && $context['reason'] === __('messages.admin_users.automatic_reasons.permanently_deleted', ['name' => $traveler->name]),
            ))
            ->once();
        Mail::assertSent(AgencyModerationMail::class, fn (AgencyModerationMail $mail): bool => $mail->hasTo($owner->email));
    }

    public function test_bulk_permanent_deletion_skips_accounts_outside_the_current_list_and_protects_administrators(): void
    {
        $this->verifiedAdmin();
        $traveler = User::factory()->create();
        $administrator = User::factory()->withRole(UserRole::ADMIN)->create();

        $this->post(route('admin.users.bulk', ['lang' => 'en']), [
            'user_ids' => [$traveler->id, $administrator->id],
            'operation' => 'permanently_delete',
            'list' => 'travelers',
            'list_page' => 1,
        ])
            ->assertRedirect(route('admin.users.travelers', ['lang' => 'en']))
            ->assertSessionHas('status', __('messages.admin_users.bulk_result', [
                'processed' => 1,
                'failed' => 1,
            ]));

        $this->assertDatabaseMissing('users', ['id' => $traveler->id]);
        $this->assertModelExists($administrator);
    }

    private function verifiedAdmin(): User
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
}
