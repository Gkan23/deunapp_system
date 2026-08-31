<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\IncidentStatus;
use App\Models\IncidentType;
use App\Models\Role;
use App\Models\Route as DeliveryRoute;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentCreationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_create_an_incident(): void
    {
        $shipment = Shipment::factory()->create();

        $this->postJson(
            route(
                'shipments.incidents.store',
                $shipment
            ),
            [
                'incident_type' => 'DELAY',
                'description' =>
                    'The delivery is delayed.',
            ]
        )->assertUnauthorized();
    }

    public function test_the_customer_can_report_an_incident_for_their_shipment(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $response = $this
            ->actingAs($customer->user)
            ->postJson(
                route(
                    'shipments.incidents.store',
                    $shipment
                ),
                [
                    'incident_type' => ' delay ',
                    'description' =>
                        '  The package has not arrived.  ',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Incident created successfully.'
            )
            ->assertJsonPath(
                'incident.shipment_id',
                $shipment->id
            )
            ->assertJsonPath(
                'incident.description',
                'The package has not arrived.'
            )
            ->assertJsonPath(
                'incident.incident_type.type_name',
                'DELAY'
            )
            ->assertJsonPath(
                'incident.incident_status.status_name',
                'OPEN'
            );

        $incidentId = $response->json(
            'incident.id'
        );

        $this->assertDatabaseHas('incidents', [
            'id' => $incidentId,
            'shipment_id' => $shipment->id,
            'reported_by_user_id' =>
                $customer->user->id,
            'incident_type_id' =>
                $this->incidentType('DELAY')->id,
            'incident_status_id' =>
                $this->incidentStatus('OPEN')->id,
            'description' =>
                'The package has not arrived.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' =>
                $customer->user->id,
            'table_name' => 'incidents',
            'record_id' => $incidentId,
            'action_type' =>
                'INCIDENT_CREATED',
        ]);
    }

    public function test_the_assigned_courier_can_report_an_incident(): void
    {
        $shipment = Shipment::factory()->create();

        $courier = Courier::factory()->create();

        $this->assignCourier(
            $shipment,
            $courier
        );

        $this->actingAs($courier->user)
            ->postJson(
                route(
                    'shipments.incidents.store',
                    $shipment
                ),
                [
                    'incident_type' =>
                        'VEHICLE_PROBLEM',
                    'description' =>
                        'The delivery vehicle has a mechanical problem.',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'incident.incident_type.type_name',
                'VEHICLE_PROBLEM'
            )
            ->assertJsonPath(
                'incident.reported_by.id',
                $courier->user->id
            );
    }

    public function test_support_and_administration_can_report_incidents(): void
    {
        $shipment = Shipment::factory()->create();

        foreach ([
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ] as $roleName) {
            $user = $this->userWithRole(
                $roleName
            );

            $this->actingAs($user)
                ->postJson(
                    route(
                        'shipments.incidents.store',
                        $shipment
                    ),
                    [
                        'incident_type' =>
                            'DAMAGED_PACKAGE',
                        'description' =>
                            'Visible damage was reported.',
                    ]
                )
                ->assertCreated()
                ->assertJsonPath(
                    'incident.reported_by.id',
                    $user->id
                );
        }

        $this->assertDatabaseCount(
            'incidents',
            2
        );
    }

    public function test_an_unrelated_customer_cannot_report_an_incident(): void
    {
        $owner = Customer::factory()->create();

        $unrelatedCustomer =
            Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $owner->id,
        ]);

        $this->actingAs(
            $unrelatedCustomer->user
        )
            ->postJson(
                route(
                    'shipments.incidents.store',
                    $shipment
                ),
                [
                    'incident_type' => 'DELAY',
                    'description' =>
                        'Unauthorized report.',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'incidents',
            0
        );
    }

    public function test_the_incident_type_is_required(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($customer->user)
            ->postJson(
                route(
                    'shipments.incidents.store',
                    $shipment
                ),
                [
                    'description' =>
                        'A type was not supplied.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'incident_type',
            ]);
    }

    public function test_the_incident_type_must_exist(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($customer->user)
            ->postJson(
                route(
                    'shipments.incidents.store',
                    $shipment
                ),
                [
                    'incident_type' =>
                        'UNKNOWN_TYPE',
                    'description' =>
                        'Unknown incident type.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'incident_type',
            ]);
    }

    public function test_the_description_is_required(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($customer->user)
            ->postJson(
                route(
                    'shipments.incidents.store',
                    $shipment
                ),
                [
                    'incident_type' =>
                        'DELAY',
                    'description' => '   ',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'description',
            ]);
    }

    public function test_an_inactive_user_cannot_report_an_incident(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $customer->user->update([
            'account_status_id' =>
                AccountStatus::query()
                    ->where(
                        'status_name',
                        'SUSPENDED'
                    )
                    ->firstOrFail()
                    ->id,
        ]);

        $this->actingAs(
            $customer->user->fresh()
        )
            ->postJson(
                route(
                    'shipments.incidents.store',
                    $shipment
                ),
                [
                    'incident_type' => 'DELAY',
                    'description' =>
                        'Inactive account attempt.',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'incidents',
            0
        );
    }

    public function test_an_unverified_user_cannot_report_an_incident(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $customer->user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this->actingAs(
            $customer->user->fresh()
        )
            ->postJson(
                route(
                    'shipments.incidents.store',
                    $shipment
                ),
                [
                    'incident_type' => 'DELAY',
                    'description' =>
                        'Unverified account attempt.',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'incidents',
            0
        );
    }

    private function incidentType(
        string $typeName
    ): IncidentType {
        return IncidentType::query()
            ->where(
                'type_name',
                $typeName
            )
            ->firstOrFail();
    }

    private function incidentStatus(
        string $statusName
    ): IncidentStatus {
        return IncidentStatus::query()
            ->where(
                'status_name',
                $statusName
            )
            ->firstOrFail();
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

    private function assignCourier(
        Shipment $shipment,
        Courier $courier
    ): RouteShipment {
        $activeRouteStatus =
            RouteStatus::query()
                ->where(
                    'status_name',
                    'ACTIVE'
                )
                ->firstOrFail();

        $route = DeliveryRoute::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' =>
                $activeRouteStatus->id,
            'route_date' => today(),
            'started_at' => now(),
            'finished_at' => null,
            'estimated_distance_km' => 10.00,
        ]);

        return RouteShipment::query()->create([
            'route_id' => $route->id,
            'shipment_id' => $shipment->id,
            'delivery_order' => 1,
            'delivery_status' =>
                'IN_PROGRESS',
        ]);
    }
}