<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\UserRole;
use App\Mail\AdminLoginCodeMail;
use App\Mail\AdminSecurityAlertMail;
use App\Models\AdminLoginChallenge;
use App\Models\Agency;
use App\Models\AgencyDocument;
use App\Models\Country;
use App\Models\Inquiry;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgencyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_traveler_login_redirects_to_the_traveler_dashboard(): void
    {
        $traveler = User::factory()->create([
            'email' => 'traveler-login@example.com',
            'role' => UserRole::TRAVELER,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $traveler->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('auth.success'));
        $response->assertSessionHas('success_redirect', route('dashboard'));
    }

    public function test_an_agency_owner_login_redirects_to_the_agency_dashboard(): void
    {
        $agency = Agency::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $agency->owner->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('auth.success'));
        $response->assertSessionHas('success_redirect', route('agency.dashboard'));
    }

    public function test_admin_is_not_authenticated_until_email_code_is_verified(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create([
            'email' => 'admin-login@example.com',
        ]);
        $originalRememberToken = $admin->remember_token;
        Mail::fake();

        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.17'])
            ->post(route('login.store'), [
                'email' => $admin->email,
                'password' => 'password',
                'remember' => '1',
            ]);

        $response->assertRedirect(route('admin.login.verify', ['lang' => 'en']));
        $this->assertGuest();
        $response->assertSessionHas('pending_admin_login.user_id', $admin->id);
        $this->assertDatabaseHas('admin_login_challenges', [
            'user_id' => $admin->id,
            'attempts' => 0,
            'consumed_at' => null,
        ]);
        $this->assertDatabaseHas('admin_login_events', [
            'user_id' => $admin->id,
            'outcome' => 'code_sent',
        ]);
        $this->assertNotSame($originalRememberToken, $admin->fresh()->remember_token);
        $code = null;
        Mail::assertSent(AdminLoginCodeMail::class, function (AdminLoginCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });
        Mail::assertSent(AdminSecurityAlertMail::class, fn (AdminSecurityAlertMail $mail): bool => $mail->ipAddress === '198.51.100.17' && ! str_contains($mail->alertMessage, (string) $code));
    }

    public function test_wrong_password_uses_the_same_generic_response_and_records_admin_failure(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create(['email' => 'admin-wrong@example.com']);
        $traveler = User::factory()->create(['email' => 'traveler-wrong@example.com']);
        $attempts = [
            $admin->email,
            $traveler->email,
            'unknown-wrong@example.com',
        ];

        foreach ($attempts as $email) {
            $this->from(route('login'))->post(route('login.store'), [
                'email' => $email,
                'password' => 'incorrect-password',
            ])->assertRedirect(route('login'))
                ->assertSessionHasErrors(['email' => __('messages.credentials_invalid')]);
        }

        $this->assertDatabaseHas('admin_login_events', [
            'user_id' => $admin->id,
            'outcome' => 'password_failed',
            'failed_step' => 'password',
        ]);
    }

    public function test_admin_email_code_verification_authenticates_and_opens_placeholder_dashboard(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create([
            'email' => 'admin-verified@example.com',
        ]);
        Mail::fake();

        $this->post(route('login.store', ['lang' => 'en']), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.login.verify', ['lang' => 'en']));

        $this->assertGuest();
        $code = null;
        Mail::assertSent(AdminLoginCodeMail::class, function (AdminLoginCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        $response = $this->post(route('admin.login.verify.submit', ['lang' => 'en']), ['code' => $code]);

        $response->assertRedirect(route('admin.totp.setup'));
        $response->assertCookie(config('admin.trusted_device_cookie'));
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('admin_login_challenges', [
            'user_id' => $admin->id,
            'consumed_at' => now(),
        ]);

        $this->get(route('admin.dashboard', ['lang' => 'en']))
            ->assertOk()
            ->assertSee(__('messages.admin_dashboard_coming_soon'))
            ->assertSee($admin->name)
            ->assertSee(route('admin.logout', ['lang' => 'en']));
    }

    public function test_successful_admin_login_from_an_unrecognized_device_sends_a_security_alert(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        Mail::fake();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.41'])
            ->post(route('login.store'), [
                'email' => $admin->email,
                'password' => 'password',
            ]);

        $code = null;
        Mail::assertSent(AdminLoginCodeMail::class, function (AdminLoginCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.41'])
            ->post(route('admin.login.verify.submit'), ['code' => $code])
            ->assertRedirect(route('admin.totp.setup'));

        Mail::assertSent(AdminSecurityAlertMail::class, fn (AdminSecurityAlertMail $mail): bool => $mail->ipAddress === '203.0.113.41'
            && $mail->alertMessage === __('messages.admin_alert_login_new_device'));
        $this->assertDatabaseHas('admin_login_events', [
            'user_id' => $admin->id,
            'ip_address' => '203.0.113.41',
            'outcome' => 'success',
            'failed_step' => 'complete',
        ]);
    }

    public function test_failed_admin_login_burst_alert_is_throttled_during_the_cooldown(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        Mail::fake();
        config(['admin.failed_alert_threshold' => 2]);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.80'])
                ->from(route('login'))
                ->post(route('login.store'), [
                    'email' => $admin->email,
                    'password' => 'incorrect-password',
                ])
                ->assertSessionHasErrors('email');
        }

        Mail::assertSent(AdminSecurityAlertMail::class, fn (AdminSecurityAlertMail $mail): bool => $mail->alertMessage === __('messages.admin_alert_failed_burst'));
        Mail::assertSentCount(1);
        $this->assertDatabaseCount('admin_login_events', 4);
    }

    public function test_five_failed_admin_password_attempts_lock_the_account_and_alert_the_admin(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        Mail::fake();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])
                ->from(route('login'))
                ->post(route('login.store'), [
                    'email' => $admin->email,
                    'password' => 'incorrect-password',
                ])
                ->assertSessionHasErrors(['email' => __('messages.credentials_invalid')]);
        }

        Mail::assertSent(AdminSecurityAlertMail::class, fn (AdminSecurityAlertMail $mail): bool => $mail->alertMessage === __('messages.admin_alert_password_lockout', ['attempts' => 5, 'minutes' => 15]));
        Mail::assertSentCount(1);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.200'])
            ->from(route('login'))
            ->post(route('login.store'), [
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->assertSessionHasErrors(['email' => __('messages.credentials_invalid')]);

        $this->assertGuest();
        $this->assertDatabaseMissing('admin_login_challenges', ['user_id' => $admin->id]);
        $this->assertDatabaseHas('admin_login_events', [
            'user_id' => $admin->id,
            'outcome' => 'blocked',
            'failed_step' => 'password_lockout',
        ]);

        $this->travel((int) config('admin.password_lockout_minutes') + 1)->minutes();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.200'])
            ->post(route('login.store'), [
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.login.verify', ['lang' => 'en']));
        Mail::assertSent(AdminLoginCodeMail::class);
        $this->travelBack();
    }

    public function test_guests_are_redirected_to_shared_login_for_every_admin_route(): void
    {
        $routes = [
            ['GET', route('admin.login.verify')],
            ['POST', route('admin.login.verify.submit')],
            ['POST', route('admin.login.resend')],
            ['GET', route('admin.totp.setup')],
            ['POST', route('admin.totp.confirm')],
            ['POST', route('admin.totp.skip')],
            ['GET', route('admin.dashboard')],
            ['POST', route('admin.logout')],
        ];

        foreach ($routes as [$method, $url]) {
            $response = $method === 'GET' ? $this->get($url) : $this->post($url, []);
            $response->assertRedirect(route('login'));
        }
    }

    public function test_travelers_and_agency_owners_are_forbidden_from_every_admin_route(): void
    {
        $traveler = User::factory()->create(['role' => UserRole::TRAVELER]);
        $agencyOwner = Agency::factory()->create()->owner;
        $routes = [
            ['GET', route('admin.login.verify')],
            ['POST', route('admin.login.verify.submit')],
            ['POST', route('admin.login.resend')],
            ['GET', route('admin.totp.setup')],
            ['POST', route('admin.totp.confirm')],
            ['POST', route('admin.totp.skip')],
            ['GET', route('admin.dashboard')],
            ['POST', route('admin.logout')],
        ];
        $requestNumber = 1;

        foreach ([$traveler, $agencyOwner] as $user) {
            foreach ($routes as [$method, $url]) {
                $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$requestNumber]);
                $response = $method === 'GET'
                    ? $this->actingAs($user)->get($url)
                    : $this->actingAs($user)->post($url, []);
                $response->assertForbidden();
                $requestNumber++;
            }
        }
    }

    public function test_wrong_admin_code_is_counted_and_fifth_attempt_invalidates_challenge(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        Mail::fake();

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('admin.login.verify.submit'), ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $challenge = AdminLoginChallenge::query()->where('user_id', $admin->id)->firstOrFail();
        $this->assertSame(5, $challenge->attempts);
        $this->assertNotNull($challenge->invalidated_at);
        $this->assertGuest();
    }

    public function test_expired_and_consumed_admin_codes_cannot_be_verified(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        Mail::fake();

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $code = null;
        Mail::assertSent(AdminLoginCodeMail::class, function (AdminLoginCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });
        $challenge = AdminLoginChallenge::query()->where('user_id', $admin->id)->firstOrFail();

        $this->travel(61)->seconds();
        $this->post(route('admin.login.verify.submit'), ['code' => $code])->assertSessionHasErrors('code');
        $this->travelBack();

        $this->assertNull($challenge->fresh()->consumed_at);

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $freshCode = null;
        Mail::assertSent(AdminLoginCodeMail::class, function (AdminLoginCodeMail $mail) use (&$freshCode): bool {
            $freshCode = $mail->code;

            return true;
        });
        $freshChallenge = AdminLoginChallenge::query()->where('user_id', $admin->id)->latest('id')->firstOrFail();

        $this->post(route('admin.login.verify.submit'), ['code' => $freshCode])
            ->assertRedirect(route('admin.totp.setup'));
        $freshChallenge->refresh();
        $this->assertNotNull($freshChallenge->consumed_at);
        $this->assertFalse(app(AdminSecondFactor::class)->verify($admin, $freshChallenge, $freshCode));
    }

    public function test_idle_admin_session_is_logged_out_and_audited(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        Mail::fake();

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $code = null;
        Mail::assertSent(AdminLoginCodeMail::class, function (AdminLoginCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });
        $this->post(route('admin.login.verify.submit'), ['code' => $code])
            ->assertRedirect(route('admin.totp.setup'));

        $this->travel((int) config('admin.idle_timeout_minutes') + 1)->minutes();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->travelBack();

        $this->assertGuest();
        $this->assertDatabaseHas('admin_login_events', [
            'user_id' => $admin->id,
            'outcome' => 'blocked',
            'failed_step' => 'idle_timeout',
        ]);
    }

    public function test_admin_code_resend_is_throttled(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        Mail::fake();

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->post(route('admin.login.resend'))->assertRedirect();
        $this->post(route('admin.login.resend'))->assertStatus(429);
    }

    public function test_admin_code_resend_does_not_reset_failed_attempts(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        Mail::fake();

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $this->post(route('admin.login.verify.submit'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertSame(1, AdminLoginChallenge::query()->where('user_id', $admin->id)->value('attempts'));

        $this->post(route('admin.login.resend'))->assertRedirect();

        $this->assertSame(1, AdminLoginChallenge::query()->where('user_id', $admin->id)->value('attempts'));
    }

    public function test_user_role_cannot_be_changed_through_mass_assignment(): void
    {
        $traveler = User::factory()->create(['role' => UserRole::TRAVELER]);

        $traveler->fill(['role' => UserRole::ADMIN])->save();

        $this->assertSame(UserRole::TRAVELER, $traveler->fresh()->role);
    }

    public function test_an_agency_owner_can_view_their_dashboard(): void
    {
        $agency = Agency::factory()->create(['company_name' => 'North Star Travel']);
        $traveler = User::factory()->create(['role' => 'traveler']);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'user_id' => $traveler->id, 'status' => 'open']);
        Review::factory()->create(['agency_id' => $agency->id, 'user_id' => $traveler->id, 'status' => 'published', 'rating' => 5]);

        $response = $this->actingAs($agency->owner)->get(route('agency.dashboard'));

        $response->assertOk();
        $response->assertSee('North Star Travel');
        $response->assertSee(__('messages.agency_dashboard_intro'));
        $response->assertSee('Open inquiries');
        $response->assertSee('1');
    }

    public function test_agency_dashboard_shows_only_a_compact_latest_inquiry_preview(): void
    {
        $agency = Agency::factory()->create(['company_name' => 'North Star Travel']);
        $olderTraveler = User::factory()->create(['name' => 'Amina T', 'role' => 'traveler']);
        $latestTraveler = User::factory()->create(['name' => 'Oberline Kamcheim', 'role' => 'traveler']);

        Inquiry::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $olderTraveler->id,
            'subject' => 'Need a 7-day trip',
            'status' => 'open',
            'created_at' => now()->subDay(),
        ]);
        Inquiry::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $latestTraveler->id,
            'subject' => 'Second inquiry',
            'status' => 'open',
            'created_at' => now()->subHours(3),
        ]);

        $response = $this->actingAs($agency->owner)->get(route('agency.dashboard'));

        $response->assertOk();
        $response->assertSee('Latest inquiry');
        $response->assertSee('Oberline Kamcheim');
        $response->assertDontSee('Need a 7-day trip');
        $response->assertDontSee('Second inquiry');
        $response->assertSee(route('agency.inquiries.index', ['lang' => 'en']));
    }

    public function test_agency_dashboard_uses_a_persistent_workspace_shell(): void
    {
        $agency = Agency::factory()->create(['company_name' => 'North Star Travel']);

        $response = $this->actingAs($agency->owner)->get(route('agency.dashboard'));

        $response->assertOk();
        $response->assertSee('data-agency-shell', false);
        $response->assertSee('id="agency-main-content"', false);
        $response->assertSee('data-agency-sidebar', false);
        $response->assertSee('data-agency-topbar', false);
        $response->assertSee('data-agency-search', false);
        $response->assertSee('data-agency-search-submit', false);
        $response->assertSee('data-agency-language-switcher class="flex shrink-0', false);
        $response->assertSee('>EN</a>', false);
        $response->assertSee('>FR</a>', false);
        $response->assertSee('title="'.__('messages.overview').'"', false);
        $response->assertDontSee('data-agency-theme class=', false);
    }

    public function test_agency_inquiry_statuses_show_distinct_status_colors_and_close_notice(): void
    {
        $agency = Agency::factory()->create(['company_name' => 'North Star Travel']);
        $traveler = User::factory()->create(['role' => 'traveler', 'name' => 'Amina Traveler']);

        Inquiry::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $traveler->id,
            'status' => 'open',
            'subject' => 'Open request',
            'message' => 'Need a quote for a city break.',
        ]);
        Inquiry::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $traveler->id,
            'status' => 'responded',
            'subject' => 'Responded request',
            'message' => 'Already answered by the agency.',
            'reply_message' => 'We can organise a package for you.',
        ]);
        Inquiry::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $traveler->id,
            'status' => 'closed',
            'subject' => 'Closed request',
            'message' => 'This one is resolved.',
        ]);

        $response = $this->actingAs($agency->owner)->get(route('agency.inquiries.index', ['lang' => 'en']));

        $response->assertOk();
        $response->assertSee('agency-pill-accent', false);
        $response->assertSee('agency-pill-warning', false);
        $response->assertSee('agency-pill-success', false);
        $response->assertSee('data-inquiry-delete-icon', false);

        $closeResponse = $this->actingAs($agency->owner)->post(route('agency.inquiries.close', ['inquiry' => $agency->inquiries()->first()->id, 'lang' => 'en']));
        $closeResponse->assertSessionHas('inquiry_close_status', 'This inquiry was marked as closed and will disappear from the list after the next refresh.');
    }

    public function test_agency_owner_can_delete_a_closed_inquiry_so_it_disappears_from_the_dashboard(): void
    {
        $agency = Agency::factory()->create(['company_name' => 'North Star Travel']);
        $traveler = User::factory()->create(['role' => 'traveler', 'name' => 'Amina Traveler']);
        $inquiry = Inquiry::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $traveler->id,
            'status' => 'closed',
            'subject' => 'Closed request to remove',
            'message' => 'This inquiry is resolved and should be removable.',
        ]);

        $response = $this->actingAs($agency->owner)->delete(route('agency.inquiries.destroy', ['inquiry' => $inquiry->id, 'lang' => 'en']));

        $response->assertRedirect(route('agency.inquiries.index', ['lang' => 'en']))
            ->assertSessionHas('inquiry_delete_status', 'The closed inquiry was deleted.');
        $this->assertDatabaseMissing('inquiries', ['id' => $inquiry->id]);

        $dashboard = $this->actingAs($agency->owner)->get(route('agency.dashboard', ['lang' => 'en']));
        $dashboard->assertOk();
        $dashboard->assertDontSee('Closed request to remove');
    }

    public function test_agency_notification_bell_keeps_the_selected_french_locale_and_keeps_profile_in_the_sidebar(): void
    {
        $agency = Agency::factory()->create(['company_name' => 'North Star Travel']);
        $profileUrl = route('agency.profile', ['lang' => 'fr']);

        $dashboard = $this->actingAs($agency->owner)->get(route('agency.dashboard', ['lang' => 'fr']));

        $dashboard->assertOk();
        $dashboard->assertSee('aria-label="'.__('messages.notifications', [], 'fr').'"', false);
        $dashboard->assertDontSee('data-agency-header-profile', false);
        $dashboard->assertSee('href="'.$profileUrl.'"', false);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')->get($profileUrl)
            ->assertOk()
            ->assertSee('id="agency-main-content"', false)
            ->assertDontSee('data-agency-header-profile', false)
            ->assertSee(__('messages.agency_profile', [], 'fr'))
            ->assertSee('data-agency-nav-link', false);
    }

    public function test_agency_shell_shows_the_real_logo_name_and_verification_state(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('agency-logos/atlas.png', UploadedFile::fake()->image('atlas.png')->get());
        $agency = Agency::factory()->create([
            'company_name' => 'Atlas Horizon Voyages',
            'logo_path' => 'agency-logos/atlas.png',
            'verification_status' => 'verified',
        ]);

        $response = $this->actingAs($agency->owner)->get(route('agency.dashboard'));

        $response->assertOk();
        $response->assertSee('storage/agency-logos/atlas.png');
        $response->assertSee('Atlas Horizon Voyages');
        $response->assertSee(__('messages.agency_verification_status_verified'));
        $response->assertDontSee(__('messages.public_profile'));
    }

    public function test_agency_dashboard_sidebar_uses_the_uploaded_agency_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('agency-logos/emma.png', UploadedFile::fake()->image('emma.png')->get());
        $agency = Agency::factory()->create([
            'company_name' => 'Emma Travel',
            'logo_path' => 'agency-logos/emma.png',
        ]);

        $response = $this->actingAs($agency->owner)->get(route('agency.dashboard'));

        $response->assertOk();
        $response->assertSee('data-agency-brand', false);
        $response->assertSee('<div data-agency-brand', false);
        $response->assertDontSee('<a data-agency-brand', false);
        $response->assertSee('storage/agency-logos/emma.png');
        $response->assertSee('Emma Travel');
    }

    public function test_agency_profile_uses_a_searchable_destination_picker_and_agency_logo_in_sidebar_footer(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('agency-logos/emma.png', UploadedFile::fake()->image('emma.png')->get());
        Storage::disk('public')->put('profile-photos/user.png', UploadedFile::fake()->image('user.png')->get());

        $agency = Agency::factory()->create([
            'company_name' => 'Emma Travel',
            'logo_path' => 'agency-logos/emma.png',
        ]);

        $agency->owner->update(['profile_photo_path' => 'profile-photos/user.png']);

        $response = $this->actingAs($agency->owner)->get(route('agency.profile'));

        $response->assertOk();
        $response->assertSee('data-destination-filter', false);
        $response->assertSee('storage/agency-logos/emma.png');
        $response->assertDontSee('storage/profile-photos/user.png');
    }

    public function test_a_traveler_is_redirected_away_from_the_agency_dashboard(): void
    {
        $traveler = User::factory()->create(['role' => 'traveler']);

        $response = $this->actingAs($traveler)->get(route('agency.dashboard'));

        $response->assertRedirect(route('dashboard', ['lang' => 'en']));
    }

    public function test_an_agency_owner_only_sees_their_own_agency_data(): void
    {
        $ownedAgency = Agency::factory()->create(['company_name' => 'Owned Travel']);
        $otherAgency = Agency::factory()->create(['company_name' => 'Other Travel']);
        $traveler = User::factory()->create(['role' => 'traveler', 'name' => 'Amina Traveler']);
        Inquiry::factory()->create(['agency_id' => $ownedAgency->id, 'user_id' => $traveler->id, 'subject' => 'Owned inquiry', 'created_at' => now()->subDay()]);
        Inquiry::factory()->create(['agency_id' => $otherAgency->id, 'user_id' => $traveler->id, 'subject' => 'Other inquiry', 'created_at' => now()->subHours(2)]);

        $response = $this->actingAs($ownedAgency->owner)->get(route('agency.dashboard'));

        $response->assertOk();
        $response->assertSee('Latest inquiry');
        $response->assertSee('Amina Traveler');
        $response->assertDontSee('Owned inquiry');
        $response->assertDontSee('Other inquiry');
        $response->assertSee('Owned Travel');
        $response->assertDontSee('Other Travel');
    }

    public function test_an_agency_owner_can_update_their_profile_and_public_profile_uses_it(): void
    {
        Storage::fake('public');
        $agency = Agency::factory()->create(['company_name' => 'Summit & Sea Travel', 'description' => 'Old description']);
        $country = Country::factory()->create(['name' => 'Japan']);
        $service = Service::factory()->create(['name' => 'Rail journeys']);

        $response = $this->actingAs($agency->owner)->put(route('agency.settings.update'), [
            'name' => 'Amina Cole',
            'email' => $agency->owner->email,
            'phone' => '+237 600 000 000',
            'company_name' => 'Summit & Sea Travel',
            'description' => 'Journeys planned around thoughtful local knowledge.',
            'license_number' => $agency->license_number,
            'address' => 'Douala',
            'agency_phone' => '+237 699 000 000',
            'agency_email' => 'hello@summit.test',
            'website' => 'https://summit.test',
            'countries' => [$country->id],
            'services' => [$service->id],
            'logo' => UploadedFile::fake()->image('logo.jpg'),
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $response->assertRedirect();
        $agency->refresh();
        $this->assertSame('Journeys planned around thoughtful local knowledge.', $agency->description);
        $this->assertNotNull($agency->logo_path);
        $this->assertNotNull($agency->cover_image_path);
        $this->assertTrue(Storage::disk('public')->exists($agency->logo_path));
        $this->assertTrue(Storage::disk('public')->exists($agency->cover_image_path));
        $logoUrl = $agency->logoUrl();
        $coverUrl = $agency->coverImageUrl();
        $this->assertStringStartsWith(Storage::disk('public')->url($agency->logo_path).'?v=', $logoUrl);
        $this->assertStringStartsWith(Storage::disk('public')->url($agency->cover_image_path).'?v=', $coverUrl);

        $profile = $this->actingAs($agency->owner)->get(route('agency.profile'));
        $profile->assertOk()->assertSee('src="'.$logoUrl.'"', false)->assertSee('src="'.$coverUrl.'"', false);

        $public = $this->actingAs($agency->owner)->get(route('agency.show', ['slug' => 'summit-sea-travel']));
        $public->assertOk()->assertSee('Journeys planned around thoughtful local knowledge.');
        $public->assertSee('src="'.$coverUrl.'"', false);
        $public->assertSee('Japan');
        $public->assertSee('Rail journeys');
    }

    public function test_an_agency_owner_can_update_a_new_profile_with_blank_optional_phone_numbers(): void
    {
        $agency = Agency::factory()->create(['phone' => null]);
        $agency->owner->update(['phone' => null]);

        $profile = $this->actingAs($agency->owner)->get(route('agency.profile'));

        $profile->assertOk()
            ->assertSee('name="phone" value=""', false)
            ->assertSee('name="agency_phone" value=""', false);

        $response = $this->actingAs($agency->owner)->put(route('agency.settings.update'), [
            'name' => $agency->owner->name,
            'email' => $agency->owner->email,
            'company_name' => $agency->company_name,
            'description' => 'This new agency profile can now be completed.',
            'license_number' => '',
            'address' => '',
            'phone' => '',
            'agency_phone' => '',
            'agency_email' => '',
            'website' => '',
        ]);

        $response->assertRedirect();
        $this->assertSame('This new agency profile can now be completed.', $agency->fresh()->description);
    }

    public function test_an_agency_owner_can_update_their_profile_in_french(): void
    {
        $agency = Agency::factory()->create(['verification_status' => 'pending']);
        $country = Country::factory()->create(['name' => 'Cameroon']);

        $response = $this->actingAs($agency->owner)
            ->put(route('agency.settings.update', ['lang' => 'fr']), [
                'name' => $agency->owner->name,
                'email' => $agency->owner->email,
                'phone' => '+237 600 000 000',
                'company_name' => 'Voyages du Cameroun',
                'description' => 'Des voyages préparés avec soin.',
                'agency_phone' => '+237 699 000 000',
                'countries' => [$country->id],
            ]);

        $response->assertRedirect(route('agency.profile', ['lang' => 'fr']))
            ->assertSessionHas('status', __('messages.agency_profile_updated', [], 'fr'));
        $this->assertDatabaseHas('agencies', [
            'id' => $agency->id,
            'company_name' => 'Voyages du Cameroun',
            'phone' => '+237 699 000 000',
        ]);
        $this->assertDatabaseHas('agency_country', [
            'agency_id' => $agency->id,
            'country_id' => $country->id,
        ]);
    }

    public function test_french_profile_validation_errors_are_visible_and_keep_selected_destinations(): void
    {
        $agency = Agency::factory()->create(['company_name' => 'Original Agency']);
        $country = Country::factory()->create(['name' => 'Cameroon']);

        $response = $this->followingRedirects()->actingAs($agency->owner)
            ->from(route('agency.profile', ['lang' => 'fr']))
            ->put(route('agency.settings.update', ['lang' => 'fr']), [
                'name' => $agency->owner->name,
                'email' => $agency->owner->email,
                'phone' => '+237 600 000 0000',
                'company_name' => 'Updated Agency',
                'agency_phone' => '+237 699 000 000',
                'countries' => [$country->id],
            ]);

        $response->assertSee(__('messages.profile_validation_errors_title', [], 'fr'))
            ->assertSee(__('messages.cameroon_phone_invalid', [], 'fr'))
            ->assertSee('<details data-destination-picker', false)
            ->assertSee('name="countries[]" value="'.$country->id.'" checked', false);
        $this->assertDatabaseHas('agencies', [
            'id' => $agency->id,
            'company_name' => 'Original Agency',
        ]);
    }

    public function test_an_agency_owner_can_upload_a_document_and_publish_an_experience(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $agency = Agency::factory()->create();

        $document = $this->actingAs($agency->owner)->post(route('agency.documents.store'), [
            'type' => 'license',
            'document' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
        ]);
        $document->assertRedirect();

        $experience = $this->actingAs($agency->owner)->post(route('agency.experiences.store'), [
            'image' => UploadedFile::fake()->image('tokyo.jpg'),
            'destination' => 'Tokyo',
            'caption' => 'A city journey shaped by local insight.',
            'alt_text' => 'Travelers exploring Tokyo',
        ]);
        $experience->assertRedirect();

        $this->assertDatabaseHas('agency_documents', ['agency_id' => $agency->id, 'type' => 'license', 'status' => 'pending']);
        $this->assertDatabaseHas('agency_experiences', ['agency_id' => $agency->id, 'destination' => 'Tokyo']);

        $agencyProfile = $this->actingAs($agency->owner)->get(route('agency.show', ['slug' => Str::slug($agency->company_name), 'lang' => 'en']));
        $agencyProfile->assertOk()->assertSee('data-experience-delete-icon', false);

        $travelerProfile = $this->actingAs(User::factory()->create(['role' => 'traveler']))
            ->get(route('agency.show', ['slug' => Str::slug($agency->company_name), 'lang' => 'en']));
        $travelerProfile->assertOk()->assertDontSee('data-experience-delete-icon', false);
    }

    public function test_an_agency_owner_can_delete_their_own_travel_experience(): void
    {
        Storage::fake('public');
        $agency = Agency::factory()->create();
        $imagePath = 'agency-experiences/tokyo.jpg';
        Storage::disk('public')->put($imagePath, 'experience-image');
        $experience = $agency->experiences()->create([
            'image_path' => $imagePath,
            'destination' => 'Tokyo',
            'caption' => 'City lights at dusk',
            'alt_text' => 'Tokyo city lights',
            'published_at' => now(),
        ]);

        $response = $this->actingAs($agency->owner)->delete(route('agency.experiences.destroy', [
            'experience' => $experience->id,
            'lang' => 'en',
        ]));

        $response->assertRedirect(route('agency.show', [
            'slug' => Str::slug($agency->company_name),
            'lang' => 'en',
        ]));
        $this->assertDatabaseMissing('agency_experiences', ['id' => $experience->id]);
        Storage::disk('public')->assertMissing($imagePath);
    }

    public function test_an_agency_owner_cannot_delete_another_agencys_travel_experience(): void
    {
        Storage::fake('public');
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $imagePath = 'agency-experiences/other-photo.jpg';
        Storage::disk('public')->put($imagePath, 'experience-image');
        $experience = $otherAgency->experiences()->create([
            'image_path' => $imagePath,
            'destination' => 'Paris',
            'caption' => 'City visit',
            'alt_text' => 'Paris city',
            'published_at' => now(),
        ]);

        $response = $this->actingAs($agency->owner)->delete(route('agency.experiences.destroy', [
            'experience' => $experience->id,
            'lang' => 'en',
        ]));

        $response->assertForbidden();
        $this->assertDatabaseHas('agency_experiences', ['id' => $experience->id]);
        Storage::disk('public')->assertExists($imagePath);
    }

    public function test_verified_agency_scope_requires_approved_documents(): void
    {
        $agency = Agency::factory()->create(['verification_status' => 'verified']);

        $this->assertFalse(Agency::verified()->whereKey($agency->id)->exists());

        AgencyDocument::factory()->create([
            'agency_id' => $agency->id,
            'status' => 'approved',
            'uploaded_at' => now(),
            'file_path' => 'agency-documents/license-approved.pdf',
        ]);

        $this->assertTrue(Agency::verified()->whereKey($agency->id)->exists());
    }

    public function test_agency_phone_requires_cameroon_prefix(): void
    {
        Storage::fake('public');
        $agency = Agency::factory()->create();

        $invalid = $this->actingAs($agency->owner)->put(route('agency.settings.update'), [
            'name' => $agency->owner->name,
            'email' => $agency->owner->email,
            'company_name' => $agency->company_name,
            'phone' => '+237 600 000 0000',
            'agency_phone' => '699000000',
        ]);
        $invalid->assertSessionHasErrors(['phone', 'agency_phone']);

        $valid = $this->actingAs($agency->owner)->put(route('agency.settings.update'), [
            'name' => $agency->owner->name,
            'email' => $agency->owner->email,
            'company_name' => $agency->company_name,
            'agency_phone' => '+237 699 000 000',
        ]);
        $valid->assertRedirect(route('agency.profile', ['lang' => 'en']));
        $this->assertSame('+237 699 000 000', $agency->refresh()->phone);
    }

    public function test_agency_owner_can_replace_logo_and_remove_cover_without_orphaning_files(): void
    {
        Storage::fake('public');
        $oldLogo = UploadedFile::fake()->image('old-logo.jpg')->store('agency-logos/old', 'public');
        $oldCover = UploadedFile::fake()->image('old-cover.jpg')->store('agency-covers/old', 'public');
        $agency = Agency::factory()->create(['logo_path' => $oldLogo, 'cover_image_path' => $oldCover]);

        $response = $this->actingAs($agency->owner)->put(route('agency.settings.update'), [
            'name' => $agency->owner->name,
            'email' => $agency->owner->email,
            'company_name' => $agency->company_name,
            'logo' => UploadedFile::fake()->image('new-logo.webp'),
            'remove_cover_image' => '1',
        ]);

        $response->assertRedirect();
        $agency->refresh();
        $this->assertNotSame($oldLogo, $agency->logo_path);
        $this->assertTrue(Storage::disk('public')->exists($agency->logo_path));
        $this->assertFalse(Storage::disk('public')->exists($oldLogo));
        $this->assertNull($agency->cover_image_path);
        $this->assertFalse(Storage::disk('public')->exists($oldCover));
        $this->assertStringStartsWith(Storage::disk('public')->url($agency->logo_path).'?v=', $agency->logoUrl());
        $this->assertNull($agency->coverImageUrl());
    }

    public function test_agency_owner_can_update_or_delete_a_recent_verification_document_within_two_minutes_only(): void
    {
        Storage::fake('public');
        $agency = Agency::factory()->create();
        $document = AgencyDocument::factory()->create([
            'agency_id' => $agency->id,
            'status' => 'pending',
            'uploaded_at' => now(),
            'file_path' => 'agency-documents/original.pdf',
        ]);

        $this->actingAs($agency->owner)
            ->get(route('agency.profile', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('data-document-delete-icon', false);

        $update = $this->actingAs($agency->owner)->put(route('agency.documents.update', ['document' => $document->id]), [
            'document' => UploadedFile::fake()->create('replacement.pdf', 120, 'application/pdf'),
        ]);
        $update->assertRedirect(route('agency.profile', ['lang' => 'en']));
        $document->refresh();
        $this->assertNotSame('agency-documents/original.pdf', $document->file_path);

        $delete = $this->actingAs($agency->owner)->delete(route('agency.documents.destroy', ['document' => $document->id]));
        $delete->assertRedirect(route('agency.profile', ['lang' => 'en']));
        $this->assertDatabaseMissing('agency_documents', ['id' => $document->id]);

        $lockedDocument = AgencyDocument::factory()->create([
            'agency_id' => $agency->id,
            'status' => 'pending',
            'uploaded_at' => now()->subMinutes(3),
            'file_path' => 'agency-documents/locked.pdf',
        ]);

        $blockedUpdate = $this->actingAs($agency->owner)->put(route('agency.documents.update', ['document' => $lockedDocument->id]), [
            'document' => UploadedFile::fake()->create('locked-update.pdf', 120, 'application/pdf'),
        ]);
        $blockedUpdate->assertSessionHasErrors('document');
    }
}
