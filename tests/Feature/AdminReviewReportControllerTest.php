<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\ContentReport;
use App\Models\Review;
use App\Models\User;
use App\Notifications\AgencyActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class AdminReviewReportControllerTest extends TestCase
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

    public function test_reviews_are_the_default_reviews_and_reports_entry_and_only_actual_signals_are_shown(): void
    {
        $this->actAsVerifiedAdmin();
        $reportedReview = Review::factory()->create([
            'comment' => 'A review with an actual user report.',
            'agency_id' => Agency::factory(),
            'user_id' => User::factory(),
        ]);
        $policyReview = Review::factory()->create([
            'comment' => 'A review marked for a policy check.',
            'status' => 'flagged',
        ]);
        $plainReview = Review::factory()->create([
            'comment' => 'A regular review without reports or policy indicators.',
            'status' => 'published',
        ]);
        ContentReport::query()->create([
            'review_id' => $reportedReview->id,
            'reporter_id' => User::factory()->create()->id,
            'reason' => 'This review contains personal information.',
            'evidence' => 'The review includes a private phone number.',
        ]);

        $this->get(route('admin.dashboard.section', ['section' => 'trust_safety', 'lang' => 'en']))
            ->assertSee(route('admin.dashboard.page', ['page' => 'reviews_ratings', 'lang' => 'en']), false);

        $this->get(route('admin.dashboard.page', ['page' => 'reviews_ratings', 'lang' => 'en']))
            ->assertSee('data-admin-reviews', false)
            ->assertSee('data-admin-review-report-navigation', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee($reportedReview->comment)
            ->assertSee($reportedReview->user->name)
            ->assertSee($reportedReview->agency->company_name)
            ->assertSee($policyReview->comment)
            ->assertSee($plainReview->comment)
            ->assertSee('data-review-signal="report"', false)
            ->assertSee('data-review-signal="policy"', false)
            ->assertSee('data-admin-review-id="'.$plainReview->id.'" data-review-signal-count="0"', false);
    }

    public function test_each_trust_safety_subpage_highlights_its_active_navigation_link(): void
    {
        $this->actAsVerifiedAdmin();
        $pages = [
            'reviews_ratings',
            'reported_content',
            'user_agency_complaints',
            'trust_score_overview',
            'agencies_requiring_attention',
        ];

        foreach ($pages as $page) {
            $url = route('admin.dashboard.page', ['page' => $page, 'lang' => 'en']);

            $this->get($url)
                ->assertSee('href="'.$url.'"', false)
                ->assertSee('is-active"', false);
        }
    }

    public function test_verified_admin_can_hide_a_review_and_the_reason_is_audited(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $review = Review::factory()->create(['status' => 'published']);

        $this->post(route('admin.reviews.moderation', ['review' => $review->id, 'lang' => 'en']), [
            'decision' => 'hide',
            'reason' => 'This review exposes private contact information.',
        ])->assertRedirect(route('admin.dashboard.page', ['page' => 'reviews_ratings', 'lang' => 'en']));

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'is_hidden' => true,
        ]);
        $this->assertDatabaseHas('trust_safety_events', [
            'source_type' => 'review',
            'source_id' => $review->id,
            'actor_id' => $admin->id,
            'action' => 'hide',
            'reason' => 'This review exposes private contact information.',
        ]);

        $this->post(route('admin.reviews.moderation', ['review' => $review->id, 'lang' => 'en']), [
            'decision' => 'restore',
            'reason' => 'The review was rechecked and may be shown again.',
        ])->assertRedirect(route('admin.dashboard.page', ['page' => 'reviews_ratings', 'lang' => 'en']));

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'is_hidden' => false,
        ]);
        $this->assertDatabaseHas('trust_safety_events', [
            'source_type' => 'review',
            'source_id' => $review->id,
            'actor_id' => $admin->id,
            'action' => 'restore',
            'reason' => 'The review was rechecked and may be shown again.',
        ]);
    }

    public function test_review_moderation_reason_is_optional_and_both_parties_are_notified(): void
    {
        Notification::fake();
        $this->actAsVerifiedAdmin();
        $traveler = User::factory()->withRole(UserRole::TRAVELER)->create();
        $agency = Agency::factory()->create();
        $review = Review::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $traveler->id,
            'status' => 'published',
        ]);
        $agencyOwner = $agency->owner;

        $this->post(route('admin.reviews.moderation', ['review' => $review->id, 'lang' => 'en']), [
            'decision' => 'hide',
        ])->assertRedirect(route('admin.dashboard.page', ['page' => 'reviews_ratings', 'lang' => 'en']));

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'is_hidden' => true,
        ]);
        $this->assertDatabaseHas('trust_safety_events', [
            'source_type' => 'review',
            'source_id' => $review->id,
            'action' => 'hide',
            'reason' => null,
        ]);

        $this->post(route('admin.reviews.moderation', ['review' => $review->id, 'lang' => 'en']), [
            'decision' => 'restore',
        ])->assertRedirect(route('admin.dashboard.page', ['page' => 'reviews_ratings', 'lang' => 'en']));

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'is_hidden' => false,
        ]);
        $this->assertDatabaseHas('trust_safety_events', [
            'source_type' => 'review',
            'source_id' => $review->id,
            'action' => 'restore',
            'reason' => null,
        ]);

        Notification::assertSentTo($traveler, AgencyActivityNotification::class, 2);
        Notification::assertSentTo($agencyOwner, AgencyActivityNotification::class, 2);
        $this->assertSame(
            [
                'Your review for '.$agency->company_name.' has been hidden by an administrator.',
                'Your review for '.$agency->company_name.' has been restored and is visible again.',
            ],
            Notification::sent($traveler, AgencyActivityNotification::class)->pluck('message')->all(),
        );
        $this->assertSame(
            [
                'A traveler review for '.$agency->company_name.' has been hidden by an administrator.',
                'A traveler review for '.$agency->company_name.' has been restored and is visible again.',
            ],
            Notification::sent($agencyOwner, AgencyActivityNotification::class)->pluck('message')->all(),
        );
    }

    public function test_verified_admin_can_restrict_a_report_and_hide_its_review_with_audit_history(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $review = Review::factory()->create(['status' => 'published']);
        $report = ContentReport::query()->create([
            'review_id' => $review->id,
            'reporter_id' => User::factory()->create()->id,
            'reason' => 'This review contains private information.',
            'evidence' => 'A personal phone number is visible.',
        ]);

        $this->get(route('admin.dashboard.page', ['page' => 'reported_content', 'lang' => 'en']))
            ->assertSee('data-admin-reported-content', false)
            ->assertSee($report->reason)
            ->assertSee($report->evidence)
            ->assertSee($review->comment)
            ->assertSee($report->reporter->name);

        $this->post(route('admin.reports.action', ['report' => $report->id, 'lang' => 'en']), [
            'action' => 'restrict',
            'reason' => 'The reported review violates the privacy policy.',
        ])->assertRedirect(route('admin.dashboard.page', ['page' => 'reported_content', 'lang' => 'en']));

        $this->assertDatabaseHas('content_reports', [
            'id' => $report->id,
            'status' => 'restricted',
            'outcome_reason' => 'The reported review violates the privacy policy.',
        ]);
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'is_hidden' => true,
        ]);
        $this->assertDatabaseHas('trust_safety_events', [
            'source_type' => 'content_report',
            'source_id' => $report->id,
            'actor_id' => $admin->id,
            'action' => 'restrict',
        ]);
        $this->assertDatabaseHas('trust_safety_events', [
            'source_type' => 'review',
            'source_id' => $review->id,
            'actor_id' => $admin->id,
            'action' => 'restrict',
        ]);
    }

    public function test_verified_admin_can_assign_and_resolve_a_complaint_with_history(): void
    {
        $admin = $this->actAsVerifiedAdmin();
        $traveler = User::factory()->create(['name' => 'Complaint Traveler']);
        $issue = $traveler->travelerIssues()->create([
            'type' => 'service',
            'subject' => 'Service not provided',
            'description' => 'The traveler says the agreed airport transfer was not provided.',
            'status' => 'open',
        ]);

        $this->get(route('admin.dashboard.page', ['page' => 'user_agency_complaints', 'lang' => 'en']))
            ->assertSee($traveler->name)
            ->assertSee($issue->subject)
            ->assertSee($issue->description)
            ->assertSee('data-admin-complaint-id="'.$issue->id.'"', false);

        $this->post(route('admin.complaints.action', ['issue' => $issue->id, 'lang' => 'en']), [
            'action' => 'assign',
            'assignee_id' => $admin->id,
            'reason' => 'Assigned to the on-call administrator.',
        ])->assertRedirect(route('admin.dashboard.page', ['page' => 'user_agency_complaints', 'lang' => 'en']));

        $this->assertDatabaseHas('traveler_issues', [
            'id' => $issue->id,
            'assigned_to' => $admin->id,
            'status' => 'open',
        ]);

        $this->post(route('admin.complaints.action', ['issue' => $issue->id, 'lang' => 'en']), [
            'action' => 'resolve',
            'reason' => 'The agency provided the missing service and the traveler confirmed resolution.',
        ])->assertRedirect(route('admin.dashboard.page', ['page' => 'user_agency_complaints', 'lang' => 'en']));

        $this->assertDatabaseHas('traveler_issues', [
            'id' => $issue->id,
            'status' => 'resolved',
            'assigned_to' => $admin->id,
        ]);
        $this->assertDatabaseHas('trust_safety_events', [
            'source_type' => 'traveler_issue',
            'source_id' => $issue->id,
            'actor_id' => $admin->id,
            'action' => 'assign',
            'reason' => 'Assigned to the on-call administrator.',
        ]);
        $this->assertDatabaseHas('trust_safety_events', [
            'source_type' => 'traveler_issue',
            'source_id' => $issue->id,
            'actor_id' => $admin->id,
            'action' => 'resolve',
            'status_after' => 'resolved',
        ]);
    }

    public function test_non_admin_cannot_moderate_a_review(): void
    {
        $review = Review::factory()->create();
        $traveler = User::factory()->create(['role' => UserRole::TRAVELER]);

        $this->actingAs($traveler)
            ->post(route('admin.reviews.moderation', ['review' => $review->id, 'lang' => 'en']), [
                'decision' => 'hide',
                'reason' => 'This should not be allowed.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'is_hidden' => false,
        ]);
        $this->assertDatabaseMissing('trust_safety_events', [
            'source_type' => 'review',
            'source_id' => $review->id,
        ]);
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
