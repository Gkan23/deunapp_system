<?php

namespace Tests\Feature\Services\Delivery;

use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Incident;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\Trip;
use App\Models\User;
use App\Services\Delivery\FailDeliveryAttemptService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailDeliveryAttemptServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_registers_a_failed_delivery_attempt_atomically(): void
    {
        [
            $route,
            $courier,
            $routeShipment,
            $deliveryService,
        ] = $this->createActiveAttempt();

        $incident = app(FailDeliveryAttemptService::class)->execute(
            routeShipment: $routeShipment,
            reportedBy: $courier->user,
            incidentTypeName: 'RECIPIENT_ABSENT',
            description: 'The recipient was not available at the address.'
        );

        $this->assertSame(
            'FAILED',
            $routeShipment->fresh()->delivery_status
        );

        $freshDeliveryService = $deliveryService->fresh();

        $this->assertSame(
            'ASSIGNED',
            $freshDeliveryService->status
        );

        $this->assertNull($freshDeliveryService->started_at);
        $this->assertNull($freshDeliveryService->completed_at);
        $this->assertNull($freshDeliveryService->cancelled_at);

        $this->assertSame(
            $routeShipment->shipment_id,
            $incident->shipment_id
        );

        $this->assertSame(
            $courier->user_id,
            $incident->reported_by_user_id
        );

        $this->assertSame(
            'RECIPIENT_ABSENT',
            $incident->incidentType->type_name
        );

        $this->assertSame(
            'OPEN',
            $incident->incidentStatus->status_name
        );

        $this->assertSame(
            'The recipient was not available at the address.',
            $incident->description
        );

        $this->assertNotNull($incident->reported_at);

        $this->assertDatabaseCount('incidents', 1);

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'shipment_id' => $routeShipment->shipment_id,
            'reported_by_user_id' => $courier->user_id,
            'description' => 'The recipient was not available at the address.',
        ]);

        /*
         * Reporting one failed shipment does not automatically close
         * the complete route.
         */
        $this->assertSame(
            $this->findRouteStatus('ACTIVE')->id,
            $route->fresh()->route_status_id
        );

        $this->assertFalse($courier->fresh()->is_available);
    }

    public function test_it_rejects_a_shipment_from_a_non_active_route(): void
    {
        [
            $route,
            $courier,
            $routeShipment,
            $deliveryService,
        ] = $this->createActiveAttempt();

        $route->update([
            'route_status_id' => $this->findRouteStatus('PLANNED')->id,
            'started_at' => null,
        ]);

        $this->assertDomainException(
            fn () => app(FailDeliveryAttemptService::class)->execute(
                $routeShipment,
                $courier->user,
                'DELIVERY_FAILED',
                'The delivery could not be completed.'
            ),
            'Only shipments from an active route can be marked as failed.'
        );

        $this->assertAttemptWasNotModified(
            $routeShipment,
            $deliveryService
        );
    }

    public function test_it_rejects_a_route_shipment_that_is_not_in_progress(): void
    {
        [
            $route,
            $courier,
            $routeShipment,
            $deliveryService,
        ] = $this->createActiveAttempt();

        $routeShipment->update([
            'delivery_status' => 'PENDING',
        ]);

        $this->assertDomainException(
            fn () => app(FailDeliveryAttemptService::class)->execute(
                $routeShipment,
                $courier->user,
                'DELIVERY_FAILED',
                'The delivery could not be completed.'
            ),
            'Only an in-progress route shipment can be marked as failed.'
        );

        $this->assertSame(
            'PENDING',
            $routeShipment->fresh()->delivery_status
        );

        $this->assertSame(
            'IN_PROGRESS',
            $deliveryService->fresh()->status
        );

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_it_rejects_a_shipment_without_a_delivery_service(): void
    {
        [
            $route,
            $courier,
            $routeShipment,
            $deliveryService,
        ] = $this->createActiveAttempt();

        $deliveryService->delete();

        $this->assertDomainException(
            fn () => app(FailDeliveryAttemptService::class)->execute(
                $routeShipment,
                $courier->user,
                'DELIVERY_FAILED',
                'The delivery could not be completed.'
            ),
            'The shipment does not have a delivery service.'
        );

        $this->assertSame(
            'IN_PROGRESS',
            $routeShipment->fresh()->delivery_status
        );

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_it_rejects_a_delivery_service_that_is_not_in_progress(): void
    {
        [
            $route,
            $courier,
            $routeShipment,
            $deliveryService,
        ] = $this->createActiveAttempt();

        $deliveryService->update([
            'status' => 'ASSIGNED',
            'started_at' => null,
        ]);

        $this->assertDomainException(
            fn () => app(FailDeliveryAttemptService::class)->execute(
                $routeShipment,
                $courier->user,
                'DELIVERY_FAILED',
                'The delivery could not be completed.'
            ),
            'Only an in-progress delivery service can report a failed attempt.'
        );

        $this->assertSame(
            'IN_PROGRESS',
            $routeShipment->fresh()->delivery_status
        );

        $this->assertSame(
            'ASSIGNED',
            $deliveryService->fresh()->status
        );

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_it_rejects_a_reporter_who_is_not_the_assigned_courier(): void
    {
        [
            $route,
            $courier,
            $routeShipment,
            $deliveryService,
        ] = $this->createActiveAttempt();

        $otherUser = User::factory()->create();

        $this->assertDomainException(
            fn () => app(FailDeliveryAttemptService::class)->execute(
                $routeShipment,
                $otherUser,
                'DELIVERY_FAILED',
                'The delivery could not be completed.'
            ),
            'Only the courier assigned to the route can report the failed attempt.'
        );

        $this->assertAttemptWasNotModified(
            $routeShipment,
            $deliveryService
        );
    }

    public function test_it_rejects_an_empty_incident_description(): void
    {
        [
            $route,
            $courier,
            $routeShipment,
            $deliveryService,
        ] = $this->createActiveAttempt();

        $this->assertDomainException(
            fn () => app(FailDeliveryAttemptService::class)->execute(
                $routeShipment,
                $courier->user,
                'DELIVERY_FAILED',
                '   '
            ),
            'The incident description is required.'
        );

        $this->assertAttemptWasNotModified(
            $routeShipment,
            $deliveryService
        );
    }

    public function test_it_rejects_an_incident_type_that_does_not_end_the_attempt(): void
    {
        [
            $route,
            $courier,
            $routeShipment,
            $deliveryService,
        ] = $this->createActiveAttempt();

        $this->assertDomainException(
            fn () => app(FailDeliveryAttemptService::class)->execute(
                $routeShipment,
                $courier->user,
                'DELAY',
                'The courier reported a short delay.'
            ),
            'The selected incident type cannot be used for a failed delivery attempt.'
        );

        $this->assertAttemptWasNotModified(
            $routeShipment,
            $deliveryService
        );
    }

    public function test_it_prevents_duplicate_failure_reports(): void
    {
        [
            $route,
            $courier,
            $routeShipment,
            $deliveryService,
        ] = $this->createActiveAttempt();

        $service = app(FailDeliveryAttemptService::class);

        $service->execute(
            $routeShipment,
            $courier->user,
            'DELIVERY_FAILED',
            'First failed delivery report.'
        );

        $this->assertDomainException(
            fn () => $service->execute(
                $routeShipment,
                $courier->user,
                'DELIVERY_FAILED',
                'Duplicated failed delivery report.'
            ),
            'Only an in-progress route shipment can be marked as failed.'
        );

        $this->assertDatabaseCount('incidents', 1);

        $this->assertDatabaseHas('incidents', [
            'shipment_id' => $routeShipment->shipment_id,
            'description' => 'First failed delivery report.',
        ]);

        $this->assertDatabaseMissing('incidents', [
            'description' => 'Duplicated failed delivery report.',
        ]);

        $this->assertSame(
            'FAILED',
            $routeShipment->fresh()->delivery_status
        );

        $this->assertSame(
            'ASSIGNED',
            $deliveryService->fresh()->status
        );
    }

    /**
     * @return array{
     *     0: Route,
     *     1: Courier,
     *     2: RouteShipment,
     *     3: DeliveryService
     * }
     */
    private function createActiveAttempt(): array
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
            'is_active' => true,
            'is_available' => false,
        ]);

        $shipment = Shipment::factory()->create();

        $trip = Trip::factory()->create([
            'delivery_provider_id' => $provider->id,
            'status' => 'USED',
            'used_at' => now()->subHours(2),
        ]);

        $deliveryService = DeliveryService::factory()->create([
            'shipment_id' => $shipment->id,
            'trip_id' => $trip->id,
            'status' => 'IN_PROGRESS',
            'accepted_at' => now()->subHours(2),
            'started_at' => now()->subHour(),
            'completed_at' => null,
            'cancelled_at' => null,
        ]);

        $route = Route::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' => $this->findRouteStatus('ACTIVE')->id,
            'route_date' => today(),
            'started_at' => now()->subHour(),
            'finished_at' => null,
            'estimated_distance_km' => 8.50,
        ]);

        $routeShipment = RouteShipment::query()->create([
            'route_id' => $route->id,
            'shipment_id' => $shipment->id,
            'delivery_order' => 1,
            'delivery_status' => 'IN_PROGRESS',
        ]);

        return [
            $route,
            $courier,
            $routeShipment,
            $deliveryService,
        ];
    }

    private function findRouteStatus(string $statusName): RouteStatus
    {
        return RouteStatus::query()
            ->where('status_name', $statusName)
            ->firstOrFail();
    }

    private function assertAttemptWasNotModified(
        RouteShipment $routeShipment,
        DeliveryService $deliveryService
    ): void {
        $this->assertSame(
            'IN_PROGRESS',
            $routeShipment->fresh()->delivery_status
        );

        $this->assertSame(
            'IN_PROGRESS',
            $deliveryService->fresh()->status
        );

        $this->assertNotNull(
            $deliveryService->fresh()->started_at
        );

        $this->assertDatabaseCount('incidents', 0);
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

