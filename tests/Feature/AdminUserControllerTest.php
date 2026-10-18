<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\UserRole;
use App\Mail\AdminAccountSecurityMail;
use App\Models\AdminUserActivity;
use App\Models\Agency;
use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
            ->assertSee(__('messages.admin_nav.page_in_preparation', [], 'fr'));
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
            ->assertSee(__('messages.admin_users.statuses.deactivated'));

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

    public function test_deactivation_revokes_database_sessions_and_records_the_reason_and_actor(): void
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
        ]), ['reason' => 'Repeated policy violations']);

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
            'reason' => 'Repeated policy violations',
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
        ]), ['reason' => 'Unexpected sign-in activity']);

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
            'reason' => 'Unexpected sign-in activity',
        ]);
        $this->assertNotSame('original-remember-token', $traveler->refresh()->getRememberToken());
        Mail::assertSent(AdminAccountSecurityMail::class, fn (AdminAccountSecurityMail $mail): bool => $mail->hasTo($traveler->email)
            && $mail->action === 'sessions_revoked');
    }

    public function test_administrator_accounts_cannot_be_deactivated(): void
    {
        $this->verifiedAdmin();
        $targetAdmin = User::factory()->withRole(UserRole::ADMIN)->create();

        $this->post(route('admin.users.deactivate', [
            'userId' => $targetAdmin->id,
            'lang' => 'en',
        ]), ['reason' => 'Routine account review'])
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
        ]), ['reason' => 'Repeated policy violations'])
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
        ]), ['reason' => 'Unexpected sign-in activity'])
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
            'reason' => 'Repeated policy violations',
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

    public function test_restoration_preserves_previous_history_and_records_a_new_reason(): void
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
        ]), ['reason' => 'Issue was reviewed and resolved']);

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
            'reason' => 'Issue was reviewed and resolved',
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
            'reason' => 'Bulk policy review',
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
    }

    public function test_csv_export_protects_spreadsheet_formulas_and_applies_filters(): void
    {
        $this->verifiedAdmin();
        $formulaTraveler = User::factory()->create([
            'name' => '=SUM(1,1)',
            'role' => UserRole::TRAVELER,
        ]);
        $otherTraveler = User::factory()->create([
            'name' => 'Not in export',
            'role' => UserRole::TRAVELER,
        ]);
        User::factory()->withRole(UserRole::ADMIN)->create([
            'name' => 'Administrator not included',
        ]);

        $response = $this->get(route('admin.users.export', [
            'lang' => 'en',
            'list' => 'all_users',
            'search' => 'SUM',
        ]));
        $csv = $response->streamedContent();

        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString("'=SUM(1,1)", $csv);
        $this->assertStringNotContainsString($otherTraveler->email, $csv);
        $this->assertStringNotContainsString('Administrator not included', $csv);
        $this->assertStringContainsString((string) $formulaTraveler->id, $csv);
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
