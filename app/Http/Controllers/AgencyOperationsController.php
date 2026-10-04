<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Notifications\AgencyActivityNotification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function inquiries(Request $request): View
    {
        $agency = $this->currentAgency();
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $validStatuses = ['open', 'responded', 'closed'];

        $inquiries = $agency->inquiries()
            ->with('user:id,name')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
            }))
            ->when($status && in_array($status, $validStatuses, true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->appends($request->query());

        return view('agencies.inquiries.index', [
            'agency' => $agency,
            'inquiries' => $inquiries,
            'search' => $search,
            'status' => $status ?? '',
        ]);
    }

    public function reviews(Request $request): View
    {
        $agency = $this->currentAgency();
        $status = $request->query('status');
        $rating = $request->query('rating');
        $search = trim((string) $request->query('search', ''));
        $validStatuses = array_map(fn (ReviewStatus $value) => $value->value, ReviewStatus::cases());

        $reviews = $agency->reviews()
            ->with(['user:id,name,email,phone,profile_photo_path', 'booking:id,booking_reference,package_id,status', 'booking.package:id,title,destination'])
            ->when($search !== '', fn ($query) => $query->where(function ($reviewQuery) use ($search): void {
                $reviewQuery->where('comment', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('booking', fn ($bookingQuery) => $bookingQuery->where('booking_reference', 'like', "%{$search}%"));
            }))
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
            'search' => $search,
            'status' => $status ?? '',
            'rating' => $rating ?? '',
            'publishedReviewCount' => (clone $publishedReviews)->count(),
            'averageRating' => (float) ((clone $publishedReviews)->avg('rating') ?? 0),
            'ratingCounts' => $ratingCounts,
        ]);
    }

    public function respondToReview(Request $request, Review $review): RedirectResponse
    {
        $agency = $this->currentAgency();
        abort_unless($review->agency_id === $agency->id, 404);
        abort_unless($review->status === ReviewStatus::PUBLISHED, 403);

        $data = $request->validate([
            'agency_response' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $review->update([
            'agency_response' => $data['agency_response'],
            'agency_responded_at' => now(),
        ]);

        $review->user?->notify(new AgencyActivityNotification(
            __('messages.review_response_notification_title'),
            __('messages.review_response_notification', ['agency' => $agency->company_name]),
            'review_response',
            route('agency.show', ['slug' => Str::slug($agency->company_name), 'lang' => app()->getLocale()]).'#reviews'
        ));

        return back()->with('status', __('messages.review_response_saved'));
    }

    public function publishReview(Review $review): RedirectResponse
    {
        $agency = $this->currentAgency();
        abort_unless($review->agency_id === $agency->id, 404);
        abort_unless($review->status === ReviewStatus::PENDING, 409);

        DB::transaction(function () use ($agency, $review): void {
            $lockedReview = Review::query()
                ->where('agency_id', $agency->id)
                ->whereKey($review->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedReview->status === ReviewStatus::PENDING, 409);

            $lockedReview->update(['status' => ReviewStatus::PUBLISHED]);
            $agency->refresh()->recalculateTrustScoreFromPublishedReviews();
        });

        return back()->with('status', __('messages.review_published'));
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
