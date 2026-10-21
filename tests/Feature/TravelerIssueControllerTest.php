<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\TravelerIssue;
use App\Models\User;
use App\Notifications\AdminTravelerIssueReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TravelerIssueControllerTest extends TestCase
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

    public function test_guest_is_redirected_from_issue_intake(): void
    {
        $this->get(route('account.issues.create'))->assertRedirect(route('login'));
    }

    public function test_only_travelers_can_open_issue_intake(): void
    {
        $agencyOwner = User::factory()->withRole(UserRole::AGENCY_OWNER)->create();

        $this->actingAs($agencyOwner)
            ->get(route('account.issues.create'))
            ->assertForbidden();
    }

    public function test_traveler_cannot_submit_a_dispute_for_another_travelers_booking(): void
    {
        $traveler = User::factory()->create();
        $otherTraveler = User::factory()->create();
        $booking = $this->createBookingFor($otherTraveler);

        $this->actingAs($traveler)
            ->post(route('account.issues.store'), [
                'type' => 'booking_dispute',
                'booking_id' => $booking->id,
                'subject' => 'Booking not honored',
                'description' => 'The agency did not honor my confirmed booking.',
            ])
            ->assertSessionHasErrors([
                'booking_id' => __('messages.traveler_issues.booking_not_owned'),
            ]);

        $this->assertDatabaseCount('traveler_issues', 0);
    }

    public function test_traveler_can_submit_and_track_a_booking_dispute(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $traveler = User::factory()->create();
        $booking = $this->createBookingFor($traveler);

        $response = $this->actingAs($traveler)->post(route('account.issues.store'), [
            'type' => 'booking_dispute',
            'booking_id' => $booking->id,
            'subject' => 'Booking not honored',
            'description' => 'The agency did not honor my confirmed booking.',
        ]);

        $issue = TravelerIssue::query()->firstOrFail();
        $response->assertRedirect(route('account.issues.show', [
            'issue' => $issue->id,
            'lang' => 'en',
        ]));
        $this->assertDatabaseHas('traveler_issues', [
            'id' => $issue->id,
            'user_id' => $traveler->id,
            'booking_id' => $booking->id,
            'type' => 'booking_dispute',
            'status' => 'open',
        ]);
        $adminNotification = $admin->notifications()
            ->where('type', AdminTravelerIssueReceived::class)
            ->firstOrFail();
        $this->assertSame([
            'issue_id' => $issue->id,
            'issue_type' => 'booking_dispute',
            'subject' => 'Booking not honored',
        ], $adminNotification->data);

        $this->get(route('account.issues.show', ['issue' => $issue->id]))
            ->assertSee('Booking not honored')
            ->assertSee($booking->booking_reference);
    }

    public function test_traveler_issue_pages_keep_the_workspace_sidebar_visible(): void
    {
        $traveler = User::factory()->create();
        $issue = $traveler->travelerIssues()->create([
            'type' => 'complaint',
            'subject' => 'Support needed with an agency',
            'description' => 'I need help resolving an issue with an agency.',
        ]);

        $this->actingAs($traveler);

        foreach ([
            route('account.issues.index'),
            route('account.issues.create'),
            route('account.issues.show', ['issue' => $issue->id]),
        ] as $url) {
            $this->get($url)
                ->assertSee('data-account-sidebar', false)
                ->assertSee(route('account.issues.index', ['lang' => 'en']))
                ->assertSee(__('messages.traveler_issues.my_reports'));
        }
    }

    public function test_traveler_cannot_view_another_travelers_issue(): void
    {
        $traveler = User::factory()->create();
        $otherTraveler = User::factory()->create();
        $issue = $otherTraveler->travelerIssues()->create([
            'type' => 'complaint',
            'subject' => 'A private complaint',
            'description' => 'This private complaint belongs to another traveler.',
        ]);

        $this->actingAs($traveler)
            ->get(route('account.issues.show', ['issue' => $issue->id]))
            ->assertNotFound();
    }

    private function createBookingFor(User $traveler): Booking
    {
        $agency = Agency::factory()->create();
        $package = Package::factory()->for($agency)->create();
        $availability = PackageAvailability::query()->create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonth()->toDateString(),
            'capacity' => 10,
        ]);

        return Booking::query()->create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'booking_reference' => 'TC-ISSUE-'.$traveler->id,
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'traveler_count' => 1,
            'unit_price' => 100,
            'total_amount' => 100,
            'currency' => 'XAF',
        ]);
    }
}
