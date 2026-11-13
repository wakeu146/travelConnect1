<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\DocumentStatus;
use App\Enums\PackageStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Mail\AgencyModerationMail;
use App\Models\Agency;
use App\Models\AgencyDocument;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Inquiry;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\Review;
use App\Models\TrustScoreLog;
use App\Models\User;
use App\Notifications\AdminAgencyDocumentReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class AdminAgencyManagementTest extends TestCase
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

    public function test_platform_management_opens_a_hub_with_a_separate_agency_directory_link(): void
    {
        $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create(['company_name' => 'North Star Travel']);

        $this->get(route('admin.dashboard.section', ['section' => 'platform_management', 'lang' => 'en']))
            ->assertOk()
            ->assertSee('data-admin-section-hub="platform_management"', false)
            ->assertSee(__('messages.admin_nav.groups.agencies'))
            ->assertSee(__('messages.admin_nav.section_hub_descriptions.agencies'))
            ->assertSee(route('admin.dashboard.page', ['page' => 'all_agencies', 'lang' => 'en']), false)
            ->assertDontSee($agency->company_name)
            ->assertDontSee('data-admin-agency-directory', false);

        $this->get(route('admin.dashboard.section', ['section' => 'platform_management', 'lang' => 'fr']))
            ->assertOk()
            ->assertSee(__('messages.admin_nav.sections.platform_management', [], 'fr'))
            ->assertSee(__('messages.admin_nav.groups.agencies', [], 'fr'))
            ->assertSee(__('messages.admin_nav.section_hub_intros.platform_management', [], 'fr'))
            ->assertSee(__('messages.admin_nav.section_hub_descriptions.agencies', [], 'fr'))
            ->assertSee(route('admin.dashboard.page', ['page' => 'all_agencies', 'lang' => 'fr']), false);

        $this->get(route('admin.dashboard.page', ['page' => 'all_agencies', 'lang' => 'en']))
            ->assertSee('data-admin-agency-directory', false)
            ->assertSee($agency->company_name)
            ->assertSee($agency->owner->name)
            ->assertSee(__('messages.admin_nav.pages.pending_verification'))
            ->assertSee(__('messages.admin_agencies.case_statuses.never_submitted'));
    }

    public function test_trust_safety_verification_lists_share_records_and_show_decision_history(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $pendingAgency = Agency::factory()->create([
            'company_name' => 'Pending Verification Agency',
            'verification_status' => VerificationStatus::PENDING,
        ]);
        AgencyDocument::factory()->for($pendingAgency)->create([
            'type' => 'license',
            'status' => DocumentStatus::PENDING,
            'uploaded_at' => now()->subDay(),
        ]);

        $approvedAgency = Agency::factory()->create([
            'company_name' => 'Approved Verification Agency',
            'verification_status' => VerificationStatus::VERIFIED,
            'verified_at' => now()->subDay(),
        ]);
        foreach (['license', 'id_proof', 'business_registration'] as $type) {
            AgencyDocument::factory()->for($approvedAgency)->create([
                'type' => $type,
                'status' => DocumentStatus::APPROVED,
                'uploaded_at' => now()->subDays(2),
            ]);
        }
        $approvedAgency->moderationEvents()->create([
            'actor_id' => $admin->id,
            'action' => 'verification_approved',
            'previous_verification_status' => VerificationStatus::PENDING->value,
            'new_verification_status' => VerificationStatus::VERIFIED->value,
            'previous_operating_status' => 'active',
            'new_operating_status' => 'active',
            'reason' => 'All submitted documents were verified.',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $rejectedAgency = Agency::factory()->create([
            'company_name' => 'Rejected Verification Agency',
            'verification_status' => VerificationStatus::REJECTED,
        ]);
        $rejectedAgency->moderationEvents()->create([
            'actor_id' => $admin->id,
            'action' => 'verification_rejected',
            'previous_verification_status' => VerificationStatus::PENDING->value,
            'new_verification_status' => VerificationStatus::REJECTED->value,
            'previous_operating_status' => 'active',
            'new_operating_status' => 'active',
            'reason' => 'The license could not be verified.',
        ]);

        $this->get(route('admin.dashboard.section', ['section' => 'trust_safety', 'lang' => 'en']))
            ->assertSee(route('admin.dashboard.page', [
                'page' => 'verification_pending',
                'lang' => 'en',
            ]), false);

        $this->get(route('admin.dashboard.page', ['page' => 'verification_pending', 'lang' => 'en']))
            ->assertSee('data-admin-agency-directory', false)
            ->assertSee('data-admin-verification-page="queue"', false)
            ->assertSee(__('messages.admin_nav.pages.verification_pending'))
            ->assertSee(__('messages.admin_agencies.required_checks'))
            ->assertSee(__('messages.admin_agencies.submitted'))
            ->assertSee(__('messages.admin_agencies.document_types.license'))
            ->assertSee('data-required-document-status="missing"', false)
            ->assertSee($pendingAgency->company_name)
            ->assertSee('queue=verification_pending', false)
            ->assertDontSee($approvedAgency->company_name)
            ->assertDontSee($rejectedAgency->company_name);

        $this->get(route('admin.dashboard.page', ['page' => 'approved', 'lang' => 'en']))
            ->assertSee($approvedAgency->company_name)
            ->assertDontSee($pendingAgency->company_name)
            ->assertDontSee($rejectedAgency->company_name);

        $this->get(route('admin.agencies.show', [
            'agency' => $approvedAgency->id,
            'lang' => 'en',
            'queue' => 'approved',
        ]))
            ->assertSee('data-admin-verification-page="record"', false)
            ->assertSee(__('messages.admin_agencies.decision_details'))
            ->assertSee(__('messages.admin_agencies.decision_reviewer'))
            ->assertSee(__('messages.admin_agencies.decision_date'))
            ->assertSee($admin->name)
            ->assertSee('All submitted documents were verified.')
            ->assertSee(__('messages.admin_agencies.required_checks'))
            ->assertSee(__('messages.admin_agencies.document_types.business_registration'));

        $this->get(route('admin.dashboard.page', ['page' => 'rejected', 'lang' => 'en']))
            ->assertSee($rejectedAgency->company_name)
            ->assertDontSee($pendingAgency->company_name)
            ->assertDontSee($approvedAgency->company_name);

        $this->get(route('admin.agencies.show', [
            'agency' => $rejectedAgency->id,
            'lang' => 'en',
            'queue' => 'rejected',
        ]))
            ->assertSee(__('messages.admin_agencies.decision_details'))
            ->assertSee($admin->name)
            ->assertSee('The license could not be verified.')
            ->assertSee(__('messages.admin_agencies.required_checks'));
    }

    public function test_agency_directory_searches_and_filters_by_owner_country_and_verification_state(): void
    {
        $this->actAsVerifiedAdmin();
        $france = Country::factory()->create(['name' => 'France']);
        $japan = Country::factory()->create(['name' => 'Japan']);
        $matchingAgency = Agency::factory()->create([
            'company_name' => 'Northern Routes',
            'verification_status' => VerificationStatus::PENDING,
        ]);
        $matchingAgency->owner->update(['name' => 'Alex North']);
        $matchingAgency->countries()->attach($france);
        $otherAgency = Agency::factory()->create([
            'company_name' => 'Island Getaways',
            'verification_status' => VerificationStatus::VERIFIED,
        ]);
        $otherAgency->countries()->attach($japan);

        $this->get(route('admin.dashboard.page', [
            'page' => 'all_agencies',
            'lang' => 'en',
            'search' => 'Alex North',
            'country_id' => $france->id,
            'verification_status' => 'pending',
        ]))
            ->assertSee($matchingAgency->company_name)
            ->assertDontSee($otherAgency->company_name);

        $this->get(route('admin.dashboard.page', ['page' => 'verified_agencies', 'lang' => 'en']))
            ->assertSee($otherAgency->company_name)
            ->assertDontSee($matchingAgency->company_name);
    }

    public function test_admin_agency_pages_show_owner_online_and_last_active_status(): void
    {
        $this->travelTo('2026-11-08 12:00:00');
        $this->actAsVerifiedAdmin();
        $onlineAgency = Agency::factory()->create([
            'company_name' => 'Online Agency',
            'verification_status' => VerificationStatus::VERIFIED,
        ]);
        $onlineAgency->owner->forceFill([
            'is_online' => true,
            'last_active_at' => now(),
        ])->save();
        $offlineAgency = Agency::factory()->create([
            'company_name' => 'Offline Agency',
            'verification_status' => VerificationStatus::VERIFIED,
        ]);
        $offlineAgency->owner->forceFill([
            'is_online' => false,
            'last_active_at' => now()->subHours(2),
        ])->save();

        $directory = $this->get(route('admin.dashboard.page', ['page' => 'all_agencies', 'lang' => 'en']));

        $directory->assertSee('data-owner-presence="online"', false)
            ->assertSee(__('messages.admin_agencies.online_now'))
            ->assertSee('data-owner-presence="offline"', false)
            ->assertSee('h-2 w-2 rounded-full bg-emerald-500', false)
            ->assertSee('h-2 w-2 rounded-full bg-slate-400', false)
            ->assertSee('inline-flex items-center gap-2 text-xs admin-muted', false)
            ->assertSee(__('messages.admin_agencies.last_active', [
                'time' => $offlineAgency->owner->last_active_at->locale('en')->diffForHumans(),
            ]));

        $this->get(route('admin.agencies.show', ['agency' => $offlineAgency->id, 'lang' => 'en']))
            ->assertSee(__('messages.admin_agencies.last_active', [
                'time' => $offlineAgency->owner->last_active_at->locale('en')->diffForHumans(),
            ]));
    }

    public function test_agency_directory_uses_distinct_colors_for_verification_states(): void
    {
        $this->actAsVerifiedAdmin();
        Agency::factory()->create([
            'company_name' => 'Waiting Agency',
            'verification_status' => VerificationStatus::PENDING,
        ]);
        Agency::factory()->create([
            'company_name' => 'Approved Agency',
            'verification_status' => VerificationStatus::VERIFIED,
        ]);
        Agency::factory()->create([
            'company_name' => 'Rejected Agency',
            'verification_status' => VerificationStatus::REJECTED,
        ]);
        Agency::factory()->create([
            'company_name' => 'Suspended Agency',
            'operating_status' => 'suspended',
        ]);

        $this->get(route('admin.dashboard.page', ['page' => 'all_agencies', 'lang' => 'en']))
            ->assertSee('data-agency-verification-status="pending"', false)
            ->assertSee('data-agency-verification-cell="pending"', false)
            ->assertSee('bg-amber-50/80 dark:bg-amber-950/30', false)
            ->assertDontSee('border-l-4 border-amber-500', false)
            ->assertSee('border-amber-500/40 bg-amber-500/10 text-amber-800', false)
            ->assertSee('data-agency-verification-status="verified"', false)
            ->assertSee('data-agency-verification-cell="verified"', false)
            ->assertSee('bg-emerald-50/80 dark:bg-emerald-950/30', false)
            ->assertSee('border-emerald-500/40 bg-emerald-500/10 text-emerald-800', false)
            ->assertDontSee('data-verified-badge', false)
            ->assertSee('data-agency-verification-status="rejected"', false)
            ->assertSee('data-agency-operating-status="active"', false)
            ->assertSee('data-agency-operating-cell="active"', false)
            ->assertSee('data-agency-operating-status="suspended"', false)
            ->assertSee('data-agency-operating-cell="suspended"', false)
            ->assertSee('border-rose-500/40 bg-rose-500/10 text-rose-800', false)
            ->assertSee(__('messages.admin_agencies.operating_statuses.active'))
            ->assertSee(__('messages.admin_agencies.operating_statuses.suspended'));
    }

    public function test_agency_logo_is_visible_in_directory_and_profile_cover_and_rating_are_shown(): void
    {
        Storage::fake('public');
        $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create([
            'company_name' => 'Image and Rating Agency',
            'logo_path' => 'agency-logos/image-rating/logo.png',
            'cover_image_path' => 'agency-covers/image-rating/cover.jpg',
        ]);
        Storage::disk('public')->put($agency->logo_path, 'logo bytes');
        Storage::disk('public')->put($agency->cover_image_path, 'cover bytes');

        $firstReviewer = User::factory()->create(['role' => UserRole::TRAVELER]);
        $secondReviewer = User::factory()->create(['role' => UserRole::TRAVELER]);
        Review::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $firstReviewer->id,
            'status' => 'published',
            'rating' => 4,
        ]);
        Review::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $secondReviewer->id,
            'status' => 'published',
            'rating' => 5,
        ]);
        Review::factory()->create([
            'agency_id' => $agency->id,
            'status' => 'pending',
            'rating' => 1,
        ]);

        $this->get(route('admin.dashboard.page', ['page' => 'all_agencies', 'lang' => 'en']))
            ->assertSee($agency->company_name)
            ->assertDontSee('agency-logos/image-rating/logo.png', false)
            ->assertDontSee('data-agency-logo', false);

        $this->get(route('admin.agencies.show', ['agency' => $agency->id, 'lang' => 'en']))
            ->assertSee('data-agency-cover', false)
            ->assertSee('agency-covers/image-rating/cover.jpg', false)
            ->assertSee('agency-logos/image-rating/logo.png', false)
            ->assertSee('data-agency-rating', false)
            ->assertSee('4.5/5')
            ->assertSee('★★★★★')
            ->assertSee('2');
    }

    public function test_agency_directory_keeps_country_details_out_of_the_table(): void
    {
        $this->actAsVerifiedAdmin();
        $country = Country::factory()->create(['name' => 'Profile Only Archipelago']);
        $agency = Agency::factory()->create([
            'company_name' => 'Compact Directory Agency',
            'address' => 'Hidden street details',
        ]);
        $agency->countries()->attach($country);

        $this->get(route('admin.dashboard.page', ['page' => 'all_agencies', 'lang' => 'en']))
            ->assertSee($agency->company_name)
            ->assertDontSee(__('messages.admin_agencies.location'))
            ->assertDontSee('Hidden street details');

        $this->get(route('admin.agencies.show', ['agency' => $agency->id, 'lang' => 'en']))
            ->assertSee($country->name)
            ->assertSee('Hidden street details');
    }

    public function test_verified_agency_profile_shows_badge_and_moderation_actions(): void
    {
        $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create([
            'company_name' => 'Verified Quick Actions Travel',
            'verification_status' => VerificationStatus::VERIFIED,
            'operating_status' => 'active',
        ]);

        $this->get(route('admin.agencies.show', ['agency' => $agency->id, 'lang' => 'en']))
            ->assertDontSee('data-verified-badge', false)
            ->assertSee(__('messages.admin_agencies.suspend'));
    }

    public function test_pending_agency_profile_shows_documents_missing_information_and_decision_workflow(): void
    {
        Storage::fake('public');
        $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create([
            'company_name' => 'Coastal Journeys',
            'address' => null,
            'phone' => null,
            'email' => null,
        ]);
        $document = AgencyDocument::factory()->for($agency)->create([
            'type' => 'license',
            'status' => DocumentStatus::PENDING,
            'file_path' => 'agency-documents/coastal-license.pdf',
            'uploaded_at' => now()->subDay(),
        ]);
        Storage::disk('public')->put($document->file_path, 'document bytes');

        $this->get(route('admin.agencies.show', ['agency' => $agency->id, 'lang' => 'en']))
            ->assertSee('data-admin-agency-profile', false)
            ->assertSee(__('messages.admin_agencies.verification_workflow'))
            ->assertSee(__('messages.admin_agencies.address'))
            ->assertSee(__('messages.admin_agencies.documents'))
            ->assertSee(__('messages.admin_agencies.document_types.license'))
            ->assertSee('onsubmit=', false)
            ->assertSee(__('messages.admin_agencies.suspend_confirm'))
            ->assertSee(__('messages.admin_agencies.suspend_reason_optional'))
            ->assertSee(__('messages.admin_agencies.delete_confirm', ['agency' => $agency->company_name]))
            ->assertSee(__('messages.admin_agencies.permanently_delete'))
            ->assertSee(route('admin.agencies.documents.show', [
                'agency' => $agency->id,
                'document' => $document->id,
                'lang' => 'en',
            ]), false);

        $this->get(route('admin.agencies.documents.show', [
            'agency' => $agency->id,
            'document' => $document->id,
            'lang' => 'en',
        ]))->assertOk();
    }

    public function test_approval_requires_each_required_document_type_and_does_not_record_a_decision_without_them(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create(['verification_status' => VerificationStatus::PENDING]);
        foreach (['license', 'id_proof'] as $type) {
            AgencyDocument::factory()->for($agency)->create([
                'type' => $type,
                'status' => DocumentStatus::PENDING,
            ]);
        }

        $this->post(route('admin.agencies.verification', ['agency' => $agency->id, 'lang' => 'en']), [
            'decision' => 'approve',
            'reason' => 'The submitted documents were reviewed.',
        ])->assertSessionHasErrors('decision');

        $this->assertSame(
            __('messages.admin_agencies.approval_requires_documents', [
                'documents' => __('messages.admin_agencies.document_types.business_registration'),
            ]),
            session('errors')->getBag('default')->first('decision'),
        );

        $this->assertSame(VerificationStatus::PENDING, $agency->fresh()->verification_status);
        $this->assertDatabaseMissing('agency_moderation_events', [
            'agency_id' => $agency->id,
            'actor_id' => $admin->id,
            'action' => 'verification_approved',
        ]);
        $this->assertSame(0, $agency->owner->notifications()->count());
    }

    public function test_admin_approval_updates_pending_documents_and_records_approval_history(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create(['verification_status' => VerificationStatus::PENDING]);
        $owner = $agency->owner;
        $documents = collect(['license', 'id_proof', 'business_registration'])
            ->map(fn (string $type): AgencyDocument => AgencyDocument::factory()->for($agency)->create([
                'type' => $type,
                'status' => DocumentStatus::PENDING,
            ]));
        Mail::fake();

        $this->post(route('admin.agencies.verification', ['agency' => $agency->id, 'lang' => 'en']), [
            'decision' => 'approve',
            'reason' => 'Documents checked and business details matched.',
            'queue' => 'verification_pending',
        ])->assertRedirect(route('admin.agencies.show', [
            'agency' => $agency->id,
            'lang' => 'en',
            'queue' => 'approved',
        ]));

        $this->assertSame(VerificationStatus::VERIFIED, $agency->fresh()->verification_status);
        $this->assertNotNull($agency->fresh()->verified_at);
        $this->assertSame(
            [DocumentStatus::APPROVED, DocumentStatus::APPROVED, DocumentStatus::APPROVED],
            $documents->map(fn (AgencyDocument $document): DocumentStatus => $document->fresh()->status)->all(),
        );
        $this->assertDatabaseHas('agency_moderation_events', [
            'agency_id' => $agency->id,
            'actor_id' => $admin->id,
            'action' => 'verification_approved',
            'previous_verification_status' => VerificationStatus::PENDING->value,
            'new_verification_status' => VerificationStatus::VERIFIED->value,
            'reason' => 'Documents checked and business details matched.',
        ]);
        $notification = $agency->owner->notifications()->firstOrFail();
        $this->assertSame('verification_approved', $notification->data['type']);
        $this->assertSame(__('messages.agency_verification_notification_title'), $notification->data['title']);
        $this->assertSame(__('messages.agency_verification_approved_notification'), $notification->data['message']);
        $this->assertNull($notification->read_at);
        Mail::assertSent(
            AgencyModerationMail::class,
            fn (AgencyModerationMail $mail): bool => $mail->hasTo($owner->email)
                && $mail->agencyName === $agency->company_name
                && $mail->heading === __('messages.agency_verification_approved_email_heading')
                && $mail->body === __('messages.agency_verification_approved_email_body', ['agency' => $agency->company_name])
                && $mail->envelope()->subject === __('messages.agency_verification_approved_email_subject', ['agency' => $agency->company_name]),
        );

        $this->actingAs($owner)
            ->get(route('agency.notifications.index', ['lang' => 'en']))
            ->assertSee('data-agency-notification-count="1"', false)
            ->assertSee('data-agency-notification', false)
            ->assertSee(__('messages.agency_verification_notification_title'))
            ->assertSee(__('messages.agency_verification_approved_notification'))
            ->assertSee(route('agency.profile', ['lang' => 'en']), false);
    }

    public function test_admin_can_approve_a_combined_verification_pdf_after_reviewing_it(): void
    {
        $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create(['verification_status' => VerificationStatus::PENDING]);
        $document = AgencyDocument::factory()->for($agency)->create([
            'type' => 'other',
            'status' => DocumentStatus::PENDING,
        ]);

        $this->get(route('admin.agencies.show', ['agency' => $agency->id, 'lang' => 'en']))
            ->assertSee(__('messages.admin_agencies.document_types.other'))
            ->assertSee('data-required-document-status="pending"', false)
            ->assertSee('name="decision"', false)
            ->assertSee('name="reason"', false)
            ->assertDontSee('name="reason" required', false);

        $this->post(route('admin.agencies.verification', ['agency' => $agency->id, 'lang' => 'en']), [
            'decision' => 'approve',
        ])->assertRedirect();

        $this->assertSame(VerificationStatus::VERIFIED, $agency->fresh()->verification_status);
        $this->assertSame(DocumentStatus::APPROVED, $document->fresh()->status);
        $this->assertDatabaseHas('agency_moderation_events', [
            'agency_id' => $agency->id,
            'action' => 'verification_approved',
            'reason' => null,
        ]);
    }

    public function test_admin_rejection_with_a_reason_records_the_decision(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create(['verification_status' => VerificationStatus::PENDING]);
        $owner = $agency->owner;
        Mail::fake();

        $this->post(route('admin.agencies.verification', ['agency' => $agency->id, 'lang' => 'en']), [
            'decision' => 'reject',
            'reason' => 'The submitted business details do not match the registration.',
        ])->assertRedirect();

        $this->assertSame(VerificationStatus::REJECTED, $agency->fresh()->verification_status);
        $this->assertDatabaseHas('agency_moderation_events', [
            'agency_id' => $agency->id,
            'actor_id' => $admin->id,
            'action' => 'verification_rejected',
            'reason' => 'The submitted business details do not match the registration.',
        ]);
        $notification = $agency->owner->notifications()->firstOrFail();
        $this->assertSame('verification_rejected', $notification->data['type']);
        $this->assertSame(
            __('messages.agency_verification_rejected_notification', [
                'reason' => 'The submitted business details do not match the registration.',
            ]),
            $notification->data['message'],
        );
        Mail::assertSent(
            AgencyModerationMail::class,
            fn (AgencyModerationMail $mail): bool => $mail->hasTo($owner->email)
                && $mail->agencyName === $agency->company_name
                && $mail->body === __('messages.agency_verification_rejected_notification', [
                    'reason' => 'The submitted business details do not match the registration.',
                ]),
        );
    }

    public function test_admin_can_reject_verification_without_a_reason(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create(['verification_status' => VerificationStatus::PENDING]);
        Mail::fake();

        $this->post(route('admin.agencies.verification', ['agency' => $agency->id, 'lang' => 'en']), [
            'decision' => 'reject',
        ])->assertRedirect();

        $this->assertSame(VerificationStatus::REJECTED, $agency->fresh()->verification_status);
        $this->assertDatabaseHas('agency_moderation_events', [
            'agency_id' => $agency->id,
            'actor_id' => $admin->id,
            'action' => 'verification_rejected',
            'reason' => null,
        ]);
        $this->assertSame(1, $agency->owner->notifications()->count());
        Mail::assertSent(AgencyModerationMail::class);
    }

    public function test_suspension_hides_an_agency_from_public_pages_and_blocks_new_bookings(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create([
            'company_name' => 'Atlas Horizon Voyages',
            'verification_status' => VerificationStatus::VERIFIED,
        ]);
        $owner = $agency->owner;
        Mail::fake();

        $agency->documents()->create([
            'type' => 'license',
            'file_path' => 'agency-documents/harbor-license.pdf',
            'status' => DocumentStatus::APPROVED,
            'uploaded_at' => now(),
        ]);
        $package = Package::factory()->create([
            'agency_id' => $agency->id,
            'status' => PackageStatus::PUBLISHED,
        ]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => today()->addMonth()->toDateString(),
            'capacity' => 10,
            'status' => 'active',
        ]);
        $traveler = User::factory()->create(['role' => UserRole::TRAVELER]);

        $this->post(route('admin.agencies.suspend', ['agency' => $agency->id, 'lang' => 'en']))
            ->assertRedirect(route('admin.agencies.show', ['agency' => $agency->id, 'lang' => 'en']))
            ->assertSessionHas('status', __('messages.admin_agencies.suspended'));

        $this->assertSame('suspended', $agency->fresh()->operating_status);
        $this->assertFalse(Agency::verified()->whereKey($agency->id)->exists());
        $this->assertDatabaseHas('agency_moderation_events', [
            'agency_id' => $agency->id,
            'actor_id' => $admin->id,
            'action' => 'suspended',
            'previous_operating_status' => 'active',
            'new_operating_status' => 'suspended',
            'reason' => null,
        ]);
        $notification = $owner->notifications()->firstOrFail();
        $this->assertSame('agency_suspended', $notification->data['type']);
        $this->assertSame(__('messages.agency_suspended_notification', ['agency' => $agency->company_name]), $notification->data['message']);
        $this->assertNull($notification->read_at);
        Mail::assertSent(
            AgencyModerationMail::class,
            fn (AgencyModerationMail $mail): bool => $mail->hasTo($owner->email)
                && $mail->agencyName === $agency->company_name
                && $mail->heading === __('messages.agency_suspended_email_heading')
                && $mail->body === __('messages.agency_suspended_email_body', [
                    'agency' => $agency->company_name,
                ]),
        );
        $this->actingAs($owner)
            ->get(route('agency.notifications.index', ['lang' => 'en']))
            ->assertSee('data-agency-notification-count="1"', false)
            ->assertSee(__('messages.agency_moderation_notification_title'))
            ->assertSee(__('messages.agency_suspended_notification', [
                'agency' => $agency->company_name,
            ]));

        $this->actingAs($traveler)
            ->get(route('agency.show', ['slug' => 'atlas-horizon-voyages', 'lang' => 'en']))
            ->assertNotFound();

        $this->post(route('bookings.store', ['package' => $package->id, 'lang' => 'en']), [
            'package_availability_id' => $availability->id,
            'traveler_count' => 1,
        ])->assertSessionHasErrors([
            'traveler_count' => __('messages.agency_suspended_cannot_book'),
        ]);

        $this->assertDatabaseMissing('bookings', [
            'agency_id' => $agency->id,
            'user_id' => $traveler->id,
        ]);
    }

    public function test_admin_can_reinstate_a_suspended_agency_with_audited_reason(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create([
            'company_name' => 'Northwind Journeys',
            'verification_status' => VerificationStatus::VERIFIED,
            'operating_status' => 'suspended',
            'suspended_at' => now()->subDay(),
            'suspension_reason' => 'A temporary compliance review.',
        ]);
        $owner = $agency->owner;

        $this->post(route('admin.agencies.reinstate', ['agency' => $agency->id, 'lang' => 'en']), [
            'reason' => 'The agency provided the requested compliance documentation.',
        ])->assertRedirect();

        $this->assertSame('active', $agency->fresh()->operating_status);
        $this->assertNull($agency->fresh()->suspended_at);
        $this->assertNull($agency->fresh()->suspension_reason);
        $this->assertDatabaseHas('agency_moderation_events', [
            'agency_id' => $agency->id,
            'actor_id' => $admin->id,
            'action' => 'reinstated',
            'previous_operating_status' => 'suspended',
            'new_operating_status' => 'active',
            'reason' => 'The agency provided the requested compliance documentation.',
        ]);
        $notification = $owner->notifications()->firstOrFail();
        $this->assertSame('agency_reinstated', $notification->data['type']);
        $this->assertSame(
            __('messages.agency_reinstated_notification', [
                'agency' => $agency->company_name,
                'reason' => 'The agency provided the requested compliance documentation.',
            ]),
            $notification->data['message'],
        );
        $this->assertNull($notification->read_at);
    }

    public function test_suspension_completes_and_warns_admin_when_smtp_delivery_fails(): void
    {
        $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create([
            'verification_status' => VerificationStatus::VERIFIED,
        ]);
        $owner = $agency->owner;
        $exception = new TransportException('SMTP DNS failure.');
        Mail::shouldReceive('to')
            ->once()
            ->with(Mockery::on(fn (User $recipient): bool => $recipient->is($owner)))
            ->andThrow($exception);
        $this->post(route('admin.agencies.suspend', ['agency' => $agency->id, 'lang' => 'en']), [
            'reason' => 'Repeatedly advertised trips that were not available.',
        ])
            ->assertRedirect(route('admin.agencies.show', ['agency' => $agency->id, 'lang' => 'en']))
            ->assertSessionHas('status', __('messages.admin_agencies.suspended'))
            ->assertSessionHas('warning', __('messages.agency_moderation_email_failed'));

        $this->assertSame('suspended', $agency->fresh()->operating_status);
        $notification = $owner->notifications()->firstOrFail();
        $this->assertSame('agency_suspended', $notification->data['type']);
        $this->assertNull($notification->read_at);
    }

    public function test_rejected_agency_document_resubmission_reopens_verification_and_records_the_owner(): void
    {
        Storage::fake('public');
        Notification::fake();
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $agency = Agency::factory()->create(['verification_status' => VerificationStatus::REJECTED]);
        $rejectedDocument = $agency->documents()->create([
            'type' => 'license',
            'file_path' => 'agency-documents/previous-license.pdf',
            'status' => DocumentStatus::REJECTED,
            'uploaded_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($agency->owner)
            ->post(route('agency.documents.store', ['lang' => 'en']), [
                'document' => UploadedFile::fake()->create('updated-verification-bundle.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('agency.profile', ['lang' => 'en']));

        $this->assertSame(VerificationStatus::PENDING, $agency->fresh()->verification_status);
        $this->assertSame(DocumentStatus::REJECTED, $rejectedDocument->fresh()->status);
        $this->assertDatabaseHas('agency_documents', [
            'agency_id' => $agency->id,
            'type' => 'other',
            'status' => DocumentStatus::PENDING->value,
        ]);
        $this->assertDatabaseHas('agency_moderation_events', [
            'agency_id' => $agency->id,
            'actor_id' => $agency->owner->id,
            'action' => 'verification_resubmitted',
            'previous_verification_status' => VerificationStatus::REJECTED->value,
            'new_verification_status' => VerificationStatus::PENDING->value,
        ]);
        Notification::assertSentTo($admin, AdminAgencyDocumentReceived::class);
    }

    public function test_agency_cannot_submit_again_after_a_second_verification_rejection(): void
    {
        Storage::fake('public');
        $agency = Agency::factory()->create(['verification_status' => VerificationStatus::REJECTED]);

        foreach (range(1, 2) as $rejectionNumber) {
            $agency->moderationEvents()->create([
                'action' => 'verification_rejected',
                'previous_verification_status' => VerificationStatus::PENDING->value,
                'new_verification_status' => VerificationStatus::REJECTED->value,
                'previous_operating_status' => 'active',
                'new_operating_status' => 'active',
                'reason' => 'Verification documents were insufficient.',
                'created_at' => now()->subMinutes($rejectionNumber),
                'updated_at' => now()->subMinutes($rejectionNumber),
            ]);
        }

        $this->actingAs($agency->owner)
            ->get(route('agency.profile', ['lang' => 'en']))
            ->assertSee(__('messages.verification_resubmission_limit_reached'))
            ->assertDontSee(__('messages.submit_verification_bundle'));

        $this->actingAs($agency->owner)
            ->from(route('agency.profile', ['lang' => 'en']))
            ->post(route('agency.documents.store', ['lang' => 'en']), [
                'document' => UploadedFile::fake()->create('third-verification-bundle.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('agency.profile', ['lang' => 'en']))
            ->assertSessionHasErrors([
                'document' => __('messages.verification_resubmission_limit_reached'),
            ]);

        $this->assertDatabaseCount('agency_documents', 0);
        $this->assertSame(VerificationStatus::REJECTED, $agency->fresh()->verification_status);
    }

    public function test_suspended_agency_owner_cannot_create_packages_but_keeps_their_account(): void
    {
        $agency = Agency::factory()->create([
            'verification_status' => VerificationStatus::VERIFIED,
            'operating_status' => 'suspended',
        ]);
        $destination = Country::factory()->create(['name' => 'Japan']);
        $agency->countries()->attach($destination);

        $this->actingAs($agency->owner)
            ->get(route('agency.packages.create', ['lang' => 'en']))
            ->assertSee(__('messages.agency_suspended_cannot_create_packages'))
            ->assertSee('<button type="submit" disabled', false);

        $this->post(route('agency.packages.store', ['lang' => 'en']), [
            'title' => 'Japan Discovery',
            'summary' => 'A thoughtful journey through Japan',
            'destination' => 'Japan',
            'price_from' => 900,
            'duration_days' => 8,
            'status' => PackageStatus::DRAFT->value,
        ])->assertSessionHasErrors([
            'verification' => __('messages.agency_suspended_cannot_create_packages'),
        ]);

        $this->assertSame(UserRole::AGENCY_OWNER, $agency->owner->fresh()->role);
        $this->assertDatabaseMissing('packages', [
            'agency_id' => $agency->id,
            'title' => 'Japan Discovery',
        ]);
    }

    public function test_non_admin_cannot_suspend_or_decide_verification_for_an_agency(): void
    {
        $agency = Agency::factory()->create();
        $traveler = User::factory()->create(['role' => UserRole::TRAVELER]);

        $this->actingAs($traveler)
            ->post(route('admin.agencies.suspend', ['agency' => $agency->id, 'lang' => 'en']), [
                'reason' => 'This should not be allowed.',
            ])->assertForbidden();

        $this->post(route('admin.agencies.verification', ['agency' => $agency->id, 'lang' => 'en']), [
            'decision' => 'approve',
            'reason' => 'This should not be allowed.',
        ])->assertForbidden();

        $this->assertSame('active', $agency->fresh()->operating_status);
        $this->assertSame(VerificationStatus::PENDING, $agency->fresh()->verification_status);
    }

    public function test_admin_can_permanently_delete_an_agency_and_related_records_without_deleting_the_owner_account(): void
    {
        Storage::fake('public');
        $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create([
            'company_name' => 'Remove Me Travel',
            'logo_path' => 'agency-logos/remove-me/logo.png',
            'cover_image_path' => 'agency-covers/remove-me/cover.jpg',
        ]);
        $owner = $agency->owner;
        Mail::fake();
        $traveler = User::factory()->create(['role' => UserRole::TRAVELER]);
        $agencyDocument = AgencyDocument::factory()->for($agency)->create([
            'file_path' => 'agency-documents/remove-me/license.pdf',
        ]);
        $experience = $agency->experiences()->create([
            'image_path' => 'agency-experiences/remove-me/story.jpg',
            'caption' => 'A journey',
            'alt_text' => 'Travel landscape',
            'published_at' => now(),
        ]);
        $package = Package::factory()->for($agency)->create();
        $packageImagePath = 'package-images/remove-me/photo.jpg';
        DB::table('package_images')->insert([
            'package_id' => $package->id,
            'path' => $packageImagePath,
            'caption' => null,
            'is_primary' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => today()->addMonth()->toDateString(),
            'capacity' => 10,
            'status' => 'active',
        ]);
        $booking = Booking::create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'booking_reference' => 'TC-DELETE-0001',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'traveler_count' => 1,
            'unit_price' => 100,
            'total_amount' => 100,
            'currency' => 'XAF',
        ]);
        $paymentTransactionId = DB::table('booking_payment_transactions')->insertGetId([
            'booking_id' => $booking->id,
            'payment_reference' => 'PAY-DELETE-0001',
            'provider' => 'test',
            'type' => 'charge',
            'status' => 'succeeded',
            'amount' => 100,
            'currency' => 'XAF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $review = Review::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $traveler->id,
            'booking_id' => $booking->id,
        ]);
        $inquiry = Inquiry::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $traveler->id,
        ]);
        $conversationId = DB::table('conversations')->insertGetId([
            'agency_id' => $agency->id,
            'traveler_id' => $traveler->id,
            'inquiry_id' => $inquiry->id,
            'booking_id' => $booking->id,
            'subject' => 'Trip question',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('conversation_messages')->insert([
            'conversation_id' => $conversationId,
            'sender_id' => $traveler->id,
            'body' => 'Is this trip available?',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $trustScoreLog = TrustScoreLog::factory()->create(['agency_id' => $agency->id]);

        foreach ([
            $agency->logo_path,
            $agency->cover_image_path,
            $agencyDocument->file_path,
            $experience->image_path,
            $packageImagePath,
        ] as $path) {
            Storage::disk('public')->put($path, 'agency file');
        }

        $this->delete(route('admin.agencies.destroy', ['agency' => $agency->id, 'lang' => 'en']))
            ->assertRedirect(route('admin.dashboard.page', ['page' => 'all_agencies', 'lang' => 'en']))
            ->assertSessionHas('status', __('messages.admin_agencies.permanently_deleted', ['agency' => $agency->company_name]));

        $this->assertDatabaseMissing('agencies', ['id' => $agency->id]);
        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
        $this->assertDatabaseMissing('booking_payment_transactions', ['id' => $paymentTransactionId]);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('inquiries', ['id' => $inquiry->id]);
        $this->assertDatabaseMissing('conversations', ['id' => $conversationId]);
        $this->assertDatabaseMissing('conversation_messages', ['conversation_id' => $conversationId]);
        $this->assertDatabaseMissing('agency_documents', ['id' => $agencyDocument->id]);
        $this->assertDatabaseMissing('agency_experiences', ['id' => $experience->id]);
        $this->assertDatabaseMissing('package_images', ['package_id' => $package->id]);
        $this->assertDatabaseMissing('package_availability', ['id' => $availability->id]);
        $this->assertDatabaseMissing('trust_score_logs', ['id' => $trustScoreLog->id]);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertSame(UserRole::TRAVELER, $owner->fresh()->role);
        $notification = $owner->notifications()->firstOrFail();
        $this->assertSame('agency_permanently_deleted', $notification->data['type']);
        $this->assertSame(
            __('messages.agency_permanently_deleted_notification', ['agency' => $agency->company_name]),
            $notification->data['message'],
        );
        $this->assertNull($notification->read_at);
        Mail::assertSent(
            AgencyModerationMail::class,
            fn (AgencyModerationMail $mail): bool => $mail->hasTo($owner->email)
                && $mail->agencyName === $agency->company_name
                && $mail->body === __('messages.agency_permanently_deleted_notification', [
                    'agency' => $agency->company_name,
                ]),
        );
        $this->actingAs($owner)
            ->get(route('account.activity', ['lang' => 'en']))
            ->assertSee(__('messages.agency_moderation_notification_title'))
            ->assertSee(__('messages.agency_permanently_deleted_notification', [
                'agency' => $agency->company_name,
            ]));

        foreach ([
            'agency-logos/remove-me/logo.png',
            'agency-covers/remove-me/cover.jpg',
            'agency-documents/remove-me/license.pdf',
            'agency-experiences/remove-me/story.jpg',
            $packageImagePath,
        ] as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_admin_can_permanently_delete_a_suspended_agency(): void
    {
        $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create([
            'operating_status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => 'Repeated policy violations.',
        ]);

        $this->delete(route('admin.agencies.destroy', ['agency' => $agency->id, 'lang' => 'en']))
            ->assertRedirect(route('admin.dashboard.page', ['page' => 'all_agencies', 'lang' => 'en']));

        $this->assertDatabaseMissing('agencies', ['id' => $agency->id]);
    }

    public function test_non_admin_cannot_permanently_delete_an_agency(): void
    {
        $agency = Agency::factory()->create();
        $traveler = User::factory()->create(['role' => UserRole::TRAVELER]);

        $this->actingAs($traveler)
            ->delete(route('admin.agencies.destroy', ['agency' => $agency->id, 'lang' => 'en']))
            ->assertForbidden();

        $this->assertDatabaseHas('agencies', ['id' => $agency->id]);
    }

    public function test_suspended_agency_owner_cannot_add_a_departure(): void
    {
        $agency = Agency::factory()->create([
            'verification_status' => VerificationStatus::VERIFIED,
            'operating_status' => 'suspended',
        ]);
        $package = Package::factory()->for($agency)->create();

        $this->actingAs($agency->owner)
            ->post(route('agency.availability.store', ['lang' => 'en']), [
                'package_id' => $package->id,
                'departure_date' => today()->addMonth()->toDateString(),
                'capacity' => 10,
                'status' => 'active',
            ])
            ->assertSessionHasErrors([
                'status' => __('messages.agency_suspended_cannot_manage_availability'),
            ]);

        $this->assertDatabaseMissing('package_availability', ['package_id' => $package->id]);
    }

    public function test_suspended_agency_owner_cannot_update_departure_to_active(): void
    {
        $agency = Agency::factory()->create([
            'verification_status' => VerificationStatus::VERIFIED,
            'operating_status' => 'suspended',
        ]);
        $package = Package::factory()->for($agency)->create();
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => today()->addMonth()->toDateString(),
            'capacity' => 10,
            'status' => 'inactive',
        ]);

        $this->actingAs($agency->owner)
            ->put(route('agency.availability.update', ['availability' => $availability->id, 'lang' => 'en']), [
                'package_id' => $package->id,
                'departure_date' => $availability->departure_date->toDateString(),
                'capacity' => 10,
                'status' => 'active',
            ])
            ->assertSessionHasErrors([
                'status' => __('messages.agency_suspended_cannot_manage_availability'),
            ]);

        $this->assertSame('inactive', $availability->fresh()->status->value);
    }

    public function test_suspended_agency_owner_cannot_reactivate_departure_but_can_deactivate_it(): void
    {
        $agency = Agency::factory()->create([
            'verification_status' => VerificationStatus::VERIFIED,
            'operating_status' => 'suspended',
        ]);
        $package = Package::factory()->for($agency)->create();
        $availability = PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => today()->addMonth()->toDateString(),
            'capacity' => 10,
            'status' => 'inactive',
        ]);

        $this->actingAs($agency->owner)
            ->patch(route('agency.availability.status', ['availability' => $availability->id, 'lang' => 'en']), [
                'status' => 'active',
            ])
            ->assertSessionHasErrors([
                'status' => __('messages.agency_suspended_cannot_manage_availability'),
            ]);

        $this->assertSame('inactive', $availability->fresh()->status->value);

        $this->patch(route('agency.availability.status', ['availability' => $availability->id, 'lang' => 'en']), [
            'status' => 'inactive',
        ])->assertRedirect();

        $this->assertSame('inactive', $availability->fresh()->status->value);
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
}
