<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\AuditLog;
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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class IncidentShowPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);

        $this->withoutVite();
    }

    public function test_a_guest_cannot_view_or_update_an_incident(): void
    {
        $incident = $this->createIncident();

        $this->get(
            route('portal.incidents.show', $incident)
        )->assertRedirect(
            route('login.page')
        );

        $this->patch(
            route('portal.incidents.status.update', $incident),
            [
                'status' => 'IN_REVIEW',
            ]
        )->assertRedirect(
            route('login.page')
        );

        $this->assertNoStatusChange($incident, 'OPEN');
    }

    public function test_an_unverified_user_cannot_view_or_update_an_incident(): void
    {
        $incident = $this->createIncident();

        $user = $this->userWithRole('SUPPORT_AGENT');

        $user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this->actingAs($user)
            ->get(
                route('portal.incidents.show', $incident)
            )
            ->assertRedirect(
                route('verification.notice')
            );

        $this->submitStatusChange(
            $user,
            $incident,
            [
                'status' => 'IN_REVIEW',
            ]
        )->assertRedirect(
            route('verification.notice')
        );

        $this->assertNoStatusChange($incident, 'OPEN');
    }

    public function test_a_customer_can_view_their_incident_without_the_status_form(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $incident = $this->createIncident(
            shipment: $shipment
        );

        $this->actingAs($customer->user)
            ->get(
                route('portal.incidents.show', $incident)
            )
            ->assertOk()
            ->assertViewIs('incidents.show')
            ->assertSee($incident->description)
            ->assertSee($shipment->tracking_code)
            ->assertSee($customer->user->name)
            ->assertViewHas('canManageStatus', false)
            ->assertDontSee(
                route(
                    'portal.incidents.status.update',
                    $incident
                ),
                escape: false
            );
    }

    public function test_an_unrelated_customer_cannot_view_the_incident(): void
    {
        $incident = $this->createIncident();

        $otherCustomer = Customer::factory()->create();

        $this->actingAs($otherCustomer->user)
            ->get(
                route('portal.incidents.show', $incident)
            )
            ->assertForbidden();
    }

    public function test_a_provider_can_only_view_incidents_related_to_their_trips(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $otherProvider = DeliveryProvider::factory()->create();

        $ownShipment = Shipment::factory()->create();
        $otherShipment = Shipment::factory()->create();

        $this->linkShipmentToProvider(
            $ownShipment,
            $provider
        );

        $this->linkShipmentToProvider(
            $otherShipment,
            $otherProvider
        );

        $ownIncident = $this->createIncident(
            shipment: $ownShipment
        );

        $otherIncident = $this->createIncident(
            shipment: $otherShipment
        );

        $this->actingAs($provider->user)
            ->get(
                route('portal.incidents.show', $ownIncident)
            )
            ->assertOk()
            ->assertViewHas('canManageStatus', false);

        $this->get(
            route('portal.incidents.show', $otherIncident)
        )->assertForbidden();
    }

    public function test_a_courier_can_only_view_incidents_from_their_routes(): void
    {
        $courier = Courier::factory()->create();

        $otherCourier = Courier::factory()->create();

        $ownShipment = Shipment::factory()->create();
        $otherShipment = Shipment::factory()->create();

        $this->linkShipmentToCourier(
            $ownShipment,
            $courier
        );

        $this->linkShipmentToCourier(
            $otherShipment,
            $otherCourier
        );

        $ownIncident = $this->createIncident(
            shipment: $ownShipment
        );

        $otherIncident = $this->createIncident(
            shipment: $otherShipment
        );

        $this->actingAs($courier->user)
            ->get(
                route('portal.incidents.show', $ownIncident)
            )
            ->assertOk()
            ->assertViewHas('canManageStatus', false);

        $this->get(
            route('portal.incidents.show', $otherIncident)
        )->assertForbidden();
    }

    public function test_support_and_administration_can_view_the_status_form(): void
    {
        $incident = $this->createIncident();

        foreach (
            [
                'SUPPORT_AGENT',
                'ADMINISTRATOR',
            ] as $roleName
        ) {
            $user = $this->userWithRole($roleName);

            $this->actingAs($user)
                ->get(
                    route('portal.incidents.show', $incident)
                )
                ->assertOk()
                ->assertViewHas('canManageStatus', true)
                ->assertViewHas(
                    'availableStatuses',
                    fn ($statuses) =>
                        $statuses->pluck('status_name')->all()
                        === ['IN_REVIEW']
                )
                ->assertSee(
                    route(
                        'portal.incidents.status.update',
                        $incident
                    ),
                    escape: false
                );
        }
    }

    public function test_related_customers_providers_and_couriers_cannot_change_status(): void
    {
        $customer = Customer::factory()->create();
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
        ]);

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->linkShipmentToProvider(
            $shipment,
            $provider
        );

        $this->linkShipmentToCourier(
            $shipment,
            $courier
        );

        $incident = $this->createIncident(
            shipment: $shipment
        );

        foreach (
            [
                $customer->user,
                $provider->user,
                $courier->user,
            ] as $user
        ) {
            $this->actingAs($user)
                ->get(
                    route('portal.incidents.show', $incident)
                )
                ->assertOk();

            $this->submitStatusChange(
                $user,
                $incident,
                [
                    'status' => 'IN_REVIEW',
                ]
            )->assertForbidden();
        }

        $this->assertNoStatusChange($incident, 'OPEN');
    }

    public function test_inactive_staff_cannot_view_or_update_an_incident(): void
    {
        $incident = $this->createIncident();

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        foreach (
            [
                'SUPPORT_AGENT',
                'ADMINISTRATOR',
            ] as $roleName
        ) {
            $user = $this->userWithRole($roleName);

            $user->update([
                'account_status_id' => $suspendedStatus->id,
            ]);

            $this->actingAs($user)
                ->get(
                    route('portal.incidents.show', $incident)
                )
                ->assertForbidden();

            $this->submitStatusChange(
                $user,
                $incident,
                [
                    'status' => 'IN_REVIEW',
                ]
            )->assertForbidden();
        }

        $this->assertNoStatusChange($incident, 'OPEN');
    }

    public function test_support_can_start_review_without_a_comment(): void
    {
        $incident = $this->createIncident();

        $user = $this->userWithRole('SUPPORT_AGENT');

        $this->submitStatusChange(
            $user,
            $incident,
            [
                'status' => ' in_review ',
            ]
        )
            ->assertRedirect(
                route('portal.incidents.show', $incident)
            )
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertSame(
            'IN_REVIEW',
            $incident->fresh()->incidentStatus->status_name
        );

        $audit = AuditLog::query()
            ->where('table_name', 'incidents')
            ->where('record_id', $incident->id)
            ->where('action_type', 'STATUS_CHANGED')
            ->firstOrFail();

        $this->assertSame(
            $user->id,
            $audit->performed_by_user_id
        );

        $this->assertSame(
            'OPEN',
            $audit->details['from_status']
        );

        $this->assertSame(
            'IN_REVIEW',
            $audit->details['to_status']
        );

        $this->assertNull(
            $audit->details['comment']
        );
    }

    public function test_support_can_resolve_an_incident_with_a_comment(): void
    {
        $incident = $this->createIncident(
            statusName: 'IN_REVIEW'
        );

        $user = $this->userWithRole('SUPPORT_AGENT');

        $this->submitStatusChange(
            $user,
            $incident,
            [
                'status' => 'RESOLVED',
                'comment' => 'The reported problem was corrected.',
            ]
        )
            ->assertRedirect(
                route('portal.incidents.show', $incident)
            )
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertSame(
            'RESOLVED',
            $incident->fresh()->incidentStatus->status_name
        );

        $audit = AuditLog::query()
            ->where('table_name', 'incidents')
            ->where('record_id', $incident->id)
            ->where('action_type', 'STATUS_CHANGED')
            ->firstOrFail();

        $this->assertSame(
            'The reported problem was corrected.',
            $audit->details['comment']
        );
    }

    public function test_resolving_an_incident_requires_a_comment(): void
    {
        $incident = $this->createIncident(
            statusName: 'IN_REVIEW'
        );

        $user = $this->userWithRole('SUPPORT_AGENT');

        $this->submitStatusChange(
            $user,
            $incident,
            [
                'status' => 'RESOLVED',
                'comment' => '   ',
            ]
        )
            ->assertRedirect(
                route('portal.incidents.show', $incident)
            )
            ->assertSessionHasErrors('status');

        $this->assertNoStatusChange(
            $incident,
            'IN_REVIEW'
        );
    }

    public function test_an_administrator_can_reopen_a_resolved_incident(): void
    {
        $incident = $this->createIncident(
            statusName: 'RESOLVED'
        );

        $user = $this->userWithRole('ADMINISTRATOR');

        $this->submitStatusChange(
            $user,
            $incident,
            [
                'status' => 'IN_REVIEW',
                'comment' => 'The problem occurred again.',
            ]
        )
            ->assertRedirect(
                route('portal.incidents.show', $incident)
            )
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'IN_REVIEW',
            $incident->fresh()->incidentStatus->status_name
        );
    }

    public function test_reopening_a_resolved_incident_requires_a_comment(): void
    {
        $incident = $this->createIncident(
            statusName: 'RESOLVED'
        );

        $user = $this->userWithRole('ADMINISTRATOR');

        $this->submitStatusChange(
            $user,
            $incident,
            [
                'status' => 'IN_REVIEW',
            ]
        )
            ->assertRedirect(
                route('portal.incidents.show', $incident)
            )
            ->assertSessionHasErrors('status');

        $this->assertNoStatusChange(
            $incident,
            'RESOLVED'
        );
    }

    public function test_a_resolved_incident_can_be_closed_without_a_comment(): void
    {
        $incident = $this->createIncident(
            statusName: 'RESOLVED'
        );

        $user = $this->userWithRole('ADMINISTRATOR');

        $this->submitStatusChange(
            $user,
            $incident,
            [
                'status' => 'CLOSED',
            ]
        )
            ->assertRedirect(
                route('portal.incidents.show', $incident)
            )
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertSame(
            'CLOSED',
            $incident->fresh()->incidentStatus->status_name
        );
    }

    public function test_invalid_transitions_and_the_same_status_are_rejected(): void
    {
        $incident = $this->createIncident();

        $user = $this->userWithRole('SUPPORT_AGENT');

        foreach (
            [
                'OPEN',
                'RESOLVED',
                'CLOSED',
            ] as $targetStatus
        ) {
            $this->submitStatusChange(
                $user,
                $incident,
                [
                    'status' => $targetStatus,
                    'comment' => 'Invalid transition attempt.',
                ]
            )
                ->assertRedirect(
                    route('portal.incidents.show', $incident)
                )
                ->assertSessionHasErrors('status');

            $this->assertNoStatusChange(
                $incident,
                'OPEN'
            );
        }
    }

    public function test_a_closed_incident_has_no_form_and_cannot_be_reopened(): void
    {
        $incident = $this->createIncident(
            statusName: 'CLOSED'
        );

        $user = $this->userWithRole('ADMINISTRATOR');

        $this->actingAs($user)
            ->get(
                route('portal.incidents.show', $incident)
            )
            ->assertOk()
            ->assertViewHas(
                'availableStatuses',
                fn ($statuses) => $statuses->isEmpty()
            )
            ->assertDontSee(
                route(
                    'portal.incidents.status.update',
                    $incident
                ),
                escape: false
            );

        $this->submitStatusChange(
            $user,
            $incident,
            [
                'status' => 'IN_REVIEW',
                'comment' => 'Attempt to reopen a closed incident.',
            ]
        )
            ->assertRedirect(
                route('portal.incidents.show', $incident)
            )
            ->assertSessionHasErrors('status');

        $this->assertNoStatusChange(
            $incident,
            'CLOSED'
        );
    }

    public function test_status_and_comment_values_are_validated(): void
    {
        $incident = $this->createIncident();

        $user = $this->userWithRole('SUPPORT_AGENT');

        $cases = [
            [
                'payload' => [],
                'errors' => ['status'],
            ],
            [
                'payload' => [
                    'status' => 'UNKNOWN_STATUS',
                ],
                'errors' => ['status'],
            ],
            [
                'payload' => [
                    'status' => 'IN_REVIEW',
                    'comment' => str_repeat('A', 2001),
                ],
                'errors' => ['comment'],
            ],
        ];

        foreach ($cases as $case) {
            $this->submitStatusChange(
                $user,
                $incident,
                $case['payload']
            )
                ->assertRedirect(
                    route('portal.incidents.show', $incident)
                )
                ->assertSessionHasErrors(
                    $case['errors']
                );

            $this->assertNoStatusChange(
                $incident,
                'OPEN'
            );
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function submitStatusChange(
        User $user,
        Incident $incident,
        array $payload
    ): TestResponse {
        return $this->actingAs($user)
            ->from(
                route('portal.incidents.show', $incident)
            )
            ->patch(
                route(
                    'portal.incidents.status.update',
                    $incident
                ),
                $payload
            );
    }

    private function createIncident(
        string $statusName = 'OPEN',
        ?Shipment $shipment = null
    ): Incident {
        $shipment ??= Shipment::factory()->create();

        return Incident::query()->create([
            'shipment_id' => $shipment->id,
            'reported_by_user_id' =>
                $shipment->customer->user_id,
            'incident_type_id' => IncidentType::query()
                ->where('type_name', 'DELAY')
                ->firstOrFail()
                ->id,
            'incident_status_id' => IncidentStatus::query()
                ->where('status_name', $statusName)
                ->firstOrFail()
                ->id,
            'description' =>
                'The shipment was delayed during delivery.',
            'reported_at' => now(),
        ]);
    }

    private function assertNoStatusChange(
        Incident $incident,
        string $expectedStatus
    ): void {
        $this->assertSame(
            $expectedStatus,
            $incident->fresh()->incidentStatus->status_name
        );

        $this->assertDatabaseMissing(
            'audit_logs',
            [
                'table_name' => 'incidents',
                'record_id' => $incident->id,
                'action_type' => 'STATUS_CHANGED',
            ]
        );
    }

    private function linkShipmentToProvider(
        Shipment $shipment,
        DeliveryProvider $provider
    ): void {
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
    }

    private function linkShipmentToCourier(
        Shipment $shipment,
        Courier $courier
    ): void {
        $route = DeliveryRoute::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' => RouteStatus::query()
                ->where('status_name', 'PLANNED')
                ->firstOrFail()
                ->id,
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
    }

    private function userWithRole(
        string $roleName
    ): User {
        return User::factory()->create([
            'role_id' => Role::query()
                ->where('role_name', $roleName)
                ->firstOrFail()
                ->id,
        ]);
    }
}
