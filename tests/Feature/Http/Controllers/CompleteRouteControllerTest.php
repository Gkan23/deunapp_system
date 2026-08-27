<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteRouteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_complete_a_route(): void
    {
        $scenario = $this->createScenario([
            'DELIVERED',
        ]);

        $this->patchJson(
            route(
                'routes.complete',
                $scenario['route']
            )
        )->assertUnauthorized();

        $this->assertRouteWasNotCompleted(
            $scenario
        );
    }

    public function test_the_linked_provider_can_complete_a_route(): void
    {
        $scenario = $this->createScenario([
            'DELIVERED',
            'FAILED',
        ]);

        $response = $this
            ->actingAs($scenario['provider']->user)
            ->patchJson(
                route(
                    'routes.complete',
                    $scenario['route']
                )
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Route completed successfully.'
            )
            ->assertJsonPath(
                'route.route_status.status_name',
                'COMPLETED'
            );

        $this->assertRouteWasCompleted(
            $scenario
        );
    }

    public function test_the_assigned_courier_can_complete_a_route(): void
    {
        $scenario = $this->createScenario([
            'DELIVERED',
        ]);

        $this
            ->actingAs($scenario['courier']->user)
            ->patchJson(
                route(
                    'routes.complete',
                    $scenario['route']
                )
            )
            ->assertOk();

        $this->assertRouteWasCompleted(
            $scenario
        );
    }

    public function test_an_unrelated_provider_cannot_complete_a_route(): void
    {
        $scenario = $this->createScenario([
            'DELIVERED',
        ]);

        $unrelatedProvider =
            DeliveryProvider::factory()->create();

        $this
            ->actingAs($unrelatedProvider->user)
            ->patchJson(
                route(
                    'routes.complete',
                    $scenario['route']
                )
            )
            ->assertForbidden();

        $this->assertRouteWasNotCompleted(
            $scenario
        );
    }

    public function test_an_unassigned_courier_cannot_complete_a_route(): void
    {
        $scenario = $this->createScenario([
            'FAILED',
        ]);

        $unassignedCourier =
            Courier::factory()->create();

        $this
            ->actingAs($unassignedCourier->user)
            ->patchJson(
                route(
                    'routes.complete',
                    $scenario['route']
                )
            )
            ->assertForbidden();

        $this->assertRouteWasNotCompleted(
            $scenario
        );
    }

    public function test_an_administrator_can_complete_a_route(): void
    {
        $scenario = $this->createScenario([
            'DELIVERED',
        ]);

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this
            ->actingAs($administrator)
            ->patchJson(
                route(
                    'routes.complete',
                    $scenario['route']
                )
            )
            ->assertOk();

        $this->assertRouteWasCompleted(
            $scenario
        );
    }

    public function test_a_route_that_is_not_active_cannot_be_completed(): void
    {
        $scenario = $this->createScenario(
            ['DELIVERED'],
            routeStatus: 'PLANNED'
        );

        $this
            ->actingAs($scenario['provider']->user)
            ->patchJson(
                route(
                    'routes.complete',
                    $scenario['route']
                )
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Only an active route can be completed.'
            );

        $this->assertRouteWasNotCompleted(
            $scenario
        );
    }

    public function test_non_terminal_shipments_prevent_completion(): void
    {
        $scenario = $this->createScenario([
            'DELIVERED',
            'IN_PROGRESS',
        ]);

        $this
            ->actingAs($scenario['provider']->user)
            ->patchJson(
                route(
                    'routes.complete',
                    $scenario['route']
                )
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Every route shipment must be delivered or failed before completing the route.'
            );

        $this->assertRouteWasNotCompleted(
            $scenario
        );
    }

    public function test_an_empty_route_cannot_be_completed(): void
    {
        $scenario = $this->createScenario([]);

        $this
            ->actingAs($scenario['provider']->user)
            ->patchJson(
                route(
                    'routes.complete',
                    $scenario['route']
                )
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'A route without shipments cannot be completed.'
            );

        $this->assertRouteWasNotCompleted(
            $scenario
        );
    }

    /**
     * @param array<int, string> $deliveryStatuses
     * @return array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     route: Route
     * }
     */
    private function createScenario(
        array $deliveryStatuses,
        string $routeStatus = 'ACTIVE'
    ): array {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
            'is_active' => true,
            'is_available' => false,
        ]);

        $isStarted = in_array(
            $routeStatus,
            ['ACTIVE', 'COMPLETED'],
            true
        );

        $route = Route::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' =>
                $this->findRouteStatus(
                    $routeStatus
                )->id,
            'route_date' => today(),
            'started_at' => $isStarted
                ? now()->subHour()
                : null,
            'finished_at' =>
                $routeStatus === 'COMPLETED'
                    ? now()->subMinutes(10)
                    : null,
            'estimated_distance_km' => 10.50,
        ]);

        foreach (
            $deliveryStatuses as
            $index => $deliveryStatus
        ) {
            $shipment =
                Shipment::factory()->create();

            RouteShipment::query()->create([
                'route_id' => $route->id,
                'shipment_id' => $shipment->id,
                'delivery_order' => $index + 1,
                'delivery_status' =>
                    $deliveryStatus,
            ]);
        }

        return [
            'provider' => $provider,
            'courier' => $courier,
            'route' => $route,
        ];
    }

    private function findRouteStatus(
        string $statusName
    ): RouteStatus {
        return RouteStatus::query()
            ->where(
                'status_name',
                $statusName
            )
            ->firstOrFail();
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

    /**
     * @param array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     route: Route
     * } $scenario
     */
    private function assertRouteWasCompleted(
        array $scenario
    ): void {
        $scenario['route']->refresh();
        $scenario['courier']->refresh();

        $this->assertSame(
            'COMPLETED',
            $scenario['route']
                ->routeStatus
                ->status_name
        );

        $this->assertNotNull(
            $scenario['route']->finished_at
        );

        $this->assertTrue(
            $scenario['courier']->is_available
        );
    }

    /**
     * @param array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     route: Route
     * } $scenario
     */
    private function assertRouteWasNotCompleted(
        array $scenario
    ): void {
        $scenario['route']->refresh();
        $scenario['courier']->refresh();

        $this->assertNotSame(
            'COMPLETED',
            $scenario['route']
                ->routeStatus
                ->status_name
        );

        $this->assertNull(
            $scenario['route']->finished_at
        );

        $this->assertFalse(
            $scenario['courier']->is_available
        );
    }
}