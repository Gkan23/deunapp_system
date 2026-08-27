<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\Route;
use App\Models\RouteStatus;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteQueryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_list_or_show_routes(): void
    {
        $scenario = $this->createRoute();

        $this
            ->getJson(route('routes.index'))
            ->assertUnauthorized();

        $this
            ->getJson(
                route(
                    'routes.show',
                    $scenario['route']
                )
            )
            ->assertUnauthorized();
    }

    public function test_a_customer_cannot_list_routes(): void
    {
        $customer = Customer::factory()->create();

        $this
            ->actingAs($customer->user)
            ->getJson(route('routes.index'))
            ->assertForbidden();
    }

    public function test_a_provider_only_receives_their_routes(): void
    {
        $ownScenario = $this->createRoute();
        $otherScenario = $this->createRoute();

        $response = $this
            ->actingAs(
                $ownScenario['provider']->user
            )
            ->getJson(route('routes.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $routeIds = collect(
            $response->json('data')
        )->pluck('id');

        $this->assertTrue(
            $routeIds->contains(
                $ownScenario['route']->id
            )
        );

        $this->assertFalse(
            $routeIds->contains(
                $otherScenario['route']->id
            )
        );
    }

    public function test_a_courier_only_receives_assigned_routes(): void
    {
        $ownScenario = $this->createRoute();
        $otherScenario = $this->createRoute();

        $response = $this
            ->actingAs(
                $ownScenario['courier']->user
            )
            ->getJson(route('routes.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $routeIds = collect(
            $response->json('data')
        )->pluck('id');

        $this->assertTrue(
            $routeIds->contains(
                $ownScenario['route']->id
            )
        );

        $this->assertFalse(
            $routeIds->contains(
                $otherScenario['route']->id
            )
        );
    }

    public function test_support_and_administration_receive_all_routes(): void
    {
        $this->createRoute();
        $this->createRoute();

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
                ->getJson(route('routes.index'))
                ->assertOk()
                ->assertJsonCount(2, 'data');
        }
    }

    public function test_a_provider_can_only_show_their_route(): void
    {
        $ownScenario = $this->createRoute();
        $otherScenario = $this->createRoute();

        $this
            ->actingAs(
                $ownScenario['provider']->user
            )
            ->getJson(
                route(
                    'routes.show',
                    $ownScenario['route']
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'route.id',
                $ownScenario['route']->id
            );

        $this
            ->getJson(
                route(
                    'routes.show',
                    $otherScenario['route']
                )
            )
            ->assertForbidden();
    }

    public function test_a_courier_can_only_show_their_route(): void
    {
        $ownScenario = $this->createRoute();
        $otherScenario = $this->createRoute();

        $this
            ->actingAs(
                $ownScenario['courier']->user
            )
            ->getJson(
                route(
                    'routes.show',
                    $ownScenario['route']
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'route.id',
                $ownScenario['route']->id
            );

        $this
            ->getJson(
                route(
                    'routes.show',
                    $otherScenario['route']
                )
            )
            ->assertForbidden();
    }

    public function test_support_and_administration_can_show_any_route(): void
    {
        $scenario = $this->createRoute();

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
                        'routes.show',
                        $scenario['route']
                    )
                )
                ->assertOk()
                ->assertJsonPath(
                    'route.id',
                    $scenario['route']->id
                );
        }
    }

    /**
     * @return array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     route: Route
     * }
     */
    private function createRoute(): array
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

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
            'estimated_distance_km' => 8.50,
        ]);

        return [
            'provider' => $provider,
            'courier' => $courier,
            'route' => $route,
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
}