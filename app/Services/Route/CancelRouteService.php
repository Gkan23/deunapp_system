<?php

namespace App\Services\Route;

use App\Models\Courier;
use App\Models\DeliveryService;
use App\Models\Incident;
use App\Models\IncidentStatus;
use App\Models\IncidentType;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleStatus;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CancelRouteService
{
    private const CANCELLABLE_ROUTE_STATUSES = [
        'PLANNED',
        'ACTIVE',
    ];

    private const CANCELLABLE_DELIVERY_STATUSES = [
        'PENDING',
        'IN_PROGRESS',
    ];

    /**
     * Cancela una ruta planificada o activa.
     *
     * Si la ruta estaba activa, libera el vehículo
     * siempre que continúe en IN_USE.
     *
     * @throws DomainException
     */
    public function execute(
        Route $route,
        User $cancelledBy,
        string $reason
    ): Route {
        return DB::transaction(function () use (
            $route,
            $cancelledBy,
            $reason
        ): Route {
            $normalizedReason = trim(
                $reason
            );

            if ($normalizedReason === '') {
                throw new DomainException(
                    'The route cancellation reason is required.'
                );
            }

            $lockedRoute = Route::query()
                ->whereKey(
                    $route->getKey()
                )
                ->lockForUpdate()
                ->firstOrFail();

            $currentRouteStatus =
                RouteStatus::query()
                    ->whereKey(
                        $lockedRoute
                            ->route_status_id
                    )
                    ->firstOrFail();

            if (
                ! in_array(
                    $currentRouteStatus
                        ->status_name,
                    self::CANCELLABLE_ROUTE_STATUSES,
                    true
                )
            ) {
                throw new DomainException(
                    'Only planned or active routes can be cancelled.'
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

            $affectedRouteShipments =
                $routeShipments
                    ->filter(
                        fn (
                            RouteShipment $routeShipment
                        ): bool => in_array(
                            $routeShipment
                                ->delivery_status,
                            self::CANCELLABLE_DELIVERY_STATUSES,
                            true
                        )
                    )
                    ->values();

            $deliveryServices =
                $this->lockDeliveryServices(
                    $affectedRouteShipments
                );

            $this->validateDeliveryServices(
                $affectedRouteShipments,
                $deliveryServices
            );

            $cancelledRouteStatus =
                RouteStatus::query()
                    ->where(
                        'status_name',
                        'CANCELLED'
                    )
                    ->firstOrFail();

            $cancellationIncidentType =
                IncidentType::query()
                    ->where(
                        'type_name',
                        'CANCELLATION'
                    )
                    ->firstOrFail();

            $openIncidentStatus =
                IncidentStatus::query()
                    ->where(
                        'status_name',
                        'OPEN'
                    )
                    ->firstOrFail();

            $cancelledAt = now();

            foreach (
                $affectedRouteShipments
                as $routeShipment
            ) {
                $deliveryService =
                    $deliveryServices->get(
                        $routeShipment
                            ->shipment_id
                    );

                $routeShipment->update([
                    'delivery_status' =>
                        'CANCELLED',
                ]);

                $deliveryService->update([
                    'status' => 'ASSIGNED',
                    'started_at' => null,
                    'completed_at' => null,
                    'cancelled_at' => null,
                ]);

                Incident::query()->create([
                    'shipment_id' =>
                        $routeShipment
                            ->shipment_id,
                    'reported_by_user_id' =>
                        $cancelledBy
                            ->getKey(),
                    'incident_type_id' =>
                        $cancellationIncidentType
                            ->id,
                    'incident_status_id' =>
                        $openIncidentStatus
                            ->id,
                    'description' =>
                        $normalizedReason,
                    'reported_at' =>
                        $cancelledAt,
                ]);
            }

            $lockedRoute->update([
                'route_status_id' =>
                    $cancelledRouteStatus->id,
                'finished_at' =>
                    $cancelledAt,
            ]);

            $activeRouteStatus =
                RouteStatus::query()
                    ->where(
                        'status_name',
                        'ACTIVE'
                    )
                    ->firstOrFail();

            $hasAnotherActiveRoute =
                Route::query()
                    ->where(
                        'courier_id',
                        $courier->id
                    )
                    ->where(
                        'route_status_id',
                        $activeRouteStatus->id
                    )
                    ->whereKeyNot(
                        $lockedRoute->id
                    )
                    ->exists();

            $courier->update([
                'is_available' =>
                    $courier->is_active
                    && ! $hasAnotherActiveRoute,
            ]);

            $this->releaseVehicle(
                route: $lockedRoute,
                vehicle: $vehicle,
                previousRouteStatus:
                    $currentRouteStatus,
                activeRouteStatus:
                    $activeRouteStatus
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

    private function releaseVehicle(
        Route $route,
        ?Vehicle $vehicle,
        RouteStatus $previousRouteStatus,
        RouteStatus $activeRouteStatus
    ): void {
        /*
         * Una ruta solamente planificada todavía
         * no había ocupado el vehículo.
         */
        if (
            $previousRouteStatus->status_name
            !== 'ACTIVE'
        ) {
            return;
        }

        /*
         * Las rutas antiguas pueden no tener
         * un vehículo asignado.
         */
        if ($vehicle === null) {
            return;
        }

        /*
         * Si el vehículo fue enviado a mantenimiento,
         * ese estado debe conservarse.
         */
        if (
            $vehicle
                ->vehicleStatus
                ->status_name
            !== 'IN_USE'
        ) {
            return;
        }

        /*
         * Protección para datos antiguos:
         * no libera el vehículo si otra ruta activa
         * todavía lo está utilizando.
         */
        $hasAnotherActiveVehicleRoute =
            Route::query()
                ->where(
                    'vehicle_id',
                    $vehicle->id
                )
                ->where(
                    'route_status_id',
                    $activeRouteStatus->id
                )
                ->whereKeyNot(
                    $route->id
                )
                ->exists();

        if ($hasAnotherActiveVehicleRoute) {
            return;
        }

        $availableVehicleStatus =
            VehicleStatus::query()
                ->where(
                    'status_name',
                    'AVAILABLE'
                )
                ->firstOrFail();

        $vehicle->update([
            'vehicle_status_id' =>
                $availableVehicleStatus->id,
        ]);
    }

    /**
     * @param Collection<int, RouteShipment> $routeShipments
     * @return Collection<int, DeliveryService>
     */
    private function lockDeliveryServices(
        Collection $routeShipments
    ): Collection {
        if ($routeShipments->isEmpty()) {
            return collect();
        }

        return DeliveryService::query()
            ->whereIn(
                'shipment_id',
                $routeShipments->pluck(
                    'shipment_id'
                )
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('shipment_id');
    }

    /**
     * @param Collection<int, RouteShipment> $routeShipments
     * @param Collection<int, DeliveryService> $deliveryServices
     *
     * @throws DomainException
     */
    private function validateDeliveryServices(
        Collection $routeShipments,
        Collection $deliveryServices
    ): void {
        foreach (
            $routeShipments
            as $routeShipment
        ) {
            $deliveryService =
                $deliveryServices->get(
                    $routeShipment
                        ->shipment_id
                );

            if ($deliveryService === null) {
                throw new DomainException(
                    'Every pending route shipment must have a delivery service.'
                );
            }

            if (
                ! in_array(
                    $deliveryService->status,
                    [
                        'ASSIGNED',
                        'IN_PROGRESS',
                    ],
                    true
                )
            ) {
                throw new DomainException(
                    'Only assigned or in-progress delivery services can be returned for rescheduling.'
                );
            }
        }
    }
}