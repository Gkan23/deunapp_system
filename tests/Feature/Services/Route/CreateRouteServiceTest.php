<?php

namespace Tests\Feature\Services\Route;

use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Shipment;
use App\Models\Trip;
use App\Services\Route\CreateRouteService;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateRouteServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_creates_a_planned_route_with_ordered_shipments(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $firstShipment = $this->createAssignedShipment(
            $provider
        );

        $secondShipment = $this->createAssignedShipment(
            $provider
        );

        $route = app(CreateRouteService::class)->handle(
            $courier,
            [
                $secondShipment,
                $firstShipment,
            ],
            today()->addDay(),
            15.75
        );

        $this->assertSame(
            $courier->id,
            $route->courier_id
        );

        $this->assertSame(
            'PLANNED',
            $route->routeStatus->status_name
        );

        $this->assertNull($route->started_at);
        $this->assertNull($route->finished_at);

        $this->assertDatabaseCount('routes', 1);
        $this->assertDatabaseCount(
            'route_shipments',
            2
        );

        $this->assertDatabaseHas('route_shipments', [
            'route_id' => $route->id,
            'shipment_id' => $secondShipment->id,
            'delivery_order' => 1,
            'delivery_status' => 'PENDING',
        ]);

        $this->assertDatabaseHas('route_shipments', [
            'route_id' => $route->id,
            'shipment_id' => $firstShipment->id,
            'delivery_order' => 2,
            'delivery_status' => 'PENDING',
        ]);
    }

    public function test_it_requires_at_least_one_shipment(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $this->assertDomainException(
            'At least one shipment is required.',
            fn () => app(CreateRouteService::class)
                ->handle(
                    $courier,
                    [],
                    today()
                )
        );

        $this->assertDatabaseCount('routes', 0);
    }

    public function test_it_rejects_duplicate_shipments(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $shipment = $this->createAssignedShipment(
            $provider
        );

        $this->assertDomainException(
            'A shipment cannot be repeated within the same route.',
            fn () => app(CreateRouteService::class)
                ->handle(
                    $courier,
                    [$shipment, $shipment],
                    today()
                )
        );

        $this->assertDatabaseCount('routes', 0);
        $this->assertDatabaseCount(
            'route_shipments',
            0
        );
    }

    public function test_it_rejects_an_inactive_courier(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create([
                'is_active' => false,
            ]);

        $shipment = $this->createAssignedShipment(
            $provider
        );

        $this->assertDomainException(
            'The courier is inactive.',
            fn () => app(CreateRouteService::class)
                ->handle(
                    $courier,
                    [$shipment],
                    today()
                )
        );

        $this->assertDatabaseCount('routes', 0);
    }

    public function test_it_rejects_an_unavailable_courier(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create([
                'is_available' => false,
            ]);

        $shipment = $this->createAssignedShipment(
            $provider
        );

        $this->assertDomainException(
            'The courier is not available.',
            fn () => app(CreateRouteService::class)
                ->handle(
                    $courier,
                    [$shipment],
                    today()
                )
        );

        $this->assertDatabaseCount('routes', 0);
    }

    public function test_it_rejects_an_inactive_provider(): void
    {
        $provider = DeliveryProvider::factory()->create([
            'is_active' => false,
        ]);

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $shipment = $this->createAssignedShipment(
            $provider
        );

        $this->assertDomainException(
            'The courier delivery provider is inactive.',
            fn () => app(CreateRouteService::class)
                ->handle(
                    $courier,
                    [$shipment],
                    today()
                )
        );

        $this->assertDatabaseCount('routes', 0);
    }

    public function test_it_requires_an_assigned_delivery_service(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $shipment = Shipment::factory()->create();

        $this->assertDomainException(
            'Each shipment must have an assigned delivery service.',
            fn () => app(CreateRouteService::class)
                ->handle(
                    $courier,
                    [$shipment],
                    today()
                )
        );

        $this->assertDatabaseCount('routes', 0);
        $this->assertDatabaseCount(
            'route_shipments',
            0
        );
    }

    public function test_it_rejects_a_trip_from_another_provider(): void
    {
        $courierProvider = DeliveryProvider::factory()
            ->create();

        $tripProvider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()
            ->for($courierProvider)
            ->create();

        $shipment = $this->createAssignedShipment(
            $tripProvider
        );

        $this->assertDomainException(
            'All shipment trips must belong to the courier provider.',
            fn () => app(CreateRouteService::class)
                ->handle(
                    $courier,
                    [$shipment],
                    today()
                )
        );

        $this->assertDatabaseCount('routes', 0);
        $this->assertDatabaseCount(
            'route_shipments',
            0
        );
    }

    public function test_it_rejects_a_shipment_already_in_an_open_route(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $shipment = $this->createAssignedShipment(
            $provider
        );

        $routeService = app(CreateRouteService::class);

        $routeService->handle(
            $courier,
            [$shipment],
            today()
        );

        $this->assertDomainException(
            'A shipment already belongs to a planned or active route.',
            fn () => $routeService->handle(
                $courier,
                [$shipment],
                today()->addDay()
            )
        );

        $this->assertDatabaseCount('routes', 1);
        $this->assertDatabaseCount(
            'route_shipments',
            1
        );
    }

    public function test_it_rejects_a_past_route_date(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $shipment = $this->createAssignedShipment(
            $provider
        );

        $this->assertDomainException(
            'The route date cannot be in the past.',
            fn () => app(CreateRouteService::class)
                ->handle(
                    $courier,
                    [$shipment],
                    today()->subDay()
                )
        );

        $this->assertDatabaseCount('routes', 0);
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

        $service = DeliveryService::factory()->create([
            'trip_id' => $trip->id,
            'status' => 'ASSIGNED',
            'accepted_at' => now(),
        ]);

        return $service->shipment;
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
