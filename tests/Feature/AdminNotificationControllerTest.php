<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\AgencyDocument;
use App\Models\TravelerIssue;
use App\Models\User;
use App\Notifications\AdminAgencyDocumentReceived;
use App\Notifications\AdminTravelerIssueReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AdminNotificationControllerTest extends TestCase
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

    public function test_verified_admin_sees_unread_traveler_reports_in_the_notification_bell(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $issue = $this->createTravelerIssue();
        $notification = new AdminTravelerIssueReceived($issue);
        $admin->notify($notification);
        $storedNotification = $admin->notifications()
            ->where('type', AdminTravelerIssueReceived::class)
            ->firstOrFail();

        $this->get(route('admin.dashboard', ['lang' => 'en']))
            ->assertSee('data-admin-notification-count="1"', false)
            ->assertSee($issue->subject)
            ->assertSee(__('messages.admin_nav.traveler_issue_notification_title', [
                'type' => __('messages.admin_alerts.categories.booking_dispute'),
            ]))
            ->assertSee(route('admin.notifications.open', [
                'notification' => $storedNotification->id,
                'lang' => 'en',
            ]), false);

        $this->assertDatabaseHas('notifications', [
            'id' => $storedNotification->id,
            'notifiable_id' => $admin->id,
            'read_at' => null,
        ]);
    }

    public function test_agency_document_upload_notifies_every_admin_and_appears_in_the_notification_bell(): void
    {
        Storage::fake('public');
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $anotherAdmin = User::factory()->withRole(UserRole::ADMIN)->create();
        $agency = Agency::factory()->create(['company_name' => 'Atlas Travel Agency']);

        $this->actingAs($agency->owner)->post(route('agency.documents.store', ['lang' => 'en']), [
            'type' => 'license',
            'document' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('agency.profile', ['lang' => 'en']));

        $document = AgencyDocument::query()->where('agency_id', $agency->id)->firstOrFail();
        foreach ([$admin, $anotherAdmin] as $recipient) {
            $notification = $recipient->notifications()
                ->where('type', AdminAgencyDocumentReceived::class)
                ->firstOrFail();

            $this->assertSame([
                'agency_id' => $agency->id,
                'agency_name' => $agency->company_name,
                'document_id' => $document->id,
                'document_type' => 'license',
            ], $notification->data);
            $this->assertNull($notification->read_at);
        }

        $this->actingAs($admin)->withSession($this->verifiedAdminSession($admin))
            ->get(route('admin.dashboard', ['lang' => 'en']))
            ->assertSee('data-admin-notification-count="1"', false)
            ->assertSee('data-admin-notification-type="agency_document"', false)
            ->assertSee(__('messages.admin_nav.agency_document_notification_title'))
            ->assertSee($agency->company_name)
            ->assertSee(__('messages.admin_nav.agency_document_types.license'));
    }

    public function test_opening_an_agency_document_notification_marks_it_read_and_opens_the_agency_profile(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create(['company_name' => 'Coastal Tours']);
        $document = AgencyDocument::factory()->for($agency)->create();
        $document->setRelation('agency', $agency);
        $admin->notify(new AdminAgencyDocumentReceived($document));
        $notification = $admin->notifications()->firstOrFail();

        $this->get(route('admin.notifications.open', [
            'notification' => $notification->id,
            'lang' => 'en',
        ]))->assertRedirect(route('admin.agencies.show', [
            'agency' => $agency->id,
            'lang' => 'en',
        ]));

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'notifiable_id' => $admin->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    public function test_replacing_an_agency_document_notifies_all_admins_again(): void
    {
        Storage::fake('public');
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $agency = Agency::factory()->create();
        $document = AgencyDocument::factory()->for($agency)->create([
            'file_path' => 'agency-documents/old-license.pdf',
            'uploaded_at' => now()->subMinute(),
        ]);
        Storage::disk('public')->put($document->file_path, 'old document');

        $this->actingAs($agency->owner)->put(route('agency.documents.update', [
            'document' => $document->id,
            'lang' => 'en',
        ]), [
            'document' => UploadedFile::fake()->create('replacement-license.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('agency.profile', ['lang' => 'en']));

        $notification = $admin->notifications()
            ->where('type', AdminAgencyDocumentReceived::class)
            ->firstOrFail();
        $this->assertSame($agency->id, $notification->data['agency_id']);
        $this->assertSame($document->id, $notification->data['document_id']);
        $this->assertNull($notification->read_at);
    }

    public function test_marking_all_admin_notifications_as_read_includes_agency_documents(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $agency = Agency::factory()->create();
        $document = AgencyDocument::factory()->for($agency)->create();
        $document->setRelation('agency', $agency);
        $admin->notify(new AdminAgencyDocumentReceived($document));

        $this->patch(route('admin.notifications.read-all', ['lang' => 'en']))->assertRedirect();

        $this->assertSame(0, $admin->unreadNotifications()
            ->where('type', AdminAgencyDocumentReceived::class)
            ->count());
    }

    public function test_opening_a_notification_marks_it_read_and_opens_its_alert_record(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $issue = $this->createTravelerIssue();
        $admin->notify(new AdminTravelerIssueReceived($issue));
        $notification = $admin->notifications()->firstOrFail();

        $this->get(route('admin.notifications.open', [
            'notification' => $notification->id,
            'lang' => 'en',
        ]))->assertRedirect(route('admin.dashboard.page', [
            'page' => 'alerts',
            'source' => 'traveler_issue',
            'source_id' => $issue->id,
            'lang' => 'en',
        ]));

        $this->get(route('admin.dashboard', ['lang' => 'en']))
            ->assertDontSee(route('admin.notifications.open', [
                'notification' => $notification->id,
                'lang' => 'en',
            ]), false)
            ->assertDontSee('data-admin-notification-count=', false)
            ->assertSee(__('messages.admin_nav.notifications_empty'));

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    public function test_admin_cannot_open_another_admins_notification(): void
    {
        $this->actAsVerifiedAdmin();
        $otherAdmin = User::factory()->withRole(UserRole::ADMIN)->create();
        $issue = $this->createTravelerIssue();
        $otherAdmin->notify(new AdminTravelerIssueReceived($issue));
        $notification = $otherAdmin->notifications()->firstOrFail();

        $this->get(route('admin.notifications.open', [
            'notification' => $notification->id,
            'lang' => 'en',
        ]))->assertNotFound();
    }

    public function test_non_admin_cannot_open_admin_notifications(): void
    {
        $traveler = User::factory()->create();

        $this->actingAs($traveler)
            ->get(route('admin.notifications.open', ['notification' => '00000000-0000-0000-0000-000000000000']))
            ->assertForbidden();
    }

    public function test_verified_admin_can_mark_all_traveler_issue_notifications_as_read(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $issue = $this->createTravelerIssue();
        $admin->notify(new AdminTravelerIssueReceived($issue));

        $this->patch(route('admin.notifications.read-all', ['lang' => 'en']))
            ->assertRedirect();

        $this->assertSame(0, $admin->unreadNotifications()
            ->where('type', AdminTravelerIssueReceived::class)
            ->count());

        $this->get(route('admin.dashboard', ['lang' => 'en']))
            ->assertDontSee(route('admin.notifications.open', [
                'notification' => $admin->notifications()->firstOrFail()->id,
                'lang' => 'en',
            ]), false)
            ->assertSee(__('messages.admin_nav.notifications_empty'));
    }

    private function actAsVerifiedAdmin(): User
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $this->actingAs($admin)->withSession($this->verifiedAdminSession($admin));

        return $admin;
    }

    /**
     * @return array<string, int>
     */
    private function verifiedAdminSession(User $admin): array
    {
        return [
            'admin_login.verified_user_id' => $admin->id,
            'admin_login.session_version' => $admin->admin_session_version,
            'admin_login.verified_at' => now()->timestamp,
            'admin_login.last_activity' => now()->timestamp,
        ];
    }

    private function createTravelerIssue(): TravelerIssue
    {
        $traveler = User::factory()->create();

        return $traveler->travelerIssues()->create([
            'type' => 'booking_dispute',
            'subject' => 'Booking was not honored',
            'description' => 'The agency did not honor the confirmed booking.',
        ]);
    }
}
