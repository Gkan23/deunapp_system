<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignDeliveryServiceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_assign_a_delivery_service(): void
    {
        $service = DeliveryService::factory()
            ->create();

        $this->patchJson(
            route(
                'delivery-services.assign',
                $service
            )
        )->assertUnauthorized();

        $this->assertServiceWasNotAssigned(
            $service
        );
    }

    public function test_an_active_provider_can_assign_an_available_trip(): void
    {
        $provider = DeliveryProvider::factory()
            ->create([
                'is_active' => true,
            ]);

        $trip = Trip::factory()
            ->for($provider)
            ->create();

        $deliveryService =
            DeliveryService::factory()
                ->create();

        $response = $this
            ->actingAs($provider->user)
            ->patchJson(
                route(
                    'delivery-services.assign',
                    $deliveryService
                )
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Delivery service assigned successfully.'
            )
            ->assertJsonPath(
                'delivery_service.status',
                'ASSIGNED'
            )
            ->assertJsonPath(
                'delivery_service.trip.id',
                $trip->id
            )
            ->assertJsonPath(
                'delivery_service.trip.status',
                'USED'
            );

        $this->assertDatabaseHas(
            'delivery_services',
            [
                'id' => $deliveryService->id,
                'trip_id' => $trip->id,
                'status' => 'ASSIGNED',
            ]
        );

        $this->assertDatabaseHas(
            'trips',
            [
                'id' => $trip->id,
                'delivery_provider_id' =>
                    $provider->id,
                'status' => 'USED',
            ]
        );

        $this->assertDatabaseHas(
            'trip_transactions',
            [
                'delivery_provider_id' =>
                    $provider->id,
                'trip_id' => $trip->id,
                'transaction_type' => 'DEBIT',
                'quantity' => 1,
            ]
        );
    }

    public function test_an_inactive_provider_cannot_assign_a_service(): void
    {
        $provider = DeliveryProvider::factory()
            ->create([
                'is_active' => false,
            ]);

        $trip = Trip::factory()
            ->for($provider)
            ->create();

        $deliveryService =
            DeliveryService::factory()
                ->create();

        $this
            ->actingAs($provider->user)
            ->patchJson(
                route(
                    'delivery-services.assign',
                    $deliveryService
                )
            )
            ->assertForbidden();

        $this->assertServiceWasNotAssigned(
            $deliveryService
        );

        $this->assertSame(
            'AVAILABLE',
            $trip->fresh()->status
        );
    }

    public function test_a_customer_cannot_assign_a_delivery_service(): void
    {
        $customer = Customer::factory()->create();

        $deliveryService =
            DeliveryService::factory()
                ->create();

        $this
            ->actingAs($customer->user)
            ->patchJson(
                route(
                    'delivery-services.assign',
                    $deliveryService
                )
            )
            ->assertForbidden();

        $this->assertServiceWasNotAssigned(
            $deliveryService
        );
    }

    public function test_an_administrator_can_assign_for_a_provider(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $provider = DeliveryProvider::factory()
            ->create([
                'is_active' => true,
            ]);

        $trip = Trip::factory()
            ->for($provider)
            ->create();

        $deliveryService =
            DeliveryService::factory()
                ->create();

        $this
            ->actingAs($administrator)
            ->patchJson(
                route(
                    'delivery-services.assign',
                    $deliveryService
                ),
                [
                    'delivery_provider_id' =>
                        $provider->id,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'delivery_service.status',
                'ASSIGNED'
            )
            ->assertJsonPath(
                'delivery_service.trip.id',
                $trip->id
            );

        $this->assertDatabaseHas(
            'trip_transactions',
            [
                'delivery_provider_id' =>
                    $provider->id,
                'trip_id' => $trip->id,
                'transaction_type' => 'DEBIT',
            ]
        );
    }

    public function test_an_administrator_must_select_a_provider(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $deliveryService =
            DeliveryService::factory()
                ->create();

        $this
            ->actingAs($administrator)
            ->patchJson(
                route(
                    'delivery-services.assign',
                    $deliveryService
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'delivery_provider_id',
            ]);

        $this->assertServiceWasNotAssigned(
            $deliveryService
        );
    }

    public function test_missing_available_trips_returns_a_domain_error(): void
    {
        $provider = DeliveryProvider::factory()
            ->create([
                'is_active' => true,
            ]);

        $deliveryService =
            DeliveryService::factory()
                ->create();

        $this
            ->actingAs($provider->user)
            ->patchJson(
                route(
                    'delivery-services.assign',
                    $deliveryService
                )
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'No matching trips are available for this provider.'
            );

        $this->assertServiceWasNotAssigned(
            $deliveryService
        );
    }

    public function test_the_same_service_cannot_be_assigned_twice(): void
    {
        $provider = DeliveryProvider::factory()
            ->create([
                'is_active' => true,
            ]);

        Trip::factory()
            ->count(2)
            ->for($provider)
            ->create();

        $deliveryService =
            DeliveryService::factory()
                ->create();

        $url = route(
            'delivery-services.assign',
            $deliveryService
        );

        $this
            ->actingAs($provider->user)
            ->patchJson($url)
            ->assertOk();

        $this
            ->patchJson($url)
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The delivery service is not available for assignment.'
            );

        $this->assertDatabaseCount(
            'trip_transactions',
            1
        );

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

    private function assertServiceWasNotAssigned(
        DeliveryService $deliveryService
    ): void {
        $deliveryService->refresh();

        $this->assertSame(
            'REQUESTED',
            $deliveryService->status
        );

        $this->assertNull(
            $deliveryService->trip_id
        );

        $this->assertNull(
            $deliveryService->accepted_at
        );

        $this->assertDatabaseCount(
            'trip_transactions',
            0
        );
    }
}