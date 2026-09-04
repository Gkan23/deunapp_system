<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\Route as DeliveryRoute;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RouteIndexPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);

        $this->withoutVite();
    }

    public function test_a_guest_cannot_view_the_route_list(): void
    {
        $this->get(route('portal.routes.index'))
            ->assertRedirect(route('login.page'));
    }

    public function test_an_unverified_user_is_redirected_to_verification(): void
    {
        $user = $this->userWithRole('ADMINISTRATOR');

        $user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this->assertFalse($user->hasVerifiedEmail());

        $this->actingAs($user)
            ->get(route('portal.routes.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_a_customer_cannot_view_the_route_list(): void
    {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->get(route('portal.routes.index'))
            ->assertForbidden();
    }

    public function test_inactive_accounts_cannot_view_the_route_list(): void
    {
        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        foreach ([
            'DELIVERY_PROVIDER',
            'COURIER',
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ] as $roleName) {
            $user = $this->userWithRole($roleName);

            $user->update([
                'account_status_id' => $suspendedStatus->id,
            ]);

            $this->actingAs($user)
                ->get(route('portal.routes.index'))
                ->assertForbidden();
        }
    }

    public function test_a_provider_only_sees_routes_of_their_couriers(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $firstCourier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
        ]);

        $secondCourier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
        ]);

        $firstRoute = $this->createRoute($firstCourier);

        $secondRoute = $this->createRoute(
            $secondCourier,
            'ACTIVE'
        );

        $otherRoute = $this->createRoute();

        $response = $this->actingAs($provider->user)
            ->get(route('portal.routes.index'))
            ->assertOk()
            ->assertViewIs('routes.index')
            ->assertViewHas('totalRoutes', 2)
            ->assertViewHas('plannedRoutes', 1)
            ->assertViewHas('activeRoutes', 1);

        $this->assertListedRoutes($response, [
            $firstRoute,
            $secondRoute,
        ]);

        $response->assertDontSee(
            route('routes.map.view', $otherRoute),
            escape: false
        );
    }

    public function test_a_courier_only_sees_their_own_routes(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
        ]);

        $otherCourier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
        ]);

        $ownRoute = $this->createRoute($courier);

        $this->createRoute($otherCourier);

        $this->createRoute();

        $response = $this->actingAs($courier->user)
            ->get(route('portal.routes.index'))
            ->assertOk()
            ->assertSee('Mis rutas')
            ->assertViewHas('totalRoutes', 1);

        $this->assertListedRoutes($response, [$ownRoute]);
    }

    public function test_support_and_administration_can_view_all_routes(): void
    {
        $firstRoute = $this->createRoute();

        $secondRoute = $this->createRoute(
            statusName: 'ACTIVE'
        );

        foreach ([
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ] as $roleName) {
            $response = $this->actingAs(
                $this->userWithRole($roleName)
            )
                ->get(route('portal.routes.index'))
                ->assertOk()
                ->assertViewHas('totalRoutes', 2);

            $this->assertListedRoutes($response, [
                $firstRoute,
                $secondRoute,
            ]);
        }
    }

    public function test_routes_can_be_filtered_by_status_and_date_range(): void
    {
        $courier = Courier::factory()->create();

        $expectedRoute = $this->createRoute(
            $courier,
            'ACTIVE',
            '2026-09-04'
        );

        $this->createRoute(
            $courier,
            'PLANNED',
            '2026-09-04'
        );

        $this->createRoute(
            $courier,
            'ACTIVE',
            '2026-09-03'
        );

        $this->createRoute(
            $courier,
            'ACTIVE',
            '2026-09-05'
        );

        $response = $this->actingAs($courier->user)
            ->get(route('portal.routes.index', [
                'status' => 'ACTIVE',
                'date_from' => '2026-09-04',
                'date_to' => '2026-09-04',
            ]))
            ->assertOk()
            ->assertViewHas('totalRoutes', 4)
            ->assertViewHas('activeRoutes', 3);

        $this->assertListedRoutes($response, [$expectedRoute]);
    }

    public function test_search_matches_courier_plate_and_route_number_without_leaking_routes(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
        ]);

        $courier->user->update([
            'name' => 'Repartidor Busqueda Especial',
        ]);

        $vehicle = Vehicle::factory()->create([
            'courier_id' => $courier->id,
            'plate_number' => 'TEST-ROUTE-123',
        ]);

        $expectedRoute = $this->createRoute($courier);

        $expectedRoute->update([
            'vehicle_id' => $vehicle->id,
        ]);

        $otherCourier = Courier::factory()->create();

        $otherCourier->user->update([
            'name' => 'Repartidor Busqueda Especial',
        ]);

        $otherVehicle = Vehicle::factory()->create([
            'courier_id' => $otherCourier->id,
            'plate_number' => 'TEST-ROUTE-999',
        ]);

        $otherRoute = $this->createRoute($otherCourier);

        $otherRoute->update([
            'vehicle_id' => $otherVehicle->id,
        ]);

        foreach ([
            'Busqueda Especial',
            'TEST-ROUTE',
            (string) $expectedRoute->id,
        ] as $search) {
            $response = $this->actingAs($provider->user)
                ->get(route('portal.routes.index', [
                    'search' => $search,
                ]))
                ->assertOk();

            $this->assertListedRoutes($response, [
                $expectedRoute,
            ]);
        }

        $response = $this->actingAs($provider->user)
            ->get(route('portal.routes.index', [
                'search' => (string) $otherRoute->id,
            ]))
            ->assertOk();

        $this->assertListedRoutes($response, []);
    }

    public function test_the_page_shows_an_empty_state(): void
    {
        $courier = Courier::factory()->create();

        $response = $this->actingAs($courier->user)
            ->get(route('portal.routes.index'))
            ->assertOk()
            ->assertSee('No se encontraron rutas')
            ->assertViewHas('totalRoutes', 0)
            ->assertViewHas('plannedRoutes', 0)
            ->assertViewHas('activeRoutes', 0);

        $this->assertListedRoutes($response, []);
    }

    public function test_route_cards_show_the_map_link_nullable_values_and_shipment_count(): void
    {
        $courier = Courier::factory()->create();

        $deliveryRoute = $this->createRoute($courier);

        foreach (range(1, 2) as $order) {
            $shipment = Shipment::factory()->create();

            RouteShipment::query()->create([
                'route_id' => $deliveryRoute->id,
                'shipment_id' => $shipment->id,
                'delivery_order' => $order,
                'delivery_status' => 'PENDING',
            ]);
        }

        $this->actingAs($courier->user)
            ->get(route('portal.routes.index'))
            ->assertOk()
            ->assertSee($courier->user->name)
            ->assertSee('Sin vehículo asignado')
            ->assertSee('No estimada')
            ->assertSee('Sin iniciar')
            ->assertSee('Sin finalizar')
            ->assertSee(
                route('routes.map.view', $deliveryRoute),
                escape: false
            )
            ->assertViewHas('routes', function ($routes): bool {
                return (int) $routes
                    ->getCollection()
                    ->first()
                    ->route_shipments_count === 2;
            });
    }

    public function test_routes_are_paginated_and_filters_are_preserved(): void
    {
        $courier = Courier::factory()->create();

        $createdRoutes = [];

        foreach (range(1, 16) as $number) {
            $createdRoutes[] = $this->createRoute(
                $courier,
                'PLANNED',
                '2026-09-04'
            );
        }

        $response = $this->actingAs($courier->user)
            ->get(route('portal.routes.index', [
                'status' => 'PLANNED',
            ]))
            ->assertOk();

        $paginator = $response->viewData('routes');

        $this->assertSame(16, $paginator->total());

        $this->assertSame(
            15,
            $paginator->getCollection()->count()
        );

        $this->assertSame(
            $createdRoutes[15]->id,
            $paginator->getCollection()->first()->id
        );

        $this->assertStringContainsString(
            'status=PLANNED',
            $paginator->url(2)
        );

        $secondPage = $this->actingAs($courier->user)
            ->get(route('portal.routes.index', [
                'status' => 'PLANNED',
                'page' => 2,
            ]))
            ->assertOk();

        $this->assertListedRoutes(
            $secondPage,
            [$createdRoutes[0]]
        );
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $user = $this->userWithRole('ADMINISTRATOR');

        $cases = [
            [
                'filters' => ['search' => str_repeat('a', 101)],
                'field' => 'search',
            ],
            [
                'filters' => ['search' => ['invalid']],
                'field' => 'search',
            ],
            [
                'filters' => ['status' => 'UNKNOWN'],
                'field' => 'status',
            ],
            [
                'filters' => ['date_from' => 'not-a-date'],
                'field' => 'date_from',
            ],
            [
                'filters' => ['date_to' => '2026-02-30'],
                'field' => 'date_to',
            ],
            [
                'filters' => [
                    'date_from' => '2026-09-05',
                    'date_to' => '2026-09-04',
                ],
                'field' => 'date_to',
            ],
            [
                'filters' => ['page' => 0],
                'field' => 'page',
            ],
        ];

        foreach ($cases as $case) {
            $this->actingAs($user)
                ->from(route('portal.routes.index'))
                ->get(route(
                    'portal.routes.index',
                    $case['filters']
                ))
                ->assertRedirect(route('portal.routes.index'))
                ->assertSessionHasErrors($case['field']);
        }
    }

    public function test_the_dashboard_links_to_the_blade_route_list(): void
    {
        foreach ([
            'DELIVERY_PROVIDER',
            'COURIER',
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ] as $roleName) {
            $this->actingAs(
                $this->userWithRole($roleName)
            )
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee(
                    route('portal.routes.index'),
                    escape: false
                );
        }
    }

    private function createRoute(
        ?Courier $courier = null,
        string $statusName = 'PLANNED',
        string $routeDate = '2026-09-04'
    ): DeliveryRoute {
        $courier ??= Courier::factory()->create();

        return DeliveryRoute::query()->create([
            'courier_id' => $courier->id,
            'vehicle_id' => null,
            'route_status_id' => RouteStatus::query()
                ->where('status_name', $statusName)
                ->firstOrFail()
                ->id,
            'route_date' => $routeDate,
            'started_at' => null,
            'finished_at' => null,
            'estimated_distance_km' => null,
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        return User::factory()->create([
            'role_id' => Role::query()
                ->where('role_name', $roleName)
                ->firstOrFail()
                ->id,
        ]);
    }

    /**
     * @param array<int, DeliveryRoute> $expectedRoutes
     */
    private function assertListedRoutes(
        TestResponse $response,
        array $expectedRoutes
    ): void {
        $expectedIds = array_map(
            fn (DeliveryRoute $route): int => (int) $route->id,
            $expectedRoutes
        );

        $actualIds = $response
            ->viewData('routes')
            ->getCollection()
            ->map(
                fn (DeliveryRoute $route): int => (int) $route->id
            )
            ->all();

        $this->assertEqualsCanonicalizing(
            $expectedIds,
            $actualIds
        );
    }
}