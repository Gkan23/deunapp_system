<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Shipment;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleStatus;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateRouteVehicleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            CatalogSeeder::class
        );
    }

    public function test_a_provider_can_create_a_route_with_their_couriers_vehicle(): void
    {
        [
            $provider,
            $courier,
            $vehicle,
        ] = $this->createScenario();

        $shipment = $this
            ->createAssignedShipment(
                $provider
            );

        $this->actingAs($provider->user)
            ->postJson(
                route('routes.store'),
                $this->validPayload(
                    $courier,
                    $vehicle,
                    [$shipment]
                )
            )
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Route created successfully.'
            )
            ->assertJsonPath(
                'route.courier_id',
                $courier->id
            )
            ->assertJsonPath(
                'route.vehicle_id',
                $vehicle->id
            )
            ->assertJsonPath(
                'route.vehicle.vehicle_status.status_name',
                'AVAILABLE'
            );

        $this->assertDatabaseHas(
            'routes',
            [
                'courier_id' => $courier->id,
                'vehicle_id' => $vehicle->id,
            ]
        );
    }

    public function test_an_administrator_can_create_a_route_with_a_vehicle(): void
    {
        [
            $provider,
            $courier,
            $vehicle,
        ] = $this->createScenario();

        $shipment = $this
            ->createAssignedShipment(
                $provider
            );

        $administrator = User::factory()
            ->administrator()
            ->create();

        $this->actingAs($administrator)
            ->postJson(
                route('routes.store'),
                $this->validPayload(
                    $courier,
                    $vehicle,
                    [$shipment]
                )
            )
            ->assertCreated()
            ->assertJsonPath(
                'route.vehicle_id',
                $vehicle->id
            );

        $this->assertDatabaseCount(
            'routes',
            1
        );
    }

    public function test_the_selected_vehicle_must_exist(): void
    {
        [
            $provider,
            $courier,
            $vehicle,
        ] = $this->createScenario();

        $shipment = $this
            ->createAssignedShipment(
                $provider
            );

        $payload = $this->validPayload(
            $courier,
            $vehicle,
            [$shipment]
        );

        $payload['vehicle_id'] = 999999;

        $this->actingAs($provider->user)
            ->postJson(
                route('routes.store'),
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'vehicle_id',
            ]);

        $this->assertDatabaseCount(
            'routes',
            0
        );
    }

    public function test_the_vehicle_must_belong_to_the_selected_courier(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $selectedCourier = Courier::factory()
            ->for($provider)
            ->create();

        $otherCourier = Courier::factory()
            ->for($provider)
            ->create();

        $otherVehicle = Vehicle::factory()
            ->for($otherCourier)
            ->create();

        $shipment = $this
            ->createAssignedShipment(
                $provider
            );

        $this->actingAs($provider->user)
            ->postJson(
                route('routes.store'),
                $this->validPayload(
                    $selectedCourier,
                    $otherVehicle,
                    [$shipment]
                )
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The selected vehicle does not belong to the courier.'
            );

        $this->assertDatabaseCount(
            'routes',
            0
        );
    }

    public function test_an_unavailable_vehicle_cannot_be_assigned(): void
    {
        [
            $provider,
            $courier,
            $vehicle,
        ] = $this->createScenario();

        $maintenanceStatus =
            VehicleStatus::query()
                ->where(
                    'status_name',
                    'MAINTENANCE'
                )
                ->firstOrFail();

        $vehicle->update([
            'vehicle_status_id' =>
                $maintenanceStatus->id,
        ]);

        $shipment = $this
            ->createAssignedShipment(
                $provider
            );

        $this->actingAs($provider->user)
            ->postJson(
                route('routes.store'),
                $this->validPayload(
                    $courier,
                    $vehicle,
                    [$shipment]
                )
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Only an available vehicle can be assigned to a route.'
            );

        $this->assertDatabaseCount(
            'routes',
            0
        );
    }

    public function test_the_same_vehicle_cannot_have_two_open_routes_on_the_same_date(): void
    {
        [
            $provider,
            $courier,
            $vehicle,
        ] = $this->createScenario();

        $firstShipment =
            $this->createAssignedShipment(
                $provider
            );

        $secondShipment =
            $this->createAssignedShipment(
                $provider
            );

        $this->actingAs($provider->user)
            ->postJson(
                route('routes.store'),
                $this->validPayload(
                    $courier,
                    $vehicle,
                    [$firstShipment]
                )
            )
            ->assertCreated();

        $this->actingAs($provider->user)
            ->postJson(
                route('routes.store'),
                $this->validPayload(
                    $courier,
                    $vehicle,
                    [$secondShipment]
                )
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The vehicle already belongs to another planned or active route on this date.'
            );

        $this->assertDatabaseCount(
            'routes',
            1
        );

        $this->assertDatabaseCount(
            'route_shipments',
            1
        );
    }

    /**
     * @return array{
     *     0: DeliveryProvider,
     *     1: Courier,
     *     2: Vehicle
     * }
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

        return [
            $provider,
            $courier,
            $vehicle,
        ];
    }

    private function createAssignedShipment(
        DeliveryProvider $provider
    ): Shipment {
        $trip = Trip::factory()
            ->for($provider)
            ->create([
                'status' => 'USED',
                'used_at' => now(),
            ]);

        $deliveryService =
            DeliveryService::factory()
                ->create([
                    'trip_id' => $trip->id,
                    'status' => 'ASSIGNED',
                    'accepted_at' => now(),
                ]);

        return $deliveryService->shipment;
    }

    /**
     * @param array<int, Shipment> $shipments
     * @return array<string, mixed>
     */
    private function validPayload(
        Courier $courier,
        Vehicle $vehicle,
        array $shipments
    ): array {
        return [
            'courier_id' => $courier->id,
            'vehicle_id' => $vehicle->id,
            'shipment_ids' =>
                collect($shipments)
                    ->map(
                        fn (
                            Shipment $shipment
                        ): int =>
                            $shipment->id
                    )
                    ->values()
                    ->all(),
            'route_date' =>
                today()->toDateString(),
            'estimated_distance_km' =>
                10.50,
        ];
    }
}