<?php

namespace App\Services\Shipment;

use App\Models\Courier;
use App\Models\CourierLocation;
use App\Models\RouteShipment;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;

class GetShipmentTrackingService
{
    /**
     * Obtiene la ubicación actual relacionada con un envío.
     *
     * La ubicación solamente se publica cuando el envío
     * pertenece a una ruta activa y todavía está pendiente
     * o en proceso de entrega.
     *
     * @return array<string, mixed>
     */
    public function execute(Shipment $shipment): array
    {
        $shipment->loadMissing('shipmentStatus');

        $routeShipment = RouteShipment::query()
            ->with([
                'route.courier',
            ])
            ->where(
                'shipment_id',
                $shipment->getKey()
            )
            ->whereIn(
                'delivery_status',
                [
                    'PENDING',
                    'IN_PROGRESS',
                ]
            )
            ->whereHas(
                'route.routeStatus',
                function (Builder $query): void {
                    $query->where(
                        'status_name',
                        'ACTIVE'
                    );
                }
            )
            ->orderByDesc('id')
            ->first();

        if ($routeShipment === null) {
            return $this->unavailableTrackingData(
                shipment: $shipment,
                reason: 'NO_ACTIVE_ROUTE'
            );
        }

        $route = $routeShipment->route;
        $courier = $route->courier;

        $location = CourierLocation::query()
            ->where(
                'courier_id',
                $courier->getKey()
            )
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();

        if ($location === null) {
            return [
                ...$this->shipmentData($shipment),
                'tracking_available' => false,
                'reason' => 'LOCATION_NOT_RECORDED',
                'route' => [
                    'id' => $route->id,
                    'delivery_order' =>
                        $routeShipment->delivery_order,
                    'delivery_status' =>
                        $routeShipment->delivery_status,
                    'started_at' =>
                        $route->started_at
                            ?->toIso8601String(),
                ],
                'courier' =>
                    $this->courierData($courier),
                'location' => null,
            ];
        }

        $latitude = (float) $location->latitude;
        $longitude = (float) $location->longitude;

        return [
            ...$this->shipmentData($shipment),
            'tracking_available' => true,
            'reason' => null,
            'route' => [
                'id' => $route->id,
                'delivery_order' =>
                    $routeShipment->delivery_order,
                'delivery_status' =>
                    $routeShipment->delivery_status,
                'started_at' =>
                    $route->started_at
                        ?->toIso8601String(),
            ],
            'courier' =>
                $this->courierData($courier),
            'location' => [
                /*
                 * Mapbox utiliza el orden:
                 *
                 * [longitud, latitud]
                 */
                'type' => 'Point',
                'coordinates' => [
                    $longitude,
                    $latitude,
                ],
                'latitude' => $latitude,
                'longitude' => $longitude,
                'gps_accuracy' =>
                    $location->gps_accuracy === null
                        ? null
                        : (float) $location->gps_accuracy,
                'recorded_at' =>
                    $location
                        ->recorded_at
                        ->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableTrackingData(
        Shipment $shipment,
        string $reason
    ): array {
        return [
            ...$this->shipmentData($shipment),
            'tracking_available' => false,
            'reason' => $reason,
            'route' => null,
            'courier' => null,
            'location' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentData(
        Shipment $shipment
    ): array {
        return [
            'shipment_id' => $shipment->id,
            'tracking_code' =>
                $shipment->tracking_code,
            'shipment_status' =>
                $shipment
                    ->shipmentStatus
                    ?->status_name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function courierData(
        Courier $courier
    ): array {
        return [
            'id' => $courier->id,
            'is_active' =>
                (bool) $courier->is_active,
            'is_available' =>
                (bool) $courier->is_available,
        ];
    }
}