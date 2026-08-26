<?php

namespace App\Services\Rating;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DeliveryService;
use App\Models\Rating;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\Trip;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateRatingService
{
    /**
     * Create a rating for a completed delivery service.
     *
     * @throws DomainException
     */
    public function execute(
        DeliveryService $deliveryService,
        Customer $customer,
        int $punctualityScore,
        int $customerServiceScore,
        int $packageConditionScore,
        ?string $comment = null
    ): Rating {
        $this->validateScores([
            $punctualityScore,
            $customerServiceScore,
            $packageConditionScore,
        ]);

        try {
            return DB::transaction(function () use (
                $deliveryService,
                $customer,
                $punctualityScore,
                $customerServiceScore,
                $packageConditionScore,
                $comment
            ): Rating {
                /*
                 * Shipment is locked first to maintain a consistent
                 * lock order with the delivery completion service.
                 */
                $shipment = Shipment::query()
                    ->whereKey($deliveryService->shipment_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedDeliveryService = DeliveryService::query()
                    ->whereKey($deliveryService->getKey())
                    ->where('shipment_id', $shipment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    (int) $lockedDeliveryService->customer_id
                    !== (int) $customer->getKey()
                    || (int) $shipment->customer_id
                    !== (int) $customer->getKey()
                ) {
                    throw new DomainException(
                        'Only the customer who owns the delivery service can rate it.'
                    );
                }

                if ($lockedDeliveryService->status !== 'COMPLETED') {
                    throw new DomainException(
                        'Only a completed delivery service can be rated.'
                    );
                }

                if ($lockedDeliveryService->completed_at === null) {
                    throw new DomainException(
                        'The completed delivery service does not have a completion date.'
                    );
                }

                if ($lockedDeliveryService->trip_id === null) {
                    throw new DomainException(
                        'A completed delivery service must have an assigned trip before it can be rated.'
                    );
                }

                $trip = Trip::query()
                    ->whereKey($lockedDeliveryService->trip_id)
                    ->firstOrFail();

                $deliveredStatus = ShipmentStatus::query()
                    ->where('status_name', 'DELIVERED')
                    ->firstOrFail();

                if (
                    (int) $shipment->shipment_status_id
                    !== (int) $deliveredStatus->id
                ) {
                    throw new DomainException(
                        'Only a delivered shipment can be rated.'
                    );
                }

                if ($shipment->delivered_at === null) {
                    throw new DomainException(
                        'The delivered shipment does not have a delivery date.'
                    );
                }

                if (
                    Rating::query()
                        ->where(
                            'delivery_service_id',
                            $lockedDeliveryService->id
                        )
                        ->lockForUpdate()
                        ->exists()
                ) {
                    throw new DomainException(
                        'The delivery service has already been rated.'
                    );
                }

                $normalizedComment = $comment === null
                    ? null
                    : trim($comment);

                if ($normalizedComment === '') {
                    $normalizedComment = null;
                }

                $overallScore = round(
                    (
                        $punctualityScore
                        + $customerServiceScore
                        + $packageConditionScore
                    ) / 3,
                    2
                );

                $ratedAt = now();

                $rating = Rating::query()->create([
                    'delivery_service_id' => $lockedDeliveryService->id,
                    'customer_id' => $customer->id,
                    'punctuality_score' => $punctualityScore,
                    'customer_service_score' => $customerServiceScore,
                    'package_condition_score' => $packageConditionScore,
                    'overall_score' => $overallScore,
                    'comment' => $normalizedComment,
                    'rated_at' => $ratedAt,
                ]);

                AuditLog::query()->create([
                    'performed_by_user_id' => $customer->user_id,
                    'table_name' => 'ratings',
                    'record_id' => $rating->id,
                    'action_type' => 'RATING_CREATED',
                    'details' => [
                        'delivery_service_id' => $lockedDeliveryService->id,
                        'shipment_id' => $shipment->id,
                        'delivery_provider_id' => $trip
                            ->delivery_provider_id,
                        'punctuality_score' => $punctualityScore,
                        'customer_service_score' => $customerServiceScore,
                        'package_condition_score' => $packageConditionScore,
                        'overall_score' => number_format(
                            $overallScore,
                            2,
                            '.',
                            ''
                        ),
                        'comment' => $normalizedComment,
                    ],
                    'performed_at' => $ratedAt,
                ]);

                return $rating->load([
                    'deliveryService',
                    'customer',
                ]);
            }, attempts: 3);
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            throw new DomainException(
                'The delivery service has already been rated.',
                0,
                $exception
            );
        }
    }

    /**
     * @param array<int, int> $scores
     *
     * @throws DomainException
     */
    private function validateScores(array $scores): void
    {
        foreach ($scores as $score) {
            if ($score < 1 || $score > 5) {
                throw new DomainException(
                    'Every rating score must be between 1 and 5.'
                );
            }
        }
    }
}


