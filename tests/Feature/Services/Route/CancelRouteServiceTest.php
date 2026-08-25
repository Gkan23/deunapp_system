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
use App\Models\User;
use App\Services\Route\CancelRouteService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelRouteServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_cancels_a_planned_route_atomically(): void
    {
        [
            $route,
            $courier,
            $items,
        ] = $this->createRouteScenario(
            routeStatus: 'PLANNED',
            deliveryStatuses: ['PENDING', 'PENDING']
        );

        $cancelledRoute = app(CancelRouteService::class)->execute(
            $route,
            $courier->user,
            'The planned route was cancelled by operations.'
        );

        $this->assertSame(
            $this->findRouteStatus('CANCELLED')->id,
            $cancelledRoute->route_status_id
        );

        $this->assertNotNull($cancelledRoute->finished_at);
        $this->assertNull($cancelledRoute->started_at);
        $this->assertTrue($courier->fresh()->is_available);

        foreach ($items as $item) {
            $this->assertSame(
                'CANCELLED',
                $item['routeShipment']->fresh()->delivery_status
            );

            $this->assertSame(
                'ASSIGNED',
                $item['deliveryService']->fresh()->status
            );

            $this->assertNull(
                $item['deliveryService']->fresh()->started_at
            );
        }

        $this->assertDatabaseCount('incidents', 2);

        $this->assertDatabaseHas('incidents', [
            'description' => 'The planned route was cancelled by operations.',
        ]);
    }

    public function test_it_cancels_an_active_route_atomically(): void
    {
        [
            $route,
            $courier,
            $items,
        ] = $this->createRouteScenario(
            routeStatus: 'ACTIVE',
            deliveryStatuses: ['IN_PROGRESS', 'IN_PROGRESS']
        );

        $cancelledRoute = app(CancelRouteService::class)->execute(
            $route,
            $courier->user,
            'The active route was cancelled because of an operational problem.'
        );

        $this->assertSame(
            $this->findRouteStatus('CANCELLED')->id,
            $cancelledRoute->route_status_id
        );

        $this->assertNotNull($cancelledRoute->started_at);
        $this->assertNotNull($cancelledRoute->finished_at);
        $this->assertTrue($courier->fresh()->is_available);

        foreach ($items as $item) {
            $this->assertSame(
                'CANCELLED',
                $item['routeShipment']->fresh()->delivery_status
            );

            $this->assertSame(
                'ASSIGNED',
                $item['deliveryService']->fresh()->status
            );

            $this->assertNull(
                $item['deliveryService']->fresh()->started_at
            );
        }

        $this->assertDatabaseCount('incidents', 2);
    }

    public function test_it_preserves_terminal_route_shipments(): void
    {
        [
            $route,
            $courier,
            $items,
        ] = $this->createRouteScenario(
            routeStatus: 'ACTIVE',
            deliveryStatuses: [
                'DELIVERED',
                'FAILED',
                'IN_PROGRESS',
            ]
        );

        app(CancelRouteService::class)->execute(
            $route,
            $courier->user,
            'The remaining delivery was cancelled.'
        );

        $this->assertSame(
            'DELIVERED',
            $items[0]['routeShipment']->fresh()->delivery_status
        );

        $this->assertSame(
            'COMPLETED',
            $items[0]['deliveryService']->fresh()->status
        );

        $this->assertSame(
            'FAILED',
            $items[1]['routeShipment']->fresh()->delivery_status
        );

        $this->assertSame(
            'ASSIGNED',
            $items[1]['deliveryService']->fresh()->status
        );

        $this->assertSame(
            'CANCELLED',
            $items[2]['routeShipment']->fresh()->delivery_status
        );

        $this->assertSame(
            'ASSIGNED',
            $items[2]['deliveryService']->fresh()->status
        );

        $this->assertDatabaseCount('incidents', 1);
    }

    public function test_it_rejects_a_completed_route(): void
    {
        [
            $route,
            $courier,
            $items,
        ] = $this->createRouteScenario(
            routeStatus: 'COMPLETED',
            deliveryStatuses: ['DELIVERED']
        );

        $this->assertDomainException(
            fn () => app(CancelRouteService::class)->execute(
                $route,
                $courier->user,
                'Invalid cancellation attempt.'
            ),
            'Only planned or active routes can be cancelled.'
        );

        $this->assertSame(
            $this->findRouteStatus('COMPLETED')->id,
            $route->fresh()->route_status_id
        );

        $this->assertSame(
            'DELIVERED',
            $items[0]['routeShipment']->fresh()->delivery_status
        );

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_it_prevents_duplicate_route_cancellation(): void
    {
        [
            $route,
            $courier,
        ] = $this->createRouteScenario(
            routeStatus: 'ACTIVE',
            deliveryStatuses: ['IN_PROGRESS']
        );

        $service = app(CancelRouteService::class);

        $service->execute(
            $route,
            $courier->user,
            'First cancellation.'
        );

        $this->assertDomainException(
            fn () => $service->execute(
                $route,
                $courier->user,
                'Duplicated cancellation.'
            ),
            'Only planned or active routes can be cancelled.'
        );

        $this->assertDatabaseCount('incidents', 1);

        $this->assertDatabaseMissing('incidents', [
            'description' => 'Duplicated cancellation.',
        ]);
    }

    public function test_it_rejects_an_empty_cancellation_reason(): void
    {
        [
            $route,
            $courier,
            $items,
        ] = $this->createRouteScenario(
            routeStatus: 'ACTIVE',
            deliveryStatuses: ['IN_PROGRESS']
        );

        $this->assertDomainException(
            fn () => app(CancelRouteService::class)->execute(
                $route,
                $courier->user,
                '   '
            ),
            'The route cancellation reason is required.'
        );

        $this->assertSame(
            $this->findRouteStatus('ACTIVE')->id,
            $route->fresh()->route_status_id
        );

        $this->assertSame(
            'IN_PROGRESS',
            $items[0]['routeShipment']->fresh()->delivery_status
        );

        $this->assertSame(
            'IN_PROGRESS',
            $items[0]['deliveryService']->fresh()->status
        );

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_it_keeps_an_inactive_courier_unavailable(): void
    {
        [
            $route,
            $courier,
        ] = $this->createRouteScenario(
            routeStatus: 'ACTIVE',
            deliveryStatuses: ['IN_PROGRESS'],
            courierActive: false
        );

        app(CancelRouteService::class)->execute(
            $route,
            $courier->user,
            'The route was cancelled after disabling the courier.'
        );

        $this->assertFalse($courier->fresh()->is_available);

        $this->assertSame(
            $this->findRouteStatus('CANCELLED')->id,
            $route->fresh()->route_status_id
        );
    }

    public function test_it_keeps_the_courier_unavailable_when_another_route_is_active(): void
    {
        [
            $route,
            $courier,
        ] = $this->createRouteScenario(
            routeStatus: 'PLANNED',
            deliveryStatuses: ['PENDING']
        );

        $courier->update([
            'is_available' => false,
        ]);

        Route::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' => $this->findRouteStatus('ACTIVE')->id,
            'route_date' => today(),
            'started_at' => now()->subMinutes(30),
            'finished_at' => null,
            'estimated_distance_km' => 3.50,
        ]);

        app(CancelRouteService::class)->execute(
            $route,
            $courier->user,
            'The planned route was replaced by an active route.'
        );

        $this->assertFalse($courier->fresh()->is_available);
    }

    public function test_it_allows_cancelling_an_empty_planned_route(): void
    {
        [
            $route,
            $courier,
        ] = $this->createRouteScenario(
            routeStatus: 'PLANNED',
            deliveryStatuses: []
        );

        $cancelledRoute = app(CancelRouteService::class)->execute(
            $route,
            $courier->user,
            'The empty planned route is no longer required.'
        );

        $this->assertSame(
            $this->findRouteStatus('CANCELLED')->id,
            $cancelledRoute->route_status_id
        );

        $this->assertNotNull($cancelledRoute->finished_at);
        $this->assertTrue($courier->fresh()->is_available);
        $this->assertDatabaseCount('incidents', 0);
    }

    /**
     * @return array{
     *     0: Route,
     *     1: Courier,
     *     2: \Illuminate\Support\Collection
     * }
     */
    private function createRouteScenario(
        string $routeStatus,
        array $deliveryStatuses,
        bool $courierActive = true
    ): array {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
            'is_active' => $courierActive,
            'is_available' => $routeStatus !== 'ACTIVE',
        ]);

        $isStarted = in_array(
            $routeStatus,
            ['ACTIVE', 'COMPLETED'],
            true
        );

        $route = Route::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' => $this->findRouteStatus($routeStatus)->id,
            'route_date' => today(),
            'started_at' => $isStarted
                ? now()->subHour()
                : null,
            'finished_at' => $routeStatus === 'COMPLETED'
                ? now()->subMinutes(10)
                : null,
            'estimated_distance_km' => 8.50,
        ]);

        $items = collect();

        foreach ($deliveryStatuses as $index => $deliveryStatus) {
            $shipment = Shipment::factory()->create();

            $trip = Trip::factory()->create([
                'delivery_provider_id' => $provider->id,
                'status' => 'USED',
                'used_at' => now()->subHours(2),
            ]);

            $serviceStatus = match ($deliveryStatus) {
                'PENDING', 'FAILED', 'CANCELLED' => 'ASSIGNED',
                'IN_PROGRESS' => 'IN_PROGRESS',
                'DELIVERED' => 'COMPLETED',
            };

            $deliveryService = DeliveryService::factory()->create([
                'shipment_id' => $shipment->id,
                'trip_id' => $trip->id,
                'status' => $serviceStatus,
                'accepted_at' => now()->subHours(2),
                'started_at' => $serviceStatus === 'IN_PROGRESS'
                    ? now()->subHour()
                    : null,
                'completed_at' => $serviceStatus === 'COMPLETED'
                    ? now()->subMinutes(15)
                    : null,
                'cancelled_at' => null,
            ]);

            $routeShipment = RouteShipment::query()->create([
                'route_id' => $route->id,
                'shipment_id' => $shipment->id,
                'delivery_order' => $index + 1,
                'delivery_status' => $deliveryStatus,
            ]);

            $items->push([
                'shipment' => $shipment,
                'routeShipment' => $routeShipment,
                'deliveryService' => $deliveryService,
            ]);
        }

        return [$route, $courier, $items];
    }

    private function findRouteStatus(string $statusName): RouteStatus
    {
        return RouteStatus::query()
            ->where('status_name', $statusName)
            ->firstOrFail();
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