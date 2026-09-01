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

class RouteMapPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Evita que Laravel intente buscar el
         * manifest de Vite durante estas pruebas.
         */
        $this->withoutVite();

        $this->seed(
            CatalogSeeder::class
        );

        /*
         * Token falso utilizado exclusivamente
         * durante las pruebas.
         */
        config([
            'services.mapbox.public_token' =>
                'pk.test-public-token',
        ]);
    }

    public function test_a_guest_cannot_view_the_route_map_page(): void
    {
        $scenario = $this->createRoute();

        $this->getJson(
            route(
                'routes.map.view',
                $scenario['route']
            )
        )->assertUnauthorized();
    }

    public function test_a_provider_can_only_view_their_route_map_page(): void
    {
        $ownScenario =
            $this->createRoute();

        $otherScenario =
            $this->createRoute();

        $this->actingAs(
            $ownScenario['provider']->user
        )
            ->get(
                route(
                    'routes.map.view',
                    $ownScenario['route']
                )
            )
            ->assertOk()
            ->assertSee(
                'Mapa de la ruta',
                false
            );

        $this->getJson(
            route(
                'routes.map.view',
                $otherScenario['route']
            )
        )->assertForbidden();
    }

    public function test_an_assigned_courier_can_view_the_route_map_page(): void
    {
        $scenario = $this->createRoute();

        $this->actingAs(
            $scenario['courier']->user
        )
            ->get(
                route(
                    'routes.map.view',
                    $scenario['route']
                )
            )
            ->assertOk()
            ->assertSee(
                'Mapa de la ruta',
                false
            );
    }

    public function test_support_and_administration_can_view_the_route_map_page(): void
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
            $this->actingAs($user)
                ->get(
                    route(
                        'routes.map.view',
                        $scenario['route']
                    )
                )
                ->assertOk()
                ->assertSee(
                    'Mapa de la ruta',
                    false
                );
        }
    }

    public function test_a_customer_cannot_view_the_route_map_page(): void
    {
        $scenario = $this->createRoute();

        $customer = Customer::factory()
            ->create();

        $this->actingAs(
            $customer->user
        )
            ->getJson(
                route(
                    'routes.map.view',
                    $scenario['route']
                )
            )
            ->assertForbidden();
    }

    public function test_an_unverified_user_cannot_view_the_route_map_page(): void
    {
        $scenario = $this->createRoute();

        $unverifiedUser =
            $scenario['provider']->user;

        /*
         * email_verified_at no se encuentra
         * dentro de $fillable en el modelo User.
         *
         * forceFill permite preparar el usuario
         * no verificado exclusivamente para esta
         * prueba.
         */
        $unverifiedUser->forceFill([
            'email_verified_at' => null,
        ])->save();

        /*
         * Confirma que el cambio fue guardado
         * antes de realizar la petición.
         */
        $this->assertNull(
            $unverifiedUser
                ->fresh()
                ->email_verified_at
        );

        $this->actingAs(
            $unverifiedUser->fresh()
        )
            ->getJson(
                route(
                    'routes.map.view',
                    $scenario['route']
                )
            )
            ->assertForbidden();
    }

    public function test_the_page_contains_the_map_configuration(): void
    {
        $scenario = $this->createRoute();

        $mapDataUrl = route(
            'routes.map',
            $scenario['route']
        );

        $mapPageUrl = route(
            'routes.map.view',
            $scenario['route']
        );

        $response = $this->actingAs(
            $scenario['provider']->user
        )->get($mapPageUrl);

        $response
            ->assertOk()
            ->assertSee(
                'Mapa de la ruta',
                false
            )
            ->assertSee(
                'pk.test-public-token',
                false
            )
            ->assertSee(
                $mapDataUrl,
                false
            )
            ->assertSee(
                'route-map-application',
                false
            )
            ->assertSee(
                'route-map',
                false
            );
    }

    /**
     * Crea una ruta planificada perteneciente a
     * un proveedor y a uno de sus repartidores.
     *
     * @return array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     route: Route
     * }
     */
    private function createRoute(): array
    {
        $provider =
            DeliveryProvider::factory()
                ->create();

        $courier = Courier::factory()
            ->for($provider)
            ->create();

        $plannedStatus =
            RouteStatus::query()
                ->where(
                    'status_name',
                    'PLANNED'
                )
                ->firstOrFail();

        $route = Route::query()->create([
            'courier_id' =>
                $courier->id,
            'vehicle_id' => null,
            'route_status_id' =>
                $plannedStatus->id,
            'route_date' =>
                today()->toDateString(),
            'started_at' => null,
            'finished_at' => null,
            'estimated_distance_km' =>
                10.50,
        ]);

        return [
            'provider' => $provider,
            'courier' => $courier,
            'route' => $route,
        ];
    }

    /**
     * Crea un usuario con el rol indicado.
     */
    private function userWithRole(
        string $roleName
    ): User {
        $role = Role::query()
            ->where(
                'role_name',
                $roleName
            )
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}