<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Role;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryServiceQueryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_list_or_show_services(): void
    {
        $service = DeliveryService::factory()
            ->create();

        $this
            ->getJson(
                route('delivery-services.index')
            )
            ->assertUnauthorized();

        $this
            ->getJson(
                route(
                    'delivery-services.show',
                    $service
                )
            )
            ->assertUnauthorized();
    }

    public function test_a_customer_only_receives_their_services(): void
    {
        $ownService = DeliveryService::factory()
            ->create();

        $otherService = DeliveryService::factory()
            ->create();

        $response = $this
            ->actingAs(
                $ownService->customer->user
            )
            ->getJson(
                route('delivery-services.index')
            );

        $this->assertContainsOnlyService(
            $response,
            $ownService,
            $otherService
        );
    }

    public function test_a_provider_only_receives_linked_services(): void
    {
        $linkedProvider =
            DeliveryProvider::factory()->create();

        $otherProvider =
            DeliveryProvider::factory()->create();

        $linkedService = $this
            ->createServiceForProvider(
                $linkedProvider
            );

        $otherService = $this
            ->createServiceForProvider(
                $otherProvider
            );

        $response = $this
            ->actingAs($linkedProvider->user)
            ->getJson(
                route('delivery-services.index')
            );

        $this->assertContainsOnlyService(
            $response,
            $linkedService,
            $otherService
        );
    }

    public function test_a_courier_only_receives_assigned_services(): void
    {
        $firstProvider =
            DeliveryProvider::factory()->create();

        $secondProvider =
            DeliveryProvider::factory()->create();

        $assignedCourier = Courier::factory()
            ->for($firstProvider)
            ->create();

        $otherCourier = Courier::factory()
            ->for($secondProvider)
            ->create();

        $assignedService = $this
            ->createServiceForProvider(
                $firstProvider
            );

        $otherService = $this
            ->createServiceForProvider(
                $secondProvider
            );

        $this->attachServiceToCourier(
            $assignedService,
            $assignedCourier
        );

        $this->attachServiceToCourier(
            $otherService,
            $otherCourier
        );

        $response = $this
            ->actingAs($assignedCourier->user)
            ->getJson(
                route('delivery-services.index')
            );

        $this->assertContainsOnlyService(
            $response,
            $assignedService,
            $otherService
        );
    }

    public function test_support_and_administration_receive_all_services(): void
    {
        DeliveryService::factory()->count(2)->create();

        $users = [
            $this->userWithRole(
                'SUPPORT_AGENT'
            ),
            $this->userWithRole(
                'ADMINISTRATOR'
            ),
        ];

        foreach ($users as $user) {
            $this
                ->actingAs($user)
                ->getJson(
                    route('delivery-services.index')
                )
                ->assertOk()
                ->assertJsonCount(2, 'data');
        }
    }

    public function test_a_customer_can_only_show_their_service(): void
    {
        $ownService = DeliveryService::factory()
            ->create();

        $otherService = DeliveryService::factory()
            ->create();

        $this
            ->actingAs(
                $ownService->customer->user
            )
            ->getJson(
                route(
                    'delivery-services.show',
                    $ownService
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'delivery_service.id',
                $ownService->id
            );

        $this
            ->getJson(
                route(
                    'delivery-services.show',
                    $otherService
                )
            )
            ->assertForbidden();
    }

    public function test_a_provider_can_only_show_linked_services(): void
    {
        $linkedProvider =
            DeliveryProvider::factory()->create();

        $otherProvider =
            DeliveryProvider::factory()->create();

        $linkedService = $this
            ->createServiceForProvider(
                $linkedProvider
            );

        $otherService = $this
            ->createServiceForProvider(
                $otherProvider
            );

        $this
            ->actingAs($linkedProvider->user)
            ->getJson(
                route(
                    'delivery-services.show',
                    $linkedService
                )
            )
            ->assertOk();

        $this
            ->getJson(
                route(
                    'delivery-services.show',
                    $otherService
                )
            )
            ->assertForbidden();
    }

    public function test_a_courier_can_only_show_assigned_services(): void
    {
        $firstProvider =
            DeliveryProvider::factory()->create();

        $secondProvider =
            DeliveryProvider::factory()->create();

        $assignedCourier = Courier::factory()
            ->for($firstProvider)
            ->create();

        $otherCourier = Courier::factory()
            ->for($secondProvider)
            ->create();

        $assignedService = $this
            ->createServiceForProvider(
                $firstProvider
            );

        $otherService = $this
            ->createServiceForProvider(
                $secondProvider
            );

        $this->attachServiceToCourier(
            $assignedService,
            $assignedCourier
        );

        $this->attachServiceToCourier(
            $otherService,
            $otherCourier
        );

        $this
            ->actingAs($assignedCourier->user)
            ->getJson(
                route(
                    'delivery-services.show',
                    $assignedService
                )
            )
            ->assertOk();

        $this
            ->getJson(
                route(
                    'delivery-services.show',
                    $otherService
                )
            )
            ->assertForbidden();
    }

    public function test_support_and_administration_can_show_any_service(): void
    {
        $service = DeliveryService::factory()
            ->create();

        $users = [
            $this->userWithRole(
                'SUPPORT_AGENT'
            ),
            $this->userWithRole(
                'ADMINISTRATOR'
            ),
        ];

        foreach ($users as $user) {
            $this
                ->actingAs($user)
                ->getJson(
                    route(
                        'delivery-services.show',
                        $service
                    )
                )
                ->assertOk()
                ->assertJsonPath(
                    'delivery_service.id',
                    $service->id
                );
        }
    }

    private function createServiceForProvider(
        DeliveryProvider $provider
    ): DeliveryService {
        $trip = Trip::factory()
            ->for($provider)
            ->create([
                'status' => 'USED',
                'used_at' => now(),
            ]);

        return DeliveryService::factory()
            ->create([
                'trip_id' => $trip->id,
                'trip_type_id' =>
                    $trip->trip_type_id,
                'status' => 'ASSIGNED',
                'accepted_at' => now(),
            ]);
    }

    private function attachServiceToCourier(
        DeliveryService $service,
        Courier $courier
    ): void {
        $plannedStatus = RouteStatus::query()
            ->where('status_name', 'PLANNED')
            ->firstOrFail();

        $route = Route::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' =>
                $plannedStatus->id,
            'route_date' => today(),
            'started_at' => null,
            'finished_at' => null,
            'estimated_distance_km' => null,
        ]);

        RouteShipment::query()->create([
            'route_id' => $route->id,
            'shipment_id' =>
                $service->shipment_id,
            'delivery_order' => 1,
            'delivery_status' => 'PENDING',
        ]);
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

    private function assertContainsOnlyService(
        $response,
        DeliveryService $expectedService,
        DeliveryService $unexpectedService
    ): void {
        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $serviceIds = collect(
            $response->json('data')
        )->pluck('id');

        $this->assertTrue(
            $serviceIds->contains(
                $expectedService->id
            )
        );

        $this->assertFalse(
            $serviceIds->contains(
                $unexpectedService->id
            )
        );
    }
}
