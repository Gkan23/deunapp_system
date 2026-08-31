<?php

namespace App\Services\Route;

use App\Models\Courier;
use App\Models\DeliveryService;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Vehicle;
use App\Models\VehicleStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ActivateRouteService
{
    /**
     * Activa una ruta planificada.
     *
     * Cuando la ruta tiene un vehículo asignado,
     * el vehículo cambia de AVAILABLE a IN_USE.
     *
     * @throws DomainException
     */
    public function handle(
        Route $route
    ): Route {
        return DB::transaction(function () use (
            $route
        ): Route {
            $lockedRoute = Route::query()
                ->with('routeStatus')
                ->whereKey(
                    $route->getKey()
                )
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateRoute(
                $lockedRoute
            );

            $courier = Courier::query()
                ->with('deliveryProvider')
                ->whereKey(
                    $lockedRoute->courier_id
                )
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateCourier(
                $courier
            );

            $vehicle = $this
                ->lockAndValidateVehicle(
                    $lockedRoute,
                    $courier
                );

            $activeStatus = RouteStatus::query()
                ->where(
                    'status_name',
                    'ACTIVE'
                )
                ->firstOrFail();

            $this->validateActiveRouteConflict(
                $lockedRoute,
                $activeStatus
            );

            if ($vehicle !== null) {
                $this->validateActiveVehicleConflict(
                    $lockedRoute,
                    $vehicle,
                    $activeStatus
                );
            }

            $routeShipments =
                RouteShipment::query()
                    ->with([
                        'shipment.deliveryService.trip',
                    ])
                    ->where(
                        'route_id',
                        $lockedRoute->id
                    )
                    ->orderBy(
                        'delivery_order'
                    )
                    ->lockForUpdate()
                    ->get();

            if ($routeShipments->isEmpty()) {
                throw new DomainException(
                    'An empty route cannot be activated.'
                );
            }

            foreach (
                $routeShipments as $routeShipment
            ) {
                $this->validateRouteShipment(
                    $routeShipment,
                    $courier
                );
            }

            $inUseVehicleStatus = null;

            if ($vehicle !== null) {
                $inUseVehicleStatus =
                    VehicleStatus::query()
                        ->where(
                            'status_name',
                            'IN_USE'
                        )
                        ->firstOrFail();
            }

            $now = now();

            $lockedRoute->update([
                'route_status_id' =>
                    $activeStatus->id,
                'started_at' => $now,
                'finished_at' => null,
            ]);

            $courier->update([
                'is_available' => false,
            ]);

            if (
                $vehicle !== null
                && $inUseVehicleStatus !== null
            ) {
                $vehicle->update([
                    'vehicle_status_id' =>
                        $inUseVehicleStatus->id,
                ]);
            }

            foreach (
                $routeShipments as $routeShipment
            ) {
                $service = $routeShipment
                    ->shipment
                    ->deliveryService;

                $routeShipment->update([
                    'delivery_status' =>
                        'IN_PROGRESS',
                ]);

                $service->update([
                    'status' => 'IN_PROGRESS',
                    'started_at' => $now,
                ]);
            }

            return $lockedRoute
                ->refresh()
                ->load([
                    'courier.deliveryProvider',
                    'vehicle.vehicleType',
                    'vehicle.vehicleStatus',
                    'routeStatus',
                    'routeShipments.shipment.deliveryService.trip',
                ]);
        }, attempts: 3);
    }

    private function validateRoute(
        Route $route
    ): void {
        if (
            $route
                ->routeStatus
                ->status_name
            !== 'PLANNED'
        ) {
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

        if (
            ! $courier
                ->deliveryProvider
                ->is_active
        ) {
            throw new DomainException(
                'The courier delivery provider is inactive.'
            );
        }
    }

    private function lockAndValidateVehicle(
        Route $route,
        Courier $courier
    ): ?Vehicle {
        /*
         * Las rutas antiguas pueden no tener un vehículo.
         * Esta compatibilidad se eliminará cuando todas
         * las rutas existentes hayan sido actualizadas.
         */
        if ($route->vehicle_id === null) {
            return null;
        }

        $vehicle = Vehicle::query()
            ->with([
                'vehicleStatus',
                'vehicleType',
            ])
            ->whereKey(
                $route->vehicle_id
            )
            ->lockForUpdate()
            ->firstOrFail();

        if (
            (int) $vehicle->courier_id
            !== (int) $courier->id
        ) {
            throw new DomainException(
                'The route vehicle does not belong to the route courier.'
            );
        }

        if (
            $vehicle
                ->vehicleStatus
                ->status_name
            !== 'AVAILABLE'
        ) {
            throw new DomainException(
                'Only an available vehicle can activate a route.'
            );
        }

        return $vehicle;
    }

    private function validateActiveRouteConflict(
        Route $route,
        RouteStatus $activeStatus
    ): void {
        $activeRoute = Route::query()
            ->where(
                'courier_id',
                $route->courier_id
            )
            ->where(
                'route_status_id',
                $activeStatus->id
            )
            ->whereKeyNot(
                $route->id
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($activeRoute !== null) {
            throw new DomainException(
                'The courier already has an active route.'
            );
        }
    }

    private function validateActiveVehicleConflict(
        Route $route,
        Vehicle $vehicle,
        RouteStatus $activeStatus
    ): void {
        $activeVehicleRoute = Route::query()
            ->where(
                'vehicle_id',
                $vehicle->id
            )
            ->where(
                'route_status_id',
                $activeStatus->id
            )
            ->whereKeyNot(
                $route->id
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($activeVehicleRoute !== null) {
            throw new DomainException(
                'The vehicle is already being used by another active route.'
            );
        }
    }

    private function validateRouteShipment(
        RouteShipment $routeShipment,
        Courier $courier
    ): void {
        if (
            $routeShipment->delivery_status
            !== 'PENDING'
        ) {
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
            (int) $service
                ->trip
                ->delivery_provider_id
            !== (int) $courier
                ->delivery_provider_id
        ) {
            throw new DomainException(
                'The route shipment provider does not match the courier provider.'
            );
        }
    }
}