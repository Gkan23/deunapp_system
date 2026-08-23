<?php

namespace App\Services\Route;

use App\Models\Courier;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CreateRouteService
{
    /**
     * @param  array<int, Shipment>  $shipments
     */
    public function handle(
        Courier $courier,
        array $shipments,
        CarbonInterface $routeDate,
        ?float $estimatedDistanceKm = null
    ): Route {
        $shipmentIds = $this->extractShipmentIds(
            $shipments
        );

        $normalizedRouteDate = Carbon::parse(
            $routeDate->toDateString()
        )->startOfDay();

        $this->validateRouteData(
            $normalizedRouteDate,
            $estimatedDistanceKm
        );

        return DB::transaction(function () use (
            $courier,
            $shipmentIds,
            $normalizedRouteDate,
            $estimatedDistanceKm
        ): Route {
            $lockedCourier = Courier::query()
                ->with('deliveryProvider')
                ->lockForUpdate()
                ->findOrFail($courier->getKey());

            $this->validateCourier($lockedCourier);

            $lockedShipments = Shipment::query()
                ->with([
                    'shipmentStatus',
                    'deliveryService.trip',
                ])
                ->whereIn('id', $shipmentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (
                $lockedShipments->count()
                !== count($shipmentIds)
            ) {
                throw new DomainException(
                    'One or more shipments could not be found.'
                );
            }

            $shipmentMap = $lockedShipments->keyBy(
                fn (Shipment $shipment) => $shipment->id
            );

            foreach ($shipmentIds as $shipmentId) {
                $shipment = $shipmentMap->get(
                    $shipmentId
                );

                $this->validateShipment(
                    $shipment,
                    $lockedCourier
                );
            }

            $this->validateRouteConflicts(
                $shipmentIds
            );

            $plannedStatus = RouteStatus::query()
                ->where('status_name', 'PLANNED')
                ->firstOrFail();

            $route = Route::query()->create([
                'courier_id' => $lockedCourier->id,
                'route_status_id' => $plannedStatus->id,
                'route_date' => $normalizedRouteDate->toDateString(),
                'started_at' => null,
                'finished_at' => null,
                'estimated_distance_km' => $estimatedDistanceKm,
            ]);

            foreach (
                $shipmentIds as $index => $shipmentId
            ) {
                RouteShipment::query()->create([
                    'route_id' => $route->id,
                    'shipment_id' => $shipmentId,
                    'delivery_order' => $index + 1,
                    'delivery_status' => 'PENDING',
                ]);
            }

            return $route->load([
                'courier.deliveryProvider',
                'routeStatus',
                'routeShipments.shipment.deliveryService.trip',
            ]);
        }, attempts: 3);
    }

    /**
     * @param  array<int, Shipment>  $shipments
     * @return array<int, int>
     */
    private function extractShipmentIds(
        array $shipments
    ): array {
        if ($shipments === []) {
            throw new DomainException(
                'At least one shipment is required.'
            );
        }

        $shipmentIds = [];

        foreach ($shipments as $shipment) {
            if (
                ! $shipment instanceof Shipment
                || ! $shipment->exists
            ) {
                throw new DomainException(
                    'Every route item must be a persisted shipment.'
                );
            }

            $shipmentIds[] = (int) $shipment->getKey();
        }

        if (
            count($shipmentIds)
            !== count(array_unique($shipmentIds))
        ) {
            throw new DomainException(
                'A shipment cannot be repeated within the same route.'
            );
        }

        return $shipmentIds;
    }

    private function validateRouteData(
        CarbonInterface $routeDate,
        ?float $estimatedDistanceKm
    ): void {
        if ($routeDate->lt(today())) {
            throw new DomainException(
                'The route date cannot be in the past.'
            );
        }

        if (
            $estimatedDistanceKm !== null
            && $estimatedDistanceKm < 0
        ) {
            throw new DomainException(
                'The estimated distance cannot be negative.'
            );
        }
    }

    private function validateCourier(
        Courier $courier
    ): void {
        if (! $courier->is_active) {
            throw new DomainException(
                'The courier is inactive.'
            );
        }

        if (! $courier->is_available) {
            throw new DomainException(
                'The courier is not available.'
            );
        }

        if (! $courier->deliveryProvider->is_active) {
            throw new DomainException(
                'The courier delivery provider is inactive.'
            );
        }
    }

    private function validateShipment(
        Shipment $shipment,
        Courier $courier
    ): void {
        if (
            in_array(
                $shipment->shipmentStatus->status_name,
                ['DELIVERED', 'CANCELLED'],
                true
            )
        ) {
            throw new DomainException(
                'A terminal shipment cannot be added to a route.'
            );
        }

        $service = $shipment->deliveryService;

        if (
            $service === null
            || $service->status !== 'ASSIGNED'
            || $service->trip === null
        ) {
            throw new DomainException(
                'Each shipment must have an assigned delivery service.'
            );
        }

        if (
            (int) $service->trip->delivery_provider_id
            !== (int) $courier->delivery_provider_id
        ) {
            throw new DomainException(
                'All shipment trips must belong to the courier provider.'
            );
        }
    }

    /**
     * @param  array<int, int>  $shipmentIds
     */
    private function validateRouteConflicts(
        array $shipmentIds
    ): void {
        $conflict = RouteShipment::query()
            ->whereIn('shipment_id', $shipmentIds)
            ->whereHas(
                'route.routeStatus',
                fn ($query) => $query->whereIn(
                    'status_name',
                    ['PLANNED', 'ACTIVE']
                )
            )
            ->lockForUpdate()
            ->first();

        if ($conflict !== null) {
            throw new DomainException(
                'A shipment already belongs to a planned or active route.'
            );
        }
    }
}
