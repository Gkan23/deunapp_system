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
use App\Models\VehicleStatus;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PortalRouteActivationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);

        $this->withoutVite();

        $this->travelTo(
            now()->startOfDay()->addHours(9)
        );
    }

    public function test_a_guest_cannot_activate_a_route(): void
    {
        $scenario = $this->readyScenario();

        $before = $this->snapshot($scenario);

        $this->patch(
            $this->activationUrl($scenario['route'])
        )->assertRedirect(route('login.page'));

        $this->assertSame(
            $before,
            $this->snapshot($scenario)
        );
    }

    public function test_an_unverified_user_cannot_activate_a_route(): void
    {
        $scenario = $this->readyScenario();

        $user = $scenario['courier']->user;

        $user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this->assertFalse($user->hasVerifiedEmail());

        $before = $this->snapshot($scenario);

        $this->submit($user, $scenario['route'])
            ->assertRedirect(route('verification.notice'));

        $this->assertSame(
            $before,
            $this->snapshot($scenario)
        );
    }

    public function test_authorized_roles_can_activate_a_ready_route(): void
    {
        foreach ([
            'DELIVERY_PROVIDER',
            'COURIER',
            'ADMINISTRATOR',
        ] as $roleName) {
            $scenario = $this->readyScenario();

            $actor = match ($roleName) {
                'DELIVERY_PROVIDER' => $scenario['provider']->user,
                'COURIER' => $scenario['courier']->user,
                default => $this->userWithRole('ADMINISTRATOR'),
            };

            $this->submit($actor, $scenario['route'])
                ->assertRedirect(
                    $this->detailUrl($scenario['route'])
                )
                ->assertSessionHasNoErrors()
                ->assertSessionHas(
                    'status',
                    'La ruta fue activada correctamente.'
                );

            $route = $scenario['route']->fresh('routeStatus');

            $vehicle = $scenario['vehicle']->fresh('vehicleStatus');

            $assignment = $scenario['assignment']->fresh();

            $service = $scenario['service']->fresh();

            $this->assertSame(
                'ACTIVE',
                $route->routeStatus->status_name
            );

            $this->assertNotNull($route->started_at);

            $this->assertNull($route->finished_at);

            $this->assertFalse(
                (bool) $scenario['courier']->fresh()->is_available
            );

            $this->assertSame(
                'IN_USE',
                $vehicle->vehicleStatus->status_name
            );

            $this->assertSame(
                'IN_PROGRESS',
                $assignment->delivery_status
            );

            $this->assertSame(
                'IN_PROGRESS',
                $service->status
            );

            $this->assertNotNull($service->started_at);

            $this->assertTrue(
                $route->started_at->equalTo($service->started_at)
            );
        }
    }

    public function test_unauthorized_users_cannot_activate_a_route(): void
    {
        $scenario = $this->readyScenario();

        $otherProvider = DeliveryProvider::factory()->create();

        $otherCourier = Courier::factory()->create([
            'delivery_provider_id' => $scenario['provider']->id,
        ]);

        $actors = [
            $scenario['shipment']->customer->user,
            $this->userWithRole('SUPPORT_AGENT'),
            $otherProvider->user,
            $otherCourier->user,
        ];

        $before = $this->snapshot($scenario);

        foreach ($actors as $actor) {
            $this->submit($actor, $scenario['route'])
                ->assertForbidden();

            $this->assertSame(
                $before,
                $this->snapshot($scenario)
            );
        }
    }

    public function test_inactive_accounts_cannot_activate_a_route(): void
    {
        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        foreach ([
            'DELIVERY_PROVIDER',
            'COURIER',
            'ADMINISTRATOR',
        ] as $roleName) {
            $scenario = $this->readyScenario();

            $actor = match ($roleName) {
                'DELIVERY_PROVIDER' => $scenario['provider']->user,
                'COURIER' => $scenario['courier']->user,
                default => $this->userWithRole('ADMINISTRATOR'),
            };

            $actor->update([
                'account_status_id' => $suspendedStatus->id,
            ]);

            $before = $this->snapshot($scenario);

            $this->submit($actor, $scenario['route'])
                ->assertForbidden();

            $this->assertSame(
                $before,
                $this->snapshot($scenario)
            );
        }
    }

    public function test_a_route_can_only_be_activated_on_its_scheduled_date(): void
    {
        foreach ([
            today()->subDay(),
            today()->addDay(),
        ] as $date) {
            $scenario = $this->readyScenario();

            $scenario['route']->update([
                'route_date' => $date,
            ]);

            $this->assertRejectedWithoutChanges(
                $scenario,
                'A route can only be activated on its scheduled date.'
            );
        }
    }

    public function test_only_planned_routes_can_be_activated(): void
    {
        foreach ([
            'ACTIVE',
            'COMPLETED',
            'CANCELLED',
        ] as $statusName) {
            $scenario = $this->readyScenario();

            $scenario['route']->update([
                'route_status_id' => $this->routeStatusId(
                    $statusName
                ),
            ]);

            $this->assertRejectedWithoutChanges(
                $scenario,
                'Only a planned route can be activated.'
            );
        }
    }

    public function test_an_empty_route_cannot_be_activated(): void
    {
        $courier = Courier::factory()->create([
            'is_active' => true,
            'is_available' => true,
        ]);

        $route = $this->createRoute($courier);

        $routeBefore = $route->fresh()->getAttributes();

        $courierBefore = $courier->fresh()->getAttributes();

        $this->submit($courier->user, $route)
            ->assertRedirect($this->detailUrl($route))
            ->assertSessionHasErrors([
                'activation' => 'An empty route cannot be activated.',
            ]);

        $this->assertSame(
            $routeBefore,
            $route->fresh()->getAttributes()
        );

        $this->assertSame(
            $courierBefore,
            $courier->fresh()->getAttributes()
        );
    }

    public function test_courier_and_provider_availability_rules_are_preserved(): void
    {
        foreach ([
            'inactive_courier',
            'unavailable_courier',
            'inactive_provider',
        ] as $condition) {
            $scenario = $this->readyScenario();

            if ($condition === 'inactive_courier') {
                $scenario['courier']->update([
                    'is_active' => false,
                ]);

                $message = 'The courier is inactive.';
            } elseif ($condition === 'unavailable_courier') {
                $scenario['courier']->update([
                    'is_available' => false,
                ]);

                $message = 'The courier is not available.';
            } else {
                $scenario['provider']->update([
                    'is_active' => false,
                ]);

                $message = 'The courier delivery provider is inactive.';
            }

            $this->assertRejectedWithoutChanges(
                $scenario,
                $message
            );
        }
    }

    public function test_an_unavailable_or_unrelated_vehicle_is_rejected(): void
    {
        $unavailableScenario = $this->readyScenario();

        $unavailableScenario['vehicle']->update([
            'vehicle_status_id' => VehicleStatus::query()
                ->where('status_name', 'IN_USE')
                ->firstOrFail()
                ->id,
        ]);

        $this->assertRejectedWithoutChanges(
            $unavailableScenario,
            'Only an available vehicle can activate a route.'
        );

        $unrelatedScenario = $this->readyScenario();

        $otherCourier = Courier::factory()->create();

        $unrelatedScenario['vehicle']->update([
            'courier_id' => $otherCourier->id,
        ]);

        $this->assertRejectedWithoutChanges(
            $unrelatedScenario,
            'The route vehicle does not belong to the route courier.'
        );
    }

    public function test_a_courier_with_an_active_route_cannot_activate_another(): void
    {
        $scenario = $this->readyScenario();

        $this->createRoute(
            $scenario['courier'],
            'ACTIVE'
        );

        $this->assertRejectedWithoutChanges(
            $scenario,
            'The courier already has an active route.'
        );
    }

    public function test_non_pending_assignments_are_rejected(): void
    {
        $scenario = $this->readyScenario();

        $scenario['assignment']->update([
            'delivery_status' => 'IN_PROGRESS',
        ]);

        $this->assertRejectedWithoutChanges(
            $scenario,
            'Every route shipment must be pending before activation.'
        );
    }

    public function test_services_must_be_assigned_and_belong_to_the_provider(): void
    {
        $unassignedScenario = $this->readyScenario();

        $unassignedScenario['service']->update([
            'status' => 'REQUESTED',
        ]);

        $this->assertRejectedWithoutChanges(
            $unassignedScenario,
            'Every route shipment must have an assigned delivery service.'
        );

        $missingTripScenario = $this->readyScenario();

        $missingTripScenario['service']->update([
            'trip_id' => null,
        ]);

        $this->assertRejectedWithoutChanges(
            $missingTripScenario,
            'Every route shipment must have an assigned delivery service.'
        );

        $wrongProviderScenario = $this->readyScenario();

        $otherTrip = Trip::factory()->create([
            'status' => 'USED',
            'used_at' => now(),
        ]);

        $wrongProviderScenario['service']->update([
            'trip_id' => $otherTrip->id,
        ]);

        $this->assertRejectedWithoutChanges(
            $wrongProviderScenario,
            'The route shipment provider does not match the courier provider.'
        );
    }

    public function test_the_activation_form_respects_role_and_route_status(): void
    {
        $scenario = $this->readyScenario();

        $url = $this->activationUrl($scenario['route']);

        foreach ([
            $scenario['provider']->user,
            $scenario['courier']->user,
            $this->userWithRole('ADMINISTRATOR'),
        ] as $actor) {
            $this->actingAs($actor)
                ->get($this->detailUrl($scenario['route']))
                ->assertOk()
                ->assertSee('Activar ruta')
                ->assertSee($url, escape: false)
                ->assertSee(
                    'name="_method" value="PATCH"',
                    escape: false
                );
        }

        $this->actingAs($this->userWithRole('SUPPORT_AGENT'))
            ->get($this->detailUrl($scenario['route']))
            ->assertOk()
            ->assertDontSee($url, escape: false);

        $scenario['route']->update([
            'route_status_id' => $this->routeStatusId('ACTIVE'),
        ]);

        $this->actingAs($scenario['courier']->user)
            ->get($this->detailUrl($scenario['route']))
            ->assertOk()
            ->assertDontSee($url, escape: false);
    }

    public function test_viewing_the_form_does_not_activate_the_route(): void
    {
        $scenario = $this->readyScenario();

        $before = $this->snapshot($scenario);

        $this->actingAs($scenario['courier']->user)
            ->get($this->detailUrl($scenario['route']))
            ->assertOk()
            ->assertSee(
                $this->activationUrl($scenario['route']),
                escape: false
            );

        $this->assertSame(
            $before,
            $this->snapshot($scenario)
        );
    }

    public function test_repeated_activation_is_rejected_without_changing_the_started_route(): void
    {
        $scenario = $this->readyScenario();

        $actor = $scenario['courier']->user;

        $this->submit($actor, $scenario['route'])
            ->assertSessionHasNoErrors();

        $before = $this->snapshot($scenario);

        $this->travel(5)->minutes();

        $this->submit($actor, $scenario['route'])
            ->assertRedirect(
                $this->detailUrl($scenario['route'])
            )
            ->assertSessionHasErrors([
                'activation' => 'Only a planned route can be activated.',
            ]);

        $this->assertSame(
            $before,
            $this->snapshot($scenario)
        );

        $this->actingAs($actor)
            ->get($this->detailUrl($scenario['route']))
            ->assertOk()
            ->assertSee('No fue posible activar la ruta.')
            ->assertSee('Only a planned route can be activated.');
    }

    /**
     * Crear una ruta que cumple los requisitos de activación.
     *
     * @return array<string, mixed>
     */
    private function readyScenario(): array
    {
        $provider = DeliveryProvider::factory()->create([
            'is_active' => true,
        ]);

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        $vehicle = Vehicle::factory()->create([
            'courier_id' => $courier->id,
            'vehicle_status_id' => VehicleStatus::query()
                ->where('status_name', 'AVAILABLE')
                ->firstOrFail()
                ->id,
        ]);

        $route = $this->createRoute($courier);

        $route->update([
            'vehicle_id' => $vehicle->id,
        ]);

        $shipment = Shipment::factory()->create();

        $trip = Trip::factory()->create([
            'delivery_provider_id' => $provider->id,
            'status' => 'USED',
            'used_at' => now(),
        ]);

        $service = DeliveryService::factory()->create([
            'shipment_id' => $shipment->id,
            'trip_id' => $trip->id,
            'status' => 'ASSIGNED',
            'accepted_at' => now(),
            'started_at' => null,
        ]);

        $assignment = RouteShipment::query()->create([
            'route_id' => $route->id,
            'shipment_id' => $shipment->id,
            'delivery_order' => 1,
            'delivery_status' => 'PENDING',
        ]);

        return [
            'provider' => $provider,
            'courier' => $courier,
            'vehicle' => $vehicle,
            'route' => $route,
            'shipment' => $shipment,
            'service' => $service,
            'assignment' => $assignment,
        ];
    }

    private function createRoute(
        Courier $courier,
        string $statusName = 'PLANNED'
    ): DeliveryRoute {
        return DeliveryRoute::query()->create([
            'courier_id' => $courier->id,
            'vehicle_id' => null,
            'route_status_id' => $this->routeStatusId($statusName),
            'route_date' => today(),
            'started_at' => null,
            'finished_at' => null,
            'estimated_distance_km' => null,
        ]);
    }

    private function routeStatusId(string $statusName): int
    {
        return (int) RouteStatus::query()
            ->where('status_name', $statusName)
            ->firstOrFail()
            ->id;
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

    private function activationUrl(DeliveryRoute $route): string
    {
        return route('portal.routes.activate', $route);
    }

    private function submit(
        User $actor,
        DeliveryRoute $route
    ): TestResponse {
        return $this->actingAs($actor)
            ->from($this->detailUrl($route))
            ->patch($this->activationUrl($route));
    }

    /**
     * Capturar los registros afectados por la activación.
     *
     * @param array<string, mixed> $scenario
     * @return array<string, array<string, mixed>>
     */
    private function snapshot(array $scenario): array
    {
        $snapshot = [];

        foreach ([
            'route',
            'courier',
            'vehicle',
            'assignment',
            'service',
        ] as $key) {
            $snapshot[$key] = $scenario[$key]
                ->fresh()
                ->getAttributes();
        }

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function assertRejectedWithoutChanges(
        array $scenario,
        string $message
    ): void {
        $before = $this->snapshot($scenario);

        /*
         * Usar administración permite comprobar el rechazo
         * del servicio sin confundirlo con una denegación
         * de permisos del actor.
         */
        $actor = $this->userWithRole('ADMINISTRATOR');

        $this->submit($actor, $scenario['route'])
            ->assertRedirect(
                $this->detailUrl($scenario['route'])
            )
            ->assertSessionHasErrors([
                'activation' => $message,
            ]);

        $this->assertSame(
            $before,
            $this->snapshot($scenario)
        );
    }
}