<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentTransactionType;
use App\Models\Booking;
use App\Models\BookingPaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class PaymentSettlementService
{
    public function record(
        Booking $booking,
        string $provider,
        PaymentTransactionType $type,
        PaymentTransactionStatus $status,
        string $amount,
        string $currency,
        ?string $providerReference = null,
        ?string $paymentMethod = null,
        ?string $failureReason = null,
        ?\DateTimeInterface $processedAt = null,
    ): BookingPaymentTransaction {
        if (! is_numeric($amount) || (float) $amount <= 0) {
            throw new InvalidArgumentException('Payment transaction amount must be greater than zero.');
        }

        if (strtoupper($currency) !== strtoupper($booking->currency)) {
            throw new InvalidArgumentException('Payment transaction currency must match the booking currency.');
        }

        if (trim($provider) === '') {
            throw new InvalidArgumentException('Payment provider is required.');
        }

        if (! preg_match('/^[A-Z]{3}$/', strtoupper($currency))) {
            throw new InvalidArgumentException('Payment transaction currency must be a three-letter code.');
        }

        return DB::transaction(function () use ($booking, $provider, $type, $status, $amount, $currency, $providerReference, $paymentMethod, $failureReason, $processedAt): BookingPaymentTransaction {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($providerReference !== null) {
                $existingTransaction = BookingPaymentTransaction::query()
                    ->where('provider', $provider)
                    ->where('provider_reference', $providerReference)
                    ->first();

                if ($existingTransaction) {
                    if ($existingTransaction->booking_id !== $lockedBooking->id
                        || $existingTransaction->type !== $type
                        || (float) $existingTransaction->amount !== (float) $amount
                        || $existingTransaction->currency !== strtoupper($currency)) {
                        throw new RuntimeException('Provider reference was already used for a different payment transaction.');
                    }

                    return $existingTransaction;
                }
            }

            $transaction = $lockedBooking->paymentTransactions()->create([
                'payment_reference' => 'PAY-'.Str::upper(Str::random(24)),
                'provider' => $provider,
                'provider_reference' => $providerReference,
                'payment_method' => $paymentMethod,
                'type' => $type,
                'status' => $status,
                'amount' => number_format((float) $amount, 2, '.', ''),
                'currency' => strtoupper($currency),
                'failure_reason' => $failureReason,
                'processed_at' => $processedAt ?? ($status === PaymentTransactionStatus::PENDING ? null : now()),
            ]);

            $this->reconcileBooking($lockedBooking);

            return $transaction;
        });
    }

    public function settle(
        BookingPaymentTransaction $transaction,
        PaymentTransactionStatus $status,
        ?string $failureReason = null,
        ?\DateTimeInterface $processedAt = null,
    ): BookingPaymentTransaction {
        return DB::transaction(function () use ($transaction, $status, $failureReason, $processedAt): BookingPaymentTransaction {
            $booking = Booking::query()->whereKey($transaction->booking_id)->lockForUpdate()->firstOrFail();
            $lockedTransaction = BookingPaymentTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($lockedTransaction->status === PaymentTransactionStatus::SUCCEEDED && $status !== PaymentTransactionStatus::SUCCEEDED) {
                throw new RuntimeException('A succeeded payment transaction cannot be moved to another status.');
            }

            $lockedTransaction->update([
                'status' => $status,
                'failure_reason' => $failureReason,
                'processed_at' => $processedAt ?? ($status === PaymentTransactionStatus::PENDING ? null : now()),
            ]);

            $this->reconcileBooking($booking);

            return $lockedTransaction->refresh();
        });
    }

    public function reconcileBooking(Booking $booking): Booking
    {
        $successfulTransactions = $booking->paymentTransactions()
            ->where('status', PaymentTransactionStatus::SUCCEEDED->value);
        $charges = (clone $successfulTransactions)
            ->where('type', PaymentTransactionType::CHARGE->value)
            ->sum('amount');
        $refunds = (clone $successfulTransactions)
            ->where('type', PaymentTransactionType::REFUND->value)
            ->sum('amount');

        $chargeCents = (int) round((float) $charges * 100);
        $refundCents = (int) round((float) $refunds * 100);

        if ($refundCents > $chargeCents) {
            throw new RuntimeException('Successful refunds cannot exceed successful charges.');
        }

        $netPaidCents = $chargeCents - $refundCents;
        $bookingTotalCents = (int) round((float) $booking->total_amount * 100);
        $paymentStatus = match (true) {
            $netPaidCents === 0 && $refundCents > 0 => PaymentStatus::REFUNDED,
            $netPaidCents > 0 && $netPaidCents >= $bookingTotalCents => PaymentStatus::PAID,
            $netPaidCents > 0 => PaymentStatus::PARTIAL,
            default => PaymentStatus::UNPAID,
        };

        $booking->update(['payment_status' => $paymentStatus]);

        return $booking->refresh();
    }
}
