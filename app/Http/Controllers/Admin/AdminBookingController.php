<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\AdminBookingManagementService;
use App\Services\BookingDisputeService;
use App\Services\BookingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminBookingController extends Controller
{
    public function index(Request $request, AdminDashboardController $dashboard, AdminBookingManagementService $management): View
    {
        return $this->listing($request, $dashboard, $management, 'all_bookings');
    }

    public function pending(Request $request, AdminDashboardController $dashboard, AdminBookingManagementService $management): View
    {
        return $this->listing($request, $dashboard, $management, 'pending_bookings');
    }

    public function confirmed(Request $request, AdminDashboardController $dashboard, AdminBookingManagementService $management): View
    {
        return $this->listing($request, $dashboard, $management, 'confirmed_bookings');
    }

    public function completed(Request $request, AdminDashboardController $dashboard, AdminBookingManagementService $management): View
    {
        return $this->listing($request, $dashboard, $management, 'completed_bookings');
    }

    public function cancelledRejected(Request $request, AdminDashboardController $dashboard, AdminBookingManagementService $management): View
    {
        return $this->listing($request, $dashboard, $management, 'cancelled_rejected_bookings');
    }

    public function expired(Request $request, AdminDashboardController $dashboard, AdminBookingManagementService $management): View
    {
        return $this->listing($request, $dashboard, $management, 'expired_bookings');
    }

    public function show(
        Request $request,
        Booking $booking,
        AdminDashboardController $dashboard,
        BookingService $bookingService,
        BookingDisputeService $disputes,
    ): View {
        $this->authorize('viewAdmin', $booking);
        $booking->load([
            'traveler:id,name,email,created_at',
            'agency:id,company_name,user_id',
            'package:id,title,destination,duration_days,agency_id',
            'customRequest:id,reference,title,destination,travel_date',
            'customProposal:id,reference,price,currency',
            'availability:id,package_id,departure_date,capacity,status',
            'statusHistory.actor:id,name',
            'travelerIssues' => fn (Builder $query) => $query
                ->where('type', 'booking_dispute')
                ->with(['assignedAdmin:id,name', 'activities.actor:id,name']),
        ]);

        $timeline = collect([[
            'event_type' => 'created',
            'from_status' => null,
            'to_status' => BookingStatus::PENDING->value,
            'reason' => null,
            'created_at' => $booking->created_at,
            'actor' => null,
        ]]);
        $recordedStatuses = $booking->statusHistory
            ->where('event_type', 'status_transition')
            ->pluck('to_status')
            ->all();

        foreach ([
            BookingStatus::CONFIRMED->value => $booking->confirmed_at,
            BookingStatus::REJECTED->value => $booking->rejected_at,
            BookingStatus::CANCELLED->value => $booking->cancelled_at,
            BookingStatus::COMPLETED->value => $booking->completed_at,
        ] as $status => $timestamp) {
            if ($timestamp !== null && ! in_array($status, $recordedStatuses, true)) {
                $timeline->push([
                    'event_type' => 'legacy_status',
                    'from_status' => null,
                    'to_status' => $status,
                    'reason' => null,
                    'created_at' => $timestamp,
                    'actor' => null,
                ]);
            }
        }

        foreach ($booking->statusHistory as $history) {
            $timeline->push($history);
        }

        $timeline = $timeline->sortBy(fn ($event) => data_get($event, 'created_at'))->values();

        return $dashboard->renderBookingsModule($request, 'all_bookings', 'show', [
            'booking' => $booking,
            'capacitySnapshot' => $booking->availability
                ? $bookingService->capacitySnapshots([$booking->availability])->get($booking->availability->id)
                : null,
            'timeline' => $timeline,
            'admins' => $disputes->administrators(),
        ], __('messages.admin_bookings.details_title'));
    }

    public function transition(
        Request $request,
        Booking $booking,
        AdminBookingManagementService $management,
    ): RedirectResponse {
        $this->authorize('interveneAdmin', $booking);
        $data = $request->validate([
            'status' => ['required', Rule::in([
                BookingStatus::CONFIRMED->value,
                BookingStatus::REJECTED->value,
                BookingStatus::CANCELLED->value,
                BookingStatus::COMPLETED->value,
            ])],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $management->transition($booking, $request->user(), BookingStatus::from($data['status']), $data['reason']);

        return redirect()
            ->route('admin.bookings.show', ['booking' => $booking->id, 'lang' => app()->getLocale()])
            ->with('status', __('messages.admin_bookings.transition_saved'));
    }

    public function note(
        Request $request,
        Booking $booking,
        AdminBookingManagementService $management,
    ): RedirectResponse {
        $this->authorize('interveneAdmin', $booking);
        $data = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:3000'],
        ]);

        $management->addNote($booking, $request->user(), $data['note']);

        return redirect()
            ->route('admin.bookings.show', ['booking' => $booking->id, 'lang' => app()->getLocale()])
            ->with('status', __('messages.admin_bookings.note_saved'));
    }

    public function openDispute(
        Request $request,
        Booking $booking,
        BookingDisputeService $disputes,
    ): RedirectResponse {
        $this->authorize('interveneAdmin', $booking);
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:5', 'max:160'],
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
            'category' => ['required', Rule::in(['booking', 'payment', 'departure', 'service', 'other'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ]);

        $issue = $disputes->openForBooking($booking, $request->user(), $data);

        return redirect()
            ->route('admin.booking-disputes.show', ['issue' => $issue->id, 'lang' => app()->getLocale()])
            ->with('status', __('messages.admin_disputes.case_opened'));
    }

    public function export(Request $request, AdminBookingManagementService $management): StreamedResponse
    {
        $exportPage = $request->validate([
            'page' => ['nullable', Rule::in([
                'all_bookings',
                'pending_bookings',
                'confirmed_bookings',
                'completed_bookings',
                'cancelled_rejected_bookings',
                'expired_bookings',
            ])],
        ])['page'] ?? 'all_bookings';
        $filters = $this->validatedFilters($request);
        $selectedIds = $request->validate([
            'booking_ids' => ['nullable', 'array', 'max:200'],
            'booking_ids.*' => ['integer', 'distinct'],
        ])['booking_ids'] ?? [];

        $query = $management->query($exportPage, $filters)
            ->when($selectedIds !== [], fn (Builder $bookingQuery) => $bookingQuery->whereKey($selectedIds))
            ->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('Unable to open the booking export stream.');
            }
            fputcsv($output, [
                'Booking reference',
                'Traveler name',
                'Traveler email',
                'Agency',
                'Package',
                'Departure date',
                'Traveler count',
                'Unit price snapshot',
                'Total price snapshot',
                'Currency',
                'Booking status',
                'Payment status',
                'Created at',
            ]);

            $query->chunk(500, function ($bookings) use ($output): void {
                foreach ($bookings as $booking) {
                    fputcsv($output, array_map($this->safeCsvValue(...), [
                        $booking->booking_reference,
                        $booking->traveler?->name,
                        $booking->traveler?->email,
                        $booking->agency?->company_name,
                        $booking->package?->title ?? $booking->customRequest?->title,
                        $booking->customRequest?->travel_date?->toDateString() ?? $booking->availability?->departure_date?->toDateString(),
                        $booking->traveler_count,
                        $booking->unit_price,
                        $booking->total_amount,
                        $booking->currency,
                        $booking->status->value,
                        $booking->payment_status->value,
                        $booking->created_at?->toDateTimeString(),
                    ]));
                }
            });

            fclose($output);
        }, 'bookings.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_map(
                fn (BookingStatus $status): string => $status->value,
                BookingStatus::cases(),
            ))],
            'payment_status' => ['nullable', Rule::in(array_map(
                fn (PaymentStatus $status): string => $status->value,
                PaymentStatus::cases(),
            ))],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
            'departure_from' => ['nullable', 'date_format:Y-m-d'],
            'departure_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:departure_from'],
            'agency' => ['nullable', 'string', 'max:160'],
            'package' => ['nullable', 'string', 'max:191'],
            'currency' => ['nullable', 'regex:/^[A-Za-z]{3}$/'],
            'sort' => ['nullable', Rule::in(['newest', 'departure', 'amount', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
    }

    private function listing(
        Request $request,
        AdminDashboardController $dashboard,
        AdminBookingManagementService $management,
        string $page,
    ): View {
        $filters = $this->validatedFilters($request);
        $data = [
            'page' => $page,
            'filters' => $filters,
            'bookings' => $management->listing($page, $filters),
            'summary' => $management->summary(),
            'currencies' => Booking::query()->distinct()->orderBy('currency')->pluck('currency'),
        ];

        return $dashboard->renderBookingsModule(
            $request,
            $page,
            $page === 'all_bookings' ? 'index' : match ($page) {
                'pending_bookings' => 'pending',
                'confirmed_bookings' => 'confirmed',
                'completed_bookings' => 'completed',
                'cancelled_rejected_bookings' => 'cancelled-rejected',
                default => 'expired',
            },
            $data,
        );
    }

    private function safeCsvValue(mixed $value): string|int|float|null
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
