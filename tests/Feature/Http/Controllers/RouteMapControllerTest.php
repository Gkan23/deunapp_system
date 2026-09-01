<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\CourierLocation;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteMapControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            CatalogSeeder::class
        );
    }

    public function test_a_guest_cannot_view_a_route_map(): void
    {
        $scenario = $this->createScenario();

        $this->getJson(
            route(
                'routes.map',
                $scenario['route']
            )
        )->assertUnauthorized();
    }

    public function test_a_provider_can_only_view_their_route_map(): void
    {
        $ownScenario =
            $this->createScenario();

        $otherScenario =
            $this->createScenario();

        $this->actingAs(
            $ownScenario['provider']->user
        )
            ->getJson(
                route(
                    'routes.map',
                    $ownScenario['route']
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.route.id',
                $ownScenario['route']->id
            );

        $this->getJson(
            route(
                'routes.map',
                $otherScenario['route']
            )
        )->assertForbidden();
    }

    public function test_an_assigned_courier_can_only_view_their_route_map(): void
    {
        $ownScenario =
            $this->createScenario();

        $otherScenario =
            $this->createScenario();

        $this->actingAs(
            $ownScenario['courier']->user
        )
            ->getJson(
                route(
                    'routes.map',
                    $ownScenario['route']
                )
            )
            ->assertOk();

        $this->getJson(
            route(
                'routes.map',
                $otherScenario['route']
            )
        )->assertForbidden();
    }

    public function test_support_and_administration_can_view_route_maps(): void
    {
        $scenario = $this->createScenario();

        $users = [
            $this->userWithRole(
                'SUPPORT_AGENT'
            ),
            $this->userWithRole(
                'ADMINISTRATOR'
            ),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)
                ->getJson(
                    route(
                        'routes.map',
                        $scenario['route']
                    )
                )
                ->assertOk()
                ->assertJsonPath(
                    'data.route.id',
                    $scenario['route']->id
                );
        }
    }

    public function test_a_customer_cannot_view_a_route_map(): void
    {
        $scenario = $this->createScenario();

        $customer = Customer::factory()
            ->create();

        $this->actingAs($customer->user)
            ->getJson(
                route(
                    'routes.map',
                    $scenario['route']
                )
            )
            ->assertForbidden();
    }

    public function test_route_map_returns_ordered_stops_and_geojson(): void
    {
        $scenario = $this->createScenario();

        $response = $this->actingAs(
            $scenario['provider']->user
        )->getJson(
            route(
                'routes.map',
                $scenario['route']
            )
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.route.id',
                $scenario['route']->id
            )
            ->assertJsonPath(
                'data.vehicle.id',
                $scenario['vehicle']->id
            )
            ->assertJsonPath(
                'data.summary.stop_count',
                2
            )
            ->assertJsonPath(
                'data.summary.geocoded_destination_count',
                2
            )
            ->assertJsonPath(
                'data.summary.has_courier_location',
                true
            )
            ->assertJsonPath(
                'data.stops.0.delivery_order',
                1
            )
            ->assertJsonPath(
                'data.stops.0.shipment.id',
                $scenario['secondShipment']->id
            )
            ->assertJsonPath(
                'data.stops.1.delivery_order',
                2
            )
            ->assertJsonPath(
                'data.stops.1.shipment.id',
                $scenario['firstShipment']->id
            )
            ->assertJsonPath(
                'data.courier.latest_location.latitude',
                12.1500000
            )
            ->assertJsonPath(
                'data.courier.latest_location.longitude',
                -86.2500000
            )
            ->assertJsonPath(
                'data.geojson.type',
                'FeatureCollection'
            )
            ->assertJsonCount(
                5,
                'data.geojson.features'
            );

        /*
         * La primera característica corresponde
         * a la ubicación más reciente del repartidor.
         */
        $response
            ->assertJsonPath(
                'data.geojson.features.0.properties.marker_type',
                'COURIER'
            )
            ->assertJsonPath(
                'data.geojson.features.0.geometry.coordinates.0',
                -86.2500000
            )
            ->assertJsonPath(
                'data.geojson.features.0.geometry.coordinates.1',
                12.1500000
            );
    }

    public function test_route_map_handles_missing_coordinates(): void
    {
        $scenario = $this->createScenario();

        CourierLocation::query()
            ->where(
                'courier_id',
                $scenario['courier']->id
            )
            ->delete();

        foreach (
            [
                $scenario['firstShipment'],
                $scenario['secondShipment'],
            ] as $shipment
        ) {
            $shipment->originAddress->update([
                'latitude' => null,
                'longitude' => null,
            ]);

            $shipment
                ->destinationAddress
                ->update([
                    'latitude' => null,
                    'longitude' => null,
                ]);
        }

        $this->actingAs(
            $scenario['provider']->user
        )
            ->getJson(
                route(
                    'routes.map',
                    $scenario['route']
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.courier.latest_location',
                null
            )
            ->assertJsonPath(
                'data.summary.has_courier_location',
                false
            )
            ->assertJsonPath(
                'data.summary.geocoded_destination_count',
                0
            )
            ->assertJsonCount(
                0,
                'data.geojson.features'
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function createScenario(): array
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $vehicle = Vehicle::factory()
            ->for($courier)
            ->create();

        $route = Route::query()->create([
            'courier_id' => $courier->id,
            'vehicle_id' => $vehicle->id,
            'route_status_id' =>
                $this->routeStatusId(
                    'PLANNED'
                ),
            'route_date' => today(),
            'started_at' => null,
            'finished_at' => null,
            'estimated_distance_km' =>
                18.75,
        ]);

        $firstShipment =
            Shipment::factory()->create();

        $secondShipment =
            Shipment::factory()->create();

        $firstShipment
            ->originAddress
            ->update([
                'address_line' =>
                    'First origin',
                'latitude' => 12.1000000,
                'longitude' => -86.1000000,
            ]);

        $firstShipment
            ->destinationAddress
            ->update([
                'address_line' =>
                    'First destination',
                'latitude' => 12.2000000,
                'longitude' => -86.2000000,
            ]);

        $secondShipment
            ->originAddress
            ->update([
                'address_line' =>
                    'Second origin',
                'latitude' => 12.3000000,
                'longitude' => -86.3000000,
            ]);

        $secondShipment
            ->destinationAddress
            ->update([
                'address_line' =>
                    'Second destination',
                'latitude' => 12.4000000,
                'longitude' => -86.4000000,
            ]);

        RouteShipment::query()->create([
            'route_id' => $route->id,
            'shipment_id' =>
                $firstShipment->id,
            'delivery_order' => 2,
            'delivery_status' =>
                'PENDING',
        ]);

        RouteShipment::query()->create([
            'route_id' => $route->id,
            'shipment_id' =>
                $secondShipment->id,
            'delivery_order' => 1,
            'delivery_status' =>
                'PENDING',
        ]);

        CourierLocation::query()->create([
            'courier_id' => $courier->id,
            'latitude' => 12.0500000,
            'longitude' => -86.0500000,
            'gps_accuracy' => 12.50,
            'recorded_at' =>
                now()->subMinutes(10),
        ]);

        CourierLocation::query()->create([
            'courier_id' => $courier->id,
            'latitude' => 12.1500000,
            'longitude' => -86.2500000,
            'gps_accuracy' => 5.25,
            'recorded_at' => now(),
        ]);

        return [
            'provider' => $provider,
            'courier' => $courier,
            'vehicle' => $vehicle,
            'route' => $route,
            'firstShipment' =>
                $firstShipment,
            'secondShipment' =>
                $secondShipment,
        ];
    }

    private function routeStatusId(
        string $statusName
    ): int {
        return RouteStatus::query()
            ->where(
                'status_name',
                $statusName
            )
            ->firstOrFail()
            ->id;
    }

    private function userWithRole(
        string $roleName
    ): User {
        $role = Role::query()
            ->where(
                'role_name',
                $roleName
            )
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}