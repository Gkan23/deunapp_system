<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Role;
use App\Models\Route;
use App\Models\RouteStatus;
use App\Models\Trip;
use App\Models\User;
use App\Services\Route\CreateRouteService;
use Carbon\CarbonInterface;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivateRouteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_activate_a_route(): void
    {
        $scenario = $this->createScenario();

        $this->patchJson(
            route(
                'routes.activate',
                $scenario['route']
            )
        )->assertUnauthorized();

        $this->assertRouteRemainsPlanned(
            $scenario
        );
    }

    public function test_the_linked_provider_can_activate_the_route(): void
    {
        $scenario = $this->createScenario(
            shipmentCount: 2
        );

        $response = $this
            ->actingAs($scenario['provider']->user)
            ->patchJson(
                route(
                    'routes.activate',
                    $scenario['route']
                )
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Route activated successfully.'
            )
            ->assertJsonPath(
                'route.route_status.status_name',
                'ACTIVE'
            );

        $this->assertRouteWasActivated(
            $scenario
        );
    }

    public function test_the_assigned_courier_can_activate_the_route(): void
    {
        $scenario = $this->createScenario();

        $this
            ->actingAs($scenario['courier']->user)
            ->patchJson(
                route(
                    'routes.activate',
                    $scenario['route']
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'route.route_status.status_name',
                'ACTIVE'
            );

        $this->assertRouteWasActivated(
            $scenario
        );
    }

    public function test_an_unrelated_provider_cannot_activate_the_route(): void
    {
        $scenario = $this->createScenario();

        $unrelatedProvider =
            DeliveryProvider::factory()->create();

        $this
            ->actingAs($unrelatedProvider->user)
            ->patchJson(
                route(
                    'routes.activate',
                    $scenario['route']
                )
            )
            ->assertForbidden();

        $this->assertRouteRemainsPlanned(
            $scenario
        );
    }

    public function test_an_unassigned_courier_cannot_activate_the_route(): void
    {
        $scenario = $this->createScenario();

        $unassignedCourier =
            Courier::factory()->create();

        $this
            ->actingAs($unassignedCourier->user)
            ->patchJson(
                route(
                    'routes.activate',
                    $scenario['route']
                )
            )
            ->assertForbidden();

        $this->assertRouteRemainsPlanned(
            $scenario
        );
    }

    public function test_an_administrator_can_activate_a_route(): void
    {
        $scenario = $this->createScenario();

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this
            ->actingAs($administrator)
            ->patchJson(
                route(
                    'routes.activate',
                    $scenario['route']
                )
            )
            ->assertOk();

        $this->assertRouteWasActivated(
            $scenario
        );
    }

    public function test_a_future_route_cannot_be_activated(): void
    {
        $scenario = $this->createScenario(
            routeDate: today()->addDay()
        );

        $this
            ->actingAs($scenario['provider']->user)
            ->patchJson(
                route(
                    'routes.activate',
                    $scenario['route']
                )
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'A route can only be activated on its scheduled date.'
            );

        $this->assertRouteRemainsPlanned(
            $scenario
        );
    }

    public function test_a_route_that_is_not_planned_cannot_be_activated(): void
    {
        $scenario = $this->createScenario();

        $activeStatus = RouteStatus::query()
            ->where('status_name', 'ACTIVE')
            ->firstOrFail();

        $scenario['route']->update([
            'route_status_id' => $activeStatus->id,
            'started_at' => now(),
        ]);

        $this
            ->actingAs($scenario['provider']->user)
            ->patchJson(
                route(
                    'routes.activate',
                    $scenario['route']
                )
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Only a planned route can be activated.'
            );

        foreach (
            $scenario['services'] as $service
        ) {
            $this->assertSame(
                'ASSIGNED',
                $service->fresh()->status
            );
        }
    }

    public function test_an_empty_route_cannot_be_activated(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $plannedStatus = RouteStatus::query()
            ->where('status_name', 'PLANNED')
            ->firstOrFail();

        $route = Route::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' =>
                $plannedStatus->id,
            'route_date' => today(),
            'started_at' => null,
            'finished_at' => null,
            'estimated_distance_km' => null,
        ]);

        $this
            ->actingAs($provider->user)
            ->patchJson(
                route(
                    'routes.activate',
                    $route
                )
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'An empty route cannot be activated.'
            );

        $route->refresh();

        $this->assertSame(
            'PLANNED',
            $route->routeStatus->status_name
        );

        $this->assertNull($route->started_at);
    }

    /**
     * @return array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     route: Route,
     *     services: array<int, DeliveryService>
     * }
     */
    private function createScenario(
        int $shipmentCount = 1,
        ?CarbonInterface $routeDate = null
    ): array {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $services = [];
        $shipments = [];

        for (
            $index = 0;
            $index < $shipmentCount;
            $index++
        ) {
            $service = $this
                ->createAssignedService($provider);

            $services[] = $service;
            $shipments[] = $service->shipment;
        }

        $route = app(
            CreateRouteService::class
        )->handle(
            $courier,
            $shipments,
            $routeDate ?? today(),
            10.50
        );

        return [
            'provider' => $provider,
            'courier' => $courier,
            'route' => $route,
            'services' => $services,
        ];
    }

    private function createAssignedService(
        DeliveryProvider $provider
    ): DeliveryService {
        $trip = Trip::factory()
            ->for($provider)
            ->create([
                'status' => 'USED',
                'used_at' => now(),
            ]);

        return DeliveryService::factory()
            ->create([
                'trip_id' => $trip->id,
                'status' => 'ASSIGNED',
                'accepted_at' => now(),
                'started_at' => null,
            ]);
    }

    /**
     * @param array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     route: Route,
     *     services: array<int, DeliveryService>
     * } $scenario
     */
    private function assertRouteWasActivated(
        array $scenario
    ): void {
        $scenario['route']->refresh();
        $scenario['courier']->refresh();

        $this->assertSame(
            'ACTIVE',
            $scenario['route']
                ->routeStatus
                ->status_name
        );

        $this->assertNotNull(
            $scenario['route']->started_at
        );

        $this->assertFalse(
            $scenario['courier']->is_available
        );

        foreach (
            $scenario['route']
                ->routeShipments()
                ->get() as $routeShipment
        ) {
            $this->assertSame(
                'IN_PROGRESS',
                $routeShipment->delivery_status
            );
        }

        foreach (
            $scenario['services'] as $service
        ) {
            $service->refresh();

            $this->assertSame(
                'IN_PROGRESS',
                $service->status
            );

            $this->assertNotNull(
                $service->started_at
            );
        }
    }

    /**
     * @param array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     route: Route,
     *     services: array<int, DeliveryService>
     * } $scenario
     */
    private function assertRouteRemainsPlanned(
        array $scenario
    ): void {
        $scenario['route']->refresh();
        $scenario['courier']->refresh();

        $this->assertSame(
            'PLANNED',
            $scenario['route']
                ->routeStatus
                ->status_name
        );

        $this->assertNull(
            $scenario['route']->started_at
        );

        $this->assertTrue(
            $scenario['courier']->is_available
        );

        foreach (
            $scenario['services'] as $service
        ) {
            $service->refresh();

            $this->assertSame(
                'ASSIGNED',
                $service->status
            );

            $this->assertNull(
                $service->started_at
            );
        }
    }

    private function userWithRole(
        string $roleName
    ): User {
        $role = Role::query()
            ->where('role_name', $roleName)
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
