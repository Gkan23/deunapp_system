<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Role;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleStatus;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalRouteCancellationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);

        $this->withoutVite();
    }

    public function test_a_guest_cannot_cancel_a_route_from_the_portal(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'PLANNED',
            deliveryStatuses: ['PENDING']
        );

        $this->patch(
            $this->cancellationUrl(
                $scenario['route']
            ),
            $this->validPayload()
        )->assertRedirect('/login');

        $this->assertRouteWasNotCancelled(
            $scenario
        );
    }

    public function test_an_unverified_user_cannot_cancel_a_route_from_the_portal(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'PLANNED',
            deliveryStatuses: ['PENDING']
        );

        $user = $scenario['provider']->user;

        $user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this
            ->actingAs($user)
            ->patch(
                $this->cancellationUrl(
                    $scenario['route']
                ),
                $this->validPayload()
            )
            ->assertRedirect(
                route('verification.notice')
            );

        $this->assertRouteWasNotCancelled(
            $scenario
        );
    }

    public function test_the_linked_provider_can_cancel_a_planned_route_from_the_portal(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'PLANNED',
            deliveryStatuses: [
                'PENDING',
                'PENDING',
            ]
        );

        $reason =
            'La ruta planificada ya no será necesaria.';

        $this
            ->actingAs(
                $scenario['provider']->user
            )
            ->from(
                $this->detailUrl(
                    $scenario['route']
                )
            )
            ->patch(
                $this->cancellationUrl(
                    $scenario['route']
                ),
                [
                    'reason' => $reason,
                ]
            )
            ->assertRedirect(
                $this->detailUrl(
                    $scenario['route']
                )
            )
            ->assertSessionHas(
                'status',
                'La ruta fue cancelada correctamente.'
            )
            ->assertSessionHasNoErrors();

        $this->assertRouteWasCancelled(
            scenario: $scenario,
            expectedIncidentCount: 2,
            expectedReason: $reason,
            cancelledBy:
                $scenario['provider']->user
        );
    }

    public function test_the_linked_provider_can_cancel_an_active_route_and_release_its_vehicle(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'ACTIVE',
            deliveryStatuses: [
                'IN_PROGRESS',
                'IN_PROGRESS',
            ],
            withVehicle: true
        );

        $reason =
            'La ruta activa fue cancelada por operaciones.';

        $this
            ->actingAs(
                $scenario['provider']->user
            )
            ->patch(
                $this->cancellationUrl(
                    $scenario['route']
                ),
                [
                    'reason' => $reason,
                ]
            )
            ->assertRedirect(
                $this->detailUrl(
                    $scenario['route']
                )
            )
            ->assertSessionHas(
                'status',
                'La ruta fue cancelada correctamente.'
            );

        $this->assertRouteWasCancelled(
            scenario: $scenario,
            expectedIncidentCount: 2,
            expectedReason: $reason,
            cancelledBy:
                $scenario['provider']->user
        );

        $this->assertNotNull(
            $scenario['vehicle']
        );

        $this->assertSame(
            'AVAILABLE',
            $scenario['vehicle']
                ->fresh()
                ->vehicleStatus
                ->status_name
        );
    }

    public function test_an_administrator_can_cancel_a_route_from_the_portal(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'PLANNED',
            deliveryStatuses: ['PENDING']
        );

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $reason =
            'La administración canceló la ruta.';

        $this
            ->actingAs($administrator)
            ->patch(
                $this->cancellationUrl(
                    $scenario['route']
                ),
                [
                    'reason' => $reason,
                ]
            )
            ->assertRedirect(
                $this->detailUrl(
                    $scenario['route']
                )
            )
            ->assertSessionHas(
                'status',
                'La ruta fue cancelada correctamente.'
            );

        $this->assertRouteWasCancelled(
            scenario: $scenario,
            expectedIncidentCount: 1,
            expectedReason: $reason,
            cancelledBy: $administrator
        );
    }

    public function test_the_assigned_courier_cannot_cancel_the_route_from_the_portal(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'ACTIVE',
            deliveryStatuses: ['IN_PROGRESS']
        );

        $this
            ->actingAs(
                $scenario['courier']->user
            )
            ->patch(
                $this->cancellationUrl(
                    $scenario['route']
                ),
                $this->validPayload()
            )
            ->assertForbidden();

        $this->assertRouteWasNotCancelled(
            $scenario
        );
    }

    public function test_customer_and_support_roles_cannot_cancel_a_route(): void
    {
        foreach (
            [
                'CUSTOMER',
                'SUPPORT_AGENT',
            ] as $roleName
        ) {
            $scenario = $this->createScenario(
                routeStatus: 'PLANNED',
                deliveryStatuses: ['PENDING']
            );

            $user = $this->userWithRole(
                $roleName
            );

            $this
                ->actingAs($user)
                ->patch(
                    $this->cancellationUrl(
                        $scenario['route']
                    ),
                    $this->validPayload()
                )
                ->assertForbidden();

            $this->assertRouteWasNotCancelled(
                $scenario
            );
        }
    }

    public function test_an_unrelated_provider_cannot_cancel_the_route(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'PLANNED',
            deliveryStatuses: ['PENDING']
        );

        $unrelatedProvider =
            DeliveryProvider::factory()
                ->create();

        $this
            ->actingAs(
                $unrelatedProvider->user
            )
            ->patch(
                $this->cancellationUrl(
                    $scenario['route']
                ),
                $this->validPayload()
            )
            ->assertForbidden();

        $this->assertRouteWasNotCancelled(
            $scenario
        );
    }

    public function test_an_inactive_provider_account_cannot_cancel_the_route(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'PLANNED',
            deliveryStatuses: ['PENDING']
        );

        $inactiveStatus =
            AccountStatus::query()
                ->where(
                    'status_name',
                    '!=',
                    'ACTIVE'
                )
                ->firstOrFail();

        $scenario['provider']->user->update([
            'account_status_id' =>
                $inactiveStatus->id,
        ]);

        $this
            ->actingAs(
                $scenario['provider']->user
            )
            ->patch(
                $this->cancellationUrl(
                    $scenario['route']
                ),
                $this->validPayload()
            )
            ->assertForbidden();

        $this->assertRouteWasNotCancelled(
            $scenario
        );
    }

    public function test_the_cancellation_reason_is_required(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'ACTIVE',
            deliveryStatuses: ['IN_PROGRESS']
        );

        $this
            ->actingAs(
                $scenario['provider']->user
            )
            ->from(
                $this->detailUrl(
                    $scenario['route']
                )
            )
            ->patch(
                $this->cancellationUrl(
                    $scenario['route']
                ),
                [
                    'reason' => '   ',
                ]
            )
            ->assertRedirect(
                $this->detailUrl(
                    $scenario['route']
                )
            )
            ->assertSessionHasErrors([
                'reason',
            ]);

        $this->assertRouteWasNotCancelled(
            $scenario
        );
    }

    public function test_the_cancellation_reason_cannot_exceed_two_thousand_characters(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'PLANNED',
            deliveryStatuses: ['PENDING']
        );

        $this
            ->actingAs(
                $scenario['provider']->user
            )
            ->from(
                $this->detailUrl(
                    $scenario['route']
                )
            )
            ->patch(
                $this->cancellationUrl(
                    $scenario['route']
                ),
                [
                    'reason' => str_repeat(
                        'a',
                        2001
                    ),
                ]
            )
            ->assertRedirect(
                $this->detailUrl(
                    $scenario['route']
                )
            )
            ->assertSessionHasErrors([
                'reason',
            ]);

        $this->assertRouteWasNotCancelled(
            $scenario
        );
    }

    public function test_completed_and_cancelled_routes_cannot_be_cancelled_from_the_portal(): void
    {
        foreach (
            [
                'COMPLETED',
                'CANCELLED',
            ] as $routeStatusName
        ) {
            $deliveryStatuses =
                $routeStatusName === 'COMPLETED'
                    ? ['DELIVERED']
                    : [];

            $scenario = $this->createScenario(
                routeStatus: $routeStatusName,
                deliveryStatuses:
                    $deliveryStatuses
            );

            $this
                ->actingAs(
                    $scenario['provider']->user
                )
                ->from(
                    $this->detailUrl(
                        $scenario['route']
                    )
                )
                ->patch(
                    $this->cancellationUrl(
                        $scenario['route']
                    ),
                    $this->validPayload()
                )
                ->assertRedirect(
                    $this->detailUrl(
                        $scenario['route']
                    )
                )
                ->assertSessionHasErrors([
                    'cancellation' =>
                        'Only planned or active routes can be cancelled.',
                ]);

            $this->assertSame(
                $routeStatusName,
                $scenario['route']
                    ->fresh()
                    ->routeStatus
                    ->status_name
            );

            $this->assertDatabaseCount(
                'incidents',
                0
            );
        }
    }

    public function test_a_delivery_service_inconsistency_does_not_partially_cancel_the_route(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'ACTIVE',
            deliveryStatuses: ['IN_PROGRESS']
        );

        $scenario['items'][0]
            ['deliveryService']
            ->delete();

        $this
            ->actingAs(
                $scenario['provider']->user
            )
            ->from(
                $this->detailUrl(
                    $scenario['route']
                )
            )
            ->patch(
                $this->cancellationUrl(
                    $scenario['route']
                ),
                $this->validPayload()
            )
            ->assertRedirect(
                $this->detailUrl(
                    $scenario['route']
                )
            )
            ->assertSessionHasErrors([
                'cancellation' =>
                    'Every pending route shipment must have a delivery service.',
            ]);

        $this->assertSame(
            'ACTIVE',
            $scenario['route']
                ->fresh()
                ->routeStatus
                ->status_name
        );

        $this->assertNull(
            $scenario['route']
                ->fresh()
                ->finished_at
        );

        $this->assertSame(
            'IN_PROGRESS',
            $scenario['items'][0]
                ['routeShipment']
                ->fresh()
                ->delivery_status
        );

        $this->assertFalse(
            $scenario['courier']
                ->fresh()
                ->is_available
        );

        $this->assertDatabaseCount(
            'incidents',
            0
        );
    }

    public function test_the_cancellation_form_is_only_visible_to_authorized_roles(): void
    {
        $scenario = $this->createScenario(
            routeStatus: 'PLANNED',
            deliveryStatuses: ['PENDING']
        );

        $cancellationUrl =
            $this->cancellationUrl(
                $scenario['route']
            );

        $this
            ->actingAs(
                $scenario['provider']->user
            )
            ->get(
                $this->detailUrl(
                    $scenario['route']
                )
            )
            ->assertOk()
            ->assertSee('Cancelar ruta')
            ->assertSee(
                'Motivo de cancelación'
            )
            ->assertSee(
                $cancellationUrl,
                escape: false
            );

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this
            ->actingAs($administrator)
            ->get(
                $this->detailUrl(
                    $scenario['route']
                )
            )
            ->assertOk()
            ->assertSee('Cancelar ruta')
            ->assertSee(
                $cancellationUrl,
                escape: false
            );

        foreach (
            [
                $scenario['courier']->user,
                $this->userWithRole(
                    'SUPPORT_AGENT'
                ),
            ] as $unauthorizedUser
        ) {
            $this
                ->actingAs($unauthorizedUser)
                ->get(
                    $this->detailUrl(
                        $scenario['route']
                    )
                )
                ->assertOk()
                ->assertDontSee(
                    $cancellationUrl,
                    escape: false
                );
        }
    }

    public function test_terminal_routes_do_not_display_the_cancellation_form(): void
    {
        foreach (
            [
                'COMPLETED',
                'CANCELLED',
            ] as $routeStatusName
        ) {
            $scenario = $this->createScenario(
                routeStatus: $routeStatusName,
                deliveryStatuses:
                    $routeStatusName === 'COMPLETED'
                        ? ['DELIVERED']
                        : []
            );

            $this
                ->actingAs(
                    $scenario['provider']->user
                )
                ->get(
                    $this->detailUrl(
                        $scenario['route']
                    )
                )
                ->assertOk()
                ->assertDontSee(
                    $this->cancellationUrl(
                        $scenario['route']
                    ),
                    escape: false
                );
        }
    }

    /**
     * @param array<int, string> $deliveryStatuses
     *
     * @return array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     vehicle: Vehicle|null,
     *     route: Route,
     *     items: array<int, array{
     *         shipment: Shipment,
     *         routeShipment: RouteShipment,
     *         deliveryService: DeliveryService
     *     }>
     * }
     */
    private function createScenario(
        string $routeStatus,
        array $deliveryStatuses,
        bool $withVehicle = false
    ): array {
        $provider =
            DeliveryProvider::factory()
                ->create();

        $isActiveRoute =
            $routeStatus === 'ACTIVE';

        $courier = Courier::factory()
            ->for($provider)
            ->create([
                'is_active' => true,
                'is_available' =>
                    ! $isActiveRoute,
            ]);

        $vehicle = null;

        if ($withVehicle) {
            $vehicleStatusName =
                $isActiveRoute
                    ? 'IN_USE'
                    : 'AVAILABLE';

            $vehicle = Vehicle::factory()
                ->for($courier)
                ->create([
                    'vehicle_status_id' =>
                        $this->vehicleStatusId(
                            $vehicleStatusName
                        ),
                ]);
        }

        $isStarted = in_array(
            $routeStatus,
            [
                'ACTIVE',
                'COMPLETED',
            ],
            true
        );

        $isFinished = in_array(
            $routeStatus,
            [
                'COMPLETED',
                'CANCELLED',
            ],
            true
        );

        $route = Route::query()->create([
            'courier_id' => $courier->id,
            'vehicle_id' => $vehicle?->id,
            'route_status_id' =>
                $this->routeStatusId(
                    $routeStatus
                ),
            'route_date' =>
                today()->toDateString(),
            'started_at' =>
                $isStarted
                    ? now()->subHour()
                    : null,
            'finished_at' =>
                $isFinished
                    ? now()->subMinutes(10)
                    : null,
            'estimated_distance_km' =>
                8.50,
        ]);

        $items = [];

        foreach (
            $deliveryStatuses as
            $index => $deliveryStatus
        ) {
            $shipment =
                Shipment::factory()->create();

            $trip = Trip::factory()
                ->for($provider)
                ->create([
                    'status' => 'USED',
                    'used_at' =>
                        now()->subHours(2),
                ]);

            $serviceStatus = match (
                $deliveryStatus
            ) {
                'PENDING',
                'FAILED',
                'CANCELLED' => 'ASSIGNED',
                'IN_PROGRESS' => 'IN_PROGRESS',
                'DELIVERED' => 'COMPLETED',
            };

            $deliveryService =
                DeliveryService::factory()
                    ->create([
                        'shipment_id' =>
                            $shipment->id,
                        'trip_id' =>
                            $trip->id,
                        'status' =>
                            $serviceStatus,
                        'accepted_at' =>
                            now()->subHours(2),
                        'started_at' =>
                            $serviceStatus
                            === 'IN_PROGRESS'
                                ? now()->subHour()
                                : null,
                        'completed_at' =>
                            $serviceStatus
                            === 'COMPLETED'
                                ? now()
                                    ->subMinutes(15)
                                : null,
                        'cancelled_at' =>
                            null,
                    ]);

            $routeShipment =
                RouteShipment::query()
                    ->create([
                        'route_id' =>
                            $route->id,
                        'shipment_id' =>
                            $shipment->id,
                        'delivery_order' =>
                            $index + 1,
                        'delivery_status' =>
                            $deliveryStatus,
                    ]);

            $items[] = [
                'shipment' => $shipment,
                'routeShipment' =>
                    $routeShipment,
                'deliveryService' =>
                    $deliveryService,
            ];
        }

        return [
            'provider' => $provider,
            'courier' => $courier,
            'vehicle' => $vehicle,
            'route' => $route,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'reason' =>
                'La ruta fue cancelada por operaciones.',
        ];
    }

    private function detailUrl(
        Route $route
    ): string {
        return route(
            'portal.routes.show',
            $route
        );
    }

    private function cancellationUrl(
        Route $route
    ): string {
        return route(
            'portal.routes.cancel',
            $route
        );
    }

    private function routeStatusId(
        string $statusName
    ): int {
        return RouteStatus::query()
            ->where(
                'status_name',
                $statusName
            )
            ->firstOrFail()
            ->id;
    }

    private function vehicleStatusId(
        string $statusName
    ): int {
        return VehicleStatus::query()
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

    /**
     * @param array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     vehicle: Vehicle|null,
     *     route: Route,
     *     items: array<int, array{
     *         shipment: Shipment,
     *         routeShipment: RouteShipment,
     *         deliveryService: DeliveryService
     *     }>
     * } $scenario
     */
    private function assertRouteWasCancelled(
        array $scenario,
        int $expectedIncidentCount,
        string $expectedReason,
        User $cancelledBy
    ): void {
        $route =
            $scenario['route']->fresh();

        $courier =
            $scenario['courier']->fresh();

        $this->assertSame(
            'CANCELLED',
            $route
                ->routeStatus
                ->status_name
        );

        $this->assertNotNull(
            $route->finished_at
        );

        $this->assertTrue(
            $courier->is_available
        );

        foreach (
            $scenario['items'] as $item
        ) {
            $this->assertSame(
                'CANCELLED',
                $item['routeShipment']
                    ->fresh()
                    ->delivery_status
            );

            $deliveryService =
                $item['deliveryService']
                    ->fresh();

            $this->assertSame(
                'ASSIGNED',
                $deliveryService->status
            );

            $this->assertNull(
                $deliveryService->started_at
            );

            $this->assertNull(
                $deliveryService->completed_at
            );

            $this->assertNull(
                $deliveryService->cancelled_at
            );
        }

        $this->assertDatabaseCount(
            'incidents',
            $expectedIncidentCount
        );

        if ($expectedIncidentCount > 0) {
            $this->assertDatabaseHas(
                'incidents',
                [
                    'reported_by_user_id' =>
                        $cancelledBy->id,
                    'description' =>
                        $expectedReason,
                ]
            );
        }
    }

    /**
     * @param array{
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     vehicle: Vehicle|null,
     *     route: Route,
     *     items: array<int, array{
     *         shipment: Shipment,
     *         routeShipment: RouteShipment,
     *         deliveryService: DeliveryService
     *     }>
     * } $scenario
     */
    private function assertRouteWasNotCancelled(
        array $scenario
    ): void {
        $route =
            $scenario['route']->fresh();

        $this->assertNotSame(
            'CANCELLED',
            $route
                ->routeStatus
                ->status_name
        );

        foreach (
            $scenario['items'] as $item
        ) {
            $this->assertNotSame(
                'CANCELLED',
                $item['routeShipment']
                    ->fresh()
                    ->delivery_status
            );
        }

        $this->assertDatabaseCount(
            'incidents',
            0
        );
    }
}