<?php

namespace App\Services;

use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentTransactionType;
use App\Models\Booking;
use App\Models\BookingPaymentTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AdminFinanceManagementService
{
    /**
     * @param  array{created_from?: string, created_to?: string}  $filters
     * @return array{
     *     from: string,
     *     to: string,
     *     ledgerAvailable: bool,
     *     bookingCount: int,
     *     paymentAttemptCount: int,
     *     confirmedTransactionCount: int,
     *     currencySummaries: array<int, array{
     *         currency: string,
     *         successfulChargeTotal: string,
     *         successfulChargeCount: int,
     *         pendingCount: int,
     *         failedCount: int,
     *         refundTotal: string,
     *         refundCount: int
     *     }>,
     *     moneyTrends: array<int, array{
     *         currency: string,
     *         range: string,
     *         maximum: float,
     *         middle: float,
     *         maximumLabel: string,
     *         middleLabel: string,
     *         dates: array<int, string>,
     *         charges: array<int, float>,
     *         refunds: array<int, float>,
     *         chargePoints: array<int, array{x: float, y: float, value: float}>,
     *         refundPoints: array<int, array{x: float, y: float, value: float}>,
     *         chargePath: string,
     *         chargeAreaPath: string,
     *         refundPath: string,
     *         refundAreaPath: string
     *     }>,
     *     transactions: LengthAwarePaginator|null
     * }
     */
    public function pageData(string $page, array $filters): array
    {
        $to = isset($filters['created_to'])
            ? CarbonImmutable::parse($filters['created_to'])->endOfDay()
            : CarbonImmutable::instance(now())->endOfDay();
        $from = isset($filters['created_from'])
            ? CarbonImmutable::parse($filters['created_from'])->startOfDay()
            : $to->subDays(29)->startOfDay();

        $transactions = null;
        if (in_array($page, [
            'all_transactions',
            'successful_payments',
            'pending_failed_payments',
            'refunds',
        ], true)) {
            $transactions = $this->transactionQuery($page, $from, $to)
                ->with([
                    'booking:id,booking_reference,user_id,agency_id,status,payment_status',
                    'booking.traveler:id,name',
                    'booking.agency:id,company_name',
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString();
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'ledgerAvailable' => BookingPaymentTransaction::query()->exists(),
            'bookingCount' => Booking::query()
                ->whereBetween('created_at', [$from, $to])
                ->count(),
            'paymentAttemptCount' => $this->transactionQuery('all_transactions', $from, $to)
                ->where('type', PaymentTransactionType::CHARGE->value)
                ->count(),
            'confirmedTransactionCount' => $this->transactionQuery('successful_payments', $from, $to)->count(),
            'currencySummaries' => $this->currencySummaries($from, $to),
            'moneyTrends' => $page === 'financial_overview'
                ? $this->moneyTrends($from, $to)
                : [],
            'transactions' => $transactions,
        ];
    }

    /**
     * @return array<int, array{
     *     currency: string,
     *     range: string,
     *     maximum: float,
     *     middle: float,
     *     maximumLabel: string,
     *     middleLabel: string,
     *     dates: array<int, string>,
     *     charges: array<int, float>,
     *     refunds: array<int, float>,
     *     chargePoints: array<int, array{x: float, y: float, value: float}>,
     *     refundPoints: array<int, array{x: float, y: float, value: float}>,
     *     chargePath: string,
     *     chargeAreaPath: string,
     *     refundPath: string,
     *     refundAreaPath: string
     * }>
     */
    private function moneyTrends(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dailyTotals = BookingPaymentTransaction::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('status', PaymentTransactionStatus::SUCCEEDED->value)
            ->whereIn('type', [
                PaymentTransactionType::CHARGE->value,
                PaymentTransactionType::REFUND->value,
            ])
            ->select('currency')
            ->selectRaw('DATE(created_at) AS transaction_date')
            ->selectRaw(
                'SUM(CASE WHEN type = ? THEN amount ELSE 0 END) AS successful_charges',
                [PaymentTransactionType::CHARGE->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN type = ? THEN amount ELSE 0 END) AS successful_refunds',
                [PaymentTransactionType::REFUND->value],
            )
            ->groupByRaw('DATE(created_at), currency')
            ->orderByRaw('DATE(created_at) ASC')
            ->get();

        if ($dailyTotals->isEmpty()) {
            return [];
        }

        $dayCount = (int) $from->diffInDays($to) + 1;
        $bucketSize = max(1, (int) ceil($dayCount / 30));
        $bucketCount = (int) ceil($dayCount / $bucketSize);
        $dates = [];
        $chargesByCurrency = [];
        $refundsByCurrency = [];

        for ($bucket = 0; $bucket < $bucketCount; $bucket++) {
            $bucketStart = $from->addDays($bucket * $bucketSize);
            $dates[] = $bucketStart->toDateString();
        }

        foreach ($dailyTotals as $dailyTotal) {
            $currency = $dailyTotal->currency;
            $chargesByCurrency[$currency] ??= array_fill(0, $bucketCount, 0.0);
            $refundsByCurrency[$currency] ??= array_fill(0, $bucketCount, 0.0);

            $dayOffset = (int) $from->diffInDays(CarbonImmutable::parse($dailyTotal->transaction_date));
            $bucket = min($bucketCount - 1, (int) floor($dayOffset / $bucketSize));
            $chargesByCurrency[$currency][$bucket] += (float) $dailyTotal->successful_charges;
            $refundsByCurrency[$currency][$bucket] += (float) $dailyTotal->successful_refunds;
        }

        $trends = [];
        foreach ($chargesByCurrency as $currency => $charges) {
            $refunds = $refundsByCurrency[$currency];
            $maximum = $this->chartMaximum(max([...$charges, ...$refunds]));
            $chargeCoordinates = $this->chartCoordinates($charges, $maximum);
            $refundCoordinates = $this->chartCoordinates($refunds, $maximum);
            $chartBottom = 250.0;

            $trends[] = [
                'currency' => $currency,
                'range' => $from->toDateString().' — '.$to->toDateString(),
                'maximum' => $maximum,
                'middle' => $maximum / 2,
                'maximumLabel' => $this->formatAxisValue($maximum),
                'middleLabel' => $this->formatAxisValue($maximum / 2),
                'dates' => $dates,
                'charges' => $charges,
                'refunds' => $refunds,
                'chargePoints' => $chargeCoordinates,
                'refundPoints' => $refundCoordinates,
                'chargePath' => $this->curvePath($chargeCoordinates),
                'chargeAreaPath' => $this->areaPath($chargeCoordinates, $chartBottom),
                'refundPath' => $this->curvePath($refundCoordinates),
                'refundAreaPath' => $this->areaPath($refundCoordinates, $chartBottom),
            ];
        }

        return $trends;
    }

    private function chartMaximum(float $value): float
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
        $divisor = match (true) {
            $value >= 1_000_000 => 1_000_000,
            $value >= 1_000 => 1_000,
            default => 1,
        };

        if ($divisor === 1) {
            return number_format($value, 0);
        }

        $suffix = $divisor === 1_000_000 ? 'M' : 'K';
        $formatted = number_format($value / $divisor, 1, '.', '');

        return rtrim(rtrim($formatted, '0'), '.').$suffix;
    }

    /**
     * @param  array<int, float>  $values
     * @return array<int, array{x: float, y: float, value: float}>
     */
    private function chartCoordinates(array $values, float $maximum): array
    {
        $left = 70.0;
        $right = 776.0;
        $top = 30.0;
        $bottom = 250.0;
        $lastIndex = count($values) - 1;

        return array_map(
            fn (float $value, int $index): array => [
                'x' => $lastIndex === 0 ? ($left + $right) / 2 : $left + ($index / $lastIndex) * ($right - $left),
                'y' => $bottom - ($value / $maximum) * ($bottom - $top),
                'value' => $value,
            ],
            $values,
            array_keys($values),
        );
    }

    /**
     * @param  array<int, array{x: float, y: float, value: float}>  $points
     */
    private function curvePath(array $points): string
    {
        if (count($points) < 2) {
            return isset($points[0])
                ? 'M '.number_format($points[0]['x'], 2, '.', '').' '.number_format($points[0]['y'], 2, '.', '')
                : '';
        }

        $path = 'M '.number_format($points[0]['x'], 2, '.', '').' '.number_format($points[0]['y'], 2, '.', '');
        $count = count($points);

        for ($index = 0; $index < $count - 1; $index++) {
            $previous = $points[max(0, $index - 1)];
            $current = $points[$index];
            $next = $points[$index + 1];
            $following = $points[min($count - 1, $index + 2)];
            $controlOneX = $current['x'] + ($next['x'] - $previous['x']) / 6;
            $controlOneY = $current['y'] + ($next['y'] - $previous['y']) / 6;
            $controlTwoX = $next['x'] - ($following['x'] - $current['x']) / 6;
            $controlTwoY = $next['y'] - ($following['y'] - $current['y']) / 6;
            $controlOneY = max(30, min(250, $controlOneY));
            $controlTwoY = max(30, min(250, $controlTwoY));

            $path .= ' C '.number_format($controlOneX, 2, '.', '').' '.number_format($controlOneY, 2, '.', '')
                .' '.number_format($controlTwoX, 2, '.', '').' '.number_format($controlTwoY, 2, '.', '')
                .' '.number_format($next['x'], 2, '.', '').' '.number_format($next['y'], 2, '.', '');
        }

        return $path;
    }

    /**
     * @param  array<int, array{x: float, y: float, value: float}>  $points
     */
    private function areaPath(array $points, float $bottom): string
    {
        if ($points === []) {
            return '';
        }

        return $this->curvePath($points)
            .' L '.number_format($points[array_key_last($points)]['x'], 2, '.', '').' '.number_format($bottom, 2, '.', '')
            .' L '.number_format($points[0]['x'], 2, '.', '').' '.number_format($bottom, 2, '.', '')
            .' Z';
    }

    private function transactionQuery(string $page, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $query = BookingPaymentTransaction::query()
            ->whereBetween('created_at', [$from, $to]);

        if ($page === 'successful_payments') {
            return $query
                ->where('type', PaymentTransactionType::CHARGE->value)
                ->where('status', PaymentTransactionStatus::SUCCEEDED->value);
        }

        if ($page === 'pending_failed_payments') {
            return $query
                ->where('type', PaymentTransactionType::CHARGE->value)
                ->whereIn('status', [
                    PaymentTransactionStatus::PENDING->value,
                    PaymentTransactionStatus::FAILED->value,
                ]);
        }

        if ($page === 'refunds') {
            return $query->where('type', PaymentTransactionType::REFUND->value);
        }

        return $query;
    }

    /**
     * @return array<int, array{
     *     currency: string,
     *     successfulChargeTotal: string,
     *     successfulChargeCount: int,
     *     pendingCount: int,
     *     failedCount: int,
     *     refundTotal: string,
     *     refundCount: int
     * }>
     */
    private function currencySummaries(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return BookingPaymentTransaction::query()
            ->whereBetween('created_at', [$from, $to])
            ->select('currency')
            ->selectRaw(
                'SUM(CASE WHEN type = ? AND status = ? THEN amount ELSE 0 END) AS successful_charge_total',
                [PaymentTransactionType::CHARGE->value, PaymentTransactionStatus::SUCCEEDED->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN type = ? AND status = ? THEN 1 ELSE 0 END) AS successful_charge_count',
                [PaymentTransactionType::CHARGE->value, PaymentTransactionStatus::SUCCEEDED->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN type = ? AND status = ? THEN 1 ELSE 0 END) AS pending_count',
                [PaymentTransactionType::CHARGE->value, PaymentTransactionStatus::PENDING->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN type = ? AND status = ? THEN 1 ELSE 0 END) AS failed_count',
                [PaymentTransactionType::CHARGE->value, PaymentTransactionStatus::FAILED->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN type = ? AND status = ? THEN amount ELSE 0 END) AS refund_total',
                [PaymentTransactionType::REFUND->value, PaymentTransactionStatus::SUCCEEDED->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN type = ? AND status = ? THEN 1 ELSE 0 END) AS refund_count',
                [PaymentTransactionType::REFUND->value, PaymentTransactionStatus::SUCCEEDED->value],
            )
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn (BookingPaymentTransaction $summary): array => [
                'currency' => $summary->currency,
                'successfulChargeTotal' => (string) $summary->successful_charge_total,
                'successfulChargeCount' => (int) $summary->successful_charge_count,
                'pendingCount' => (int) $summary->pending_count,
                'failedCount' => (int) $summary->failed_count,
                'refundTotal' => (string) $summary->refund_total,
                'refundCount' => (int) $summary->refund_count,
            ])
            ->all();
    }
}
