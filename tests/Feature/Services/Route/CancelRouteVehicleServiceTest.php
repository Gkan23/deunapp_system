<?php

namespace Tests\Feature\Services\Route;

use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleStatus;
use App\Services\Route\CancelRouteService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelRouteVehicleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            CatalogSeeder::class
        );
    }

    public function test_cancelling_an_active_route_releases_the_vehicle(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'ACTIVE',
            vehicleStatus: 'IN_USE'
        );

        $cancelledRoute = app(
            CancelRouteService::class
        )->execute(
            $scenario['route'],
            $scenario['courier']->user,
            'The active route was cancelled.'
        );

        $this->assertSame(
            'CANCELLED',
            $cancelledRoute
                ->routeStatus
                ->status_name
        );

        $this->assertSame(
            'AVAILABLE',
            $cancelledRoute
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

        $this->assertDatabaseCount(
            'incidents',
            1
        );
    }

    public function test_cancelling_a_planned_route_does_not_change_the_vehicle_status(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'PLANNED',
            vehicleStatus: 'AVAILABLE'
        );

        $cancelledRoute = app(
            CancelRouteService::class
        )->execute(
            $scenario['route'],
            $scenario['courier']->user,
            'The planned route is no longer required.'
        );

        $this->assertSame(
            'CANCELLED',
            $cancelledRoute
                ->routeStatus
                ->status_name
        );

        $this->assertSame(
            'AVAILABLE',
            $cancelledRoute
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
                        'AVAILABLE'
                    ),
            ]
        );
    }

    public function test_cancelling_an_active_route_preserves_vehicle_maintenance(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'ACTIVE',
            vehicleStatus: 'MAINTENANCE'
        );

        $cancelledRoute = app(
            CancelRouteService::class
        )->execute(
            $scenario['route'],
            $scenario['courier']->user,
            'The route was cancelled because of vehicle maintenance.'
        );

        $this->assertSame(
            'CANCELLED',
            $cancelledRoute
                ->routeStatus
                ->status_name
        );

        $this->assertSame(
            'MAINTENANCE',
            $cancelledRoute
                ->vehicle
                ->vehicleStatus
                ->status_name
        );
    }

    public function test_vehicle_status_is_not_changed_when_cancellation_fails(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'ACTIVE',
            vehicleStatus: 'IN_USE'
        );

        $this->assertDomainException(
            fn () => app(
                CancelRouteService::class
            )->execute(
                $scenario['route'],
                $scenario['courier']->user,
                '   '
            ),
            'The route cancellation reason is required.'
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

        $this->assertDatabaseCount(
            'incidents',
            0
        );
    }

    public function test_cancellation_rejects_a_vehicle_that_no_longer_belongs_to_the_courier(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'ACTIVE',
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
                CancelRouteService::class
            )->execute(
                $scenario['route'],
                $scenario['courier']->user,
                'Invalid vehicle relationship.'
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

        $this->assertDatabaseCount(
            'incidents',
            0
        );
    }

    /**
     * @return array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     vehicle: Vehicle,
     *     route: Route,
     *     routeShipment: RouteShipment,
     *     deliveryService: DeliveryService
     * }
     */
    private function createScenario(
        string $routeStatus,
        string $vehicleStatus
    ): array {
        $provider = DeliveryProvider::factory()
            ->create();

        $isActiveRoute =
            $routeStatus === 'ACTIVE';

        $courier = Courier::factory()
            ->for($provider)
            ->create([
                'is_active' => true,
                'is_available' =>
                    ! $isActiveRoute,
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
                    $routeStatus
                ),
            'route_date' =>
                today()->toDateString(),
            'started_at' =>
                $isActiveRoute
                    ? now()->subHour()
                    : null,
            'finished_at' => null,
            'estimated_distance_km' =>
                10.50,
        ]);

        $shipment = Shipment::factory()
            ->create();

        $trip = Trip::factory()
            ->for($provider)
            ->create([
                'status' => 'USED',
                'used_at' =>
                    now()->subHours(2),
            ]);

        $deliveryServiceStatus =
            $isActiveRoute
                ? 'IN_PROGRESS'
                : 'ASSIGNED';

        $deliveryService =
            DeliveryService::factory()
                ->create([
                    'shipment_id' =>
                        $shipment->id,
                    'trip_id' => $trip->id,
                    'status' =>
                        $deliveryServiceStatus,
                    'accepted_at' =>
                        now()->subHours(2),
                    'started_at' =>
                        $isActiveRoute
                            ? now()->subHour()
                            : null,
                    'completed_at' => null,
                    'cancelled_at' => null,
                ]);

        $routeShipment =
            RouteShipment::query()->create([
                'route_id' => $route->id,
                'shipment_id' =>
                    $shipment->id,
                'delivery_order' => 1,
                'delivery_status' =>
                    $isActiveRoute
                        ? 'IN_PROGRESS'
                        : 'PENDING',
            ]);

        return [
            'provider' => $provider,
            'courier' => $courier,
            'vehicle' => $vehicle,
            'route' => $route,
            'routeShipment' =>
                $routeShipment,
            'deliveryService' =>
                $deliveryService,
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