<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\AgencyDocument;
use App\Models\Booking;
use App\Models\Service;
use App\Models\TravelRequest;
use App\Models\TravelRequestProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomTravelRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_traveler_publishes_to_selected_eligible_agencies_only(): void
    {
        $traveler = User::factory()->create(['role' => UserRole::TRAVELER]);
        $selectedAgency = $this->verifiedAgency();
        $otherAgency = $this->verifiedAgency();
        $unverifiedAgency = Agency::factory()->create();
        $service = Service::factory()->create();

        $response = $this->actingAs($traveler)->post(route('account.custom-requests.store'), $this->requestPayload(
            [$service->id],
            [$selectedAgency->id],
        ));

        $travelRequest = TravelRequest::query()->firstOrFail();
        $response->assertRedirect(route('account.custom-requests.show', [
            'travelRequest' => $travelRequest->id,
            'lang' => app()->getLocale(),
        ]));
        $this->assertSame([$selectedAgency->id], $travelRequest->recipients()->pluck('agency_id')->all());
        $this->assertNotNull($travelRequest->reference);
        $this->assertSame(1, $selectedAgency->owner->notifications()->whereJsonContains('data->type', 'custom_request')->count());
        $this->assertSame(0, $otherAgency->owner->notifications()->count());
        $this->assertSame(0, $unverifiedAgency->owner->notifications()->count());
    }

    public function test_traveler_can_share_with_all_verified_active_agencies_and_cannot_target_an_unverified_agency(): void
    {
        $traveler = User::factory()->create(['role' => UserRole::TRAVELER]);
        $firstAgency = $this->verifiedAgency();
        $secondAgency = $this->verifiedAgency();
        $unverifiedAgency = Agency::factory()->create();
        $service = Service::factory()->create();

        $this->actingAs($traveler)->post(route('account.custom-requests.store'), $this->requestPayload(
            [$service->id],
            [],
            'all',
        ))->assertRedirect()->assertSessionHasNoErrors();

        $travelRequest = TravelRequest::query()->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$firstAgency->id, $secondAgency->id],
            $travelRequest->recipients()->pluck('agency_id')->all(),
        );
        $this->assertSame('all', $travelRequest->sharing_mode);

        $this->actingAs(User::factory()->create(['role' => UserRole::TRAVELER]))
            ->post(route('account.custom-requests.store'), $this->requestPayload(
                [$service->id],
                [$unverifiedAgency->id],
            ))
            ->assertSessionHasErrors('agency_ids');
    }

    public function test_agency_can_open_and_explicitly_mark_only_its_request_as_read(): void
    {
        $traveler = User::factory()->create(['role' => UserRole::TRAVELER]);
        $agency = $this->verifiedAgency();
        $otherAgency = $this->verifiedAgency();
        $travelRequest = $this->publishedRequest($traveler, $agency);
        $otherRequest = $this->publishedRequest($traveler, $otherAgency);

        $this->actingAs($agency->owner)
            ->get(route('agency.custom-requests.show', ['travelRequest' => $travelRequest->id]))
            ->assertOk()
            ->assertSee($travelRequest->reference);

        $recipient = $travelRequest->recipients()->firstOrFail();
        $this->assertNotNull($recipient->first_viewed_at);
        $this->assertNull($recipient->read_at);

        $this->actingAs($agency->owner)
            ->patch(route('agency.custom-requests.read', ['travelRequest' => $travelRequest->id]))
            ->assertRedirect();
        $this->assertNotNull($recipient->fresh()->read_at);
        $this->assertDatabaseMissing('travel_request_proposals', ['travel_request_id' => $travelRequest->id]);

        $this->actingAs($agency->owner)
            ->get(route('agency.custom-requests.show', ['travelRequest' => $otherRequest->id]))
            ->assertNotFound();
    }

    public function test_traveler_selects_one_proposal_and_creates_a_pending_unpaid_custom_booking(): void
    {
        $traveler = User::factory()->create(['role' => UserRole::TRAVELER]);
        $selectedAgency = $this->verifiedAgency();
        $competingAgency = $this->verifiedAgency();
        $travelRequest = $this->publishedRequest($traveler, $selectedAgency, $competingAgency);

        $selectedProposal = $this->submitProposal($travelRequest, $selectedAgency, 4500);
        $competingProposal = $this->submitProposal($travelRequest, $competingAgency, 5100);

        $this->actingAs($traveler)->post(route('account.custom-requests.proposals.select', [
            'travelRequest' => $travelRequest->id,
            'proposal' => $selectedProposal->id,
        ]), ['confirm' => '1'])->assertRedirect();

        $booking = Booking::query()->where('custom_request_id', $travelRequest->id)->firstOrFail();
        $this->assertSame('custom', $booking->booking_type);
        $this->assertNull($booking->package_id);
        $this->assertNull($booking->package_availability_id);
        $this->assertSame((float) 4500, (float) $booking->total_amount);
        $this->assertSame(PaymentStatus::UNPAID, $booking->payment_status);
        $this->assertSame($selectedProposal->reference, $booking->quote_snapshot['reference']);
        $this->assertSame('converted', $travelRequest->fresh()->status);
        $this->assertSame('selected', $selectedProposal->fresh()->status);
        $this->assertSame('not_selected', $competingProposal->fresh()->status);
        $this->assertSame(1, Booking::query()->where('custom_request_id', $travelRequest->id)->count());

        $this->actingAs($traveler)
            ->get(route('account.bookings'))
            ->assertOk()
            ->assertSee($travelRequest->title)
            ->assertSee($travelRequest->travel_date->translatedFormat('d M Y'));
        $this->actingAs($selectedAgency->owner)
            ->get(route('agency.bookings.index'))
            ->assertOk()
            ->assertSee($travelRequest->title)
            ->assertSee($travelRequest->travel_date->translatedFormat('d M Y'));

        $this->actingAs($traveler)->post(route('account.custom-requests.proposals.select', [
            'travelRequest' => $travelRequest->id,
            'proposal' => $selectedProposal->id,
        ]), ['confirm' => '1'])->assertRedirect()->assertSessionHasErrors('proposal');
        $this->assertSame(1, Booking::query()->where('custom_request_id', $travelRequest->id)->count());

        $this->actingAs($competingAgency->owner)
            ->get(route('agency.custom-requests.show', ['travelRequest' => $travelRequest->id]))
            ->assertOk();
        $this->actingAs($competingAgency->owner)
            ->get(route('agency.custom-requests.proposal.create', ['travelRequest' => $travelRequest->id]))
            ->assertNotFound();
    }

    public function test_super_admin_can_hold_request_and_preserve_private_notes(): void
    {
        $traveler = User::factory()->create(['role' => UserRole::TRAVELER]);
        $agency = $this->verifiedAgency();
        $travelRequest = $this->publishedRequest($traveler, $agency);
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $this->actingAs($admin)->withSession([
            'admin_login.verified_user_id' => $admin->id,
            'admin_login.session_version' => $admin->admin_session_version,
            'admin_login.verified_at' => now()->timestamp,
            'admin_login.last_activity' => now()->timestamp,
        ]);

        $this->post(route('admin.custom-requests.moderate', [
            'travelRequest' => $travelRequest->id,
            'action' => 'hold',
        ]), ['reason' => 'Reviewing request details'])->assertRedirect();

        $this->assertSame('on_hold', $travelRequest->fresh()->status);
        $this->assertSame('on_hold', $travelRequest->recipients()->firstOrFail()->status);
        $this->actingAs($agency->owner)
            ->get(route('agency.custom-requests.show', ['travelRequest' => $travelRequest->id]))
            ->assertForbidden();

        $this->actingAs($admin)->withSession([
            'admin_login.verified_user_id' => $admin->id,
            'admin_login.session_version' => $admin->admin_session_version,
            'admin_login.verified_at' => now()->timestamp,
            'admin_login.last_activity' => now()->timestamp,
        ])->post(route('admin.custom-requests.notes.store', ['travelRequest' => $travelRequest->id]), [
            'content' => 'Internal investigation note.',
        ])->assertRedirect();

        $this->actingAs($traveler)->get(route('account.custom-requests.show', ['travelRequest' => $travelRequest->id]))
            ->assertOk()
            ->assertDontSee('Internal investigation note.');
        $this->assertDatabaseHas('travel_request_admin_notes', [
            'travel_request_id' => $travelRequest->id,
            'admin_id' => $admin->id,
            'content' => 'Internal investigation note.',
        ]);
    }

    private function verifiedAgency(): Agency
    {
        $agency = Agency::factory()->create(['verification_status' => 'verified']);
        AgencyDocument::factory()->create(['agency_id' => $agency->id, 'status' => 'approved']);

        return $agency;
    }

    /**
     * @param  list<int>  $serviceIds
     * @param  list<int>  $agencyIds
     * @return array<string, mixed>
     */
    private function requestPayload(array $serviceIds, array $agencyIds, string $sharingMode = 'selected'): array
    {
        return [
            'title' => 'Family trip to Japan',
            'origin' => 'Paris',
            'destination' => 'Tokyo',
            'destination_country' => 'Japan',
            'travel_date' => today()->addMonths(3)->toDateString(),
            'return_date' => today()->addMonths(3)->addDays(10)->toDateString(),
            'traveler_count' => 3,
            'budget_min' => 3000,
            'budget_max' => 6000,
            'budget_currency' => config('travelconnect.currency'),
            'description' => 'A family trip with a mix of city visits and outdoor activities.',
            'service_ids' => $serviceIds,
            'agency_ids' => $agencyIds,
            'sharing_mode' => $sharingMode,
        ];
    }

    private function publishedRequest(User $traveler, Agency ...$agencies): TravelRequest
    {
        $service = Service::factory()->create();
        $response = $this->actingAs($traveler)->post(route('account.custom-requests.store'), $this->requestPayload(
            [$service->id],
            collect($agencies)->pluck('id')->all(),
        ));
        $response->assertRedirect();

        return TravelRequest::query()->latest('id')->firstOrFail();
    }

    private function submitProposal(TravelRequest $travelRequest, Agency $agency, float $price): TravelRequestProposal
    {
        $this->actingAs($agency->owner)
            ->post(route('agency.custom-requests.proposal.store', ['travelRequest' => $travelRequest->id]), [
                'price' => $price,
                'currency' => config('travelconnect.currency'),
                'services_included_text' => 'Accommodation',
                'services_excluded_text' => 'International flights',
                'itinerary' => 'A ten-day Tokyo and Kyoto itinerary.',
                'message' => 'We can arrange this family trip with a private guide.',
                'terms' => 'Payment terms will be agreed separately.',
                'valid_until' => today()->addDays(14)->toDateString(),
            ])
            ->assertRedirect();

        return $travelRequest->proposals()->where('agency_id', $agency->id)->firstOrFail();
    }
}
