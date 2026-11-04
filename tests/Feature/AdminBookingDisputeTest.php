<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\TravelerIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminBookingDisputeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(AdminSecondFactor::class, Mockery::mock(AdminSecondFactor::class));
    }

    public function test_admin_can_open_assign_resolve_and_note_a_real_booking_dispute(): void
    {
        $admin = $this->actingAsVerifiedAdmin();
        $assignee = User::factory()->withRole(UserRole::ADMIN)->create();
        $booking = $this->createBooking();

        $this->actingAs($admin)
            ->post(route('admin.bookings.disputes.store', ['booking' => $booking->id, 'lang' => 'en']), [
                'subject' => 'Departure details need review',
                'reason' => 'The traveler reported conflicting departure information.',
                'category' => 'departure',
                'priority' => 'high',
            ])
            ->assertRedirect();

        $issue = TravelerIssue::query()->where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame('booking_dispute', $issue->type);
        $this->assertSame('departure', $issue->category);
        $this->assertSame('open', $issue->status);
        $this->assertSame($admin->id, $issue->opened_by_admin_id);

        $this->actingAs($admin)
            ->patch(route('admin.booking-disputes.assignment', ['issue' => $issue->id, 'lang' => 'en']), [
                'assigned_admin_id' => $assignee->id,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('admin.booking-disputes.status', ['issue' => $issue->id, 'lang' => 'en']), [
                'status' => 'in_review',
                'reason' => 'The submitted departure details are being checked.',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.booking-disputes.notes.store', ['issue' => $issue->id, 'lang' => 'en']), [
                'note' => 'Compared the itinerary against the stored departure record.',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('admin.booking-disputes.status', ['issue' => $issue->id, 'lang' => 'en']), [
                'status' => 'resolved',
                'reason' => 'The itinerary was corrected and the traveler was informed.',
            ])
            ->assertRedirect();

        $issue->refresh();
        $this->assertSame('resolved', $issue->status);
        $this->assertSame($assignee->id, $issue->assigned_admin_id);
        $this->assertSame('The itinerary was corrected and the traveler was informed.', $issue->resolution_reason);
        $this->assertNotNull($issue->resolved_at);
        $this->assertSame('pending', $booking->fresh()->status->value);
        $this->assertSame(PaymentStatus::UNPAID, $booking->fresh()->payment_status);
        $this->assertDatabaseHas('booking_dispute_activities', [
            'traveler_issue_id' => $issue->id,
            'event_type' => 'internal_note',
            'note' => 'Compared the itinerary against the stored departure record.',
        ]);
        $this->assertSame(5, $issue->activities()->count());

        $this->actingAs($booking->traveler)
            ->get(route('account.issues.show', ['issue' => $issue->id, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('The traveler reported conflicting departure information.')
            ->assertDontSee('Compared the itinerary against the stored departure record.');
    }

    public function test_dispute_filters_use_booking_and_case_data(): void
    {
        $admin = $this->actingAsVerifiedAdmin();
        $booking = $this->createBooking('TC-DISPUTE-SEARCH-001');
        $issue = $booking->traveler->travelerIssues()->create([
            'booking_id' => $booking->id,
            'type' => 'booking_dispute',
            'category' => 'booking',
            'subject' => 'Traveler name mismatch report',
            'description' => 'A report linked to this specific booking.',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $otherBooking = $this->createBooking('TC-DISPUTE-SEARCH-002', travelerName: 'Unrelated Traveler');
        $otherBooking->traveler->travelerIssues()->create([
            'booking_id' => $otherBooking->id,
            'type' => 'booking_dispute',
            'category' => 'other',
            'subject' => 'Unrelated case',
            'description' => 'A separate report.',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.booking-disputes.index', [
                'lang' => 'en',
                'search' => 'TC-DISPUTE-SEARCH-001',
                'category' => 'booking',
            ]))
            ->assertOk()
            ->assertSee($issue->subject)
            ->assertDontSee('Unrelated case');
    }

    public function test_invalid_dispute_transition_is_rejected_without_overwriting_history(): void
    {
        $admin = $this->actingAsVerifiedAdmin();
        $booking = $this->createBooking();
        $issue = $booking->traveler->travelerIssues()->create([
            'booking_id' => $booking->id,
            'type' => 'booking_dispute',
            'category' => 'general',
            'subject' => 'Dismissed case',
            'description' => 'This case has been dismissed for a documented reason.',
            'status' => 'dismissed',
            'priority' => 'normal',
            'resolution_reason' => 'The reported issue was not substantiated.',
            'resolved_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.booking-disputes.status', ['issue' => $issue->id, 'lang' => 'en']), [
                'status' => 'under_review',
                'reason' => 'Trying an unsupported transition.',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('dismissed', $issue->fresh()->status);
        $this->assertSame('The reported issue was not substantiated.', $issue->fresh()->resolution_reason);
        $this->assertDatabaseMissing('booking_dispute_activities', [
            'traveler_issue_id' => $issue->id,
            'event_type' => 'status_changed',
        ]);
    }

    public function test_reopening_a_resolved_dispute_clears_its_resolution_metadata(): void
    {
        $admin = $this->actingAsVerifiedAdmin();
        $booking = $this->createBooking();
        $issue = $booking->traveler->travelerIssues()->create([
            'booking_id' => $booking->id,
            'type' => 'booking_dispute',
            'category' => 'general',
            'subject' => 'Previously resolved case',
            'description' => 'The reported issue was previously resolved.',
            'status' => 'resolved',
            'priority' => 'normal',
            'resolution_reason' => 'The itinerary was corrected.',
            'resolved_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.booking-disputes.status', ['issue' => $issue->id, 'lang' => 'en']), [
                'status' => 'in_review',
                'reason' => 'New evidence requires the case to be reviewed again.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('traveler_issues', [
            'id' => $issue->id,
            'status' => 'in_review',
            'resolution_reason' => null,
            'resolved_at' => null,
        ]);
        $this->assertDatabaseHas('booking_dispute_activities', [
            'traveler_issue_id' => $issue->id,
            'event_type' => 'status_changed',
            'from_status' => 'resolved',
            'to_status' => 'in_review',
            'note' => 'New evidence requires the case to be reviewed again.',
        ]);
    }

    public function test_non_admin_cannot_manage_booking_dispute_cases(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($booking->traveler)
            ->get(route('admin.booking-disputes.index'))
            ->assertForbidden();

        $this->actingAs($booking->traveler)
            ->post(route('admin.bookings.disputes.store', ['booking' => $booking->id]), [
                'subject' => 'Unauthorized case creation',
                'reason' => 'This request must not create an administrative case.',
                'category' => 'booking',
                'priority' => 'high',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('traveler_issues', [
            'booking_id' => $booking->id,
            'type' => 'booking_dispute',
        ]);
    }

    private function actingAsVerifiedAdmin(): User
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

    private function createBooking(string $reference = 'TC-DISPUTE-BOOKING-001', string $travelerName = 'Dispute Traveler'): Booking
    {
        $agency = Agency::factory()->create(['company_name' => 'Dispute Travel Agency']);
        $package = Package::factory()->for($agency)->create([
            'title' => 'Dispute Package',
            'status' => 'published',
        ]);
        $availability = PackageAvailability::query()->create([
            'package_id' => $package->id,
            'departure_date' => now()->addMonths(2)->toDateString(),
            'capacity' => 8,
            'status' => 'active',
        ]);
        $traveler = User::factory()->create([
            'name' => $travelerName,
            'role' => UserRole::TRAVELER,
        ]);

        return Booking::query()->create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'booking_reference' => $reference,
            'status' => 'pending',
            'payment_status' => PaymentStatus::UNPAID,
            'traveler_count' => 1,
            'unit_price' => '500.00',
            'total_amount' => '500.00',
            'currency' => 'XAF',
            'expires_at' => now()->addHour(),
        ]);
    }
}
