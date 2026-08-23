<?php

namespace Tests\Feature\Services\Delivery;

use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Trip;
use App\Services\Delivery\AssignAvailableTripService;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignAvailableTripServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_assigns_an_available_trip_atomically(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $trip = Trip::factory()
            ->for($provider)
            ->create();

        $service = DeliveryService::factory()->create();

        $assignedService = app(
            AssignAvailableTripService::class
        )->handle($service, $provider);

        $this->assertSame(
            $trip->id,
            $assignedService->trip_id
        );

        $this->assertSame(
            'ASSIGNED',
            $assignedService->status
        );

        $this->assertNotNull(
            $assignedService->accepted_at
        );

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'delivery_provider_id' => $provider->id,
            'status' => 'USED',
        ]);

        $this->assertDatabaseHas('delivery_services', [
            'id' => $service->id,
            'trip_id' => $trip->id,
            'status' => 'ASSIGNED',
        ]);

        $this->assertDatabaseHas('trip_transactions', [
            'delivery_provider_id' => $provider->id,
            'recharge_id' => null,
            'trip_id' => $trip->id,
            'transaction_type' => 'DEBIT',
            'quantity' => 1,
        ]);
    }

    public function test_it_rejects_assignment_without_available_trips(): void
    {
        $provider = DeliveryProvider::factory()->create();
        $service = DeliveryService::factory()->create();

        $this->assertDomainException(
            'No matching trips are available for this provider.',
            fn () => app(AssignAvailableTripService::class)
                ->handle($service, $provider)
        );

        $service->refresh();

        $this->assertNull($service->trip_id);
        $this->assertSame('REQUESTED', $service->status);
        $this->assertNull($service->accepted_at);

        $this->assertDatabaseCount(
            'trip_transactions',
            0
        );
    }

    public function test_it_does_not_use_a_trip_of_another_type(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $intermunicipalTrip = Trip::factory()
            ->for($provider)
            ->intermunicipal()
            ->create();

        $localService = DeliveryService::factory()->create();

        $this->assertDomainException(
            'No matching trips are available for this provider.',
            fn () => app(AssignAvailableTripService::class)
                ->handle($localService, $provider)
        );

        $intermunicipalTrip->refresh();
        $localService->refresh();

        $this->assertSame(
            'AVAILABLE',
            $intermunicipalTrip->status
        );

        $this->assertNull(
            $intermunicipalTrip->used_at
        );

        $this->assertNull($localService->trip_id);
        $this->assertSame(
            'REQUESTED',
            $localService->status
        );

        $this->assertDatabaseCount(
            'trip_transactions',
            0
        );
    }

    public function test_it_prevents_assigning_the_same_service_twice(): void
    {
        $provider = DeliveryProvider::factory()->create();

        Trip::factory()
            ->count(2)
            ->for($provider)
            ->create();

        $service = DeliveryService::factory()->create();

        $assignmentService = app(
            AssignAvailableTripService::class
        );

        $assignmentService->handle(
            $service,
            $provider
        );

        $this->assertDomainException(
            'The delivery service is not available for assignment.',
            fn () => $assignmentService->handle(
                $service,
                $provider
            )
        );

        $service->refresh();

        $this->assertSame(
            'ASSIGNED',
            $service->status
        );

        $this->assertNotNull($service->trip_id);

        $this->assertSame(
            1,
            Trip::query()
                ->where('status', 'USED')
                ->count()
        );

        $this->assertSame(
            1,
            Trip::query()
                ->where('status', 'AVAILABLE')
                ->count()
        );

        $this->assertDatabaseCount(
            'trip_transactions',
            1
        );
    }

    public function test_it_rejects_an_inactive_provider(): void
    {
        $provider = DeliveryProvider::factory()->create([
            'is_active' => false,
        ]);

        $trip = Trip::factory()
            ->for($provider)
            ->create();

        $service = DeliveryService::factory()->create();

        $this->assertDomainException(
            'The delivery provider is inactive.',
            fn () => app(AssignAvailableTripService::class)
                ->handle($service, $provider)
        );

        $trip->refresh();
        $service->refresh();

        $this->assertSame('AVAILABLE', $trip->status);
        $this->assertNull($trip->used_at);

        $this->assertSame(
            'REQUESTED',
            $service->status
        );

        $this->assertNull($service->trip_id);

        $this->assertDatabaseCount(
            'trip_transactions',
            0
        );
    }

    public function test_it_does_not_use_a_trip_from_another_provider(): void
    {
        $requestedProvider = DeliveryProvider::factory()
            ->create();

        $otherProvider = DeliveryProvider::factory()
            ->create();

        $otherProviderTrip = Trip::factory()
            ->for($otherProvider)
            ->create();

        $service = DeliveryService::factory()->create();

        $this->assertDomainException(
            'No matching trips are available for this provider.',
            fn () => app(AssignAvailableTripService::class)
                ->handle($service, $requestedProvider)
        );

        $otherProviderTrip->refresh();
        $service->refresh();

        $this->assertSame(
            'AVAILABLE',
            $otherProviderTrip->status
        );

        $this->assertNull(
            $otherProviderTrip->used_at
        );

        $this->assertSame(
            'REQUESTED',
            $service->status
        );

        $this->assertNull($service->trip_id);

        $this->assertDatabaseCount(
            'trip_transactions',
            0
        );
    }

    public function test_it_does_not_assign_a_used_trip(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $usedTrip = Trip::factory()
            ->for($provider)
            ->create([
                'status' => 'USED',
                'used_at' => now(),
            ]);

        $service = DeliveryService::factory()->create();

        $this->assertDomainException(
            'No matching trips are available for this provider.',
            fn () => app(AssignAvailableTripService::class)
                ->handle($service, $provider)
        );

        $usedTrip->refresh();
        $service->refresh();

        $this->assertSame('USED', $usedTrip->status);
        $this->assertNotNull($usedTrip->used_at);

        $this->assertSame(
            'REQUESTED',
            $service->status
        );

        $this->assertNull($service->trip_id);

        $this->assertDatabaseCount(
            'trip_transactions',
            0
        );
    }

    public function test_it_rejects_a_customer_mismatch(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $trip = Trip::factory()
            ->for($provider)
            ->create();

        $service = DeliveryService::factory()->create();
        $differentCustomer = Customer::factory()->create();

        $service->update([
            'customer_id' => $differentCustomer->id,
        ]);

        $this->assertDomainException(
            'The service and shipment customers do not match.',
            fn () => app(AssignAvailableTripService::class)
                ->handle($service, $provider)
        );

        $trip->refresh();
        $service->refresh();

        $this->assertSame('AVAILABLE', $trip->status);
        $this->assertNull($trip->used_at);

        $this->assertSame(
            'REQUESTED',
            $service->status
        );

        $this->assertNull($service->trip_id);

        $this->assertDatabaseCount(
            'trip_transactions',
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
