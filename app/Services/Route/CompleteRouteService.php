<?php

namespace App\Services\Route;

use App\Models\Courier;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Vehicle;
use App\Models\VehicleStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

class CompleteRouteService
{
    private const TERMINAL_DELIVERY_STATUSES = [
        'DELIVERED',
        'FAILED',
    ];

    /**
     * Completa una ruta activa.
     *
     * Todos los envíos deben encontrarse en un
     * estado terminal antes de completar la ruta.
     *
     * Si el vehículo continúa en IN_USE,
     * vuelve automáticamente a AVAILABLE.
     *
     * @throws DomainException
     */
    public function execute(
        Route $route
    ): Route {
        return DB::transaction(function () use (
            $route
        ): Route {
            $lockedRoute = Route::query()
                ->whereKey(
                    $route->getKey()
                )
                ->lockForUpdate()
                ->firstOrFail();

            $activeStatus = RouteStatus::query()
                ->where(
                    'status_name',
                    'ACTIVE'
                )
                ->firstOrFail();

            if (
                (int) $lockedRoute
                    ->route_status_id
                !== (int) $activeStatus->id
            ) {
                throw new DomainException(
                    'Only an active route can be completed.'
                );
            }

            $courier = Courier::query()
                ->whereKey(
                    $lockedRoute->courier_id
                )
                ->lockForUpdate()
                ->firstOrFail();

            $vehicle = $this
                ->lockAndValidateVehicle(
                    $lockedRoute,
                    $courier
                );

            $routeShipments =
                RouteShipment::query()
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
                    'A route without shipments cannot be completed.'
                );
            }

            $hasNonTerminalShipment =
                $routeShipments->contains(
                    fn (
                        RouteShipment $routeShipment
                    ): bool => ! in_array(
                        $routeShipment
                            ->delivery_status,
                        self::TERMINAL_DELIVERY_STATUSES,
                        true
                    )
                );

            if ($hasNonTerminalShipment) {
                throw new DomainException(
                    'Every route shipment must be delivered or failed before completing the route.'
                );
            }

            $completedStatus =
                RouteStatus::query()
                    ->where(
                        'status_name',
                        'COMPLETED'
                    )
                    ->firstOrFail();

            $completedAt = now();

            $lockedRoute->update([
                'route_status_id' =>
                    $completedStatus->id,
                'finished_at' =>
                    $completedAt,
            ]);

            /*
             * Un repartidor inactivo no debe aparecer
             * disponible después de terminar la ruta.
             */
            $courier->update([
                'is_available' =>
                    (bool) $courier->is_active,
            ]);

            $this->releaseVehicle(
                $vehicle
            );

            return $lockedRoute->fresh([
                'routeStatus',
                'courier.deliveryProvider',
                'vehicle.vehicleType',
                'vehicle.vehicleStatus',
                'routeShipments',
            ]);
        }, attempts: 3);
    }

    /**
     * Bloquea el vehículo asignado y verifica que
     * todavía pertenezca al repartidor de la ruta.
     */
    private function lockAndValidateVehicle(
        Route $route,
        Courier $courier
    ): ?Vehicle {
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

        return $vehicle;
    }

    /**
     * Libera únicamente vehículos que continúan
     * en IN_USE.
     *
     * Un vehículo puesto en mantenimiento durante
     * la ruta debe conservar ese estado.
     */
    private function releaseVehicle(
        ?Vehicle $vehicle
    ): void {
        if (
            $vehicle === null
            || $vehicle
                ->vehicleStatus
                ->status_name
                !== 'IN_USE'
        ) {
            return;
        }

        $availableStatus =
            VehicleStatus::query()
                ->where(
                    'status_name',
                    'AVAILABLE'
                )
                ->firstOrFail();

        $vehicle->update([
            'vehicle_status_id' =>
                $availableStatus->id,
        ]);
    }
}