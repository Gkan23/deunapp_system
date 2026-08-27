<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Role;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\ShipmentStatus;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteDeliveryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_complete_a_delivery(): void
    {
        $scenario = $this->createScenario();

        $this->patchJson(
            route(
                'delivery-services.complete',
                $scenario['service']
            ),
            $this->validPayload()
        )->assertUnauthorized();

        $this->assertDatabaseCount(
            'delivery_proofs',
            0
        );
    }

    public function test_the_assigned_courier_can_complete_the_delivery(): void
    {
        $scenario = $this->createScenario();

        $response = $this
            ->actingAs($scenario['courier']->user)
            ->patchJson(
                route(
                    'delivery-services.complete',
                    $scenario['service']
                ),
                $this->validPayload()
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Delivery completed successfully.'
            )
            ->assertJsonPath(
                'delivery_service.status',
                'COMPLETED'
            )
            ->assertJsonPath(
                'delivery_service.shipment.'
                    .'shipment_status.status_name',
                'DELIVERED'
            )
            ->assertJsonPath(
                'delivery_service.shipment.'
                    .'delivery_proof.receiver_name',
                'Juan Perez'
            );

        $this->assertDatabaseHas(
            'delivery_proofs',
            [
                'shipment_id' =>
                    $scenario['service']->shipment_id,
                'receiver_name' => 'Juan Perez',
                'photo_url' =>
                    'proofs/test-delivery.jpg',
            ]
        );

        $this->assertDatabaseHas(
            'delivery_services',
            [
                'id' => $scenario['service']->id,
                'status' => 'COMPLETED',
            ]
        );

        $this->assertDatabaseHas(
            'route_shipments',
            [
                'id' =>
                    $scenario['routeShipment']->id,
                'delivery_status' => 'DELIVERED',
            ]
        );

        $this->assertDatabaseHas(
            'shipment_status_history',
            [
                'shipment_id' =>
                    $scenario['service']->shipment_id,
                'changed_by_user_id' =>
                    $scenario['courier']->user->id,
                'comment' =>
                    'Entrega confirmada correctamente.',
            ]
        );
    }

    public function test_an_unassigned_courier_cannot_complete_the_delivery(): void
    {
        $scenario = $this->createScenario();

        $unassignedCourier = Courier::factory()
            ->create();

        $this
            ->actingAs($unassignedCourier->user)
            ->patchJson(
                route(
                    'delivery-services.complete',
                    $scenario['service']
                ),
                $this->validPayload()
            )
            ->assertForbidden();

        $this->assertNoCompletionChanges(
            $scenario['service']
        );
    }

    public function test_a_provider_cannot_record_courier_delivery_proof(): void
    {
        $scenario = $this->createScenario();

        $this
            ->actingAs($scenario['provider']->user)
            ->patchJson(
                route(
                    'delivery-services.complete',
                    $scenario['service']
                ),
                $this->validPayload()
            )
            ->assertForbidden();

        $this->assertNoCompletionChanges(
            $scenario['service']
        );
    }

    public function test_an_administrator_can_complete_a_delivery(): void
    {
        $scenario = $this->createScenario();

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this
            ->actingAs($administrator)
            ->patchJson(
                route(
                    'delivery-services.complete',
                    $scenario['service']
                ),
                $this->validPayload()
            )
            ->assertOk()
            ->assertJsonPath(
                'delivery_service.status',
                'COMPLETED'
            )
            ->assertJsonPath(
                'delivery_service.shipment.'
                    .'shipment_status.status_name',
                'DELIVERED'
            );

        $this->assertDatabaseHas(
            'delivery_proofs',
            [
                'shipment_id' =>
                    $scenario['service']->shipment_id,
            ]
        );
    }

    public function test_delivery_evidence_is_required(): void
    {
        $scenario = $this->createScenario();

        $payload = $this->validPayload();

        $payload['photo_url'] = null;
        $payload['signature_url'] = null;
        $payload['receiver_identity_number'] = null;

        $this
            ->actingAs($scenario['courier']->user)
            ->patchJson(
                route(
                    'delivery-services.complete',
                    $scenario['service']
                ),
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'delivery_evidence',
            ]);

        $this->assertNoCompletionChanges(
            $scenario['service']
        );
    }

    public function test_domain_errors_are_returned_as_unprocessable(): void
    {
        $scenario = $this->createScenario();

        $scenario['service']->update([
            'status' => 'ASSIGNED',
            'started_at' => null,
        ]);

        $this
            ->actingAs($scenario['courier']->user)
            ->patchJson(
                route(
                    'delivery-services.complete',
                    $scenario['service']
                ),
                $this->validPayload()
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The delivery service is not in progress.'
            );

        $this->assertNoCompletionChanges(
            $scenario['service']
        );
    }

    /**
     * @return array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     service: DeliveryService,
     *     route: Route,
     *     routeShipment: RouteShipment
     * }
     */
    private function createScenario(): array
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $trip = Trip::factory()
            ->for($provider)
            ->create([
                'status' => 'USED',
                'used_at' => now(),
            ]);

        $service = DeliveryService::factory()
            ->create([
                'trip_id' => $trip->id,
                'status' => 'IN_PROGRESS',
                'accepted_at' =>
                    now()->subMinutes(10),
                'started_at' =>
                    now()->subMinutes(5),
            ]);

        $outForDelivery = ShipmentStatus::query()
            ->where(
                'status_name',
                'OUT_FOR_DELIVERY'
            )
            ->firstOrFail();

        $service->shipment->update([
            'shipment_status_id' =>
                $outForDelivery->id,
        ]);

        $activeRouteStatus = RouteStatus::query()
            ->where('status_name', 'ACTIVE')
            ->firstOrFail();

        $route = Route::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' =>
                $activeRouteStatus->id,
            'route_date' => today(),
            'started_at' =>
                now()->subMinutes(20),
            'finished_at' => null,
            'estimated_distance_km' => '8.50',
        ]);

        $routeShipment = RouteShipment::query()
            ->create([
                'route_id' => $route->id,
                'shipment_id' =>
                    $service->shipment_id,
                'delivery_order' => 1,
                'delivery_status' =>
                    'IN_PROGRESS',
            ]);

        return [
            'provider' => $provider,
            'courier' => $courier,
            'service' => $service,
            'route' => $route,
            'routeShipment' => $routeShipment,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'photo_url' =>
                'proofs/test-delivery.jpg',
            'signature_url' => null,
            'receiver_name' => 'Juan Perez',
            'receiver_identity_number' => null,
            'latitude' => '13.0919444',
            'longitude' => '-86.3538889',
            'comment' =>
                'Entrega confirmada correctamente.',
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
}