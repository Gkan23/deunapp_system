<?php

namespace Tests\Feature\Policies;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Incident;
use App\Models\IncidentStatus;
use App\Models\IncidentType;
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

class IncidentPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_supported_roles_can_access_the_incident_list(): void
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
                    Incident::class
                )
            );
        }
    }

    public function test_an_inactive_user_cannot_access_incidents(): void
    {
        $customer = Customer::factory()->create();
        $incident = $this->incidentFor($customer);

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $customer->user->update([
            'account_status_id' => $suspendedStatus->id,
        ]);

        $user = $customer->user->fresh();

        $this->assertFalse(
            $this->allows($user, 'view', $incident)
        );

        $this->assertFalse(
            $this->allows(
                $user,
                'create',
                Incident::class
            )
        );
    }

    public function test_supported_active_users_can_report_incidents(): void
    {
        $users = [
            Customer::factory()->create()->user,

            DeliveryProvider::factory()->create([
                'is_active' => true,
            ])->user,

            Courier::factory()->create([
                'is_active' => true,
            ])->user,

            $this->userWithRole('SUPPORT_AGENT'),
            $this->userWithRole('ADMINISTRATOR'),
        ];

        foreach ($users as $user) {
            $this->assertTrue(
                $this->allows(
                    $user,
                    'create',
                    Incident::class
                )
            );
        }
    }

    public function test_missing_or_inactive_profiles_cannot_report_incidents(): void
    {
        $customerWithoutProfile = User::factory()
            ->customer()
            ->create();

        $inactiveProvider = DeliveryProvider::factory()->create([
            'is_active' => false,
        ]);

        $inactiveCourier = Courier::factory()->create([
            'is_active' => false,
        ]);

        foreach ([
            $customerWithoutProfile,
            $inactiveProvider->user,
            $inactiveCourier->user,
        ] as $user) {
            $this->assertFalse(
                $this->allows(
                    $user,
                    'create',
                    Incident::class
                )
            );
        }
    }

    public function test_a_customer_can_view_an_incident_from_their_shipment(): void
    {
        $customer = Customer::factory()->create();
        $incident = $this->incidentFor($customer);

        $this->assertTrue(
            $this->allows(
                $customer->user,
                'view',
                $incident
            )
        );
    }

    public function test_a_customer_cannot_view_another_customers_incident(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $incident = $this->incidentFor($otherCustomer);

        $this->assertFalse(
            $this->allows(
                $customer->user,
                'view',
                $incident
            )
        );
    }

    public function test_only_the_linked_provider_can_view_the_incident(): void
    {
        $customer = Customer::factory()->create();
        $linkedProvider = DeliveryProvider::factory()->create();
        $unrelatedProvider = DeliveryProvider::factory()->create();

        $incident = $this->incidentFor(
            $customer,
            $linkedProvider
        );

        $this->assertTrue(
            $this->allows(
                $linkedProvider->user,
                'view',
                $incident
            )
        );

        $this->assertFalse(
            $this->allows(
                $unrelatedProvider->user,
                'view',
                $incident
            )
        );
    }

    public function test_only_the_assigned_courier_can_view_the_incident(): void
    {
        $customer = Customer::factory()->create();
        $assignedCourier = Courier::factory()->create();
        $unassignedCourier = Courier::factory()->create();

        $incident = $this->incidentFor(
            $customer,
            $assignedCourier->deliveryProvider
        );

        $this->assignCourier(
            $assignedCourier,
            $incident->shipment
        );

        $this->assertTrue(
            $this->allows(
                $assignedCourier->user,
                'view',
                $incident
            )
        );

        $this->assertFalse(
            $this->allows(
                $unassignedCourier->user,
                'view',
                $incident
            )
        );
    }

    public function test_support_and_administration_can_manage_incidents(): void
    {
        $incident = $this->incidentFor(
            Customer::factory()->create()
        );

        $users = [
            $this->userWithRole('SUPPORT_AGENT'),
            $this->userWithRole('ADMINISTRATOR'),
        ];

        foreach ($users as $user) {
            $this->assertTrue(
                $this->allows($user, 'view', $incident)
            );

            foreach ([
                'review',
                'resolve',
                'close',
            ] as $ability) {
                $this->assertTrue(
                    $this->allows(
                        $user,
                        $ability,
                        $incident
                    )
                );
            }
        }
    }

    public function test_operational_users_cannot_manage_incident_statuses(): void
    {
        $customer = Customer::factory()->create();
        $provider = DeliveryProvider::factory()->create();

        $incident = $this->incidentFor(
            $customer,
            $provider
        );

        foreach ([
            $customer->user,
            $provider->user,
        ] as $user) {
            foreach ([
                'review',
                'resolve',
                'close',
            ] as $ability) {
                $this->assertFalse(
                    $this->allows(
                        $user,
                        $ability,
                        $incident
                    )
                );
            }
        }
    }

    public function test_direct_update_and_deletion_are_denied(): void
    {
        $customer = Customer::factory()->create();
        $incident = $this->incidentFor($customer);
        $user = $customer->user;

        foreach ([
            'update',
            'delete',
            'restore',
            'forceDelete',
        ] as $ability) {
            $this->assertFalse(
                $this->allows(
                    $user,
                    $ability,
                    $incident
                )
            );
        }
    }

    private function incidentFor(
        Customer $customer,
        ?DeliveryProvider $provider = null
    ): Incident {
        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        if ($provider !== null) {
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

        $incidentType = IncidentType::query()
            ->firstOrFail();

        $openStatus = IncidentStatus::query()
            ->where('status_name', 'OPEN')
            ->firstOrFail();

        return Incident::query()->create([
            'shipment_id' => $shipment->id,
            'reported_by_user_id' => $customer->user_id,
            'incident_type_id' => $incidentType->id,
            'incident_status_id' => $openStatus->id,
            'description' => 'The delivery has an operational issue.',
            'reported_at' => now(),
        ]);
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

