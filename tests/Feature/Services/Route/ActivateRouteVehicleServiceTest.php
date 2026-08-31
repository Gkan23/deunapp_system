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
use App\Services\Route\ActivateRouteService;
use App\Services\Route\CreateRouteService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivateRouteVehicleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            CatalogSeeder::class
        );
    }

    public function test_activating_a_route_changes_the_vehicle_to_in_use(): void
    {
        $scenario = $this->createScenario();

        $activatedRoute = app(
            ActivateRouteService::class
        )->handle(
            $scenario['route']
        );

        $this->assertSame(
            'ACTIVE',
            $activatedRoute
                ->routeStatus
                ->status_name
        );

        $this->assertSame(
            'IN_USE',
            $activatedRoute
                ->vehicle
                ->vehicleStatus
                ->status_name
        );

        $this->assertFalse(
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
                        'IN_USE'
                    ),
            ]
        );
    }

    public function test_an_unavailable_vehicle_cannot_activate_the_route(): void
    {
        $scenario = $this->createScenario();

        $scenario['vehicle']->update([
            'vehicle_status_id' =>
                $this->vehicleStatusId(
                    'MAINTENANCE'
                ),
        ]);

        $this->assertDomainException(
            fn () => app(
                ActivateRouteService::class
            )->handle(
                $scenario['route']
            ),
            'Only an available vehicle can activate a route.'
        );

        $this->assertRouteRemainsPlanned(
            $scenario['route']
        );

        $this->assertSame(
            'MAINTENANCE',
            $scenario['vehicle']
                ->fresh()
                ->vehicleStatus
                ->status_name
        );
    }

    public function test_the_vehicle_must_still_belong_to_the_route_courier(): void
    {
        $scenario = $this->createScenario();

        $otherCourier = Courier::factory()
            ->for($scenario['provider'])
            ->create();

        $scenario['vehicle']->update([
            'courier_id' =>
                $otherCourier->id,
        ]);

        $this->assertDomainException(
            fn () => app(
                ActivateRouteService::class
            )->handle(
                $scenario['route']
            ),
            'The route vehicle does not belong to the route courier.'
        );

        $this->assertRouteRemainsPlanned(
            $scenario['route']
        );

        $this->assertSame(
            'AVAILABLE',
            $scenario['vehicle']
                ->fresh()
                ->vehicleStatus
                ->status_name
        );
    }

    public function test_a_vehicle_cannot_be_used_by_two_active_routes(): void
    {
        $scenario = $this->createScenario();

        $otherCourier = Courier::factory()
            ->for($scenario['provider'])
            ->create([
                'is_available' => false,
            ]);

        Route::query()->create([
            'courier_id' =>
                $otherCourier->id,
            'vehicle_id' =>
                $scenario['vehicle']->id,
            'route_status_id' =>
                $this->routeStatusId(
                    'ACTIVE'
                ),
            'route_date' =>
                today()->subDay(),
            'started_at' =>
                now()->subHour(),
            'finished_at' => null,
            'estimated_distance_km' =>
                8.50,
        ]);

        $this->assertDomainException(
            fn () => app(
                ActivateRouteService::class
            )->handle(
                $scenario['route']
            ),
            'The vehicle is already being used by another active route.'
        );

        $this->assertRouteRemainsPlanned(
            $scenario['route']
        );

        $this->assertSame(
            'AVAILABLE',
            $scenario['vehicle']
                ->fresh()
                ->vehicleStatus
                ->status_name
        );
    }

    public function test_vehicle_status_is_not_changed_when_route_activation_fails(): void
    {
        $scenario = $this->createScenario();

        $scenario['routeShipment']->update([
            'delivery_status' =>
                'IN_PROGRESS',
        ]);

        $this->assertDomainException(
            fn () => app(
                ActivateRouteService::class
            )->handle(
                $scenario['route']
            ),
            'Every route shipment must be pending before activation.'
        );

        $this->assertRouteRemainsPlanned(
            $scenario['route']
        );

        $this->assertSame(
            'AVAILABLE',
            $scenario['vehicle']
                ->fresh()
                ->vehicleStatus
                ->status_name
        );

        $this->assertTrue(
            $scenario['courier']
                ->fresh()
                ->is_available
        );
    }

    /**
     * @return array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     vehicle: Vehicle,
     *     shipment: Shipment,
     *     route: Route,
     *     routeShipment: RouteShipment
     * }
     */
    private function createScenario(): array
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create([
                'is_active' => true,
                'is_available' => true,
            ]);

        $vehicle = Vehicle::factory()
            ->for($courier)
            ->create([
                'vehicle_status_id' =>
                    $this->vehicleStatusId(
                        'AVAILABLE'
                    ),
            ]);

        $shipment = $this
            ->createAssignedShipment(
                $provider
            );

        $route = app(
            CreateRouteService::class
        )->handle(
            $courier,
            [$shipment],
            today(),
            10.50,
            $vehicle
        );

        $routeShipment =
            RouteShipment::query()
                ->where(
                    'route_id',
                    $route->id
                )
                ->firstOrFail();

        return [
            'provider' => $provider,
            'courier' => $courier,
            'vehicle' => $vehicle,
            'shipment' => $shipment,
            'route' => $route,
            'routeShipment' =>
                $routeShipment,
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

    private function assertRouteRemainsPlanned(
        Route $route
    ): void {
        $route->refresh();

        $this->assertSame(
            $this->routeStatusId(
                'PLANNED'
            ),
            $route->route_status_id
        );

        $this->assertNull(
            $route->started_at
        );

        $this->assertNull(
            $route->finished_at
        );
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