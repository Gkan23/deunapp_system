<?php

namespace Tests\Feature\Services\Route;

use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Shipment;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleStatus;
use App\Services\Route\CreateRouteService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateRouteVehicleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            CatalogSeeder::class
        );
    }

    public function test_it_assigns_an_available_vehicle_to_a_route(): void
    {
        [
            $provider,
            $courier,
            $vehicle,
        ] = $this->createProviderScenario();

        $shipment = $this->createAssignedShipment(
            $provider
        );

        $route = app(
            CreateRouteService::class
        )->handle(
            $courier,
            [$shipment],
            today(),
            12.50,
            $vehicle
        );

        $this->assertSame(
            $vehicle->id,
            $route->vehicle_id
        );

        $this->assertSame(
            $vehicle->id,
            $route->vehicle->id
        );

        $this->assertSame(
            'AVAILABLE',
            $route
                ->vehicle
                ->vehicleStatus
                ->status_name
        );

        $this->assertDatabaseHas(
            'routes',
            [
                'id' => $route->id,
                'courier_id' => $courier->id,
                'vehicle_id' => $vehicle->id,
            ]
        );
    }

    public function test_a_route_can_temporarily_be_created_without_a_vehicle(): void
    {
        [
            $provider,
            $courier,
        ] = $this->createProviderScenario();

        $shipment = $this->createAssignedShipment(
            $provider
        );

        $route = app(
            CreateRouteService::class
        )->handle(
            $courier,
            [$shipment],
            today()
        );

        $this->assertNull(
            $route->vehicle_id
        );

        $this->assertDatabaseHas(
            'routes',
            [
                'id' => $route->id,
                'courier_id' => $courier->id,
                'vehicle_id' => null,
            ]
        );
    }

    public function test_it_rejects_a_vehicle_from_another_courier(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $selectedCourier = Courier::factory()
            ->for($provider)
            ->create();

        $otherCourier = Courier::factory()
            ->for($provider)
            ->create();

        $vehicle = Vehicle::factory()
            ->for($otherCourier)
            ->create();

        $shipment = $this->createAssignedShipment(
            $provider
        );

        $this->assertDomainException(
            fn () => app(
                CreateRouteService::class
            )->handle(
                $selectedCourier,
                [$shipment],
                today(),
                null,
                $vehicle
            ),
            'The selected vehicle does not belong to the courier.'
        );

        $this->assertDatabaseCount(
            'routes',
            0
        );
    }

    public function test_it_rejects_a_vehicle_that_is_not_available(): void
    {
        [
            $provider,
            $courier,
            $vehicle,
        ] = $this->createProviderScenario();

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

        $shipment = $this->createAssignedShipment(
            $provider
        );

        $this->assertDomainException(
            fn () => app(
                CreateRouteService::class
            )->handle(
                $courier,
                [$shipment],
                today(),
                null,
                $vehicle
            ),
            'Only an available vehicle can be assigned to a route.'
        );

        $this->assertDatabaseCount(
            'routes',
            0
        );
    }

    public function test_it_rejects_the_same_vehicle_for_two_open_routes_on_the_same_date(): void
    {
        [
            $provider,
            $courier,
            $vehicle,
        ] = $this->createProviderScenario();

        $firstShipment =
            $this->createAssignedShipment(
                $provider
            );

        $secondShipment =
            $this->createAssignedShipment(
                $provider
            );

        $service = app(
            CreateRouteService::class
        );

        $service->handle(
            $courier,
            [$firstShipment],
            today(),
            null,
            $vehicle
        );

        $this->assertDomainException(
            fn () => $service->handle(
                $courier,
                [$secondShipment],
                today(),
                null,
                $vehicle
            ),
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

    public function test_the_same_vehicle_can_be_planned_for_different_dates(): void
    {
        [
            $provider,
            $courier,
            $vehicle,
        ] = $this->createProviderScenario();

        $firstShipment =
            $this->createAssignedShipment(
                $provider
            );

        $secondShipment =
            $this->createAssignedShipment(
                $provider
            );

        $service = app(
            CreateRouteService::class
        );

        $firstRoute = $service->handle(
            $courier,
            [$firstShipment],
            today(),
            null,
            $vehicle
        );

        $secondRoute = $service->handle(
            $courier,
            [$secondShipment],
            today()->addDay(),
            null,
            $vehicle
        );

        $this->assertNotSame(
            $firstRoute->id,
            $secondRoute->id
        );

        $this->assertSame(
            $vehicle->id,
            $firstRoute->vehicle_id
        );

        $this->assertSame(
            $vehicle->id,
            $secondRoute->vehicle_id
        );

        $this->assertDatabaseCount(
            'routes',
            2
        );

        $this->assertDatabaseCount(
            'route_shipments',
            2
        );
    }

    /**
     * @return array{
     *     0: DeliveryProvider,
     *     1: Courier,
     *     2: Vehicle
     * }
     */
    private function createProviderScenario(): array
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

    private function assertDomainException(
        Closure $callback,
        string $expectedMessage
    ): void {
        try {
            $callback();

            $this->fail(
                'A DomainException was expected.'
            );
        } catch (DomainException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );
        }
    }
}