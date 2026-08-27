<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Role;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_update_or_cancel_shipments(): void
    {
        $shipment = Shipment::factory()->create();

        $pickedUp = $this->shipmentStatus(
            'PICKED_UP'
        );

        $this->patchJson(
            route(
                'shipments.status.update',
                $shipment
            ),
            [
                'shipment_status_id' =>
                    $pickedUp->id,
            ]
        )->assertUnauthorized();

        $this->patchJson(
            route(
                'shipments.cancel',
                $shipment
            ),
            [
                'comment' =>
                    'Cancellation requested.',
            ]
        )->assertUnauthorized();
    }

    public function test_a_linked_provider_can_update_the_status(): void
    {
        $shipment = Shipment::factory()->create();

        $provider =
            DeliveryProvider::factory()->create();

        $this->linkProviderToShipment(
            $provider,
            $shipment
        );

        $pickedUp = $this->shipmentStatus(
            'PICKED_UP'
        );

        $response = $this
            ->actingAs($provider->user)
            ->patchJson(
                route(
                    'shipments.status.update',
                    $shipment
                ),
                [
                    'shipment_status_id' =>
                        $pickedUp->id,

                    'comment' =>
                        'Package collected by provider.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Shipment status updated successfully.'
            )
            ->assertJsonPath(
                'shipment.shipment_status.status_name',
                'PICKED_UP'
            );

        $this->assertDatabaseHas(
            'shipment_status_history',
            [
                'shipment_id' => $shipment->id,

                'shipment_status_id' =>
                    $pickedUp->id,

                'changed_by_user_id' =>
                    $provider->user_id,

                'comment' =>
                    'Package collected by provider.',
            ]
        );
    }

    public function test_an_assigned_courier_can_update_the_status(): void
    {
        $shipment = Shipment::factory()->create();
        $courier = Courier::factory()->create();

        $this->assignCourierToShipment(
            $courier,
            $shipment
        );

        $pickedUp = $this->shipmentStatus(
            'PICKED_UP'
        );

        $response = $this
            ->actingAs($courier->user)
            ->patchJson(
                route(
                    'shipments.status.update',
                    $shipment
                ),
                [
                    'shipment_status_id' =>
                        $pickedUp->id,

                    'comment' =>
                        'Courier collected the package.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Shipment status updated successfully.'
            )
            ->assertJsonPath(
                'shipment.shipment_status.status_name',
                'PICKED_UP'
            );

        $this->assertDatabaseHas(
            'shipment_status_history',
            [
                'shipment_id' => $shipment->id,

                'shipment_status_id' =>
                    $pickedUp->id,

                'changed_by_user_id' =>
                    $courier->user_id,

                'comment' =>
                    'Courier collected the package.',
            ]
        );
    }

    public function test_unrelated_users_cannot_update_the_status(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $unrelatedProvider =
            DeliveryProvider::factory()->create();

        $unassignedCourier =
            Courier::factory()->create();

        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $pickedUp = $this->shipmentStatus(
            'PICKED_UP'
        );

        $users = [
            $customer->user,
            $unrelatedProvider->user,
            $unassignedCourier->user,
            $supportAgent,
        ];

        foreach ($users as $user) {
            $this
                ->actingAs($user)
                ->patchJson(
                    route(
                        'shipments.status.update',
                        $shipment
                    ),
                    [
                        'shipment_status_id' =>
                            $pickedUp->id,
                    ]
                )
                ->assertForbidden();
        }

        $shipment->refresh();

        $this->assertSame(
            'REQUESTED',
            $shipment->shipmentStatus->status_name
        );
    }

    public function test_the_owner_can_cancel_the_shipment(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $response = $this
            ->actingAs($customer->user)
            ->patchJson(
                route(
                    'shipments.cancel',
                    $shipment
                ),
                [
                    'comment' =>
                        'Customer no longer needs delivery.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Shipment cancelled successfully.'
            )
            ->assertJsonPath(
                'shipment.shipment_status.status_name',
                'CANCELLED'
            );

        $cancelled = $this->shipmentStatus(
            'CANCELLED'
        );

        $this->assertDatabaseHas(
            'shipment_status_history',
            [
                'shipment_id' => $shipment->id,

                'shipment_status_id' =>
                    $cancelled->id,

                'changed_by_user_id' =>
                    $customer->user_id,

                'comment' =>
                    'Customer no longer needs delivery.',
            ]
        );
    }

    public function test_unrelated_users_cannot_cancel_the_shipment(): void
    {
        $customer = Customer::factory()->create();

        $otherCustomer =
            Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $provider =
            DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create();

        $users = [
            $otherCustomer->user,
            $provider->user,
            $courier->user,
        ];

        foreach ($users as $user) {
            $this
                ->actingAs($user)
                ->patchJson(
                    route(
                        'shipments.cancel',
                        $shipment
                    ),
                    [
                        'comment' =>
                            'Unauthorized cancellation.',
                    ]
                )
                ->assertForbidden();
        }

        $shipment->refresh();

        $this->assertSame(
            'REQUESTED',
            $shipment->shipmentStatus->status_name
        );
    }

    public function test_an_administrator_can_update_and_cancel_shipments(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $shipmentToUpdate =
            Shipment::factory()->create();

        $shipmentToCancel =
            Shipment::factory()->create();

        $pickedUp = $this->shipmentStatus(
            'PICKED_UP'
        );

        $this
            ->actingAs($administrator)
            ->patchJson(
                route(
                    'shipments.status.update',
                    $shipmentToUpdate
                ),
                [
                    'shipment_status_id' =>
                        $pickedUp->id,

                    'comment' =>
                        'Administrative status update.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'shipment.shipment_status.status_name',
                'PICKED_UP'
            );

        $this
            ->actingAs($administrator)
            ->patchJson(
                route(
                    'shipments.cancel',
                    $shipmentToCancel
                ),
                [
                    'comment' =>
                        'Administrative cancellation.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'shipment.shipment_status.status_name',
                'CANCELLED'
            );

        $cancelled = $this->shipmentStatus(
            'CANCELLED'
        );

        $this->assertDatabaseHas(
            'shipment_status_history',
            [
                'shipment_id' =>
                    $shipmentToUpdate->id,

                'shipment_status_id' =>
                    $pickedUp->id,

                'changed_by_user_id' =>
                    $administrator->id,
            ]
        );

        $this->assertDatabaseHas(
            'shipment_status_history',
            [
                'shipment_id' =>
                    $shipmentToCancel->id,

                'shipment_status_id' =>
                    $cancelled->id,

                'changed_by_user_id' =>
                    $administrator->id,
            ]
        );
    }

    public function test_invalid_transitions_return_a_validation_response(): void
    {
        $shipment = Shipment::factory()->create();

        $provider =
            DeliveryProvider::factory()->create();

        $this->linkProviderToShipment(
            $provider,
            $shipment
        );

        /*
         * No se permite pasar directamente desde
         * REQUESTED hasta IN_TRANSIT.
         */
        $inTransit = $this->shipmentStatus(
            'IN_TRANSIT'
        );

        $response = $this
            ->actingAs($provider->user)
            ->patchJson(
                route(
                    'shipments.status.update',
                    $shipment
                ),
                [
                    'shipment_status_id' =>
                        $inTransit->id,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The transition from REQUESTED to IN_TRANSIT is not allowed.'
            );

        $shipment->refresh();

        $this->assertSame(
            'REQUESTED',
            $shipment->shipmentStatus->status_name
        );

        $this->assertDatabaseCount(
            'shipment_status_history',
            0
        );
    }

    public function test_nonexistent_statuses_are_rejected(): void
    {
        $shipment = Shipment::factory()->create();

        $provider =
            DeliveryProvider::factory()->create();

        $this->linkProviderToShipment(
            $provider,
            $shipment
        );

        $response = $this
            ->actingAs($provider->user)
            ->patchJson(
                route(
                    'shipments.status.update',
                    $shipment
                ),
                [
                    'shipment_status_id' => 999999,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'shipment_status_id',
            ]);

        $shipment->refresh();

        $this->assertSame(
            'REQUESTED',
            $shipment->shipmentStatus->status_name
        );

        $this->assertDatabaseCount(
            'shipment_status_history',
            0
        );
    }

    /**
     * Busca un estado del catálogo.
     *
     * Se llama shipmentStatus y no status porque
     * PHPUnit ya contiene un método final status().
     */
    private function shipmentStatus(
        string $statusName
    ): ShipmentStatus {
        return ShipmentStatus::query()
            ->where(
                'status_name',
                $statusName
            )
            ->firstOrFail();
    }

    /**
     * Relaciona el proveedor mediante:
     *
     * Shipment → DeliveryService → Trip
     * → DeliveryProvider.
     */
    private function linkProviderToShipment(
        DeliveryProvider $provider,
        Shipment $shipment
    ): void {
        $trip = Trip::factory()->create([
            'delivery_provider_id' => $provider->id,
            'status' => 'USED',
            'used_at' => now(),
        ]);

        DeliveryService::factory()->create([
            'shipment_id' => $shipment->id,
            'trip_id' => $trip->id,
            'trip_type_id' => $trip->trip_type_id,
            'status' => 'ASSIGNED',
            'accepted_at' => now(),
        ]);
    }

    /**
     * Relaciona el repartidor mediante:
     *
     * Shipment → RouteShipment → Route → Courier.
     */
    private function assignCourierToShipment(
        Courier $courier,
        Shipment $shipment
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
            'shipment_id' => $shipment->id,
            'delivery_order' => 1,
            'delivery_status' => 'PENDING',
        ]);
    }

    /**
     * Crea un usuario administrativo o de soporte.
     */
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
}


