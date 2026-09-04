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
use App\Models\VehicleStatus;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PortalRouteCompletionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);

        $this->withoutVite();

        $this->travelTo(
            now()->startOfDay()->addHours(17)
        );
    }

    public function test_a_guest_cannot_complete_a_route(): void
    {
        $scenario = $this->readyScenario();

        $before = $this->snapshot($scenario);

        $this->patch(
            $this->completionUrl($scenario['route'])
        )->assertRedirect(route('login.page'));

        $this->assertSame(
            $before,
            $this->snapshot($scenario)
        );
    }

    public function test_an_unverified_user_cannot_complete_a_route(): void
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

    public function test_authorized_roles_can_complete_a_ready_route(): void
    {
        foreach ([
            'DELIVERY_PROVIDER',
            'COURIER',
            'ADMINISTRATOR',
        ] as $roleName) {
            $scenario = $this->readyScenario();

            $actor = match ($roleName) {
                'DELIVERY_PROVIDER' =>
                    $scenario['provider']->user,

                'COURIER' =>
                    $scenario['courier']->user,

                default =>
                    $this->userWithRole('ADMINISTRATOR'),
            };

            $this->submit(
                $actor,
                $scenario['route']
            )
                ->assertRedirect(
                    $this->detailUrl($scenario['route'])
                )
                ->assertSessionHasNoErrors()
                ->assertSessionHas(
                    'status',
                    'La ruta fue completada correctamente.'
                );

            $route = $scenario['route']
                ->fresh('routeStatus');

            $courier = $scenario['courier']->fresh();

            $vehicle = $scenario['vehicle']
                ->fresh('vehicleStatus');

            $this->assertSame(
                'COMPLETED',
                $route->routeStatus->status_name
            );

            $this->assertNotNull(
                $route->finished_at
            );

            $this->assertTrue(
                $route->finished_at->equalTo(now())
            );

            $this->assertTrue(
                (bool) $courier->is_available
            );

            $this->assertSame(
                'AVAILABLE',
                $vehicle->vehicleStatus->status_name
            );

            $this->assertSame(
                'DELIVERED',
                $scenario['deliveredAssignment']
                    ->fresh()
                    ->delivery_status
            );

            $this->assertSame(
                'FAILED',
                $scenario['failedAssignment']
                    ->fresh()
                    ->delivery_status
            );
        }
    }

    public function test_unauthorized_users_cannot_complete_a_route(): void
    {
        $scenario = $this->readyScenario();

        $otherProvider =
            DeliveryProvider::factory()->create();

        $otherCourier = Courier::factory()->create([
            'delivery_provider_id' =>
                $scenario['provider']->id,
        ]);

        $actors = [
            $scenario['deliveredShipment']
                ->customer
                ->user,
            $this->userWithRole('SUPPORT_AGENT'),
            $otherProvider->user,
            $otherCourier->user,
        ];

        $before = $this->snapshot($scenario);

        foreach ($actors as $actor) {
            $this->submit(
                $actor,
                $scenario['route']
            )->assertForbidden();

            $this->assertSame(
                $before,
                $this->snapshot($scenario)
            );
        }
    }

    public function test_inactive_accounts_cannot_complete_a_route(): void
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
                'DELIVERY_PROVIDER' =>
                    $scenario['provider']->user,

                'COURIER' =>
                    $scenario['courier']->user,

                default =>
                    $this->userWithRole('ADMINISTRATOR'),
            };

            $actor->update([
                'account_status_id' =>
                    $suspendedStatus->id,
            ]);

            $before = $this->snapshot($scenario);

            $this->submit(
                $actor,
                $scenario['route']
            )->assertForbidden();

            $this->assertSame(
                $before,
                $this->snapshot($scenario)
            );
        }
    }

    public function test_only_an_active_route_can_be_completed(): void
    {
        foreach ([
            'PLANNED',
            'COMPLETED',
            'CANCELLED',
        ] as $statusName) {
            $scenario = $this->readyScenario();

            $scenario['route']->update([
                'route_status_id' =>
                    $this->routeStatusId($statusName),
            ]);

            $this->assertRejectedWithoutChanges(
                $scenario,
                'Only an active route can be completed.'
            );
        }
    }

    public function test_a_route_without_shipments_cannot_be_completed(): void
    {
        $provider =
            DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
            'is_active' => true,
            'is_available' => false,
        ]);

        $route = $this->createRoute(
            $courier,
            'ACTIVE'
        );

        $scenario = [
            'provider' => $provider,
            'courier' => $courier,
            'vehicle' => null,
            'route' => $route,
            'deliveredAssignment' => null,
            'failedAssignment' => null,
        ];

        $this->assertRejectedWithoutChanges(
            $scenario,
            'A route without shipments cannot be completed.'
        );
    }

    public function test_all_assignments_must_have_terminal_statuses(): void
    {
        foreach ([
            'PENDING',
            'IN_PROGRESS',
        ] as $deliveryStatus) {
            $scenario = $this->readyScenario();

            $scenario['deliveredAssignment']->update([
                'delivery_status' => $deliveryStatus,
            ]);

            $this->assertRejectedWithoutChanges(
                $scenario,
                'Every route shipment must be delivered or failed before completing the route.'
            );
        }
    }

    public function test_a_vehicle_must_still_belong_to_the_route_courier(): void
    {
        $scenario = $this->readyScenario();

        $otherCourier = Courier::factory()->create();

        $scenario['vehicle']->update([
            'courier_id' => $otherCourier->id,
        ]);

        $this->assertRejectedWithoutChanges(
            $scenario,
            'The route vehicle does not belong to the route courier.'
        );
    }

    public function test_a_vehicle_in_maintenance_keeps_its_status(): void
    {
        $scenario = $this->readyScenario();

        $maintenanceStatus =
            VehicleStatus::query()
                ->where(
                    'status_name',
                    'MAINTENANCE'
                )
                ->firstOrFail();

        $scenario['vehicle']->update([
            'vehicle_status_id' =>
                $maintenanceStatus->id,
        ]);

        $this->submit(
            $scenario['courier']->user,
            $scenario['route']
        )->assertSessionHasNoErrors();

        $this->assertSame(
            'COMPLETED',
            $scenario['route']
                ->fresh('routeStatus')
                ->routeStatus
                ->status_name
        );

        $this->assertSame(
            'MAINTENANCE',
            $scenario['vehicle']
                ->fresh('vehicleStatus')
                ->vehicleStatus
                ->status_name
        );
    }

    public function test_an_inactive_courier_remains_unavailable(): void
    {
        $scenario = $this->readyScenario();

        $scenario['courier']->update([
            'is_active' => false,
            'is_available' => false,
        ]);

        /*
         * La administración puede completar la ruta,
         * aunque el perfil del repartidor esté inactivo.
         */
        $administrator =
            $this->userWithRole('ADMINISTRATOR');

        $this->submit(
            $administrator,
            $scenario['route']
        )->assertSessionHasNoErrors();

        $this->assertSame(
            'COMPLETED',
            $scenario['route']
                ->fresh('routeStatus')
                ->routeStatus
                ->status_name
        );

        $this->assertFalse(
            (bool) $scenario['courier']
                ->fresh()
                ->is_available
        );
    }

    public function test_a_route_without_a_vehicle_can_be_completed(): void
    {
        $scenario = $this->readyScenario();

        $scenario['route']->update([
            'vehicle_id' => null,
        ]);

        $scenario['vehicle'] = null;

        $this->submit(
            $scenario['courier']->user,
            $scenario['route']
        )->assertSessionHasNoErrors();

        $this->assertSame(
            'COMPLETED',
            $scenario['route']
                ->fresh('routeStatus')
                ->routeStatus
                ->status_name
        );

        $this->assertTrue(
            (bool) $scenario['courier']
                ->fresh()
                ->is_available
        );
    }

    public function test_the_completion_form_respects_role_and_route_status(): void
    {
        $scenario = $this->readyScenario();

        $completionUrl =
            $this->completionUrl($scenario['route']);

        foreach ([
            $scenario['provider']->user,
            $scenario['courier']->user,
            $this->userWithRole('ADMINISTRATOR'),
        ] as $actor) {
            $this->actingAs($actor)
                ->get(
                    $this->detailUrl($scenario['route'])
                )
                ->assertOk()
                ->assertSee('Completar ruta')
                ->assertSee(
                    $completionUrl,
                    escape: false
                )
                ->assertSee(
                    'name="_method" value="PATCH"',
                    escape: false
                );
        }

        $this->actingAs(
            $this->userWithRole('SUPPORT_AGENT')
        )
            ->get(
                $this->detailUrl($scenario['route'])
            )
            ->assertOk()
            ->assertDontSee(
                $completionUrl,
                escape: false
            );

        $scenario['route']->update([
            'route_status_id' =>
                $this->routeStatusId('PLANNED'),
        ]);

        $this->actingAs(
            $scenario['courier']->user
        )
            ->get(
                $this->detailUrl($scenario['route'])
            )
            ->assertOk()
            ->assertDontSee(
                $completionUrl,
                escape: false
            )
            ->assertSee(
                route(
                    'portal.routes.activate',
                    $scenario['route']
                ),
                escape: false
            );
    }

    public function test_viewing_the_page_does_not_complete_the_route(): void
    {
        $scenario = $this->readyScenario();

        $before = $this->snapshot($scenario);

        $this->actingAs(
            $scenario['courier']->user
        )
            ->get(
                $this->detailUrl($scenario['route'])
            )
            ->assertOk()
            ->assertSee(
                $this->completionUrl(
                    $scenario['route']
                ),
                escape: false
            );

        $this->assertSame(
            $before,
            $this->snapshot($scenario)
        );
    }

    public function test_repeated_completion_is_rejected_without_changes(): void
    {
        $scenario = $this->readyScenario();

        $actor = $scenario['courier']->user;

        $this->submit(
            $actor,
            $scenario['route']
        )->assertSessionHasNoErrors();

        $before = $this->snapshot($scenario);

        $this->travel(5)->minutes();

        $this->submit(
            $actor,
            $scenario['route']
        )
            ->assertRedirect(
                $this->detailUrl($scenario['route'])
            )
            ->assertSessionHasErrors([
                'completion' =>
                    'Only an active route can be completed.',
            ]);

        $this->assertSame(
            $before,
            $this->snapshot($scenario)
        );

        $this->actingAs($actor)
            ->get(
                $this->detailUrl($scenario['route'])
            )
            ->assertOk()
            ->assertSee(
                'No fue posible completar la ruta.'
            )
            ->assertSee(
                'Only an active route can be completed.'
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function readyScenario(): array
    {
        $provider =
            DeliveryProvider::factory()->create([
                'is_active' => true,
            ]);

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
            'is_active' => true,
            'is_available' => false,
        ]);

        $vehicle = Vehicle::factory()->create([
            'courier_id' => $courier->id,
            'vehicle_status_id' =>
                VehicleStatus::query()
                    ->where(
                        'status_name',
                        'IN_USE'
                    )
                    ->firstOrFail()
                    ->id,
        ]);

        $route = $this->createRoute(
            $courier,
            'ACTIVE'
        );

        $route->update([
            'vehicle_id' => $vehicle->id,
            'started_at' => now()->subHours(4),
        ]);

        $deliveredShipment =
            Shipment::factory()->create();

        $failedShipment =
            Shipment::factory()->create();

        $deliveredAssignment =
            $this->assignShipment(
                $route,
                $deliveredShipment,
                1,
                'DELIVERED'
            );

        $failedAssignment =
            $this->assignShipment(
                $route,
                $failedShipment,
                2,
                'FAILED'
            );

        return [
            'provider' => $provider,
            'courier' => $courier,
            'vehicle' => $vehicle,
            'route' => $route,
            'deliveredShipment' =>
                $deliveredShipment,
            'failedShipment' =>
                $failedShipment,
            'deliveredAssignment' =>
                $deliveredAssignment,
            'failedAssignment' =>
                $failedAssignment,
        ];
    }

    private function createRoute(
        Courier $courier,
        string $statusName
    ): DeliveryRoute {
        return DeliveryRoute::query()->create([
            'courier_id' => $courier->id,
            'vehicle_id' => null,
            'route_status_id' =>
                $this->routeStatusId($statusName),
            'route_date' => today(),
            'started_at' =>
                $statusName === 'ACTIVE'
                    ? now()->subHours(4)
                    : null,
            'finished_at' => null,
            'estimated_distance_km' => null,
        ]);
    }

    private function assignShipment(
        DeliveryRoute $route,
        Shipment $shipment,
        int $deliveryOrder,
        string $deliveryStatus
    ): RouteShipment {
        return RouteShipment::query()->create([
            'route_id' => $route->id,
            'shipment_id' => $shipment->id,
            'delivery_order' => $deliveryOrder,
            'delivery_status' => $deliveryStatus,
        ]);
    }

    private function routeStatusId(
        string $statusName
    ): int {
        return (int) RouteStatus::query()
            ->where(
                'status_name',
                $statusName
            )
            ->firstOrFail()
            ->id;
    }

    private function userWithRole(
        string $roleName
    ): User {
        return User::factory()->create([
            'role_id' => Role::query()
                ->where(
                    'role_name',
                    $roleName
                )
                ->firstOrFail()
                ->id,
        ]);
    }

    private function detailUrl(
        DeliveryRoute $route
    ): string {
        return route(
            'portal.routes.show',
            $route
        );
    }

    private function completionUrl(
        DeliveryRoute $route
    ): string {
        return route(
            'portal.routes.complete',
            $route
        );
    }

    private function submit(
        User $actor,
        DeliveryRoute $route
    ): TestResponse {
        return $this->actingAs($actor)
            ->from($this->detailUrl($route))
            ->patch(
                $this->completionUrl($route)
            );
    }

    /**
     * @param array<string, mixed> $scenario
     *
     * @return array<string, array<string, mixed>|null>
     */
    private function snapshot(
        array $scenario
    ): array {
        $snapshot = [];

        foreach ([
            'route',
            'courier',
            'vehicle',
            'deliveredAssignment',
            'failedAssignment',
        ] as $key) {
            $model = $scenario[$key] ?? null;

            $snapshot[$key] = $model === null
                ? null
                : $model
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

        $administrator =
            $this->userWithRole('ADMINISTRATOR');

        $this->submit(
            $administrator,
            $scenario['route']
        )
            ->assertRedirect(
                $this->detailUrl($scenario['route'])
            )
            ->assertSessionHasErrors([
                'completion' => $message,
            ]);

        $this->assertSame(
            $before,
            $this->snapshot($scenario)
        );
    }
}