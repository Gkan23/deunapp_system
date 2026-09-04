<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Role;
use App\Models\Route as DeliveryRoute;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteShowPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);

        $this->withoutVite();
    }

    public function test_a_guest_cannot_view_the_route_detail(): void
    {
        $route = $this->createRoute();

        $this->get($this->detailUrl($route))
            ->assertRedirect(route('login.page'));
    }

    public function test_an_unverified_user_is_redirected_to_verification(): void
    {
        $route = $this->createRoute();

        $user = $route->courier->user;

        $user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this->assertFalse($user->hasVerifiedEmail());

        $this->actingAs($user)
            ->get($this->detailUrl($route))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_a_provider_can_only_view_their_routes(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
        ]);

        $ownRoute = $this->createRoute($courier);

        $otherRoute = $this->createRoute();

        $this->actingAs($provider->user)
            ->get($this->detailUrl($ownRoute))
            ->assertOk()
            ->assertViewIs('routes.show')
            ->assertSee('Ruta #'.$ownRoute->id)
            ->assertSee($courier->user->name);

        $this->actingAs($provider->user)
            ->get($this->detailUrl($otherRoute))
            ->assertForbidden();
    }

    public function test_a_courier_cannot_view_another_couriers_route(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
        ]);

        $otherCourier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
        ]);

        $ownRoute = $this->createRoute($courier);

        $otherRoute = $this->createRoute($otherCourier);

        $this->actingAs($courier->user)
            ->get($this->detailUrl($ownRoute))
            ->assertOk();

        $this->actingAs($courier->user)
            ->get($this->detailUrl($otherRoute))
            ->assertForbidden();
    }

    public function test_support_and_administration_can_view_the_route(): void
    {
        $route = $this->createRoute();

        foreach ([
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ] as $roleName) {
            $this->actingAs($this->userWithRole($roleName))
                ->get($this->detailUrl($route))
                ->assertOk()
                ->assertSee('Ruta #'.$route->id)
                ->assertSee(
                    route('routes.map.view', $route),
                    escape: false
                );
        }
    }

    public function test_a_customer_cannot_view_the_route_even_with_an_assigned_shipment(): void
    {
        $route = $this->createRoute();

        $shipment = Shipment::factory()->create();

        $this->assignShipment($route, $shipment, 1);

        $this->actingAs($shipment->customer->user)
            ->get($this->detailUrl($route))
            ->assertForbidden();
    }

    public function test_inactive_accounts_cannot_view_the_route(): void
    {
        $route = $this->createRoute();

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $users = [
            $route->courier->user,
            $route->courier->deliveryProvider->user,
            $this->userWithRole('SUPPORT_AGENT'),
            $this->userWithRole('ADMINISTRATOR'),
        ];

        foreach ($users as $user) {
            $user->update([
                'account_status_id' => $suspendedStatus->id,
            ]);

            $this->actingAs($user)
                ->get($this->detailUrl($route))
                ->assertForbidden();
        }
    }

    public function test_an_empty_route_displays_nullable_values(): void
    {
        $route = $this->createRoute();

        $this->actingAs($route->courier->user)
            ->get($this->detailUrl($route))
            ->assertOk()
            ->assertSee('Esta ruta no tiene envíos asignados.')
            ->assertSee('Sin vehículo asignado.')
            ->assertSee('No estimada')
            ->assertSee('Sin iniciar')
            ->assertSee('Sin finalizar')
            ->assertViewHas('totalShipments', 0)
            ->assertViewHas('deliveredShipments', 0)
            ->assertViewHas('failedShipments', 0);
    }

    public function test_the_page_displays_vehicle_and_route_information(): void
    {
        $courier = Courier::factory()->create();

        $vehicle = Vehicle::factory()->create([
            'courier_id' => $courier->id,
            'plate_number' => 'ROUTE-DETAIL-001',
            'brand' => 'Honda',
            'model' => 'Modelo de prueba',
            'color' => 'Azul',
        ]);

        $route = $this->createRoute(
            $courier,
            'COMPLETED'
        );

        $route->update([
            'vehicle_id' => $vehicle->id,
            'estimated_distance_km' => '25.50',
            'started_at' => '2026-09-04 08:00:00',
            'finished_at' => '2026-09-04 12:00:00',
        ]);

        $this->actingAs($courier->user)
            ->get($this->detailUrl($route))
            ->assertOk()
            ->assertSee('ROUTE-DETAIL-001')
            ->assertSee('Honda')
            ->assertSee('Modelo de prueba')
            ->assertSee('Azul')
            ->assertSee('25.50 km')
            ->assertSee('04/09/2026 08:00')
            ->assertSee('04/09/2026 12:00')
            ->assertSee('COMPLETED');
    }

    public function test_shipments_are_ordered_and_assignment_counts_are_correct(): void
    {
        $route = $this->createRoute();

        $first = Shipment::factory()->create();
        $second = Shipment::factory()->create();
        $third = Shipment::factory()->create();

        /*
         * Insertar fuera de orden comprueba que la consulta
         * utiliza delivery_order, no el orden de creación.
         */
        $this->assignShipment($route, $third, 3, 'FAILED');
        $this->assignShipment($route, $first, 1, 'DELIVERED');
        $this->assignShipment($route, $second, 2, 'PENDING');

        $this->actingAs($route->courier->user)
            ->get($this->detailUrl($route))
            ->assertOk()
            ->assertSeeInOrder([
                $first->tracking_code,
                $second->tracking_code,
                $third->tracking_code,
            ])
            ->assertViewHas('totalShipments', 3)
            ->assertViewHas('deliveredShipments', 1)
            ->assertViewHas('failedShipments', 1)
            ->assertViewHas('stops', function ($stops): bool {
                return $stops
                    ->map(
                        fn (array $stop): int =>
                            $stop['assignment']->delivery_order
                    )
                    ->all() === [1, 2, 3];
            });
    }

    public function test_an_assigned_courier_can_see_shipment_details_and_link(): void
    {
        $route = $this->createRoute();

        $shipment = Shipment::factory()->create();

        $shipment->recipient->update([
            'first_name' => 'Destinatario',
            'last_name' => 'Autorizado',
            'phone' => '88887777',
        ]);

        $shipment->destinationAddress->update([
            'address_line' => 'Dirección de entrega autorizada',
        ]);

        $this->assignShipment($route, $shipment, 1);

        $this->actingAs($route->courier->user)
            ->get($this->detailUrl($route))
            ->assertOk()
            ->assertSee($shipment->tracking_code)
            ->assertSee('Destinatario')
            ->assertSee('Autorizado')
            ->assertSee('88887777')
            ->assertSee('Dirección de entrega autorizada')
            ->assertSee(
                route('portal.shipments.show', $shipment),
                escape: false
            )
            ->assertViewHas('stops', function ($stops) use ($shipment): bool {
                $visibleShipment = $stops->first()['shipment'];

                return $visibleShipment !== null
                    && (int) $visibleShipment->id === (int) $shipment->id
                    && (int) $visibleShipment->packages_count
                        === $shipment->packages()->count();
            });
    }

    public function test_a_provider_does_not_see_details_of_an_unauthorized_shipment(): void
    {
        $route = $this->createRoute();

        $shipment = Shipment::factory()->create();

        $shipment->recipient->update([
            'first_name' => 'NombrePrivadoUnico',
            'last_name' => 'ApellidoPrivadoUnico',
            'phone' => '89990001',
        ]);

        $shipment->destinationAddress->update([
            'address_line' => 'DireccionPrivadaUnica',
        ]);

        /*
         * Está en la ruta, pero no tiene un servicio asociado
         * al proveedor. ShipmentPolicy no permite consultarlo.
         */
        $this->assignShipment($route, $shipment, 1);

        $providerUser = $route->courier->deliveryProvider->user;

        $this->actingAs($providerUser)
            ->get($this->detailUrl($route))
            ->assertOk()
            ->assertSee('Envío con acceso restringido')
            ->assertDontSee($shipment->tracking_code)
            ->assertDontSee('NombrePrivadoUnico')
            ->assertDontSee('ApellidoPrivadoUnico')
            ->assertDontSee('89990001')
            ->assertDontSee('DireccionPrivadaUnica')
            ->assertDontSee(
                route('portal.shipments.show', $shipment),
                escape: false
            )
            ->assertViewHas('stops', function ($stops): bool {
                return $stops->first()['shipment'] === null;
            });
    }

    public function test_a_provider_can_see_a_shipment_related_to_their_service(): void
    {
        $route = $this->createRoute();

        $provider = $route->courier->deliveryProvider;

        $shipment = Shipment::factory()->create();

        $this->assignShipment($route, $shipment, 1);

        $trip = Trip::factory()->create([
            'delivery_provider_id' => $provider->id,
            'status' => 'USED',
            'used_at' => now(),
        ]);

        DeliveryService::factory()->create([
            'shipment_id' => $shipment->id,
            'trip_id' => $trip->id,
            'status' => 'ASSIGNED',
            'accepted_at' => now(),
        ]);

        $this->actingAs($provider->user)
            ->get($this->detailUrl($route))
            ->assertOk()
            ->assertSee($shipment->tracking_code)
            ->assertSee(
                route('portal.shipments.show', $shipment),
                escape: false
            );
    }

    public function test_viewing_the_page_does_not_change_the_route_or_assignments(): void
    {
        $route = $this->createRoute();

        $shipment = Shipment::factory()->create();

        $assignment = $this->assignShipment(
            $route,
            $shipment,
            1
        );

        $routeBefore = $route->fresh()->getAttributes();

        $assignmentBefore = $assignment->fresh()->getAttributes();

        $this->actingAs($route->courier->user)
            ->get($this->detailUrl($route))
            ->assertOk()
            ->assertDontSee(
                route('routes.activate', $route),
                escape: false
            )
            ->assertDontSee(
                route('routes.complete', $route),
                escape: false
            )
            ->assertDontSee(
                route('routes.cancel', $route),
                escape: false
            );

        $this->assertSame(
            $routeBefore,
            $route->fresh()->getAttributes()
        );

        $this->assertSame(
            $assignmentBefore,
            $assignment->fresh()->getAttributes()
        );
    }

    public function test_a_missing_route_returns_not_found(): void
    {
        $route = $this->createRoute();

        $missingId = $route->id + 1000;

        $this->actingAs($this->userWithRole('ADMINISTRATOR'))
            ->get(route('portal.routes.show', $missingId))
            ->assertNotFound();
    }

    public function test_the_route_list_contains_the_detail_link(): void
    {
        $route = $this->createRoute();

        $this->actingAs($route->courier->user)
            ->get(route('portal.routes.index'))
            ->assertOk()
            ->assertSee('Ver detalle')
            ->assertSee(
                $this->detailUrl($route),
                escape: false
            )
            ->assertSee(
                route('routes.map.view', $route),
                escape: false
            );
    }

    private function createRoute(
        ?Courier $courier = null,
        string $statusName = 'PLANNED'
    ): DeliveryRoute {
        $courier ??= Courier::factory()->create();

        return DeliveryRoute::query()->create([
            'courier_id' => $courier->id,
            'vehicle_id' => null,
            'route_status_id' => RouteStatus::query()
                ->where('status_name', $statusName)
                ->firstOrFail()
                ->id,
            'route_date' => '2026-09-04',
            'started_at' => null,
            'finished_at' => null,
            'estimated_distance_km' => null,
        ]);
    }

    private function assignShipment(
        DeliveryRoute $route,
        Shipment $shipment,
        int $order,
        string $deliveryStatus = 'PENDING'
    ): RouteShipment {
        return RouteShipment::query()->create([
            'route_id' => $route->id,
            'shipment_id' => $shipment->id,
            'delivery_order' => $order,
            'delivery_status' => $deliveryStatus,
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

    private function detailUrl(DeliveryRoute $route): string
    {
        return route('portal.routes.show', $route);
    }
}