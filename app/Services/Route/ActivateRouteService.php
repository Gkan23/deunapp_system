<?php

namespace App\Services\Route;

use App\Models\Courier;
use App\Models\DeliveryService;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ActivateRouteService
{
    public function handle(Route $route): Route
    {
        return DB::transaction(function () use ($route): Route {
            $lockedRoute = Route::query()
                ->with('routeStatus')
                ->lockForUpdate()
                ->findOrFail($route->getKey());

            $this->validateRoute($lockedRoute);

            $courier = Courier::query()
                ->with('deliveryProvider')
                ->lockForUpdate()
                ->findOrFail($lockedRoute->courier_id);

            $this->validateCourier($courier);

            $activeStatus = RouteStatus::query()
                ->where('status_name', 'ACTIVE')
                ->firstOrFail();

            $this->validateActiveRouteConflict(
                $lockedRoute,
                $activeStatus
            );

            $routeShipments = RouteShipment::query()
                ->with([
                    'shipment.deliveryService.trip',
                ])
                ->where('route_id', $lockedRoute->id)
                ->orderBy('delivery_order')
                ->lockForUpdate()
                ->get();

            if ($routeShipments->isEmpty()) {
                throw new DomainException(
                    'An empty route cannot be activated.'
                );
            }

            foreach ($routeShipments as $routeShipment) {
                $this->validateRouteShipment(
                    $routeShipment,
                    $courier
                );
            }

            $now = now();

            $lockedRoute->update([
                'route_status_id' => $activeStatus->id,
                'started_at' => $now,
                'finished_at' => null,
            ]);

            $courier->update([
                'is_available' => false,
            ]);

            foreach ($routeShipments as $routeShipment) {
                $service = $routeShipment
                    ->shipment
                    ->deliveryService;

                $routeShipment->update([
                    'delivery_status' => 'IN_PROGRESS',
                ]);

                $service->update([
                    'status' => 'IN_PROGRESS',
                    'started_at' => $now,
                ]);
            }

            return $lockedRoute->refresh()->load([
                'courier.deliveryProvider',
                'routeStatus',
                'routeShipments.shipment.deliveryService.trip',
            ]);
        }, attempts: 3);
    }

    private function validateRoute(Route $route): void
    {
        if ($route->routeStatus->status_name !== 'PLANNED') {
            throw new DomainException(
                'Only a planned route can be activated.'
            );
        }

        if (! $route->route_date->isToday()) {
            throw new DomainException(
                'A route can only be activated on its scheduled date.'
            );
        }
    }

    private function validateCourier(Courier $courier): void
    {
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

    private function validateActiveRouteConflict(
        Route $route,
        RouteStatus $activeStatus
    ): void {
        $activeRoute = Route::query()
            ->where('courier_id', $route->courier_id)
            ->where('route_status_id', $activeStatus->id)
            ->whereKeyNot($route->id)
            ->lockForUpdate()
            ->first();

        if ($activeRoute !== null) {
            throw new DomainException(
                'The courier already has an active route.'
            );
        }
    }

    private function validateRouteShipment(
        RouteShipment $routeShipment,
        Courier $courier
    ): void {
        if ($routeShipment->delivery_status !== 'PENDING') {
            throw new DomainException(
                'Every route shipment must be pending before activation.'
            );
        }

        $service = $routeShipment
            ->shipment
            ->deliveryService;

        if (
            ! $service instanceof DeliveryService
            || $service->status !== 'ASSIGNED'
            || $service->trip === null
        ) {
            throw new DomainException(
                'Every route shipment must have an assigned delivery service.'
            );
        }

        if (
            (int) $service->trip->delivery_provider_id
            !== (int) $courier->delivery_provider_id
        ) {
            throw new DomainException(
                'The route shipment provider does not match the courier provider.'
            );
        }
    }
}
