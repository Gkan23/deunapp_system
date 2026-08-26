<?php

namespace Tests\Feature\Policies;

use App\Models\AccountStatus;
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
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ShipmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_supported_roles_can_access_the_shipment_list(): void
    {
        $customer = Customer::factory()->create()->user;
        $provider = DeliveryProvider::factory()->create()->user;
        $courier = Courier::factory()->create()->user;
        $supportAgent = $this->userWithRole('SUPPORT_AGENT');
        $administrator = $this->userWithRole('ADMINISTRATOR');

        foreach ([
            $customer,
            $provider,
            $courier,
            $supportAgent,
            $administrator,
        ] as $user) {
            $this->assertTrue(
                Gate::forUser($user)->allows(
                    'viewAny',
                    Shipment::class
                )
            );
        }
    }

    public function test_an_inactive_user_cannot_access_shipments(): void
    {
        $customer = Customer::factory()->create();
        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $customer->user->update([
            'account_status_id' => $suspendedStatus->id,
        ]);

        $user = $customer->user->fresh();

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'viewAny',
                Shipment::class
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'view',
                $shipment
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'cancel',
                $shipment
            )
        );
    }

    public function test_a_customer_with_a_profile_can_create_shipments(): void
    {
        $customer = Customer::factory()->create();

        $this->assertTrue(
            Gate::forUser($customer->user)->allows(
                'create',
                Shipment::class
            )
        );

        $userWithoutCustomerProfile = User::factory()->customer()->create();

        $this->assertFalse(
            Gate::forUser($userWithoutCustomerProfile)->allows(
                'create',
                Shipment::class
            )
        );
    }

    public function test_a_customer_can_only_view_their_own_shipments(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $ownShipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $otherShipment = Shipment::factory()->create([
            'customer_id' => $otherCustomer->id,
        ]);

        $this->assertTrue(
            Gate::forUser($customer->user)->allows(
                'view',
                $ownShipment
            )
        );

        $this->assertFalse(
            Gate::forUser($customer->user)->allows(
                'view',
                $otherShipment
            )
        );
    }

    public function test_a_customer_can_cancel_their_own_shipment_but_cannot_operate_it(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->assertTrue(
            Gate::forUser($customer->user)->allows(
                'cancel',
                $shipment
            )
        );

        $this->assertFalse(
            Gate::forUser($customer->user)->allows(
                'updateStatus',
                $shipment
            )
        );

        $this->assertFalse(
            Gate::forUser($customer->user)->allows(
                'reportIncident',
                $shipment
            )
        );

        $this->assertFalse(
            Gate::forUser($customer->user)->allows(
                'recordDeliveryProof',
                $shipment
            )
        );
    }

    public function test_a_linked_provider_can_view_and_operate_the_shipment(): void
    {
        $shipment = Shipment::factory()->create();
        $provider = DeliveryProvider::factory()->create();

        $this->linkProviderToShipment($provider, $shipment);

        $this->assertTrue(
            Gate::forUser($provider->user)->allows(
                'view',
                $shipment
            )
        );

        $this->assertTrue(
            Gate::forUser($provider->user)->allows(
                'updateStatus',
                $shipment
            )
        );

        $this->assertTrue(
            Gate::forUser($provider->user)->allows(
                'reportIncident',
                $shipment
            )
        );

        $this->assertFalse(
            Gate::forUser($provider->user)->allows(
                'recordDeliveryProof',
                $shipment
            )
        );
    }

    public function test_an_unrelated_provider_cannot_access_the_shipment(): void
    {
        $shipment = Shipment::factory()->create();
        $linkedProvider = DeliveryProvider::factory()->create();
        $unrelatedProvider = DeliveryProvider::factory()->create();

        $this->linkProviderToShipment($linkedProvider, $shipment);

        $this->assertFalse(
            Gate::forUser($unrelatedProvider->user)->allows(
                'view',
                $shipment
            )
        );

        $this->assertFalse(
            Gate::forUser($unrelatedProvider->user)->allows(
                'updateStatus',
                $shipment
            )
        );

        $this->assertFalse(
            Gate::forUser($unrelatedProvider->user)->allows(
                'reportIncident',
                $shipment
            )
        );
    }

    public function test_an_assigned_courier_can_operate_the_shipment(): void
    {
        $shipment = Shipment::factory()->create();
        $courier = Courier::factory()->create();

        $this->assignCourierToShipment($courier, $shipment);

        $this->assertTrue(
            Gate::forUser($courier->user)->allows(
                'view',
                $shipment
            )
        );

        $this->assertTrue(
            Gate::forUser($courier->user)->allows(
                'updateStatus',
                $shipment
            )
        );

        $this->assertTrue(
            Gate::forUser($courier->user)->allows(
                'reportIncident',
                $shipment
            )
        );

        $this->assertTrue(
            Gate::forUser($courier->user)->allows(
                'recordDeliveryProof',
                $shipment
            )
        );
    }

    public function test_an_unassigned_courier_cannot_access_the_shipment(): void
    {
        $shipment = Shipment::factory()->create();
        $assignedCourier = Courier::factory()->create();
        $unassignedCourier = Courier::factory()->create();

        $this->assignCourierToShipment($assignedCourier, $shipment);

        $this->assertFalse(
            Gate::forUser($unassignedCourier->user)->allows(
                'view',
                $shipment
            )
        );

        $this->assertFalse(
            Gate::forUser($unassignedCourier->user)->allows(
                'updateStatus',
                $shipment
            )
        );

        $this->assertFalse(
            Gate::forUser($unassignedCourier->user)->allows(
                'recordDeliveryProof',
                $shipment
            )
        );
    }

    public function test_a_support_agent_can_view_and_report_but_cannot_operate_delivery(): void
    {
        $supportAgent = $this->userWithRole('SUPPORT_AGENT');
        $shipment = Shipment::factory()->create();

        $this->assertTrue(
            Gate::forUser($supportAgent)->allows(
                'view',
                $shipment
            )
        );

        $this->assertTrue(
            Gate::forUser($supportAgent)->allows(
                'reportIncident',
                $shipment
            )
        );

        $this->assertFalse(
            Gate::forUser($supportAgent)->allows(
                'updateStatus',
                $shipment
            )
        );

        $this->assertFalse(
            Gate::forUser($supportAgent)->allows(
                'cancel',
                $shipment
            )
        );

        $this->assertFalse(
            Gate::forUser($supportAgent)->allows(
                'recordDeliveryProof',
                $shipment
            )
        );
    }

    public function test_an_administrator_can_perform_shipment_domain_actions(): void
    {
        $administrator = $this->userWithRole('ADMINISTRATOR');
        $shipment = Shipment::factory()->create();

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'view',
                $shipment
            )
        );

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'cancel',
                $shipment
            )
        );

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'updateStatus',
                $shipment
            )
        );

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'reportIncident',
                $shipment
            )
        );

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'recordDeliveryProof',
                $shipment
            )
        );
    }

    public function test_direct_update_and_deletion_are_denied(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $user = $customer->user;

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'update',
                $shipment
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'delete',
                $shipment
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'restore',
                $shipment
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'forceDelete',
                $shipment
            )
        );
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

