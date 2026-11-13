<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Agency;
use App\Models\AgencyModerationEvent;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Package;
use App\Models\TravelRequest;
use App\Models\TravelRequestRecipient;
use App\Models\TrustScoreLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsManagementService
{
    /**
     * @param  array{created_from?: string, created_to?: string}  $filters
     * @return array{
     *     from: string,
     *     to: string,
     *     metrics: array<string, int>,
     *     growthTrend: array<string, mixed>,
     *     activityTrend: array<string, mixed>,
     *     bookingTrend: array<string, mixed>,
     *     verificationTrend: array<string, mixed>,
     *     trustScoreTrend: array<string, mixed>,
     *     userRoles: array<int, array{role: string, count: int, percent: float}>,
     *     bookingStatuses: array<int, array{status: string, count: int, percent: float}>,
     *     verificationStatuses: array<int, array{status: string, count: int, percent: float}>,
     *     destinations: array<int, array{name: string, count: int, percent: float}>,
     *     packages: array<int, array{name: string, count: int, percent: float}>,
     *     daily: array<int, array<string, int|string>>
     * }
     */
    public function pageData(array $filters): array
    {
        $to = isset($filters['created_to'])
            ? CarbonImmutable::parse($filters['created_to'])->endOfDay()
            : CarbonImmutable::instance(now())->endOfDay();
        $from = isset($filters['created_from'])
            ? CarbonImmutable::parse($filters['created_from'])->startOfDay()
            : $to->subDays(29)->startOfDay();
        $dates = $this->chartDates($from, $to);

        $usersByDate = $this->dailyCounts(User::withTrashed(), 'created_at', $from, $to);
        $agenciesByDate = $this->dailyCounts(Agency::withTrashed(), 'created_at', $from, $to);
        $publishedPackagesByDate = $this->dailyCounts(
            Package::withTrashed()->whereNotNull('published_at'),
            'published_at',
            $from,
            $to,
        );
        $bookingsByDate = $this->dailyCounts(Booking::query(), 'created_at', $from, $to);
        $cancellationsByDate = $this->dailyCounts(
            Booking::query()->whereNotNull('cancelled_at'),
            'cancelled_at',
            $from,
            $to,
        );
        $requestsByDate = $this->dailyCounts(TravelRequest::query(), 'created_at', $from, $to);
        $inquiriesByDate = $this->dailyCounts(Inquiry::query(), 'created_at', $from, $to);
        $requestViewsByDate = $this->dailyCounts(
            TravelRequestRecipient::query()->whereNotNull('first_viewed_at'),
            'first_viewed_at',
            $from,
            $to,
        );

        $growthTrend = $this->trendChart($dates, $from, $to, [
            'users' => ['color' => '#2563eb', 'values' => $usersByDate],
            'agencies' => ['color' => '#0f766e', 'values' => $agenciesByDate],
            'publications' => ['color' => '#d97706', 'values' => $publishedPackagesByDate],
        ]);
        $userGrowthTrend = $this->trendChart($dates, $from, $to, [
            'users' => ['color' => '#2563eb', 'values' => $usersByDate],
        ]);
        $agencyGrowthTrend = $this->trendChart($dates, $from, $to, [
            'agencies' => [
                'color' => '#0f766e',
                'values' => $this->cumulativeCounts($dates, $from, $agenciesByDate),
            ],
        ]);
        $activityTrend = $this->trendChart($dates, $from, $to, [
            'bookings' => ['color' => '#4f46e5', 'values' => $bookingsByDate],
            'cancellations' => ['color' => '#dc2626', 'values' => $cancellationsByDate],
            'requests' => ['color' => '#0f766e', 'values' => $requestsByDate],
            'inquiries' => ['color' => '#d97706', 'values' => $inquiriesByDate],
            'request_views' => ['color' => '#db2777', 'values' => $requestViewsByDate],
        ]);
        $bookingTrend = $this->trendChart($dates, $from, $to, [
            'bookings' => ['color' => '#4f46e5', 'values' => $bookingsByDate],
            'cancellations' => ['color' => '#dc2626', 'values' => $cancellationsByDate],
        ]);

        $verificationEventCounts = $this->dailyGroupedCounts(
            AgencyModerationEvent::query()
                ->whereNotNull('new_verification_status')
                ->whereColumn('new_verification_status', '!=', 'previous_verification_status'),
            'created_at',
            'new_verification_status',
            $from,
            $to,
        );
        $verificationTrend = $this->trendChart($dates, $from, $to, [
            'pending' => ['color' => '#d97706', 'values' => $verificationEventCounts[VerificationStatus::PENDING->value] ?? []],
            'verified' => ['color' => '#0f766e', 'values' => $verificationEventCounts[VerificationStatus::VERIFIED->value] ?? []],
            'rejected' => ['color' => '#dc2626', 'values' => $verificationEventCounts[VerificationStatus::REJECTED->value] ?? []],
        ]);
        $trustScoreTrend = $this->trendChart($dates, $from, $to, [
            'average_score' => [
                'color' => '#7c3aed',
                'values' => $this->dailyAverages(TrustScoreLog::query(), 'calculated_at', 'score', $from, $to),
                'aggregate' => 'average',
            ],
        ]);

        $metrics = [
            'new_users' => (int) array_sum($growthTrend['series'][0]['values']),
            'new_agencies' => (int) array_sum($growthTrend['series'][1]['values']),
            'bookings' => (int) array_sum($activityTrend['series'][0]['values']),
            'cancellations' => (int) array_sum($activityTrend['series'][1]['values']),
            'travel_requests' => (int) array_sum($activityTrend['series'][2]['values']),
            'inquiries' => (int) array_sum($activityTrend['series'][3]['values']),
            'published_packages' => (int) array_sum($growthTrend['series'][2]['values']),
        ];

        $userRoles = User::withTrashed()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('role, COUNT(*) AS total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->all();
        $bookingStatuses = Booking::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
        $verificationStatuses = Agency::withTrashed()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('verification_status, COUNT(*) AS total')
            ->groupBy('verification_status')
            ->pluck('total', 'verification_status')
            ->all();

        $userRoles = $this->distribution(
            $userRoles,
            array_map(fn (UserRole $role): string => $role->value, UserRole::cases()),
        );
        $bookingStatuses = $this->distribution(
            $bookingStatuses,
            array_map(fn (BookingStatus $status): string => $status->value, BookingStatus::cases()),
        );
        $verificationStatuses = $this->distribution(
            $verificationStatuses,
            array_map(fn (VerificationStatus $status): string => $status->value, VerificationStatus::cases()),
        );

        $packageDestinations = Booking::query()
            ->join('packages', 'packages.id', '=', 'bookings.package_id')
            ->where('bookings.booking_type', 'package')
            ->whereBetween('bookings.created_at', [$from, $to])
            ->whereNotNull('packages.destination')
            ->where('packages.destination', '!=', '')
            ->selectRaw('packages.destination AS name, COUNT(bookings.id) AS total')
            ->groupBy('packages.destination');
        $customRequestDestinations = Booking::query()
            ->join('travel_requests', 'travel_requests.id', '=', 'bookings.custom_request_id')
            ->where('bookings.booking_type', 'custom')
            ->whereBetween('bookings.created_at', [$from, $to])
            ->whereNotNull('travel_requests.destination')
            ->where('travel_requests.destination', '!=', '')
            ->selectRaw('travel_requests.destination AS name, COUNT(bookings.id) AS total')
            ->groupBy('travel_requests.destination');
        $destinations = DB::query()
            ->fromSub($packageDestinations->unionAll($customRequestDestinations), 'booking_destinations')
            ->select('name')
            ->selectRaw('SUM(total) AS total')
            ->groupBy('name')
            ->orderByDesc('total')
            ->orderBy('name')
            ->limit(6)
            ->pluck('total', 'name')
            ->all();
        $packages = Booking::query()
            ->join('packages', 'packages.id', '=', 'bookings.package_id')
            ->whereBetween('bookings.created_at', [$from, $to])
            ->selectRaw('packages.id AS package_id, packages.title AS name, COUNT(bookings.id) AS total')
            ->groupBy('packages.id', 'packages.title')
            ->orderByDesc('total')
            ->orderBy('packages.title')
            ->limit(6)
            ->get()
            ->map(fn ($package): array => [
                'name' => $package->name,
                'total' => (int) $package->total,
            ])
            ->all();

        $daily = [];
        $dayCount = (int) $from->diffInDays($to) + 1;
        $bucketSize = max(1, (int) ceil($dayCount / count($dates)));
        foreach ($dates as $index => $date) {
            $daily[] = [
                'period_from' => $date,
                'period_to' => min(
                    $to->toDateString(),
                    $from->addDays(($index + 1) * $bucketSize - 1)->toDateString(),
                ),
                'new_users' => $growthTrend['series'][0]['values'][$index],
                'new_agencies' => $growthTrend['series'][1]['values'][$index],
                'published_packages' => $growthTrend['series'][2]['values'][$index],
                'bookings' => $activityTrend['series'][0]['values'][$index],
                'cancellations' => $activityTrend['series'][1]['values'][$index],
                'travel_requests' => $activityTrend['series'][2]['values'][$index],
                'inquiries' => $activityTrend['series'][3]['values'][$index],
                'request_views' => $activityTrend['series'][4]['values'][$index],
            ];
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'metrics' => $metrics,
            'growthTrend' => $growthTrend,
            'userGrowthTrend' => $userGrowthTrend,
            'agencyGrowthTrend' => $agencyGrowthTrend,
            'bookingTrend' => $bookingTrend,
            'activityTrend' => $activityTrend,
            'verificationTrend' => $verificationTrend,
            'trustScoreTrend' => $trustScoreTrend,
            'userRoles' => $userRoles,
            'bookingStatuses' => $bookingStatuses,
            'verificationStatuses' => $verificationStatuses,
            'destinations' => $this->bars($destinations),
            'packages' => $this->bars($packages),
            'daily' => $daily,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<int, int|string>>
     */
    public function csvRows(array $data): array
    {
        $rows = [[
            'period_from',
            'period_to',
            'new_users',
            'new_agencies',
            'published_packages',
            'bookings',
            'cancellations',
            'travel_requests',
            'inquiries',
            'request_views',
        ]];

        foreach ($data['daily'] as $day) {
            $rows[] = [
                $day['period_from'],
                $day['period_to'],
                $day['new_users'],
                $day['new_agencies'],
                $day['published_packages'],
                $day['bookings'],
                $day['cancellations'],
                $day['travel_requests'],
                $day['inquiries'],
                $day['request_views'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, array{color: string, values: array<string, int|float>, aggregate?: string}>  $series
     * @return array<string, mixed>
     */
    private function trendChart(array $dates, CarbonImmutable $from, CarbonImmutable $to, array $series): array
    {
        $bucketSize = max(1, (int) ceil(($from->diffInDays($to) + 1) / count($dates)));
        $chartSeries = [];
        $maximum = 0.0;
        $hasActivity = false;

        foreach ($series as $key => $definition) {
            $hasActivity = $hasActivity || $definition['values'] !== [];
            $values = array_fill(0, count($dates), 0.0);
            $bucketCounts = array_fill(0, count($dates), 0);
            foreach ($definition['values'] as $date => $value) {
                $offset = (int) $from->diffInDays(CarbonImmutable::parse((string) $date));
                $bucket = min(count($dates) - 1, (int) floor($offset / $bucketSize));
                $values[$bucket] += (float) $value;
                $bucketCounts[$bucket]++;
            }
            if (($definition['aggregate'] ?? 'sum') === 'average') {
                foreach ($values as $bucket => $value) {
                    if ($bucketCounts[$bucket] > 0) {
                        $values[$bucket] = $value / $bucketCounts[$bucket];
                    }
                }
            }

            $maximum = max($maximum, ...$values);
            $chartSeries[] = [
                'key' => $key,
                'color' => $definition['color'],
                'values' => $values,
            ];
        }

        $axisMaximum = $this->axisMaximum($maximum);
        $chartBottom = 250.0;

        foreach ($chartSeries as &$chartLine) {
            $points = [];
            foreach ($chartLine['values'] as $index => $value) {
                $x = count($dates) === 1 ? 423.0 : 70 + ($index * 706 / (count($dates) - 1));
                $y = $chartBottom - ($value / $axisMaximum * 220);
                $points[] = ['x' => $x, 'y' => $y];
            }
            $chartLine['points'] = $points;
            $chartLine['path'] = $this->curvePath($points);
            $chartLine['areaPath'] = $this->areaPath($points, $chartBottom);
        }
        unset($chartLine);

        $dayCount = (int) $from->diffInDays($to) + 1;
        $bucketSize = max(1, (int) ceil($dayCount / count($dates)));
        $periods = [];
        foreach ($dates as $index => $date) {
            $periods[] = [
                'from' => $date,
                'to' => min(
                    $to->toDateString(),
                    CarbonImmutable::parse($date)->addDays($bucketSize - 1)->toDateString(),
                ),
            ];
        }

        $ticks = [];
        for ($index = 0; $index <= 4; $index++) {
            $value = $axisMaximum * (4 - $index) / 4;
            $ticks[] = [
                'value' => $value,
                'label' => $this->formatTickValue($value),
                'y' => 30 + ($index * 55),
            ];
        }

        return [
            'dates' => $dates,
            'periods' => $periods,
            'series' => $chartSeries,
            'maximum' => $axisMaximum,
            'ticks' => $ticks,
            'maximumLabel' => $this->formatAxisValue($axisMaximum),
            'hasActivity' => $hasActivity,
        ];
    }

    /**
     * @param  array<int, array{x: float, y: float}>  $points
     */
    private function curvePath(array $points): string
    {
        if ($points === []) {
            return '';
        }

        $path = 'M '.$this->coordinate($points[0]['x']).' '.$this->coordinate($points[0]['y']);
        for ($index = 0; $index < count($points) - 1; $index++) {
            $previous = $points[max(0, $index - 1)];
            $start = $points[$index];
            $end = $points[$index + 1];
            $next = $points[min(count($points) - 1, $index + 2)];
            $path .= ' C '
                .$this->coordinate($start['x'] + ($end['x'] - $previous['x']) / 6).' '
                .$this->coordinate($start['y'] + ($end['y'] - $previous['y']) / 6).' '
                .$this->coordinate($end['x'] - ($next['x'] - $start['x']) / 6).' '
                .$this->coordinate($end['y'] - ($next['y'] - $start['y']) / 6).' '
                .$this->coordinate($end['x']).' '.$this->coordinate($end['y']);
        }

        return $path;
    }

    /**
     * @param  array<int, array{x: float, y: float}>  $points
     */
    private function areaPath(array $points, float $bottom): string
    {
        if ($points === []) {
            return '';
        }

        return $this->curvePath($points)
            .' L '.$this->coordinate(end($points)['x']).' '.$this->coordinate($bottom)
            .' L '.$this->coordinate($points[0]['x']).' '.$this->coordinate($bottom).' Z';
    }

    private function coordinate(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function axisMaximum(float $value): float
    {
        if ($value <= 0) {
            return 1;
        }

        $magnitude = 10 ** floor(log10($value));
        $normalized = $value / $magnitude;
        $step = match (true) {
            $normalized <= 1 => 1,
            $normalized <= 2 => 2,
            $normalized <= 5 => 5,
            default => 10,
        };

        return $step * $magnitude;
    }

    private function formatAxisValue(float $value): string
    {
        return match (true) {
            $value >= 1_000_000 => rtrim(rtrim(number_format($value / 1_000_000, 1), '0'), '.').'M',
            $value >= 1_000 => rtrim(rtrim(number_format($value / 1_000, 1), '0'), '.').'K',
            default => number_format($value, floor($value) === $value ? 0 : 1),
        };
    }

    private function formatTickValue(float $value): string
    {
        if (floor($value) === $value) {
            return $this->formatAxisValue($value);
        }

        return rtrim(rtrim(number_format($value, 2, '.', ','), '0'), '.');
    }

    /**
     * @return array<int, string>
     */
    private function chartDates(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dayCount = (int) $from->diffInDays($to) + 1;
        $bucketSize = max(1, (int) ceil($dayCount / 30));
        $bucketCount = (int) ceil($dayCount / $bucketSize);
        $dates = [];

        for ($bucket = 0; $bucket < $bucketCount; $bucket++) {
            $dates[] = $from->addDays($bucket * $bucketSize)->toDateString();
        }

        return $dates;
    }

    /**
     * @param  array<int, string>  $dates
     * @param  array<string, int|float>  $countsByDate
     * @return array<string, int>
     */
    private function cumulativeCounts(array $dates, CarbonImmutable $from, array $countsByDate): array
    {
        $dayCount = (int) $from->diffInDays(CarbonImmutable::parse(end($dates))) + 1;
        $bucketSize = max(1, (int) ceil($dayCount / count($dates)));
        $countsByBucket = array_fill(0, count($dates), 0);

        foreach ($countsByDate as $date => $count) {
            $offset = (int) $from->diffInDays(CarbonImmutable::parse($date));
            $bucket = min(count($dates) - 1, (int) floor($offset / $bucketSize));
            $countsByBucket[$bucket] += (int) $count;
        }

        $cumulative = [];
        $total = 0;
        foreach ($dates as $index => $date) {
            $total += $countsByBucket[$index];
            $cumulative[$date] = $total;
        }

        return $cumulative;
    }

    /**
     * @param  Builder<Model>  $query
     * @return array<string, int|float>
     */
    private function dailyCounts(
        Builder $query,
        string $dateColumn,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        return $query
            ->whereBetween($dateColumn, [$from, $to])
            ->selectRaw('DATE('.$dateColumn.') AS metric_date')
            ->selectRaw('COUNT(*) AS total')
            ->groupByRaw('DATE('.$dateColumn.')')
            ->orderByRaw('DATE('.$dateColumn.') ASC')
            ->pluck('total', 'metric_date')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * @param  Builder<Model>  $query
     * @return array<string, array<string, int>>
     */
    private function dailyGroupedCounts(
        Builder $query,
        string $dateColumn,
        string $groupColumn,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        return $query
            ->whereBetween($dateColumn, [$from, $to])
            ->whereNotNull($groupColumn)
            ->selectRaw('DATE('.$dateColumn.') AS metric_date')
            ->addSelect($groupColumn)
            ->selectRaw('COUNT(*) AS total')
            ->groupByRaw('DATE('.$dateColumn.'), '.$groupColumn)
            ->orderByRaw('DATE('.$dateColumn.') ASC')
            ->get()
            ->groupBy($groupColumn)
            ->map(fn ($items): array => $items->pluck('total', 'metric_date')
                ->map(fn ($total): int => (int) $total)
                ->all())
            ->all();
    }

    /**
     * @param  Builder<Model>  $query
     * @return array<string, float>
     */
    private function dailyAverages(
        Builder $query,
        string $dateColumn,
        string $valueColumn,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        return $query
            ->whereBetween($dateColumn, [$from, $to])
            ->selectRaw('DATE('.$dateColumn.') AS metric_date')
            ->selectRaw('AVG('.$valueColumn.') AS average_value')
            ->groupByRaw('DATE('.$dateColumn.')')
            ->orderByRaw('DATE('.$dateColumn.') ASC')
            ->pluck('average_value', 'metric_date')
            ->map(fn ($value): float => (float) $value)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $allowed
     * @return array<int, array{role?: string, status?: string, count: int, percent: float}>
     */
    private function distribution(array $values, array $allowed): array
    {
        $knownValues = [];
        foreach ($allowed as $value) {
            $knownValues[$value] = (int) ($values[$value] ?? 0);
            unset($values[$value]);
        }
        if ($values !== []) {
            $knownValues['other'] = array_sum(array_map('intval', $values));
        }

        $total = array_sum($knownValues);
        $rows = [];
        foreach ($knownValues as $key => $count) {
            $rows[] = [
                'status' => $key,
                'count' => $count,
                'percent' => $total > 0 ? $count * 100 / $total : 0,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>|array<int, array{name: string, total: int}>  $values
     * @return array<int, array{name: string, count: int, percent: float}>
     */
    private function bars(array $values): array
    {
        $maximum = max(array_map('intval', $values) ?: [0]);

        if (array_is_list($values)) {
            return collect($values)
                ->map(fn (array $item): array => [
                    'name' => (string) $item['name'],
                    'count' => (int) $item['total'],
                    'percent' => $maximum > 0 ? (int) $item['total'] * 100 / $maximum : 0,
                ])
                ->values()
                ->all();
        }

        return collect($values)
            ->map(fn ($count, $name): array => [
                'name' => (string) $name,
                'count' => (int) $count,
                'percent' => $maximum > 0 ? (int) $count * 100 / $maximum : 0,
            ])
            ->values()
            ->all();
    }
}
