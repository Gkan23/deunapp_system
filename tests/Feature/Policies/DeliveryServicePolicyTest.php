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

class DeliveryServicePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_supported_roles_can_access_the_service_list(): void
    {
        $users = [
            Customer::factory()->create()->user,
            DeliveryProvider::factory()->create()->user,
            Courier::factory()->create()->user,
            $this->userWithRole('SUPPORT_AGENT'),
            $this->userWithRole('ADMINISTRATOR'),
        ];

        foreach ($users as $user) {
            $this->assertTrue(
                $this->allows(
                    $user,
                    'viewAny',
                    DeliveryService::class
                )
            );
        }
    }

    public function test_an_inactive_user_cannot_access_delivery_services(): void
    {
        $customer = Customer::factory()->create();
        $service = $this->serviceFor($customer);

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $customer->user->update([
            'account_status_id' => $suspendedStatus->id,
        ]);

        $user = $customer->user->fresh();

        $this->assertFalse(
            $this->allows($user, 'view', $service)
        );

        $this->assertFalse(
            $this->allows($user, 'cancel', $service)
        );

        $this->assertFalse(
            $this->allows($user, 'rate', $service)
        );
    }

    public function test_a_customer_with_a_profile_can_create_services(): void
    {
        $customer = Customer::factory()->create();
        $userWithoutProfile = User::factory()->customer()->create();

        $this->assertTrue(
            $this->allows(
                $customer->user,
                'create',
                DeliveryService::class
            )
        );

        $this->assertFalse(
            $this->allows(
                $userWithoutProfile,
                'create',
                DeliveryService::class
            )
        );
    }

    public function test_a_customer_can_operate_their_own_service(): void
    {
        $customer = Customer::factory()->create();
        $service = $this->serviceFor($customer);
        $user = $customer->user;

        $this->assertTrue(
            $this->allows($user, 'view', $service)
        );

        $this->assertTrue(
            $this->allows($user, 'cancel', $service)
        );

        $this->assertTrue(
            $this->allows($user, 'confirmPayment', $service)
        );

        $this->assertTrue(
            $this->allows($user, 'rate', $service)
        );

        $this->assertFalse(
            $this->allows($user, 'assign', $service)
        );

        $this->assertFalse(
            $this->allows($user, 'start', $service)
        );

        $this->assertFalse(
            $this->allows($user, 'complete', $service)
        );
    }

    public function test_a_customer_cannot_access_another_customers_service(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $service = $this->serviceFor($otherCustomer);

        $user = $customer->user;

        foreach ([
            'view',
            'cancel',
            'confirmPayment',
            'rate',
        ] as $ability) {
            $this->assertFalse(
                $this->allows($user, $ability, $service)
            );
        }
    }

    public function test_only_an_active_provider_can_assign_services(): void
    {
        $service = $this->serviceFor(
            Customer::factory()->create()
        );

        $activeProvider = DeliveryProvider::factory()->create([
            'is_active' => true,
        ]);

        $inactiveProvider = DeliveryProvider::factory()->create([
            'is_active' => false,
        ]);

        $this->assertTrue(
            $this->allows(
                $activeProvider->user,
                'assign',
                $service
            )
        );

        $this->assertFalse(
            $this->allows(
                $inactiveProvider->user,
                'assign',
                $service
            )
        );
    }

    public function test_the_linked_provider_can_operate_the_service(): void
    {
        $customer = Customer::factory()->create();
        $provider = DeliveryProvider::factory()->create();
        $service = $this->serviceFor($customer, $provider);
        $user = $provider->user;

        $this->assertTrue(
            $this->allows($user, 'view', $service)
        );

        $this->assertTrue(
            $this->allows($user, 'start', $service)
        );

        $this->assertTrue(
            $this->allows($user, 'complete', $service)
        );

        $this->assertTrue(
            $this->allows($user, 'cancel', $service)
        );

        $this->assertFalse(
            $this->allows($user, 'rate', $service)
        );

        $this->assertFalse(
            $this->allows($user, 'confirmPayment', $service)
        );
    }

    public function test_an_unrelated_provider_cannot_operate_the_service(): void
    {
        $customer = Customer::factory()->create();
        $linkedProvider = DeliveryProvider::factory()->create();
        $unrelatedProvider = DeliveryProvider::factory()->create();

        $service = $this->serviceFor(
            $customer,
            $linkedProvider
        );

        foreach ([
            'view',
            'start',
            'complete',
            'cancel',
        ] as $ability) {
            $this->assertFalse(
                $this->allows(
                    $unrelatedProvider->user,
                    $ability,
                    $service
                )
            );
        }
    }

    public function test_the_assigned_courier_can_operate_the_service(): void
    {
        $service = $this->serviceFor(
            Customer::factory()->create()
        );

        $courier = Courier::factory()->create();

        $this->assignCourier(
            $courier,
            $service->shipment
        );

        $user = $courier->user;

        $this->assertTrue(
            $this->allows($user, 'view', $service)
        );

        $this->assertTrue(
            $this->allows($user, 'start', $service)
        );

        $this->assertTrue(
            $this->allows($user, 'complete', $service)
        );

        $this->assertFalse(
            $this->allows($user, 'cancel', $service)
        );

        $this->assertFalse(
            $this->allows($user, 'rate', $service)
        );
    }

    public function test_an_unassigned_courier_cannot_operate_the_service(): void
    {
        $service = $this->serviceFor(
            Customer::factory()->create()
        );

        $assignedCourier = Courier::factory()->create();
        $unassignedCourier = Courier::factory()->create();

        $this->assignCourier(
            $assignedCourier,
            $service->shipment
        );

        foreach ([
            'view',
            'start',
            'complete',
        ] as $ability) {
            $this->assertFalse(
                $this->allows(
                    $unassignedCourier->user,
                    $ability,
                    $service
                )
            );
        }
    }

    public function test_a_support_agent_can_only_view_services(): void
    {
        $supportAgent = $this->userWithRole('SUPPORT_AGENT');

        $service = $this->serviceFor(
            Customer::factory()->create()
        );

        $this->assertTrue(
            $this->allows($supportAgent, 'view', $service)
        );

        foreach ([
            'assign',
            'start',
            'complete',
            'cancel',
            'confirmPayment',
            'rate',
        ] as $ability) {
            $this->assertFalse(
                $this->allows(
                    $supportAgent,
                    $ability,
                    $service
                )
            );
        }
    }

    public function test_an_administrator_can_perform_domain_actions(): void
    {
        $administrator = $this->userWithRole('ADMINISTRATOR');

        $service = $this->serviceFor(
            Customer::factory()->create()
        );

        foreach ([
            'view',
            'assign',
            'start',
            'complete',
            'cancel',
            'confirmPayment',
        ] as $ability) {
            $this->assertTrue(
                $this->allows(
                    $administrator,
                    $ability,
                    $service
                )
            );
        }

        // Las evaluaciones pertenecen exclusivamente al cliente.
        $this->assertFalse(
            $this->allows($administrator, 'rate', $service)
        );
    }

    public function test_direct_update_and_deletion_are_denied(): void
    {
        $customer = Customer::factory()->create();
        $service = $this->serviceFor($customer);
        $user = $customer->user;

        foreach ([
            'update',
            'delete',
            'restore',
            'forceDelete',
        ] as $ability) {
            $this->assertFalse(
                $this->allows($user, $ability, $service)
            );
        }
    }

    private function serviceFor(
        Customer $customer,
        ?DeliveryProvider $provider = null
    ): DeliveryService {
        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $attributes = [
            'shipment_id' => $shipment->id,
            'status' => 'REQUESTED',
        ];

        if ($provider !== null) {
            $trip = Trip::factory()->create([
                'delivery_provider_id' => $provider->id,
                'status' => 'USED',
                'used_at' => now(),
            ]);

            $attributes = array_merge($attributes, [
                'trip_id' => $trip->id,
                'trip_type_id' => $trip->trip_type_id,
                'status' => 'ASSIGNED',
                'accepted_at' => now(),
            ]);
        }

        return DeliveryService::factory()->create($attributes);
    }

    private function assignCourier(
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

    private function allows(
        User $user,
        string $ability,
        mixed $arguments
    ): bool {
        return Gate::forUser($user)->allows(
            $ability,
            $arguments
        );
    }
}

