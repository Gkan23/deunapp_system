<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Incident;
use App\Models\IncidentStatus;
use App\Models\IncidentType;
use App\Models\Role;
use App\Models\Route as DeliveryRoute;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_access_incident_endpoints(): void
    {
        $scenario = $this->createIncidentScenario();

        $incident = $scenario['incident'];

        $this->getJson(
            route('incidents.index')
        )->assertUnauthorized();

        $this->getJson(
            route('incidents.show', $incident)
        )->assertUnauthorized();

        $this->patchJson(
            route('incidents.status.update', $incident),
            [
                'status' => 'IN_REVIEW',
            ]
        )->assertUnauthorized();
    }

    public function test_a_customer_only_lists_their_incidents(): void
    {
        $scenario = $this->createIncidentScenario();
        $otherScenario = $this->createIncidentScenario();

        $response = $this
            ->actingAs($scenario['customer']->user)
            ->getJson(route('incidents.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $scenario['incident']->id
            );

        $this->assertNotSame(
            $scenario['incident']->id,
            $otherScenario['incident']->id
        );
    }

    public function test_a_provider_only_lists_linked_incidents(): void
    {
        $scenario = $this->createIncidentScenario();

        $this->createIncidentScenario();

        $response = $this
            ->actingAs($scenario['provider']->user)
            ->getJson(route('incidents.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $scenario['incident']->id
            );
    }

    public function test_a_courier_only_lists_assigned_incidents(): void
    {
        $scenario = $this->createIncidentScenario();

        $this->createIncidentScenario();

        $response = $this
            ->actingAs($scenario['courier']->user)
            ->getJson(route('incidents.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $scenario['incident']->id
            );
    }

    public function test_support_and_administration_list_all_incidents(): void
    {
        $this->createIncidentScenario();
        $this->createIncidentScenario();

        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this
            ->actingAs($supportAgent)
            ->getJson(route('incidents.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this
            ->actingAs($administrator)
            ->getJson(route('incidents.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_incident_details_respect_the_policy_scope(): void
    {
        $scenario = $this->createIncidentScenario();
        $unrelated = $this->createIncidentScenario();

        $incident = $scenario['incident'];

        $allowedUsers = [
            $scenario['customer']->user,
            $scenario['provider']->user,
            $scenario['courier']->user,
            $this->userWithRole('SUPPORT_AGENT'),
            $this->userWithRole('ADMINISTRATOR'),
        ];

        foreach ($allowedUsers as $allowedUser) {
            $this
                ->actingAs($allowedUser)
                ->getJson(
                    route('incidents.show', $incident)
                )
                ->assertOk()
                ->assertJsonPath(
                    'incident.id',
                    $incident->id
                );
        }

        $deniedUsers = [
            $unrelated['customer']->user,
            $unrelated['provider']->user,
            $unrelated['courier']->user,
        ];

        foreach ($deniedUsers as $deniedUser) {
            $this
                ->actingAs($deniedUser)
                ->getJson(
                    route('incidents.show', $incident)
                )
                ->assertForbidden();
        }
    }

    public function test_support_and_administration_can_update_incident_statuses(): void
    {
        /*
         * Soporte puede pasar una incidencia de OPEN
         * a IN_REVIEW.
         */
        $openScenario = $this->createIncidentScenario(
            'OPEN'
        );

        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $this
            ->actingAs($supportAgent)
            ->patchJson(
                route(
                    'incidents.status.update',
                    $openScenario['incident']
                ),
                [
                    'status' => 'in_review',
                    'comment' => 'The incident is being reviewed.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Incident status updated successfully.'
            )
            ->assertJsonPath(
                'incident.incident_status.status_name',
                'IN_REVIEW'
            );

        /*
         * Administración puede pasar una incidencia
         * de IN_REVIEW a RESOLVED.
         */
        $reviewScenario = $this->createIncidentScenario(
            'IN_REVIEW'
        );

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this
            ->actingAs($administrator)
            ->patchJson(
                route(
                    'incidents.status.update',
                    $reviewScenario['incident']
                ),
                [
                    'status' => 'RESOLVED',
                    'comment' => 'The reported problem was resolved.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'incident.incident_status.status_name',
                'RESOLVED'
            );

        $this->assertDatabaseCount('audit_logs', 2);

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' => $supportAgent->id,
            'table_name' => 'incidents',
            'record_id' => $openScenario['incident']->id,
            'action_type' => 'STATUS_CHANGED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' => $administrator->id,
            'table_name' => 'incidents',
            'record_id' => $reviewScenario['incident']->id,
            'action_type' => 'STATUS_CHANGED',
        ]);
    }

    public function test_operational_users_cannot_update_incident_statuses(): void
    {
        $scenario = $this->createIncidentScenario();

        $payload = [
            'status' => 'IN_REVIEW',
            'comment' => 'Unauthorized update.',
        ];

        $operationalUsers = [
            $scenario['customer']->user,
            $scenario['provider']->user,
            $scenario['courier']->user,
        ];

        foreach ($operationalUsers as $user) {
            $this
                ->actingAs($user)
                ->patchJson(
                    route(
                        'incidents.status.update',
                        $scenario['incident']
                    ),
                    $payload
                )
                ->assertForbidden();
        }

        $this->assertSame(
            'OPEN',
            $scenario['incident']
                ->fresh()
                ->incidentStatus
                ->status_name
        );

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_incident_status_data_is_validated(): void
    {
        $scenario = $this->createIncidentScenario();

        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        /*
         * El estado es obligatorio.
         */
        $this
            ->actingAs($supportAgent)
            ->patchJson(
                route(
                    'incidents.status.update',
                    $scenario['incident']
                ),
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);

        /*
         * El estado debe existir en la tabla
         * incident_statuses.
         */
        $this
            ->actingAs($supportAgent)
            ->patchJson(
                route(
                    'incidents.status.update',
                    $scenario['incident']
                ),
                [
                    'status' => 'UNKNOWN_STATUS',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);

        /*
         * El comentario no puede superar
         * 2000 caracteres.
         */
        $this
            ->actingAs($supportAgent)
            ->patchJson(
                route(
                    'incidents.status.update',
                    $scenario['incident']
                ),
                [
                    'status' => 'IN_REVIEW',
                    'comment' => str_repeat('C', 2001),
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'comment',
            ]);

        $this->assertSame(
            'OPEN',
            $scenario['incident']
                ->fresh()
                ->incidentStatus
                ->status_name
        );
    }

    public function test_incident_domain_errors_are_returned_as_unprocessable(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        /*
         * No se permite pasar directamente
         * de OPEN a CLOSED.
         */
        $openScenario = $this->createIncidentScenario(
            'OPEN'
        );

        $this
            ->actingAs($administrator)
            ->patchJson(
                route(
                    'incidents.status.update',
                    $openScenario['incident']
                ),
                [
                    'status' => 'CLOSED',
                    'comment' => 'Invalid direct closure.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The incident status transition from OPEN to CLOSED is not allowed.'
            );

        /*
         * Para resolver una incidencia es obligatorio
         * proporcionar un comentario.
         */
        $reviewScenario = $this->createIncidentScenario(
            'IN_REVIEW'
        );

        $this
            ->actingAs($administrator)
            ->patchJson(
                route(
                    'incidents.status.update',
                    $reviewScenario['incident']
                ),
                [
                    'status' => 'RESOLVED',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'A comment is required to resolve an incident.'
            );

        $this->assertDatabaseCount('audit_logs', 0);
    }

    /**
     * Crear un escenario completo que relacione:
     *
     * Cliente -> Envío -> Servicio -> Viaje -> Proveedor
     *                     |
     *                     -> Ruta -> Repartidor
     *                     |
     *                     -> Incidencia
     *
     * @return array{
     *     customer: Customer,
     *     provider: DeliveryProvider,
     *     courier: Courier,
     *     shipment: Shipment,
     *     incident: Incident
     * }
     */
    private function createIncidentScenario(
        string $statusName = 'OPEN'
    ): array {
        $customer = Customer::factory()->create();

        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
        ]);

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $trip = Trip::factory()->create([
            'delivery_provider_id' => $provider->id,
            'status' => 'USED',
            'used_at' => now()->subHour(),
        ]);

        DeliveryService::factory()->create([
            'customer_id' => $customer->id,
            'shipment_id' => $shipment->id,
            'trip_id' => $trip->id,
            'trip_type_id' => $trip->trip_type_id,
            'status' => 'ASSIGNED',
            'accepted_at' => now()->subHours(2),
        ]);

        $plannedStatus = RouteStatus::query()
            ->where('status_name', 'PLANNED')
            ->firstOrFail();

        $route = DeliveryRoute::query()->create([
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

        $incidentStatus = IncidentStatus::query()
            ->where('status_name', $statusName)
            ->firstOrFail();

        $incidentType = IncidentType::query()
            ->where('type_name', 'DELIVERY_FAILED')
            ->firstOrFail();

        $incident = Incident::query()->create([
            'shipment_id' => $shipment->id,
            'reported_by_user_id' => $courier->user_id,
            'incident_type_id' => $incidentType->id,
            'incident_status_id' => $incidentStatus->id,
            'description' => 'The delivery attempt could not be completed.',
            'reported_at' => now()->subHour(),
        ]);

        return [
            'customer' => $customer,
            'provider' => $provider,
            'courier' => $courier,
            'shipment' => $shipment,
            'incident' => $incident,
        ];
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