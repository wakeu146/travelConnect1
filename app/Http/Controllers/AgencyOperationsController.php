<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgencyOperationsController extends Controller
{
    private function currentAgency(): Agency
    {
        return auth()->user()->agency()->firstOrFail();
    }

    public function customers(Request $request): View
    {
        $agency = $this->currentAgency();
        $search = trim((string) $request->query('search', ''));

        $customers = User::query()
            ->whereHas('bookings', fn ($query) => $query->where('agency_id', $agency->id))
            ->withCount(['bookings as agency_bookings_count' => fn ($query) => $query->where('agency_id', $agency->id)])
            ->withMax(['bookings as last_agency_booking_at' => fn ($query) => $query->where('agency_id', $agency->id)], 'created_at')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20)
            ->appends($request->query());
        $customerValues = Booking::query()
            ->where('agency_id', $agency->id)
            ->whereIn('user_id', $customers->getCollection()->modelKeys())
            ->selectRaw('user_id, currency, SUM(total_amount) as booking_value')
            ->groupBy('user_id', 'currency')
            ->get()
            ->groupBy('user_id');

        return view('agencies.customers.index', compact('agency', 'customers', 'customerValues', 'search'));
    }

    public function reviews(Request $request): View
    {
        $agency = $this->currentAgency();
        $status = $request->query('status');
        $rating = $request->query('rating');
        $validStatuses = array_map(fn (ReviewStatus $value) => $value->value, ReviewStatus::cases());

        $reviews = $agency->reviews()
            ->with('user:id,name')
            ->when($status && in_array($status, $validStatuses, true), fn ($query) => $query->where('status', $status))
            ->when($rating && in_array((int) $rating, [1, 2, 3, 4, 5], true), fn ($query) => $query->where('rating', (int) $rating))
            ->latest()
            ->paginate(20)
            ->appends($request->query());

        $publishedReviews = $agency->reviews()->where('status', ReviewStatus::PUBLISHED->value);
        $ratingCounts = (clone $publishedReviews)->selectRaw('rating, COUNT(*) as review_count')->groupBy('rating')->pluck('review_count', 'rating');

        return view('agencies.reviews.index', [
            'agency' => $agency,
            'reviews' => $reviews,
            'status' => $status ?? '',
            'rating' => $rating ?? '',
            'publishedReviewCount' => (clone $publishedReviews)->count(),
            'averageRating' => (float) ((clone $publishedReviews)->avg('rating') ?? 0),
            'ratingCounts' => $ratingCounts,
        ]);
    }

    public function payments(Request $request): View
    {
        $agency = $this->currentAgency();
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('payment_status');
        $query = $agency->bookings()->with(['package:id,title', 'traveler:id,name', 'availability:id,departure_date']);

        if ($search !== '') {
            $query->where(function ($bookingQuery) use ($search): void {
                $bookingQuery->where('booking_reference', 'like', "%{$search}%")
                    ->orWhereHas('traveler', fn ($travelerQuery) => $travelerQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('package', fn ($packageQuery) => $packageQuery->where('title', 'like', "%{$search}%"));
            });
        }

        $validPaymentStatuses = array_map(fn (PaymentStatus $value) => $value->value, PaymentStatus::cases());
        if ($status && in_array($status, $validPaymentStatuses, true)) {
            $query->where('payment_status', $status);
        }

        $bookings = $query->latest()->paginate(20)->appends($request->query());

        $currencyTotals = $agency->bookings()
            ->selectRaw('currency, SUM(total_amount) as booked_value, SUM(CASE WHEN payment_status = ? THEN total_amount ELSE 0 END) as paid_value, SUM(CASE WHEN payment_status = ? THEN total_amount ELSE 0 END) as unpaid_value', [PaymentStatus::PAID->value, PaymentStatus::UNPAID->value])
            ->groupBy('currency')
            ->get();

        return view('agencies.payments.index', [
            'agency' => $agency,
            'bookings' => $bookings,
            'search' => $search,
            'paymentStatus' => $status ?? '',
            'currencyTotals' => $currencyTotals,
            'paidBookingCount' => $agency->bookings()->where('payment_status', PaymentStatus::PAID->value)->count(),
        ]);
    }

    public function analytics(Request $request): View
    {
        $agency = $this->currentAgency();
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = Carbon::parse($filters['from'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = Carbon::parse($filters['to'] ?? now()->toDateString())->endOfDay();
        $bookings = $agency->bookings()->whereBetween('created_at', [$from, $to]);
        $daily = (clone $bookings)
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as booking_count, SUM(traveler_count) as traveler_count, SUM(total_amount) as booked_value')
            ->groupBy('activity_date')
            ->orderBy('activity_date')
            ->get();
        $statusCounts = (clone $bookings)->selectRaw('status, COUNT(*) as status_count')->groupBy('status')->pluck('status_count', 'status');
        $currencyTotals = (clone $bookings)
            ->selectRaw('currency, SUM(total_amount) as booked_value, SUM(CASE WHEN payment_status = ? THEN total_amount ELSE 0 END) as paid_value', [PaymentStatus::PAID->value])
            ->groupBy('currency')
            ->get();

        return view('agencies.analytics.index', [
            'agency' => $agency,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'bookingCount' => (clone $bookings)->count(),
            'travelerCount' => (clone $bookings)->sum('traveler_count'),
            'currencyTotals' => $currencyTotals,
            'statusCounts' => $statusCounts,
            'daily' => $daily,
            'packageCount' => $agency->packages()->count(),
        ]);
    }
}