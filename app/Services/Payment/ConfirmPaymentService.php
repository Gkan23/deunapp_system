<?php

namespace App\Services\Payment;

use App\Models\AuditLog;
use App\Models\DeliveryService;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentStatus;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ConfirmPaymentService
{
    /**
     * Payment methods that require a reference.
     */
    private const REFERENCE_REQUIRED_METHODS = [
        'CARD',
        'BANK_TRANSFER',
        'MOBILE_WALLET',
    ];

    /**
     * Confirm a pending payment.
     *
     * @throws DomainException
     */
    public function execute(
        Payment $payment,
        User $performedBy,
        ?string $paymentReference = null
    ): Payment {
        try {
            return DB::transaction(function () use (
                $payment,
                $performedBy,
                $paymentReference
            ): Payment {
                $lockedPayment = Payment::query()
                    ->whereKey($payment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $pendingStatus = PaymentStatus::query()
                    ->where('status_name', 'PENDING')
                    ->firstOrFail();

                if (
                    (int) $lockedPayment->payment_status_id
                    !== (int) $pendingStatus->id
                ) {
                    throw new DomainException(
                        'Only a pending payment can be confirmed.'
                    );
                }

                $deliveryService = DeliveryService::query()
                    ->whereKey($lockedPayment->delivery_service_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($deliveryService->status === 'CANCELLED') {
                    throw new DomainException(
                        'A payment for a cancelled delivery service cannot be confirmed.'
                    );
                }

                if (
                    $deliveryService->delivery_fee === null
                    || $this->toCents(
                        $deliveryService->delivery_fee
                    ) <= 0
                ) {
                    throw new DomainException(
                        'The delivery service fee must be defined before confirming the payment.'
                    );
                }

                if (
                    $this->toCents($lockedPayment->amount)
                    !== $this->toCents(
                        $deliveryService->delivery_fee
                    )
                ) {
                    throw new DomainException(
                        'The payment amount does not match the delivery service fee.'
                    );
                }

                $paymentMethod = PaymentMethod::query()
                    ->whereKey($lockedPayment->payment_method_id)
                    ->firstOrFail();

                $normalizedReference = $paymentReference === null
                    ? null
                    : trim($paymentReference);

                if ($normalizedReference === '') {
                    $normalizedReference = null;
                }

                if (
                    in_array(
                        $paymentMethod->method_name,
                        self::REFERENCE_REQUIRED_METHODS,
                        true
                    )
                    && $normalizedReference === null
                ) {
                    throw new DomainException(
                        'A payment reference is required for this payment method.'
                    );
                }

                if (
                    $normalizedReference !== null
                    && mb_strlen($normalizedReference) > 150
                ) {
                    throw new DomainException(
                        'The payment reference may not exceed 150 characters.'
                    );
                }

                if (
                    $normalizedReference !== null
                    && Payment::query()
                        ->where('payment_reference', $normalizedReference)
                        ->whereKeyNot($lockedPayment->id)
                        ->lockForUpdate()
                        ->exists()
                ) {
                    throw new DomainException(
                        'The payment reference has already been used.'
                    );
                }

                $paidStatus = PaymentStatus::query()
                    ->where('status_name', 'PAID')
                    ->firstOrFail();

                $paidAt = now();

                $lockedPayment->update([
                    'payment_status_id' => $paidStatus->id,
                    'payment_reference' => $normalizedReference,
                    'paid_at' => $paidAt,
                ]);

                AuditLog::query()->create([
                    'performed_by_user_id' => $performedBy->getKey(),
                    'table_name' => 'payments',
                    'record_id' => $lockedPayment->id,
                    'action_type' => 'PAYMENT_CONFIRMED',
                    'details' => [
                        'from_status' => 'PENDING',
                        'to_status' => 'PAID',
                        'payment_method' => $paymentMethod->method_name,
                        'amount' => number_format(
                            (float) $lockedPayment->amount,
                            2,
                            '.',
                            ''
                        ),
                        'payment_reference' => $normalizedReference,
                        'delivery_service_id' => $deliveryService->id,
                    ],
                    'performed_at' => $paidAt,
                ]);

                return $lockedPayment->fresh([
                    'deliveryService',
                    'paymentMethod',
                    'paymentStatus',
                ]);
            }, attempts: 3);
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            throw new DomainException(
                'The payment reference has already been used.',
                0,
                $exception
            );
        }
    }

    private function toCents(
        string|int|float $amount
    ): int {
        return (int) round(
            (float) $amount * 100
        );
    }
}

