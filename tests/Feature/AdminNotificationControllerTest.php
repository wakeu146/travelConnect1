<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\UserRole;
use App\Models\TravelerIssue;
use App\Models\User;
use App\Notifications\AdminTravelerIssueReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
