<?php

namespace Tests\Feature\Http\Controllers;

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
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailDeliveryAttemptControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_report_a_failed_attempt(): void
    {
        $scenario = $this->createActiveAttempt();

        $this->patchJson(
            route(
                'route-shipments.fail-attempt',
                $scenario['routeShipment']
            ),
            $this->validPayload()
        )->assertUnauthorized();

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_the_assigned_courier_can_report_a_failed_attempt(): void
    {
        $scenario = $this->createActiveAttempt();

        $response = $this
            ->actingAs($scenario['courier']->user)
            ->patchJson(
                route(
                    'route-shipments.fail-attempt',
                    $scenario['routeShipment']
                ),
                $this->validPayload()
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Failed delivery attempt registered successfully.'
            )
            ->assertJsonPath(
                'incident.incident_type.type_name',
                'RECIPIENT_ABSENT'
            )
            ->assertJsonPath(
                'incident.incident_status.status_name',
                'OPEN'
            );

        $this->assertDatabaseHas(
            'route_shipments',
            [
                'id' =>
                    $scenario['routeShipment']->id,
                'delivery_status' => 'FAILED',
            ]
        );

        $this->assertDatabaseHas(
            'delivery_services',
            [
                'id' =>
                    $scenario['deliveryService']->id,
                'status' => 'ASSIGNED',
                'started_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
            ]
        );

        $this->assertDatabaseHas(
            'incidents',
            [
                'shipment_id' =>
                    $scenario['routeShipment']
                        ->shipment_id,
                'reported_by_user_id' =>
                    $scenario['courier']->user_id,
                'description' =>
                    'The recipient was not available.',
            ]
        );
    }

    public function test_an_unassigned_courier_cannot_report_a_failed_attempt(): void
    {
        $scenario = $this->createActiveAttempt();

        $unassignedCourier = Courier::factory()
            ->create();

        $this
            ->actingAs($unassignedCourier->user)
            ->patchJson(
                route(
                    'route-shipments.fail-attempt',
                    $scenario['routeShipment']
                ),
                $this->validPayload()
            )
            ->assertForbidden();

        $this->assertNoFailureChanges($scenario);
    }

    public function test_an_administrator_cannot_report_for_the_courier(): void
    {
        $scenario = $this->createActiveAttempt();

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this
            ->actingAs($administrator)
            ->patchJson(
                route(
                    'route-shipments.fail-attempt',
                    $scenario['routeShipment']
                ),
                $this->validPayload()
            )
            ->assertForbidden();

        $this->assertNoFailureChanges($scenario);
    }

    public function test_the_incident_type_is_validated(): void
    {
        $scenario = $this->createActiveAttempt();

        $payload = $this->validPayload();

        $payload['incident_type'] = 'DELAY';

        $this
            ->actingAs($scenario['courier']->user)
            ->patchJson(
                route(
                    'route-shipments.fail-attempt',
                    $scenario['routeShipment']
                ),
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'incident_type',
            ]);

        $this->assertNoFailureChanges($scenario);
    }

    public function test_the_description_is_required(): void
    {
        $scenario = $this->createActiveAttempt();

        $payload = $this->validPayload();

        $payload['description'] = '   ';

        $this
            ->actingAs($scenario['courier']->user)
            ->patchJson(
                route(
                    'route-shipments.fail-attempt',
                    $scenario['routeShipment']
                ),
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'description',
            ]);

        $this->assertNoFailureChanges($scenario);
    }

    public function test_domain_errors_are_returned_as_unprocessable(): void
    {
        $scenario = $this->createActiveAttempt();

        $plannedStatus = RouteStatus::query()
            ->where('status_name', 'PLANNED')
            ->firstOrFail();

        $scenario['route']->update([
            'route_status_id' => $plannedStatus->id,
            'started_at' => null,
        ]);

        $this
            ->actingAs($scenario['courier']->user)
            ->patchJson(
                route(
                    'route-shipments.fail-attempt',
                    $scenario['routeShipment']
                ),
                $this->validPayload()
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Only shipments from an active route can be marked as failed.'
            );

        $this->assertNoFailureChanges($scenario);
    }

    /**
     * @return array{
     *     route: Route,
     *     courier: Courier,
     *     routeShipment: RouteShipment,
     *     deliveryService: DeliveryService
     * }
     */
    private function createActiveAttempt(): array
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
            'is_active' => true,
            'is_available' => false,
        ]);

        $shipment = Shipment::factory()->create();

        $trip = Trip::factory()->create([
            'delivery_provider_id' => $provider->id,
            'status' => 'USED',
            'used_at' => now()->subHours(2),
        ]);

        $deliveryService = DeliveryService::factory()
            ->create([
                'shipment_id' => $shipment->id,
                'trip_id' => $trip->id,
                'status' => 'IN_PROGRESS',
                'accepted_at' => now()->subHours(2),
                'started_at' => now()->subHour(),
                'completed_at' => null,
                'cancelled_at' => null,
            ]);

        $activeStatus = RouteStatus::query()
            ->where('status_name', 'ACTIVE')
            ->firstOrFail();

        $route = Route::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' => $activeStatus->id,
            'route_date' => today(),
            'started_at' => now()->subHour(),
            'finished_at' => null,
            'estimated_distance_km' => 8.50,
        ]);

        $routeShipment = RouteShipment::query()
            ->create([
                'route_id' => $route->id,
                'shipment_id' => $shipment->id,
                'delivery_order' => 1,
                'delivery_status' =>
                    'IN_PROGRESS',
            ]);

        return [
            'route' => $route,
            'courier' => $courier,
            'routeShipment' => $routeShipment,
            'deliveryService' => $deliveryService,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'incident_type' => 'RECIPIENT_ABSENT',
            'description' =>
                'The recipient was not available.',
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

    /**
     * @param array{
     *     route: Route,
     *     courier: Courier,
     *     routeShipment: RouteShipment,
     *     deliveryService: DeliveryService
     * } $scenario
     */
    private function assertNoFailureChanges(
        array $scenario
    ): void {
        $this->assertSame(
            'IN_PROGRESS',
            $scenario['routeShipment']
                ->fresh()
                ->delivery_status
        );

        $this->assertSame(
            'IN_PROGRESS',
            $scenario['deliveryService']
                ->fresh()
                ->status
        );

        $this->assertNotNull(
            $scenario['deliveryService']
                ->fresh()
                ->started_at
        );

        $this->assertDatabaseCount(
            'incidents',
            0
        );
    }
}
