<?php

namespace Tests\Feature\Policies;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\Route;
use App\Models\RouteStatus;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RoutePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_supported_roles_can_access_the_route_list(): void
    {
        $provider = DeliveryProvider::factory()->create()->user;
        $courier = Courier::factory()->create()->user;
        $supportAgent = $this->userWithRole('SUPPORT_AGENT');
        $administrator = $this->userWithRole('ADMINISTRATOR');

        foreach ([
            $provider,
            $courier,
            $supportAgent,
            $administrator,
        ] as $user) {
            $this->assertTrue(
                Gate::forUser($user)->allows(
                    'viewAny',
                    Route::class
                )
            );
        }
    }

    public function test_an_inactive_user_cannot_access_routes(): void
    {
        $courier = Courier::factory()->create();
        $route = $this->routeFor($courier);

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $courier->user->update([
            'account_status_id' => $suspendedStatus->id,
        ]);

        $user = $courier->user->fresh();

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'viewAny',
                Route::class
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'view',
                $route
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'activate',
                $route
            )
        );
    }

    public function test_an_active_provider_can_create_routes(): void
    {
        $activeProvider = DeliveryProvider::factory()->create([
            'is_active' => true,
        ]);

        $inactiveProvider = DeliveryProvider::factory()->create([
            'is_active' => false,
        ]);

        $this->assertTrue(
            Gate::forUser($activeProvider->user)->allows(
                'create',
                Route::class
            )
        );

        $this->assertFalse(
            Gate::forUser($inactiveProvider->user)->allows(
                'create',
                Route::class
            )
        );
    }

    public function test_the_linked_provider_can_manage_the_route(): void
    {
        $provider = DeliveryProvider::factory()->create();
        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
        ]);

        $route = $this->routeFor($courier);
        $user = $provider->user;

        $this->assertTrue(
            Gate::forUser($user)->allows('view', $route)
        );

        $this->assertTrue(
            Gate::forUser($user)->allows('addShipment', $route)
        );

        $this->assertTrue(
            Gate::forUser($user)->allows('activate', $route)
        );

        $this->assertTrue(
            Gate::forUser($user)->allows('complete', $route)
        );

        $this->assertTrue(
            Gate::forUser($user)->allows('cancel', $route)
        );
    }

    public function test_an_unrelated_provider_cannot_manage_the_route(): void
    {
        $linkedProvider = DeliveryProvider::factory()->create();
        $unrelatedProvider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $linkedProvider->id,
        ]);

        $route = $this->routeFor($courier);
        $user = $unrelatedProvider->user;

        $this->assertFalse(
            Gate::forUser($user)->allows('view', $route)
        );

        $this->assertFalse(
            Gate::forUser($user)->allows('addShipment', $route)
        );

        $this->assertFalse(
            Gate::forUser($user)->allows('activate', $route)
        );

        $this->assertFalse(
            Gate::forUser($user)->allows('complete', $route)
        );

        $this->assertFalse(
            Gate::forUser($user)->allows('cancel', $route)
        );
    }

    public function test_the_assigned_courier_can_operate_the_route(): void
    {
        $courier = Courier::factory()->create();
        $route = $this->routeFor($courier);
        $user = $courier->user;

        $this->assertTrue(
            Gate::forUser($user)->allows('view', $route)
        );

        $this->assertTrue(
            Gate::forUser($user)->allows('activate', $route)
        );

        $this->assertTrue(
            Gate::forUser($user)->allows('complete', $route)
        );

        $this->assertFalse(
            Gate::forUser($user)->allows('addShipment', $route)
        );

        $this->assertFalse(
            Gate::forUser($user)->allows('cancel', $route)
        );
    }

    public function test_an_unassigned_courier_cannot_operate_the_route(): void
    {
        $assignedCourier = Courier::factory()->create();
        $unassignedCourier = Courier::factory()->create();

        $route = $this->routeFor($assignedCourier);
        $user = $unassignedCourier->user;

        $this->assertFalse(
            Gate::forUser($user)->allows('view', $route)
        );

        $this->assertFalse(
            Gate::forUser($user)->allows('activate', $route)
        );

        $this->assertFalse(
            Gate::forUser($user)->allows('complete', $route)
        );
    }

    public function test_a_support_agent_can_only_view_routes(): void
    {
        $supportAgent = $this->userWithRole('SUPPORT_AGENT');
        $route = $this->routeFor(Courier::factory()->create());

        $this->assertTrue(
            Gate::forUser($supportAgent)->allows('view', $route)
        );

        $this->assertFalse(
            Gate::forUser($supportAgent)->allows(
                'addShipment',
                $route
            )
        );

        $this->assertFalse(
            Gate::forUser($supportAgent)->allows(
                'activate',
                $route
            )
        );

        $this->assertFalse(
            Gate::forUser($supportAgent)->allows(
                'complete',
                $route
            )
        );

        $this->assertFalse(
            Gate::forUser($supportAgent)->allows(
                'cancel',
                $route
            )
        );
    }

    public function test_an_administrator_can_perform_route_domain_actions(): void
    {
        $administrator = $this->userWithRole('ADMINISTRATOR');
        $route = $this->routeFor(Courier::factory()->create());

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'create',
                Route::class
            )
        );

        $this->assertTrue(
            Gate::forUser($administrator)->allows('view', $route)
        );

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'addShipment',
                $route
            )
        );

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'activate',
                $route
            )
        );

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'complete',
                $route
            )
        );

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'cancel',
                $route
            )
        );
    }

    public function test_direct_update_and_deletion_are_denied(): void
    {
        $provider = DeliveryProvider::factory()->create();
        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
        ]);

        $route = $this->routeFor($courier);
        $user = $provider->user;

        $this->assertFalse(
            Gate::forUser($user)->allows('update', $route)
        );

        $this->assertFalse(
            Gate::forUser($user)->allows('delete', $route)
        );

        $this->assertFalse(
            Gate::forUser($user)->allows('restore', $route)
        );

        $this->assertFalse(
            Gate::forUser($user)->allows('forceDelete', $route)
        );
    }

    private function routeFor(Courier $courier): Route
    {
        $plannedStatus = RouteStatus::query()
            ->where('status_name', 'PLANNED')
            ->firstOrFail();

        return Route::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' => $plannedStatus->id,
            'route_date' => today(),
            'started_at' => null,
            'finished_at' => null,
            'estimated_distance_km' => null,
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
