<?php

namespace Tests\Feature\Services\Route;

use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Route;
use App\Models\RouteStatus;
use App\Models\Trip;
use App\Services\Route\ActivateRouteService;
use App\Services\Route\CreateRouteService;
use Carbon\CarbonInterface;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivateRouteServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_activates_a_route_atomically(): void
    {
        $scenario = $this->createScenario(
            shipmentCount: 2
        );

        $activatedRoute = app(
            ActivateRouteService::class
        )->handle($scenario['route']);

        $this->assertSame(
            'ACTIVE',
            $activatedRoute->routeStatus->status_name
        );

        $this->assertNotNull(
            $activatedRoute->started_at
        );

        $scenario['courier']->refresh();

        $this->assertFalse(
            $scenario['courier']->is_available
        );

        foreach (
            $activatedRoute->routeShipments as $routeShipment
        ) {
            $this->assertSame(
                'IN_PROGRESS',
                $routeShipment->delivery_status
            );

            $service = $routeShipment
                ->shipment
                ->deliveryService;

            $this->assertSame(
                'IN_PROGRESS',
                $service->status
            );

            $this->assertNotNull(
                $service->started_at
            );
        }

        $this->assertDatabaseCount(
            'route_shipments',
            2
        );

        $this->assertSame(
            2,
            DeliveryService::query()
                ->where('status', 'IN_PROGRESS')
                ->count()
        );
    }

    public function test_it_rejects_a_route_that_is_not_planned(): void
    {
        $scenario = $this->createScenario();

        $activeStatus = $this->findRouteStatus(
            'ACTIVE'
        );

        $scenario['route']->update([
            'route_status_id' => $activeStatus->id,
            'started_at' => now(),
        ]);

        $this->assertDomainException(
            'Only a planned route can be activated.',
            fn () => app(ActivateRouteService::class)
                ->handle($scenario['route'])
        );

        $scenario['services'][0]->refresh();

        $this->assertSame(
            'ASSIGNED',
            $scenario['services'][0]->status
        );
    }

    public function test_it_rejects_early_activation(): void
    {
        $scenario = $this->createScenario(
            routeDate: today()->addDay()
        );

        $this->assertDomainException(
            'A route can only be activated on its scheduled date.',
            fn () => app(ActivateRouteService::class)
                ->handle($scenario['route'])
        );

        $this->assertRouteRemainsPlanned(
            $scenario['route']
        );
    }

    public function test_it_rejects_an_inactive_courier(): void
    {
        $scenario = $this->createScenario();

        $scenario['courier']->update([
            'is_active' => false,
        ]);

        $this->assertDomainException(
            'The courier is inactive.',
            fn () => app(ActivateRouteService::class)
                ->handle($scenario['route'])
        );

        $this->assertRouteRemainsPlanned(
            $scenario['route']
        );
    }

    public function test_it_rejects_an_unavailable_courier(): void
    {
        $scenario = $this->createScenario();

        $scenario['courier']->update([
            'is_available' => false,
        ]);

        $this->assertDomainException(
            'The courier is not available.',
            fn () => app(ActivateRouteService::class)
                ->handle($scenario['route'])
        );

        $this->assertRouteRemainsPlanned(
            $scenario['route']
        );
    }

    public function test_it_rejects_an_inactive_provider(): void
    {
        $scenario = $this->createScenario();

        $scenario['provider']->update([
            'is_active' => false,
        ]);

        $this->assertDomainException(
            'The courier delivery provider is inactive.',
            fn () => app(ActivateRouteService::class)
                ->handle($scenario['route'])
        );

        $this->assertRouteRemainsPlanned(
            $scenario['route']
        );
    }

    public function test_it_rejects_a_second_active_route_for_the_courier(): void
    {
        $scenario = $this->createScenario();

        $activeStatus = $this->findRouteStatus(
            'ACTIVE'
        );

        Route::query()->create([
            'courier_id' => $scenario['courier']->id,
            'route_status_id' => $activeStatus->id,
            'route_date' => today(),
            'started_at' => now(),
            'finished_at' => null,
            'estimated_distance_km' => null,
        ]);

        $this->assertDomainException(
            'The courier already has an active route.',
            fn () => app(ActivateRouteService::class)
                ->handle($scenario['route'])
        );

        $this->assertRouteRemainsPlanned(
            $scenario['route']
        );
    }

    public function test_it_rejects_an_empty_route(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $plannedStatus = $this->findRouteStatus(
            'PLANNED'
        );

        $route = Route::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' => $plannedStatus->id,
            'route_date' => today(),
            'started_at' => null,
            'finished_at' => null,
            'estimated_distance_km' => null,
        ]);

        $this->assertDomainException(
            'An empty route cannot be activated.',
            fn () => app(ActivateRouteService::class)
                ->handle($route)
        );

        $this->assertRouteRemainsPlanned($route);
    }

    public function test_it_requires_pending_route_shipments(): void
    {
        $scenario = $this->createScenario();

        $scenario['route']
            ->routeShipments()
            ->firstOrFail()
            ->update([
                'delivery_status' => 'IN_PROGRESS',
            ]);

        $this->assertDomainException(
            'Every route shipment must be pending before activation.',
            fn () => app(ActivateRouteService::class)
                ->handle($scenario['route'])
        );

        $this->assertRouteRemainsPlanned(
            $scenario['route']
        );

        $scenario['services'][0]->refresh();

        $this->assertSame(
            'ASSIGNED',
            $scenario['services'][0]->status
        );
    }

    public function test_it_requires_assigned_delivery_services(): void
    {
        $scenario = $this->createScenario();

        $scenario['services'][0]->update([
            'status' => 'REQUESTED',
        ]);

        $this->assertDomainException(
            'Every route shipment must have an assigned delivery service.',
            fn () => app(ActivateRouteService::class)
                ->handle($scenario['route'])
        );

        $this->assertRouteRemainsPlanned(
            $scenario['route']
        );
    }

    public function test_it_rejects_a_provider_mismatch(): void
    {
        $scenario = $this->createScenario();

        $otherProvider = DeliveryProvider::factory()
            ->create();

        $scenario['services'][0]
            ->trip
            ->update([
                'delivery_provider_id' => $otherProvider->id,
            ]);

        $this->assertDomainException(
            'The route shipment provider does not match the courier provider.',
            fn () => app(ActivateRouteService::class)
                ->handle($scenario['route'])
        );

        $this->assertRouteRemainsPlanned(
            $scenario['route']
        );
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
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $services = [];
        $shipments = [];

        for ($index = 0; $index < $shipmentCount; $index++) {
            $service = $this->createAssignedService(
                $provider
            );

            $services[] = $service;
            $shipments[] = $service->shipment;
        }

        $route = app(CreateRouteService::class)->handle(
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

        return DeliveryService::factory()->create([
            'trip_id' => $trip->id,
            'status' => 'ASSIGNED',
            'accepted_at' => now(),
            'started_at' => null,
        ]);
    }

    private function findRouteStatus(
        string $statusName
    ): RouteStatus {
        return RouteStatus::query()
            ->where('status_name', $statusName)
            ->firstOrFail();
    }

    private function assertRouteRemainsPlanned(
        Route $route
    ): void {
        $route->refresh();

        $this->assertSame(
            'PLANNED',
            $route->routeStatus->status_name
        );

        $this->assertNull($route->started_at);
    }

    private function assertDomainException(
        string $expectedMessage,
        callable $callback
    ): void {
        try {
            $callback();
        } catch (DomainException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );

            return;
        }

        $this->fail(
            'The expected DomainException was not thrown.'
        );
    }
}
