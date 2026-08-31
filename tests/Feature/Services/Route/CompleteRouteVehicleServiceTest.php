<?php

namespace Tests\Feature\Services\Route;

use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Models\VehicleStatus;
use App\Services\Route\CompleteRouteService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteRouteVehicleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            CatalogSeeder::class
        );
    }

    public function test_completing_a_route_releases_its_vehicle(): void
    {
        $scenario = $this->createScenario(
            deliveryStatus: 'DELIVERED',
            vehicleStatus: 'IN_USE'
        );

        $completedRoute = app(
            CompleteRouteService::class
        )->execute(
            $scenario['route']
        );

        $this->assertSame(
            'COMPLETED',
            $completedRoute
                ->routeStatus
                ->status_name
        );

        $this->assertSame(
            'AVAILABLE',
            $completedRoute
                ->vehicle
                ->vehicleStatus
                ->status_name
        );

        $this->assertTrue(
            $scenario['courier']
                ->fresh()
                ->is_available
        );

        $this->assertDatabaseHas(
            'vehicles',
            [
                'id' =>
                    $scenario['vehicle']->id,
                'vehicle_status_id' =>
                    $this->vehicleStatusId(
                        'AVAILABLE'
                    ),
            ]
        );
    }

    public function test_a_vehicle_in_maintenance_is_not_made_available_after_completion(): void
    {
        $scenario = $this->createScenario(
            deliveryStatus: 'FAILED',
            vehicleStatus: 'MAINTENANCE'
        );

        $completedRoute = app(
            CompleteRouteService::class
        )->execute(
            $scenario['route']
        );

        $this->assertSame(
            'COMPLETED',
            $completedRoute
                ->routeStatus
                ->status_name
        );

        $this->assertSame(
            'MAINTENANCE',
            $completedRoute
                ->vehicle
                ->vehicleStatus
                ->status_name
        );

        $this->assertDatabaseHas(
            'vehicles',
            [
                'id' =>
                    $scenario['vehicle']->id,
                'vehicle_status_id' =>
                    $this->vehicleStatusId(
                        'MAINTENANCE'
                    ),
            ]
        );
    }

    public function test_vehicle_status_is_not_changed_when_route_completion_fails(): void
    {
        $scenario = $this->createScenario(
            deliveryStatus: 'IN_PROGRESS',
            vehicleStatus: 'IN_USE'
        );

        $this->assertDomainException(
            fn () => app(
                CompleteRouteService::class
            )->execute(
                $scenario['route']
            ),
            'Every route shipment must be delivered or failed before completing the route.'
        );

        $scenario['route']->refresh();

        $this->assertSame(
            $this->routeStatusId(
                'ACTIVE'
            ),
            $scenario['route']
                ->route_status_id
        );

        $this->assertNull(
            $scenario['route']
                ->finished_at
        );

        $this->assertSame(
            'IN_USE',
            $scenario['vehicle']
                ->fresh()
                ->vehicleStatus
                ->status_name
        );

        $this->assertFalse(
            $scenario['courier']
                ->fresh()
                ->is_available
        );
    }

    public function test_completion_rejects_a_vehicle_that_no_longer_belongs_to_the_courier(): void
    {
        $scenario = $this->createScenario(
            deliveryStatus: 'DELIVERED',
            vehicleStatus: 'IN_USE'
        );

        $otherCourier = Courier::factory()
            ->for($scenario['provider'])
            ->create();

        $scenario['vehicle']->update([
            'courier_id' =>
                $otherCourier->id,
        ]);

        $this->assertDomainException(
            fn () => app(
                CompleteRouteService::class
            )->execute(
                $scenario['route']
            ),
            'The route vehicle does not belong to the route courier.'
        );

        $scenario['route']->refresh();

        $this->assertSame(
            $this->routeStatusId(
                'ACTIVE'
            ),
            $scenario['route']
                ->route_status_id
        );

        $this->assertNull(
            $scenario['route']
                ->finished_at
        );

        $this->assertSame(
            'IN_USE',
            $scenario['vehicle']
                ->fresh()
                ->vehicleStatus
                ->status_name
        );
    }

    /**
     * @return array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     vehicle: Vehicle,
     *     route: Route,
     *     routeShipment: RouteShipment
     * }
     */
    private function createScenario(
        string $deliveryStatus,
        string $vehicleStatus
    ): array {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create([
                'is_active' => true,
                'is_available' => false,
            ]);

        $vehicle = Vehicle::factory()
            ->for($courier)
            ->create([
                'vehicle_status_id' =>
                    $this->vehicleStatusId(
                        $vehicleStatus
                    ),
            ]);

        $route = Route::query()->create([
            'courier_id' =>
                $courier->id,
            'vehicle_id' =>
                $vehicle->id,
            'route_status_id' =>
                $this->routeStatusId(
                    'ACTIVE'
                ),
            'route_date' =>
                today()->toDateString(),
            'started_at' =>
                now()->subHour(),
            'finished_at' => null,
            'estimated_distance_km' =>
                10.50,
        ]);

        $shipment = Shipment::factory()
            ->create();

        $routeShipment =
            RouteShipment::query()->create([
                'route_id' => $route->id,
                'shipment_id' =>
                    $shipment->id,
                'delivery_order' => 1,
                'delivery_status' =>
                    $deliveryStatus,
            ]);

        return [
            'provider' => $provider,
            'courier' => $courier,
            'vehicle' => $vehicle,
            'route' => $route,
            'routeShipment' =>
                $routeShipment,
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

    private function vehicleStatusId(
        string $statusName
    ): int {
        return VehicleStatus::query()
            ->where(
                'status_name',
                $statusName
            )
            ->firstOrFail()
            ->id;
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