<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Role;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateRouteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_create_a_route(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $shipment = $this
            ->createAssignedShipment($provider);

        $this->postJson(
            route('routes.store'),
            $this->validPayload(
                $courier,
                [$shipment]
            )
        )->assertUnauthorized();

        $this->assertNoRoutesCreated();
    }

    public function test_a_provider_can_create_a_route_for_their_courier(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $firstShipment = $this
            ->createAssignedShipment($provider);

        $secondShipment = $this
            ->createAssignedShipment($provider);

        $response = $this
            ->actingAs($provider->user)
            ->postJson(
                route('routes.store'),
                $this->validPayload(
                    $courier,
                    [
                        $secondShipment,
                        $firstShipment,
                    ]
                )
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Route created successfully.'
            )
            ->assertJsonPath(
                'route.courier_id',
                $courier->id
            );

        $routeId = $response->json('route.id');

        $plannedStatus = RouteStatus::query()
            ->where('status_name', 'PLANNED')
            ->firstOrFail();

        $this->assertDatabaseHas('routes', [
            'id' => $routeId,
            'courier_id' => $courier->id,
            'route_status_id' =>
                $plannedStatus->id,
        ]);

        /*
         * Comprueba que se conservó el orden recibido.
         */
        $this->assertDatabaseHas(
            'route_shipments',
            [
                'route_id' => $routeId,
                'shipment_id' =>
                    $secondShipment->id,
                'delivery_order' => 1,
                'delivery_status' => 'PENDING',
            ]
        );

        $this->assertDatabaseHas(
            'route_shipments',
            [
                'route_id' => $routeId,
                'shipment_id' =>
                    $firstShipment->id,
                'delivery_order' => 2,
                'delivery_status' => 'PENDING',
            ]
        );
    }

    public function test_a_provider_cannot_use_another_providers_courier(): void
    {
        $authenticatedProvider =
            DeliveryProvider::factory()->create();

        $otherProvider =
            DeliveryProvider::factory()->create();

        $otherCourier = Courier::factory()
            ->for($otherProvider)
            ->create();

        $shipment = $this
            ->createAssignedShipment(
                $otherProvider
            );

        $this
            ->actingAs(
                $authenticatedProvider->user
            )
            ->postJson(
                route('routes.store'),
                $this->validPayload(
                    $otherCourier,
                    [$shipment]
                )
            )
            ->assertForbidden();

        $this->assertNoRoutesCreated();
    }

    public function test_a_customer_cannot_create_a_route(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $shipment = $this
            ->createAssignedShipment($provider);

        $customer = Customer::factory()->create();

        $this
            ->actingAs($customer->user)
            ->postJson(
                route('routes.store'),
                $this->validPayload(
                    $courier,
                    [$shipment]
                )
            )
            ->assertForbidden();

        $this->assertNoRoutesCreated();
    }

    public function test_an_administrator_can_create_a_route(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $shipment = $this
            ->createAssignedShipment($provider);

        $this
            ->actingAs($administrator)
            ->postJson(
                route('routes.store'),
                $this->validPayload(
                    $courier,
                    [$shipment]
                )
            )
            ->assertCreated()
            ->assertJsonPath(
                'route.courier_id',
                $courier->id
            );

        $this->assertDatabaseCount('routes', 1);
        $this->assertDatabaseCount(
            'route_shipments',
            1
        );
    }

    public function test_at_least_one_shipment_is_required(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $payload = $this->validPayload(
            $courier,
            []
        );

        $this
            ->actingAs($provider->user)
            ->postJson(
                route('routes.store'),
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'shipment_ids',
            ]);

        $this->assertNoRoutesCreated();
    }

    public function test_an_inactive_courier_returns_a_domain_error(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create([
                'is_active' => false,
            ]);

        $shipment = $this
            ->createAssignedShipment($provider);

        $this
            ->actingAs($provider->user)
            ->postJson(
                route('routes.store'),
                $this->validPayload(
                    $courier,
                    [$shipment]
                )
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The courier is inactive.'
            );

        $this->assertNoRoutesCreated();
    }

    public function test_a_past_route_date_is_rejected(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $shipment = $this
            ->createAssignedShipment($provider);

        $payload = $this->validPayload(
            $courier,
            [$shipment]
        );

        $payload['route_date'] = today()
            ->subDay()
            ->toDateString();

        $this
            ->actingAs($provider->user)
            ->postJson(
                route('routes.store'),
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'route_date',
            ]);

        $this->assertNoRoutesCreated();
    }

    public function test_shipment_trips_must_match_the_courier_provider(): void
    {
        $courierProvider =
            DeliveryProvider::factory()->create();

        $tripProvider =
            DeliveryProvider::factory()->create();

        $courier = Courier::factory()
            ->for($courierProvider)
            ->create();

        $shipment = $this
            ->createAssignedShipment(
                $tripProvider
            );

        $this
            ->actingAs($courierProvider->user)
            ->postJson(
                route('routes.store'),
                $this->validPayload(
                    $courier,
                    [$shipment]
                )
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'All shipment trips must belong to the courier provider.'
            );

        $this->assertNoRoutesCreated();
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

        $deliveryService =
            DeliveryService::factory()->create([
                'trip_id' => $trip->id,
                'status' => 'ASSIGNED',
                'accepted_at' => now(),
            ]);

        return $deliveryService->shipment;
    }

    /**
     * @param array<int, Shipment> $shipments
     * @return array<string, mixed>
     */
    private function validPayload(
        Courier $courier,
        array $shipments
    ): array {
        return [
            'courier_id' => $courier->id,
            'shipment_ids' => collect($shipments)
                ->map(
                    fn (Shipment $shipment): int =>
                        $shipment->id
                )
                ->values()
                ->all(),
            'route_date' =>
                today()->toDateString(),
            'estimated_distance_km' => 10.50,
        ];
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

    private function assertNoRoutesCreated(): void
    {
        $this->assertDatabaseCount('routes', 0);

        $this->assertDatabaseCount(
            'route_shipments',
            0
        );
    }
}