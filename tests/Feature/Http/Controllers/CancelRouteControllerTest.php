<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Role;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelRouteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_cancel_a_route(): void
    {
        $scenario = $this->createScenario(
            'PLANNED',
            ['PENDING']
        );

        $this->patchJson(
            route(
                'routes.cancel',
                $scenario['route']
            ),
            $this->validPayload()
        )->assertUnauthorized();

        $this->assertRouteWasNotCancelled(
            $scenario
        );
    }

    public function test_the_linked_provider_can_cancel_a_planned_route(): void
    {
        $scenario = $this->createScenario(
            'PLANNED',
            ['PENDING', 'PENDING']
        );

        $response = $this
            ->actingAs($scenario['provider']->user)
            ->patchJson(
                route(
                    'routes.cancel',
                    $scenario['route']
                ),
                $this->validPayload()
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Route cancelled successfully.'
            )
            ->assertJsonPath(
                'route.route_status.status_name',
                'CANCELLED'
            );

        $this->assertRouteWasCancelled(
            $scenario,
            expectedIncidentCount: 2
        );
    }

    public function test_the_linked_provider_can_cancel_an_active_route(): void
    {
        $scenario = $this->createScenario(
            'ACTIVE',
            ['IN_PROGRESS', 'IN_PROGRESS']
        );

        $this
            ->actingAs($scenario['provider']->user)
            ->patchJson(
                route(
                    'routes.cancel',
                    $scenario['route']
                ),
                $this->validPayload()
            )
            ->assertOk()
            ->assertJsonPath(
                'route.route_status.status_name',
                'CANCELLED'
            );

        $this->assertRouteWasCancelled(
            $scenario,
            expectedIncidentCount: 2
        );
    }

    public function test_the_assigned_courier_cannot_cancel_the_route(): void
    {
        $scenario = $this->createScenario(
            'ACTIVE',
            ['IN_PROGRESS']
        );

        $this
            ->actingAs($scenario['courier']->user)
            ->patchJson(
                route(
                    'routes.cancel',
                    $scenario['route']
                ),
                $this->validPayload()
            )
            ->assertForbidden();

        $this->assertRouteWasNotCancelled(
            $scenario
        );
    }

    public function test_an_unrelated_provider_cannot_cancel_the_route(): void
    {
        $scenario = $this->createScenario(
            'PLANNED',
            ['PENDING']
        );

        $unrelatedProvider =
            DeliveryProvider::factory()->create();

        $this
            ->actingAs($unrelatedProvider->user)
            ->patchJson(
                route(
                    'routes.cancel',
                    $scenario['route']
                ),
                $this->validPayload()
            )
            ->assertForbidden();

        $this->assertRouteWasNotCancelled(
            $scenario
        );
    }

    public function test_an_administrator_can_cancel_a_route(): void
    {
        $scenario = $this->createScenario(
            'PLANNED',
            ['PENDING']
        );

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this
            ->actingAs($administrator)
            ->patchJson(
                route(
                    'routes.cancel',
                    $scenario['route']
                ),
                $this->validPayload()
            )
            ->assertOk();

        $this->assertRouteWasCancelled(
            $scenario,
            expectedIncidentCount: 1
        );
    }

    public function test_the_cancellation_reason_is_required(): void
    {
        $scenario = $this->createScenario(
            'ACTIVE',
            ['IN_PROGRESS']
        );

        $this
            ->actingAs($scenario['provider']->user)
            ->patchJson(
                route(
                    'routes.cancel',
                    $scenario['route']
                ),
                [
                    'reason' => '   ',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reason',
            ]);

        $this->assertRouteWasNotCancelled(
            $scenario
        );
    }

    public function test_a_completed_route_cannot_be_cancelled(): void
    {
        $scenario = $this->createScenario(
            'COMPLETED',
            ['DELIVERED']
        );

        $this
            ->actingAs($scenario['provider']->user)
            ->patchJson(
                route(
                    'routes.cancel',
                    $scenario['route']
                ),
                $this->validPayload()
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Only planned or active routes can be cancelled.'
            );

        $this->assertSame(
            'COMPLETED',
            $scenario['route']
                ->fresh()
                ->routeStatus
                ->status_name
        );

        $this->assertDatabaseCount(
            'incidents',
            0
        );
    }

    /**
     * @param array<int, string> $deliveryStatuses
     * @return array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     route: Route,
     *     items: array<int, array{
     *         routeShipment: RouteShipment,
     *         deliveryService: DeliveryService
     *     }>
     * }
     */
    private function createScenario(
        string $routeStatus,
        array $deliveryStatuses
    ): array {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
            'is_active' => true,
            'is_available' =>
                $routeStatus !== 'ACTIVE',
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
            'estimated_distance_km' => 8.50,
        ]);

        $items = [];

        foreach (
            $deliveryStatuses as
            $index => $deliveryStatus
        ) {
            $shipment =
                Shipment::factory()->create();

            $trip = Trip::factory()
                ->for($provider)
                ->create([
                    'status' => 'USED',
                    'used_at' =>
                        now()->subHours(2),
                ]);

            $serviceStatus = match (
                $deliveryStatus
            ) {
                'PENDING',
                'FAILED',
                'CANCELLED' => 'ASSIGNED',
                'IN_PROGRESS' => 'IN_PROGRESS',
                'DELIVERED' => 'COMPLETED',
            };

            $deliveryService =
                DeliveryService::factory()->create([
                    'shipment_id' => $shipment->id,
                    'trip_id' => $trip->id,
                    'status' => $serviceStatus,
                    'accepted_at' =>
                        now()->subHours(2),
                    'started_at' =>
                        $serviceStatus ===
                        'IN_PROGRESS'
                            ? now()->subHour()
                            : null,
                    'completed_at' =>
                        $serviceStatus ===
                        'COMPLETED'
                            ? now()->subMinutes(15)
                            : null,
                    'cancelled_at' => null,
                ]);

            $routeShipment =
                RouteShipment::query()->create([
                    'route_id' => $route->id,
                    'shipment_id' =>
                        $shipment->id,
                    'delivery_order' =>
                        $index + 1,
                    'delivery_status' =>
                        $deliveryStatus,
                ]);

            $items[] = [
                'routeShipment' =>
                    $routeShipment,
                'deliveryService' =>
                    $deliveryService,
            ];
        }

        return [
            'provider' => $provider,
            'courier' => $courier,
            'route' => $route,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'reason' =>
                'The route was cancelled by operations.',
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
     *     route: Route,
     *     items: array<int, array{
     *         routeShipment: RouteShipment,
     *         deliveryService: DeliveryService
     *     }>
     * } $scenario
     */
    private function assertRouteWasCancelled(
        array $scenario,
        int $expectedIncidentCount
    ): void {
        $scenario['route']->refresh();
        $scenario['courier']->refresh();

        $this->assertSame(
            'CANCELLED',
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

        foreach (
            $scenario['items'] as $item
        ) {
            $this->assertSame(
                'CANCELLED',
                $item['routeShipment']
                    ->fresh()
                    ->delivery_status
            );

            $deliveryService =
                $item['deliveryService']->fresh();

            $this->assertSame(
                'ASSIGNED',
                $deliveryService->status
            );

            $this->assertNull(
                $deliveryService->started_at
            );
        }

        $this->assertDatabaseCount(
            'incidents',
            $expectedIncidentCount
        );
    }

    /**
     * @param array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     route: Route,
     *     items: array<int, array{
     *         routeShipment: RouteShipment,
     *         deliveryService: DeliveryService
     *     }>
     * } $scenario
     */
    private function assertRouteWasNotCancelled(
        array $scenario
    ): void {
        $scenario['route']->refresh();

        $this->assertNotSame(
            'CANCELLED',
            $scenario['route']
                ->routeStatus
                ->status_name
        );

        foreach (
            $scenario['items'] as $item
        ) {
            $this->assertNotSame(
                'CANCELLED',
                $item['routeShipment']
                    ->fresh()
                    ->delivery_status
            );
        }

        $this->assertDatabaseCount(
            'incidents',
            0
        );
    }
}
