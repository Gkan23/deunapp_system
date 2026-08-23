<?php

namespace App\Services\Shipment;

use App\Models\DeliveryProof;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\ShipmentStatusHistory;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UpdateShipmentStatusService
{
    private const ALLOWED_TRANSITIONS = [
        'REQUESTED' => [
            'PICKED_UP',
            'CANCELLED',
        ],
        'PICKED_UP' => [
            'IN_TRANSIT',
            'CANCELLED',
        ],
        'IN_TRANSIT' => [
            'OUT_FOR_DELIVERY',
            'CANCELLED',
        ],
        'OUT_FOR_DELIVERY' => [
            'DELIVERED',
            'CANCELLED',
        ],
        'DELIVERED' => [],
        'CANCELLED' => [],
    ];

    public function handle(
        Shipment $shipment,
        ShipmentStatus $newStatus,
        ?User $changedBy = null,
        ?string $comment = null
    ): Shipment {
        return DB::transaction(function () use (
            $shipment,
            $newStatus,
            $changedBy,
            $comment
        ): Shipment {
            $lockedShipment = Shipment::query()
                ->with('shipmentStatus')
                ->lockForUpdate()
                ->findOrFail($shipment->getKey());

            $targetStatus = ShipmentStatus::query()
                ->findOrFail($newStatus->getKey());

            $currentStatusName =
                $lockedShipment->shipmentStatus->status_name;

            $targetStatusName =
                $targetStatus->status_name;

            $this->validateTransition(
                $currentStatusName,
                $targetStatusName
            );

            if ($targetStatusName === 'DELIVERED') {
                $deliveryProof = DeliveryProof::query()
                    ->where(
                        'shipment_id',
                        $lockedShipment->id
                    )
                    ->lockForUpdate()
                    ->first();

                $this->validateDeliveryProof(
                    $deliveryProof
                );
            }

            $now = now();

            $updates = [
                'shipment_status_id' => $targetStatus->id,
            ];

            if ($targetStatusName === 'DELIVERED') {
                $updates['delivered_at'] = $now;
            }

            $lockedShipment->update($updates);

            ShipmentStatusHistory::query()->create([
                'shipment_id' => $lockedShipment->id,
                'shipment_status_id' => $targetStatus->id,
                'changed_by_user_id' => $changedBy?->id,
                'comment' => $this->normalizeComment(
                    $comment
                ),
                'changed_at' => $now,
            ]);

            return $lockedShipment->refresh()->load([
                'shipmentStatus',
                'statusHistory',
                'deliveryProof',
            ]);
        }, attempts: 3);
    }

    private function validateTransition(
        string $currentStatus,
        string $targetStatus
    ): void {
        if ($currentStatus === $targetStatus) {
            throw new DomainException(
                'The shipment already has the requested status.'
            );
        }

        $allowedStatuses =
            self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];

        if (
            ! in_array(
                $targetStatus,
                $allowedStatuses,
                true
            )
        ) {
            throw new DomainException(
                "The transition from {$currentStatus} "
                ."to {$targetStatus} is not allowed."
            );
        }
    }

    private function validateDeliveryProof(
        ?DeliveryProof $deliveryProof
    ): void {
        if ($deliveryProof === null) {
            throw new DomainException(
                'Delivery proof is required before marking the shipment as delivered.'
            );
        }

        $hasReceiverName =
            trim($deliveryProof->receiver_name) !== '';

        $hasEvidence =
            $this->hasValue($deliveryProof->photo_url)
            || $this->hasValue(
                $deliveryProof->signature_url
            )
            || $this->hasValue(
                $deliveryProof->receiver_identity_number
            );

        if (! $hasReceiverName || ! $hasEvidence) {
            throw new DomainException(
                'The delivery proof is incomplete.'
            );
        }
    }

    private function hasValue(?string $value): bool
    {
        return $value !== null
            && trim($value) !== '';
    }

    private function normalizeComment(
        ?string $comment
    ): ?string {
        if ($comment === null) {
            return null;
        }

        $comment = trim($comment);

        return $comment === '' ? null : $comment;
    }
}
