<?php

namespace App\Services\Route;

use App\Models\Address;
use App\Models\CourierLocation;
use App\Models\Route;
use App\Models\RouteShipment;

class GetRouteMapDataService
{
    /**
     * Obtiene los datos necesarios para representar
     * una ruta mediante Mapbox.
     *
     * @return array<string, mixed>
     */
    public function execute(
        Route $route
    ): array {
        $loadedRoute = Route::query()
            ->with([
                'routeStatus',
                'courier.user',
                'vehicle.vehicleType',
                'vehicle.vehicleStatus',
                'routeShipments' =>
                    fn ($query) => $query
                        ->orderBy(
                            'delivery_order'
                        )
                        ->orderBy('id'),
                'routeShipments.shipment.shipmentStatus',
                'routeShipments.shipment.originAddress',
                'routeShipments.shipment.destinationAddress',
            ])
            ->findOrFail(
                $route->getKey()
            );

        $latestLocation =
            CourierLocation::query()
                ->where(
                    'courier_id',
                    $loadedRoute->courier_id
                )
                ->latest('recorded_at')
                ->latest('id')
                ->first();

        $stops = $loadedRoute
            ->routeShipments
            ->map(
                fn (
                    RouteShipment $routeShipment
                ): array => $this->stopData(
                    $routeShipment
                )
            )
            ->values()
            ->all();

        $geoJsonFeatures = [];

        if ($latestLocation !== null) {
            $geoJsonFeatures[] =
                $this->pointFeature(
                    latitude:
                        (float) $latestLocation
                            ->latitude,
                    longitude:
                        (float) $latestLocation
                            ->longitude,
                    properties: [
                        'marker_type' =>
                            'COURIER',
                        'route_id' =>
                            $loadedRoute->id,
                        'courier_id' =>
                            $loadedRoute
                                ->courier_id,
                        'recorded_at' =>
                            $latestLocation
                                ->recorded_at
                                ?->toISOString(),
                    ]
                );
        }

        foreach ($stops as $stop) {
            $origin = $stop['origin'];

            if (
                $this->hasCoordinates(
                    $origin
                )
            ) {
                $geoJsonFeatures[] =
                    $this->pointFeature(
                        latitude:
                            $origin['latitude'],
                        longitude:
                            $origin['longitude'],
                        properties: [
                            'marker_type' =>
                                'ORIGIN',
                            'route_id' =>
                                $loadedRoute->id,
                            'route_shipment_id' =>
                                $stop[
                                    'route_shipment_id'
                                ],
                            'shipment_id' =>
                                $stop[
                                    'shipment'
                                ]['id'],
                            'tracking_code' =>
                                $stop[
                                    'shipment'
                                ]['tracking_code'],
                            'delivery_order' =>
                                $stop[
                                    'delivery_order'
                                ],
                        ]
                    );
            }

            $destination =
                $stop['destination'];

            if (
                $this->hasCoordinates(
                    $destination
                )
            ) {
                $geoJsonFeatures[] =
                    $this->pointFeature(
                        latitude:
                            $destination[
                                'latitude'
                            ],
                        longitude:
                            $destination[
                                'longitude'
                            ],
                        properties: [
                            'marker_type' =>
                                'DESTINATION',
                            'route_id' =>
                                $loadedRoute->id,
                            'route_shipment_id' =>
                                $stop[
                                    'route_shipment_id'
                                ],
                            'shipment_id' =>
                                $stop[
                                    'shipment'
                                ]['id'],
                            'tracking_code' =>
                                $stop[
                                    'shipment'
                                ]['tracking_code'],
                            'delivery_order' =>
                                $stop[
                                    'delivery_order'
                                ],
                            'delivery_status' =>
                                $stop[
                                    'delivery_status'
                                ],
                        ]
                    );
            }
        }

        return [
            'route' => [
                'id' => $loadedRoute->id,
                'route_date' =>
                    $loadedRoute
                        ->route_date
                        ?->toDateString(),
                'status' =>
                    $loadedRoute
                        ->routeStatus
                        ->status_name,
                'started_at' =>
                    $loadedRoute
                        ->started_at
                        ?->toISOString(),
                'finished_at' =>
                    $loadedRoute
                        ->finished_at
                        ?->toISOString(),
                'estimated_distance_km' =>
                    $loadedRoute
                        ->estimated_distance_km
                        === null
                            ? null
                            : (float) $loadedRoute
                                ->estimated_distance_km,
            ],
            'courier' => [
                'id' =>
                    $loadedRoute->courier->id,
                'name' =>
                    $loadedRoute
                        ->courier
                        ->user
                        ->name,
                'is_available' =>
                    (bool) $loadedRoute
                        ->courier
                        ->is_available,
                'latest_location' =>
                    $latestLocation === null
                        ? null
                        : [
                            'latitude' =>
                                (float) $latestLocation
                                    ->latitude,
                            'longitude' =>
                                (float) $latestLocation
                                    ->longitude,
                            'gps_accuracy' =>
                                $latestLocation
                                    ->gps_accuracy
                                    === null
                                        ? null
                                        : (float) $latestLocation
                                            ->gps_accuracy,
                            'recorded_at' =>
                                $latestLocation
                                    ->recorded_at
                                    ?->toISOString(),
                        ],
            ],
            'vehicle' =>
                $loadedRoute->vehicle === null
                    ? null
                    : [
                        'id' =>
                            $loadedRoute
                                ->vehicle
                                ->id,
                        'plate_number' =>
                            $loadedRoute
                                ->vehicle
                                ->plate_number,
                        'brand' =>
                            $loadedRoute
                                ->vehicle
                                ->brand,
                        'model' =>
                            $loadedRoute
                                ->vehicle
                                ->model,
                        'color' =>
                            $loadedRoute
                                ->vehicle
                                ->color,
                        'type' =>
                            $loadedRoute
                                ->vehicle
                                ->vehicleType
                                ->type_name,
                        'status' =>
                            $loadedRoute
                                ->vehicle
                                ->vehicleStatus
                                ->status_name,
                    ],
            'stops' => $stops,
            'summary' => [
                'stop_count' =>
                    count($stops),
                'geocoded_destination_count' =>
                    collect($stops)
                        ->filter(
                            fn (array $stop): bool =>
                                $this->hasCoordinates(
                                    $stop[
                                        'destination'
                                    ]
                                )
                        )
                        ->count(),
                'has_courier_location' =>
                    $latestLocation !== null,
            ],
            'geojson' => [
                'type' =>
                    'FeatureCollection',
                'features' =>
                    $geoJsonFeatures,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stopData(
        RouteShipment $routeShipment
    ): array {
        $shipment =
            $routeShipment->shipment;

        return [
            'route_shipment_id' =>
                $routeShipment->id,
            'delivery_order' =>
                $routeShipment
                    ->delivery_order,
            'delivery_status' =>
                $routeShipment
                    ->delivery_status,
            'shipment' => [
                'id' => $shipment->id,
                'tracking_code' =>
                    $shipment->tracking_code,
                'status' =>
                    $shipment
                        ->shipmentStatus
                        ->status_name,
            ],
            'origin' =>
                $this->addressData(
                    $shipment->originAddress
                ),
            'destination' =>
                $this->addressData(
                    $shipment
                        ->destinationAddress
                ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function addressData(
        Address $address
    ): array {
        return [
            'id' => $address->id,
            'municipality_id' =>
                $address->municipality_id,
            'address_line' =>
                $address->address_line,
            'reference_note' =>
                $address->reference_note,
            'latitude' =>
                $address->latitude === null
                    ? null
                    : (float) $address
                        ->latitude,
            'longitude' =>
                $address->longitude === null
                    ? null
                    : (float) $address
                        ->longitude,
        ];
    }

    /**
     * @param array<string, mixed> $address
     */
    private function hasCoordinates(
        array $address
    ): bool {
        return $address['latitude'] !== null
            && $address['longitude'] !== null;
    }

    /**
     * @param array<string, mixed> $properties
     * @return array<string, mixed>
     */
    private function pointFeature(
        float $latitude,
        float $longitude,
        array $properties
    ): array {
        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                /*
                 * GeoJSON y Mapbox reciben primero
                 * longitud y después latitud.
                 */
                'coordinates' => [
                    $longitude,
                    $latitude,
                ],
            ],
            'properties' => $properties,
        ];
    }
}