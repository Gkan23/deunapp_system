<?php

namespace App\Services\Delivery;

use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Trip;
use App\Models\TripTransaction;
use DomainException;
use Illuminate\Support\Facades\DB;

final class AssignAvailableTripService
{
    public function handle(
        DeliveryService $deliveryService,
        DeliveryProvider $deliveryProvider
    ): DeliveryService {
        return DB::transaction(function () use (
            $deliveryService,
            $deliveryProvider
        ): DeliveryService {
            $service = DeliveryService::query()
                ->with('shipment')
                ->lockForUpdate()
                ->findOrFail($deliveryService->getKey());

            $provider = DeliveryProvider::query()
                ->lockForUpdate()
                ->findOrFail($deliveryProvider->getKey());

            $this->validateProvider($provider);
            $this->validateService($service);

            $trip = Trip::query()
                ->where(
                    'delivery_provider_id',
                    $provider->id
                )
                ->where(
                    'trip_type_id',
                    $service->trip_type_id
                )
                ->where('status', 'AVAILABLE')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($trip === null) {
                throw new DomainException(
                    'No matching trips are available for this provider.'
                );
            }

            $now = now();

            $trip->update([
                'status' => 'USED',
                'used_at' => $now,
            ]);

            $service->update([
                'trip_id' => $trip->id,
                'status' => 'ASSIGNED',
                'accepted_at' => $now,
            ]);

            TripTransaction::query()->create([
                'delivery_provider_id' => $provider->id,
                'recharge_id' => null,
                'trip_id' => $trip->id,
                'transaction_type' => 'DEBIT',
                'quantity' => 1,
                'description' => 'Viaje consumido por la asignación del servicio.',
                'transaction_at' => $now,
            ]);

            return $service->refresh()->load([
                'customer',
                'shipment',
                'trip.deliveryProvider',
                'trip.tripType',
            ]);
        }, attempts: 3);
    }

    private function validateProvider(
        DeliveryProvider $provider
    ): void {
        if (! $provider->is_active) {
            throw new DomainException(
                'The delivery provider is inactive.'
            );
        }
    }

    private function validateService(
        DeliveryService $service
    ): void {
        if (
            $service->status !== 'REQUESTED'
            || $service->trip_id !== null
        ) {
            throw new DomainException(
                'The delivery service is not available for assignment.'
            );
        }

        if (
            (int) $service->customer_id
            !== (int) $service->shipment->customer_id
        ) {
            throw new DomainException(
                'The service and shipment customers do not match.'
            );
        }
    }
}
