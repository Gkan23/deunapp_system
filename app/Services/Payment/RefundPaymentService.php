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

class RefundPaymentService
{
    private const REFERENCE_REQUIRED_METHODS = [
        'CARD',
        'BANK_TRANSFER',
        'MOBILE_WALLET',
    ];

    /**
     * Refund a paid payment.
     *
     * @throws DomainException
     */
    public function execute(
        Payment $payment,
        User $performedBy,
        string $reason,
        ?string $refundReference = null
    ): Payment {
        try {
            return DB::transaction(function () use (
                $payment,
                $performedBy,
                $reason,
                $refundReference
            ): Payment {
                $lockedPayment = Payment::query()
                    ->whereKey($payment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $paidStatus = PaymentStatus::query()
                    ->where('status_name', 'PAID')
                    ->firstOrFail();

                if (
                    (int) $lockedPayment->payment_status_id
                    !== (int) $paidStatus->id
                ) {
                    throw new DomainException(
                        'Only a paid payment can be refunded.'
                    );
                }

                if ($lockedPayment->paid_at === null) {
                    throw new DomainException(
                        'The payment does not have a valid payment date.'
                    );
                }

                $normalizedReason = trim($reason);

                if ($normalizedReason === '') {
                    throw new DomainException(
                        'The refund reason is required.'
                    );
                }

                $deliveryService = DeliveryService::query()
                    ->whereKey($lockedPayment->delivery_service_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $paymentMethod = PaymentMethod::query()
                    ->whereKey($lockedPayment->payment_method_id)
                    ->firstOrFail();

                $normalizedReference = $refundReference === null
                    ? null
                    : trim($refundReference);

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
                        'A refund reference is required for this payment method.'
                    );
                }

                if (
                    $normalizedReference !== null
                    && mb_strlen($normalizedReference) > 150
                ) {
                    throw new DomainException(
                        'The refund reference may not exceed 150 characters.'
                    );
                }

                if (
                    $normalizedReference !== null
                    && Payment::query()
                        ->where(function ($query) use (
                            $normalizedReference
                        ): void {
                            $query
                                ->where(
                                    'payment_reference',
                                    $normalizedReference
                                )
                                ->orWhere(
                                    'refund_reference',
                                    $normalizedReference
                                );
                        })
                        ->lockForUpdate()
                        ->exists()
                ) {
                    throw new DomainException(
                        'The refund reference has already been used.'
                    );
                }

                $refundedStatus = PaymentStatus::query()
                    ->where('status_name', 'REFUNDED')
                    ->firstOrFail();

                $refundedAt = now();

                $lockedPayment->update([
                    'payment_status_id' => $refundedStatus->id,
                    'refund_reference' => $normalizedReference,
                    'refund_reason' => $normalizedReason,
                    'refunded_at' => $refundedAt,
                ]);

                AuditLog::query()->create([
                    'performed_by_user_id' => $performedBy->getKey(),
                    'table_name' => 'payments',
                    'record_id' => $lockedPayment->id,
                    'action_type' => 'PAYMENT_REFUNDED',
                    'details' => [
                        'from_status' => 'PAID',
                        'to_status' => 'REFUNDED',
                        'payment_method' => $paymentMethod->method_name,
                        'amount' => number_format(
                            (float) $lockedPayment->amount,
                            2,
                            '.',
                            ''
                        ),
                        'payment_reference' => $lockedPayment
                            ->payment_reference,
                        'refund_reference' => $normalizedReference,
                        'refund_reason' => $normalizedReason,
                        'delivery_service_id' => $deliveryService->id,
                    ],
                    'performed_at' => $refundedAt,
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
                'The refund reference has already been used.',
                0,
                $exception
            );
        }
    }
}
