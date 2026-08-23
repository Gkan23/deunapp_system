<?php

namespace App\Services\Delivery;

use App\Models\DeliveryProof;
use App\Models\DeliveryService;
use App\Models\RouteShipment;
use App\Models\ShipmentStatus;
use App\Models\User;
use App\Services\Shipment\UpdateShipmentStatusService;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CompleteDeliveryService
{
    public function __construct(
        private readonly UpdateShipmentStatusService $statusService
    ) {}

    /**
     * @param array{
     *     photo_url?: string|null,
     *     signature_url?: string|null,
     *     receiver_name: string,
     *     receiver_identity_number?: string|null,
     *     latitude?: numeric|null,
     *     longitude?: numeric|null
     * } $proofData
     */
    public function handle(
        DeliveryService $deliveryService,
        array $proofData,
        User $completedBy,
        ?string $comment = null
    ): DeliveryService {
        return DB::transaction(function () use (
            $deliveryService,
            $proofData,
            $completedBy,
            $comment
        ): DeliveryService {
            $service = DeliveryService::query()
                ->with([
                    'shipment.shipmentStatus',
                    'trip',
                ])
                ->lockForUpdate()
                ->findOrFail($deliveryService->getKey());

            $this->validateService($service);
            $this->validateShipment($service);

            $routeShipment = $this->findRouteShipment(
                $service
            );

            $this->validateRouteProvider(
                $service,
                $routeShipment
            );

            if (
                DeliveryProof::query()
                    ->where(
                        'shipment_id',
                        $service->shipment_id
                    )
                    ->exists()
            ) {
                throw new DomainException(
                    'Delivery proof already exists for this shipment.'
                );
            }

            $normalizedProof = $this->normalizeProofData(
                $proofData
            );

            DeliveryProof::query()->create([
                'shipment_id' => $service->shipment_id,
                ...$normalizedProof,
                'recorded_at' => now(),
            ]);

            $deliveredStatus = ShipmentStatus::query()
                ->where('status_name', 'DELIVERED')
                ->firstOrFail();

            $this->statusService->handle(
                $service->shipment,
                $deliveredStatus,
                $completedBy,
                $comment
            );

            $now = now();

            $service->update([
                'status' => 'COMPLETED',
                'completed_at' => $now,
            ]);

            $routeShipment->update([
                'delivery_status' => 'DELIVERED',
            ]);

            return $service->refresh()->load([
                'customer',
                'trip.deliveryProvider',
                'shipment.shipmentStatus',
                'shipment.deliveryProof',
                'shipment.statusHistory',
                'shipment.routeShipments.route.courier',
            ]);
        }, attempts: 3);
    }

    private function validateService(
        DeliveryService $service
    ): void {
        if (
            $service->status !== 'IN_PROGRESS'
            || $service->trip_id === null
            || $service->started_at === null
        ) {
            throw new DomainException(
                'The delivery service is not in progress.'
            );
        }
    }

    private function validateShipment(
        DeliveryService $service
    ): void {
        if (
            $service->shipment
                ->shipmentStatus
                ->status_name !== 'OUT_FOR_DELIVERY'
        ) {
            throw new DomainException(
                'The shipment is not out for delivery.'
            );
        }
    }

    private function findRouteShipment(
        DeliveryService $service
    ): RouteShipment {
        $routeShipments = RouteShipment::query()
            ->with([
                'route.routeStatus',
                'route.courier',
            ])
            ->where(
                'shipment_id',
                $service->shipment_id
            )
            ->where(
                'delivery_status',
                'IN_PROGRESS'
            )
            ->lockForUpdate()
            ->get();

        if ($routeShipments->count() !== 1) {
            throw new DomainException(
                'The shipment must belong to exactly one in-progress route.'
            );
        }

        $routeShipment = $routeShipments->firstOrFail();

        if (
            $routeShipment->route
                ->routeStatus
                ->status_name !== 'ACTIVE'
        ) {
            throw new DomainException(
                'The shipment route is not active.'
            );
        }

        return $routeShipment;
    }

    private function validateRouteProvider(
        DeliveryService $service,
        RouteShipment $routeShipment
    ): void {
        $routeProviderId = $routeShipment
            ->route
            ->courier
            ->delivery_provider_id;

        $tripProviderId = $service
            ->trip
            ->delivery_provider_id;

        if ((int) $routeProviderId !== (int) $tripProviderId) {
            throw new DomainException(
                'The route courier does not belong to the trip provider.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $proofData
     * @return array{
     *     photo_url: string|null,
     *     signature_url: string|null,
     *     receiver_name: string,
     *     receiver_identity_number: string|null,
     *     latitude: mixed,
     *     longitude: mixed
     * }
     */
    private function normalizeProofData(
        array $proofData
    ): array {
        $receiverName = trim(
            (string) ($proofData['receiver_name'] ?? '')
        );

        $photoUrl = $this->normalizeOptionalString(
            $proofData['photo_url'] ?? null
        );

        $signatureUrl = $this->normalizeOptionalString(
            $proofData['signature_url'] ?? null
        );

        $receiverIdentity = $this->normalizeOptionalString(
            $proofData['receiver_identity_number'] ?? null
        );

        if ($receiverName === '') {
            throw new DomainException(
                'The receiver name is required.'
            );
        }

        if (
            $photoUrl === null
            && $signatureUrl === null
            && $receiverIdentity === null
        ) {
            throw new DomainException(
                'At least one form of delivery evidence is required.'
            );
        }

        return [
            'photo_url' => $photoUrl,
            'signature_url' => $signatureUrl,
            'receiver_name' => $receiverName,
            'receiver_identity_number' => $receiverIdentity,
            'latitude' => $proofData['latitude'] ?? null,
            'longitude' => $proofData['longitude'] ?? null,
        ];
    }

    private function normalizeOptionalString(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
