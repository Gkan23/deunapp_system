<?php

namespace Tests\Feature\Services\Delivery;

use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\ShipmentStatus;
use App\Models\Trip;
use App\Models\User;
use App\Services\Delivery\CompleteDeliveryService;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteDeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_completes_a_delivery_atomically(): void
    {
        $scenario = $this->createScenario();
        $user = User::factory()->create();

        $completedService = app(
            CompleteDeliveryService::class
        )->handle(
            $scenario['service'],
            $this->validProofData(),
            $user,
            'Entrega confirmada correctamente.'
        );

        $this->assertSame(
            'COMPLETED',
            $completedService->status
        );

        $this->assertNotNull(
            $completedService->completed_at
        );

        $this->assertSame(
            'DELIVERED',
            $completedService
                ->shipment
                ->shipmentStatus
                ->status_name
        );

        $this->assertNotNull(
            $completedService
                ->shipment
                ->delivered_at
        );

        $this->assertDatabaseHas('delivery_proofs', [
            'shipment_id' => $scenario['service']->shipment_id,
            'receiver_name' => 'Juan Pérez',
            'photo_url' => 'proofs/test-delivery.jpg',
        ]);

        $this->assertDatabaseHas(
            'shipment_status_history',
            [
                'shipment_id' => $scenario['service']->shipment_id,
                'changed_by_user_id' => $user->id,
                'comment' => 'Entrega confirmada correctamente.',
            ]
        );

        $this->assertDatabaseHas('route_shipments', [
            'id' => $scenario['routeShipment']->id,
            'delivery_status' => 'DELIVERED',
        ]);
    }

    public function test_it_rejects_a_service_that_is_not_in_progress(): void
    {
        $scenario = $this->createScenario();
        $user = User::factory()->create();

        $scenario['service']->update([
            'status' => 'ASSIGNED',
            'started_at' => null,
        ]);

        $this->assertDomainException(
            'The delivery service is not in progress.',
            fn () => app(
                CompleteDeliveryService::class
            )->handle(
                $scenario['service'],
                $this->validProofData(),
                $user
            )
        );

        $this->assertNoCompletionChanges(
            $scenario['service']
        );
    }

    public function test_it_rejects_a_shipment_that_is_not_out_for_delivery(): void
    {
        $scenario = $this->createScenario();
        $user = User::factory()->create();

        $inTransit = ShipmentStatus::query()
            ->where('status_name', 'IN_TRANSIT')
            ->firstOrFail();

        $scenario['service']->shipment->update([
            'shipment_status_id' => $inTransit->id,
        ]);

        $this->assertDomainException(
            'The shipment is not out for delivery.',
            fn () => app(
                CompleteDeliveryService::class
            )->handle(
                $scenario['service'],
                $this->validProofData(),
                $user
            )
        );

        $this->assertNoCompletionChanges(
            $scenario['service']
        );
    }

    public function test_it_requires_an_in_progress_route(): void
    {
        $scenario = $this->createScenario();
        $user = User::factory()->create();

        $scenario['routeShipment']->delete();

        $this->assertDomainException(
            'The shipment must belong to exactly one in-progress route.',
            fn () => app(
                CompleteDeliveryService::class
            )->handle(
                $scenario['service'],
                $this->validProofData(),
                $user
            )
        );

        $this->assertNoCompletionChanges(
            $scenario['service']
        );
    }

    public function test_it_rejects_a_route_from_another_provider(): void
    {
        $scenario = $this->createScenario();
        $user = User::factory()->create();

        $otherProvider = DeliveryProvider::factory()
            ->create();

        $otherCourier = Courier::factory()
            ->for($otherProvider)
            ->create();

        $scenario['route']->update([
            'courier_id' => $otherCourier->id,
        ]);

        $this->assertDomainException(
            'The route courier does not belong to the trip provider.',
            fn () => app(
                CompleteDeliveryService::class
            )->handle(
                $scenario['service'],
                $this->validProofData(),
                $user
            )
        );

        $this->assertNoCompletionChanges(
            $scenario['service']
        );
    }

    public function test_it_rejects_incomplete_proof_data(): void
    {
        $scenario = $this->createScenario();
        $user = User::factory()->create();

        $invalidProof = [
            'receiver_name' => 'Juan Pérez',
            'photo_url' => null,
            'signature_url' => null,
            'receiver_identity_number' => null,
            'latitude' => null,
            'longitude' => null,
        ];

        $this->assertDomainException(
            'At least one form of delivery evidence is required.',
            fn () => app(
                CompleteDeliveryService::class
            )->handle(
                $scenario['service'],
                $invalidProof,
                $user
            )
        );

        $this->assertNoCompletionChanges(
            $scenario['service']
        );
    }

    public function test_it_prevents_completing_the_same_service_twice(): void
    {
        $scenario = $this->createScenario();
        $user = User::factory()->create();

        $completionService = app(
            CompleteDeliveryService::class
        );

        $completionService->handle(
            $scenario['service'],
            $this->validProofData(),
            $user
        );

        $this->assertDomainException(
            'The delivery service is not in progress.',
            fn () => $completionService->handle(
                $scenario['service'],
                $this->validProofData(),
                $user
            )
        );

        $this->assertDatabaseCount(
            'delivery_proofs',
            1
        );

        $this->assertDatabaseCount(
            'shipment_status_history',
            1
        );

        $this->assertDatabaseHas('delivery_services', [
            'id' => $scenario['service']->id,
            'status' => 'COMPLETED',
        ]);
    }

    /**
     * @return array{
     *     service: DeliveryService,
     *     route: Route,
     *     routeShipment: RouteShipment
     * }
     */
    private function createScenario(): array
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $trip = Trip::factory()
            ->for($provider)
            ->create([
                'status' => 'USED',
                'used_at' => now(),
            ]);

        $service = DeliveryService::factory()->create([
            'trip_id' => $trip->id,
            'status' => 'IN_PROGRESS',
            'accepted_at' => now()->subMinutes(10),
            'started_at' => now()->subMinutes(5),
        ]);

        $outForDelivery = ShipmentStatus::query()
            ->where('status_name', 'OUT_FOR_DELIVERY')
            ->firstOrFail();

        $service->shipment->update([
            'shipment_status_id' => $outForDelivery->id,
        ]);

        $activeRouteStatus = RouteStatus::query()
            ->where('status_name', 'ACTIVE')
            ->firstOrFail();

        $route = Route::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' => $activeRouteStatus->id,
            'route_date' => today(),
            'started_at' => now()->subMinutes(20),
            'finished_at' => null,
            'estimated_distance_km' => '8.50',
        ]);

        $routeShipment = RouteShipment::query()->create([
            'route_id' => $route->id,
            'shipment_id' => $service->shipment_id,
            'delivery_order' => 1,
            'delivery_status' => 'IN_PROGRESS',
        ]);

        return [
            'service' => $service,
            'route' => $route,
            'routeShipment' => $routeShipment,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validProofData(): array
    {
        return [
            'photo_url' => 'proofs/test-delivery.jpg',
            'signature_url' => null,
            'receiver_name' => 'Juan Pérez',
            'receiver_identity_number' => null,
            'latitude' => '13.0919444',
            'longitude' => '-86.3538889',
        ];
    }

    private function assertNoCompletionChanges(
        DeliveryService $service
    ): void {
        $service->refresh();
        $service->shipment->refresh();

        $this->assertNotSame(
            'COMPLETED',
            $service->status
        );

        $this->assertNull(
            $service->completed_at
        );

        $this->assertNotSame(
            'DELIVERED',
            $service
                ->shipment
                ->shipmentStatus
                ->status_name
        );

        $this->assertNull(
            $service->shipment->delivered_at
        );

        $this->assertDatabaseCount(
            'delivery_proofs',
            0
        );

        $this->assertDatabaseCount(
            'shipment_status_history',
            0
        );
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
