<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\InquiryStatus;
use App\Enums\PackageStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Package;
use App\Models\Review;
use App\Models\TravelerIssue;
use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminOverviewData
{
    /**
     * @return array{
     *     travelers: int,
     *     online_users: int,
     *     agencies: int,
     *     agency_statuses: array<string, int>,
     *     pending_verifications: int,
     *     published_packages: int,
     *     bookings: int,
     *     booking_statuses: array<string, int>,
     *     pending_bookings: int,
     *     open_inquiries: int,
     *     open_travel_requests: int,
     *     pending_reviews: int,
     *     flagged_reviews: int,
     *     open_traveler_issues: int,
     *     recent_activity: array<int, array{type: string, title: string, created_at: Carbon}>
     * }
     */
    public function get(): array
    {
        $agencyCounts = Agency::query()
            ->select('verification_status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('verification_status')
            ->pluck('aggregate', 'verification_status');
        $packageCounts = Package::query()
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $bookingCounts = Booking::query()
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $agencyStatuses = collect(VerificationStatus::cases())
            ->mapWithKeys(fn (VerificationStatus $status): array => [
                $status->value => (int) $agencyCounts->get($status->value, 0),
            ])
            ->all();
        $bookingStatuses = collect(BookingStatus::cases())
            ->mapWithKeys(fn (BookingStatus $status): array => [
                $status->value => (int) $bookingCounts->get($status->value, 0),
            ])
            ->all();

        return [
            'travelers' => User::query()->where('role', UserRole::TRAVELER->value)->count(),
            'online_users' => User::query()
                ->where('is_online', true)
                ->where('last_active_at', '>', now()->subMinutes(5))
                ->count(),
            'agencies' => (int) $agencyCounts->sum(),
            'agency_statuses' => $agencyStatuses,
            'pending_verifications' => $agencyStatuses[VerificationStatus::PENDING->value],
            'published_packages' => (int) $packageCounts->get(PackageStatus::PUBLISHED->value, 0),
            'bookings' => (int) $bookingCounts->sum(),
            'booking_statuses' => $bookingStatuses,
            'pending_bookings' => Booking::query()
                ->where('status', BookingStatus::PENDING->value)
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now()))
                ->count(),
            'open_inquiries' => Inquiry::query()->where('status', InquiryStatus::OPEN->value)->count(),
            'open_travel_requests' => TravelRequest::open()->count(),
            'pending_reviews' => Review::query()->where('status', ReviewStatus::PENDING->value)->count(),
            'flagged_reviews' => Review::query()->where('status', ReviewStatus::FLAGGED->value)->count(),
            'open_traveler_issues' => TravelerIssue::query()->where('status', '!=', 'resolved')->count(),
            'recent_activity' => $this->recentActivity(),
        ];
    }

    /**
     * @return array<int, array{type: string, title: string, created_at: Carbon}>
     */
    private function recentActivity(): array
    {
        $travelers = DB::table((new User)->getTable())
            ->selectRaw('? as type, name as title, created_at, id', ['traveler'])
            ->where('role', UserRole::TRAVELER->value)
            ->whereNull('deleted_at');

        $agencies = DB::table((new Agency)->getTable())
            ->selectRaw('? as type, company_name as title, created_at, id', ['agency'])
            ->whereNull('deleted_at');

        $packages = DB::table((new Package)->getTable())
            ->selectRaw('? as type, title, created_at, id', ['package'])
            ->whereNull('deleted_at');

        $bookings = DB::table((new Booking)->getTable())
            ->selectRaw('? as type, booking_reference as title, created_at, id', ['booking']);

        $inquiries = DB::table((new Inquiry)->getTable())
            ->selectRaw('? as type, subject as title, created_at, id', ['inquiry']);

        $travelRequests = DB::table((new TravelRequest)->getTable())
            ->selectRaw('? as type, title, created_at, id', ['travel_request']);

        $travelerIssues = DB::table((new TravelerIssue)->getTable())
            ->selectRaw('? as type, subject as title, created_at, id', ['traveler_issue']);

        $activities = $travelers
            ->unionAll($agencies)
            ->unionAll($packages)
            ->unionAll($bookings)
            ->unionAll($inquiries)
            ->unionAll($travelRequests)
            ->unionAll($travelerIssues);

        return DB::query()
            ->fromSub($activities, 'recent_activity')
            ->select(['type', 'title', 'created_at', 'id'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->orderBy('type')
            ->limit(8)
            ->get()
            ->map(fn (object $activity): array => [
                'type' => $activity->type,
                'title' => $activity->title,
                'created_at' => Carbon::parse($activity->created_at),
            ])
            ->all();
    }
}
