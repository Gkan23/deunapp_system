<?php

namespace Tests\Feature\Services\Route;

use App\Models\Courier;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Services\Route\CompleteRouteService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteRouteServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_completes_an_active_route_atomically(): void
    {
        [$route, $courier, $routeShipments] = $this->createRouteWithStatuses([
            'DELIVERED',
            'DELIVERED',
        ]);

        $completedRoute = app(CompleteRouteService::class)
            ->execute($route);

        $completedStatus = $this->findRouteStatus('COMPLETED');

        $this->assertSame(
            $completedStatus->id,
            $completedRoute->route_status_id
        );

        $this->assertNotNull($completedRoute->finished_at);

        $this->assertTrue(
            $courier->fresh()->is_available
        );

        $this->assertDatabaseHas('routes', [
            'id' => $route->id,
            'route_status_id' => $completedStatus->id,
        ]);

        foreach ($routeShipments as $routeShipment) {
            $this->assertSame(
                'DELIVERED',
                $routeShipment->fresh()->delivery_status
            );
        }
    }

    public function test_it_completes_a_route_with_delivered_and_failed_shipments(): void
    {
        [$route, $courier, $routeShipments] = $this->createRouteWithStatuses([
            'DELIVERED',
            'FAILED',
            'DELIVERED',
        ]);

        $completedRoute = app(CompleteRouteService::class)
            ->execute($route);

        $this->assertSame(
            $this->findRouteStatus('COMPLETED')->id,
            $completedRoute->route_status_id
        );

        $this->assertNotNull($completedRoute->finished_at);
        $this->assertTrue($courier->fresh()->is_available);

        $this->assertSame(
            ['DELIVERED', 'FAILED', 'DELIVERED'],
            $routeShipments
                ->map(
                    fn (RouteShipment $routeShipment): string =>
                        $routeShipment->fresh()->delivery_status
                )
                ->all()
        );
    }

    public function test_it_rejects_a_route_that_is_not_active(): void
    {
        [$route, $courier] = $this->createRouteWithStatuses([
            'DELIVERED',
        ]);

        $route->update([
            'route_status_id' => $this->findRouteStatus('PLANNED')->id,
            'started_at' => null,
        ]);

        $this->assertDomainException(
            fn () => app(CompleteRouteService::class)->execute($route),
            'Only an active route can be completed.'
        );

        $this->assertRouteRemainsUnfinished($route, $courier, 'PLANNED');
    }

    public function test_it_rejects_an_active_route_without_shipments(): void
    {
        [$route, $courier] = $this->createRouteWithStatuses([]);

        $this->assertDomainException(
            fn () => app(CompleteRouteService::class)->execute($route),
            'A route without shipments cannot be completed.'
        );

        $this->assertRouteRemainsUnfinished($route, $courier, 'ACTIVE');
    }

    public function test_it_rejects_a_route_with_a_pending_shipment(): void
    {
        [$route, $courier] = $this->createRouteWithStatuses([
            'DELIVERED',
            'PENDING',
        ]);

        $this->assertDomainException(
            fn () => app(CompleteRouteService::class)->execute($route),
            'Every route shipment must be delivered or failed before completing the route.'
        );

        $this->assertRouteRemainsUnfinished($route, $courier, 'ACTIVE');
    }

    public function test_it_rejects_a_route_with_an_in_progress_shipment(): void
    {
        [$route, $courier] = $this->createRouteWithStatuses([
            'DELIVERED',
            'IN_PROGRESS',
        ]);

        $this->assertDomainException(
            fn () => app(CompleteRouteService::class)->execute($route),
            'Every route shipment must be delivered or failed before completing the route.'
        );

        $this->assertRouteRemainsUnfinished($route, $courier, 'ACTIVE');
    }

    public function test_it_keeps_an_inactive_courier_unavailable_after_completion(): void
    {
        [$route, $courier] = $this->createRouteWithStatuses(
            ['DELIVERED'],
            [
                'is_active' => false,
                'is_available' => false,
            ]
        );

        $completedRoute = app(CompleteRouteService::class)
            ->execute($route);

        $this->assertSame(
            $this->findRouteStatus('COMPLETED')->id,
            $completedRoute->route_status_id
        );

        $this->assertNotNull($completedRoute->finished_at);
        $this->assertFalse($courier->fresh()->is_available);
    }

    /**
     * @return array{
     *     0: Route,
     *     1: Courier,
     *     2: \Illuminate\Support\Collection<int, RouteShipment>
     * }
     */
    private function createRouteWithStatuses(
        array $deliveryStatuses,
        array $courierAttributes = []
    ): array {
        $courier = Courier::factory()->create(array_merge([
            'is_active' => true,
            'is_available' => false,
        ], $courierAttributes));

        $route = Route::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' => $this->findRouteStatus('ACTIVE')->id,
            'route_date' => today(),
            'started_at' => now()->subHour(),
            'finished_at' => null,
            'estimated_distance_km' => 10.50,
        ]);

        $routeShipments = collect();

        foreach ($deliveryStatuses as $index => $deliveryStatus) {
            $shipment = Shipment::factory()->create();

            $routeShipments->push(
                RouteShipment::query()->create([
                    'route_id' => $route->id,
                    'shipment_id' => $shipment->id,
                    'delivery_order' => $index + 1,
                    'delivery_status' => $deliveryStatus,
                ])
            );
        }

        return [$route, $courier, $routeShipments];
    }

    private function findRouteStatus(string $statusName): RouteStatus
    {
        return RouteStatus::query()
            ->where('status_name', $statusName)
            ->firstOrFail();
    }

    private function assertRouteRemainsUnfinished(
        Route $route,
        Courier $courier,
        string $expectedStatus
    ): void {
        $freshRoute = $route->fresh();

        $this->assertSame(
            $this->findRouteStatus($expectedStatus)->id,
            $freshRoute->route_status_id
        );

        $this->assertNull($freshRoute->finished_at);
        $this->assertFalse($courier->fresh()->is_available);
    }

    private function assertDomainException(
        Closure $callback,
        string $expectedMessage
    ): void {
        try {
            $callback();

            $this->fail('A DomainException was expected.');
        } catch (DomainException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );
        }
    }
}

