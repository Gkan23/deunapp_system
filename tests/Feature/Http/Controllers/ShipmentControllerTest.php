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
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_create_shipments(): void
    {
        $response = $this->postJson(
            route('shipments.store'),
            $this->validData()
        );

        $response->assertUnauthorized();
    }

    public function test_a_non_customer_cannot_create_shipments(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $response = $this
            ->actingAs($provider->user)
            ->postJson(
                route('shipments.store'),
                $this->validData()
            );

        $response->assertForbidden();
    }

    public function test_a_customer_can_create_a_shipment(): void
    {
        $customer = Customer::factory()->create();

        $response = $this
            ->actingAs($customer->user)
            ->postJson(
                route('shipments.store'),
                $this->validData()
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Shipment created successfully.'
            )
            ->assertJsonPath(
                'shipment.customer_id',
                $customer->id
            )
            ->assertJsonPath(
                'shipment.shipment_status.status_name',
                'REQUESTED'
            )
            ->assertJsonCount(
                1,
                'shipment.packages'
            );

        $shipmentId = $response->json(
            'shipment.id'
        );

        $this->assertDatabaseHas('shipments', [
            'id' => $shipmentId,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_invalid_shipment_data_is_rejected(): void
    {
        $customer = Customer::factory()->create();
        $data = $this->validData();
        $data['packages'] = [];

        $shipmentCount = Shipment::query()->count();

        $response = $this
            ->actingAs($customer->user)
            ->postJson(
                route('shipments.store'),
                $data
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'packages',
            ]);

        $this->assertDatabaseCount(
            'shipments',
            $shipmentCount
        );
    }

    public function test_customer_id_cannot_be_spoofed(): void
    {
        $authenticatedCustomer =
            Customer::factory()->create();

        $otherCustomer =
            Customer::factory()->create();

        $data = $this->validData();
        $data['customer_id'] = $otherCustomer->id;

        $response = $this
            ->actingAs($authenticatedCustomer->user)
            ->postJson(
                route('shipments.store'),
                $data
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'shipment.customer_id',
                $authenticatedCustomer->id
            );

        $shipmentId = $response->json(
            'shipment.id'
        );

        $this->assertDatabaseMissing('shipments', [
            'id' => $shipmentId,
            'customer_id' => $otherCustomer->id,
        ]);
    }

    public function test_a_guest_cannot_view_shipments(): void
    {
        $shipment = Shipment::factory()->create();

        $this->getJson(
            route('shipments.index')
        )->assertUnauthorized();

        $this->getJson(
            route('shipments.show', $shipment)
        )->assertUnauthorized();
    }

    public function test_a_customer_only_receives_their_shipments(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $ownShipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        Shipment::factory()->create([
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this
            ->actingAs($customer->user)
            ->getJson(route('shipments.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $shipmentIds = collect(
            $response->json('data')
        )->pluck('id')->all();

        $this->assertSame(
            [$ownShipment->id],
            $shipmentIds
        );
    }

    public function test_a_provider_only_receives_linked_shipments(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $linkedShipment = Shipment::factory()->create();
        Shipment::factory()->create();

        $this->linkProviderToShipment(
            $provider,
            $linkedShipment
        );

        $response = $this
            ->actingAs($provider->user)
            ->getJson(route('shipments.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $shipmentIds = collect(
            $response->json('data')
        )->pluck('id')->all();

        $this->assertSame(
            [$linkedShipment->id],
            $shipmentIds
        );
    }

    public function test_a_courier_only_receives_assigned_shipments(): void
    {
        $courier = Courier::factory()->create();

        $assignedShipment = Shipment::factory()->create();
        Shipment::factory()->create();

        $this->assignCourierToShipment(
            $courier,
            $assignedShipment
        );

        $response = $this
            ->actingAs($courier->user)
            ->getJson(route('shipments.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $shipmentIds = collect(
            $response->json('data')
        )->pluck('id')->all();

        $this->assertSame(
            [$assignedShipment->id],
            $shipmentIds
        );
    }

    public function test_support_and_administration_receive_all_shipments(): void
    {
        $firstShipment = Shipment::factory()->create();
        $secondShipment = Shipment::factory()->create();

        $users = [
            $this->userWithRole('SUPPORT_AGENT'),
            $this->userWithRole('ADMINISTRATOR'),
        ];

        foreach ($users as $user) {
            $response = $this
                ->actingAs($user)
                ->getJson(route('shipments.index'));

            $response
                ->assertOk()
                ->assertJsonCount(2, 'data');

            $shipmentIds = collect(
                $response->json('data')
            )->pluck('id');

            $this->assertTrue(
                $shipmentIds->contains(
                    $firstShipment->id
                )
            );

            $this->assertTrue(
                $shipmentIds->contains(
                    $secondShipment->id
                )
            );
        }
    }

    public function test_a_customer_can_show_only_their_shipment(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $ownShipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $otherShipment = Shipment::factory()->create([
            'customer_id' => $otherCustomer->id,
        ]);

        $this
            ->actingAs($customer->user)
            ->getJson(
                route('shipments.show', $ownShipment)
            )
            ->assertOk()
            ->assertJsonPath(
                'shipment.id',
                $ownShipment->id
            );

        $this
            ->actingAs($customer->user)
            ->getJson(
                route('shipments.show', $otherShipment)
            )
            ->assertForbidden();
    }

    public function test_provider_and_courier_can_only_show_related_shipments(): void
    {
        $provider = DeliveryProvider::factory()->create();
        $unrelatedProvider =
            DeliveryProvider::factory()->create();

        $providerShipment =
            Shipment::factory()->create();

        $this->linkProviderToShipment(
            $provider,
            $providerShipment
        );

        $this
            ->actingAs($provider->user)
            ->getJson(
                route('shipments.show', $providerShipment)
            )
            ->assertOk();

        $this
            ->actingAs($unrelatedProvider->user)
            ->getJson(
                route('shipments.show', $providerShipment)
            )
            ->assertForbidden();

        $courier = Courier::factory()->create();
        $unassignedCourier = Courier::factory()->create();

        $courierShipment =
            Shipment::factory()->create();

        $this->assignCourierToShipment(
            $courier,
            $courierShipment
        );

        $this
            ->actingAs($courier->user)
            ->getJson(
                route('shipments.show', $courierShipment)
            )
            ->assertOk();

        $this
            ->actingAs($unassignedCourier->user)
            ->getJson(
                route('shipments.show', $courierShipment)
            )
            ->assertForbidden();
    }

    public function test_support_and_administration_can_show_any_shipment(): void
    {
        $shipment = Shipment::factory()->create();

        $users = [
            $this->userWithRole('SUPPORT_AGENT'),
            $this->userWithRole('ADMINISTRATOR'),
        ];

        foreach ($users as $user) {
            $this
                ->actingAs($user)
                ->getJson(
                    route('shipments.show', $shipment)
                )
                ->assertOk()
                ->assertJsonPath(
                    'shipment.id',
                    $shipment->id
                );
        }
    }

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

    private function assignCourierToShipment(
        Courier $courier,
        Shipment $shipment
    ): void {
        $plannedStatus = RouteStatus::query()
            ->where('status_name', 'PLANNED')
            ->firstOrFail();

        $route = Route::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' => $plannedStatus->id,
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
     * @return array<string, mixed>
     */
    private function validData(): array
    {
        $referenceShipment =
            Shipment::factory()->create();

        return [
            'sender_id' =>
                $referenceShipment->sender_id,

            'recipient_id' =>
                $referenceShipment->recipient_id,

            'origin_address_id' =>
                $referenceShipment->origin_address_id,

            'destination_address_id' =>
                $referenceShipment
                    ->destination_address_id,

            'origin_branch_id' => null,
            'destination_branch_id' => null,

            'scheduled_at' => now()
                ->addDay()
                ->toDateTimeString(),

            'declared_value' => 150.50,

            'delivery_instructions' =>
                'Call the recipient before delivery.',

            'notes' => null,

            'packages' => [
                [
                    'weight' => 2.50,
                    'height' => 20,
                    'width' => 15,
                    'length' => 30,
                    'content_description' =>
                        'Books and documents',
                    'is_fragile' => false,
                    'declared_value' => 150.50,
                ],
            ],
        ];
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::query()
            ->where('role_name', $roleName)
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}


