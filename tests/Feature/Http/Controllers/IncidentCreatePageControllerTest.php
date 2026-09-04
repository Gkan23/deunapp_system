<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
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
use App\Services\Incident\CreateIncidentService;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class IncidentCreatePageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);

        $this->withoutVite();
    }

    public function test_a_guest_cannot_open_or_submit_the_form(): void
    {
        $shipment = Shipment::factory()->create();

        $this->get($this->formUrl($shipment))
            ->assertRedirect(route('login.page'));

        $this->post(
            $this->storeUrl($shipment),
            $this->validPayload()
        )->assertRedirect(route('login.page'));

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_an_unverified_user_cannot_open_or_submit_the_form(): void
    {
        $shipment = Shipment::factory()->create();

        $user = $shipment->customer->user;

        $user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this->assertFalse($user->hasVerifiedEmail());

        $this->actingAs($user)
            ->get($this->formUrl($shipment))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($user)
            ->post(
                $this->storeUrl($shipment),
                $this->validPayload()
            )
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_the_customer_can_view_the_form_and_available_types(): void
    {
        $shipment = Shipment::factory()->create();

        $user = $shipment->customer->user;

        $response = $this->actingAs($user)
            ->get($this->formUrl($shipment))
            ->assertOk()
            ->assertViewIs('incidents.create')
            ->assertSee('Reportar incidente')
            ->assertSee($shipment->tracking_code)
            ->assertSee($user->name)
            ->assertSee(
                $this->storeUrl($shipment),
                escape: false
            )
            ->assertSee('name="incident_type"', escape: false)
            ->assertSee('name="description"', escape: false);

        foreach (IncidentType::query()->get() as $type) {
            $response->assertSee(
                'value="'.$type->type_name.'"',
                escape: false
            );
        }
    }

    public function test_the_customer_can_report_an_incident_with_an_audit_record(): void
    {
        $shipment = Shipment::factory()->create();

        $user = $shipment->customer->user;

        $response = $this->submit(
            $user,
            $shipment,
            [
                'incident_type' => ' delay ',
                'description' => '  El envío presenta un retraso.  ',
            ]
        );

        $incident = Incident::query()->sole();

        $response
            ->assertRedirect(
                route('portal.incidents.show', $incident)
            )
            ->assertSessionHasNoErrors()
            ->assertSessionHas(
                'status',
                'El incidente fue reportado correctamente.'
            );

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'shipment_id' => $shipment->id,
            'reported_by_user_id' => $user->id,
            'incident_type_id' => IncidentType::query()
                ->where('type_name', 'DELAY')
                ->firstOrFail()
                ->id,
            'incident_status_id' => IncidentStatus::query()
                ->where('status_name', 'OPEN')
                ->firstOrFail()
                ->id,
            'description' => 'El envío presenta un retraso.',
        ]);

        $this->assertNotNull($incident->reported_at);

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' => $user->id,
            'table_name' => 'incidents',
            'record_id' => $incident->id,
            'action_type' => 'INCIDENT_CREATED',
        ]);

        $this->actingAs($user)
            ->get(route('portal.incidents.show', $incident))
            ->assertOk();
    }

    public function test_the_related_provider_can_report_an_incident(): void
    {
        $shipment = Shipment::factory()->create();

        $provider = DeliveryProvider::factory()->create();

        $this->linkShipmentToProvider($shipment, $provider);

        $this->actingAs($provider->user)
            ->get($this->formUrl($shipment))
            ->assertOk();

        $response = $this->submit(
            $provider->user,
            $shipment
        );

        $incident = Incident::query()->sole();

        $response->assertRedirect(
            route('portal.incidents.show', $incident)
        );

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'shipment_id' => $shipment->id,
            'reported_by_user_id' => $provider->user_id,
        ]);
    }

    public function test_the_assigned_courier_can_report_an_incident(): void
    {
        $shipment = Shipment::factory()->create();

        $courier = Courier::factory()->create();

        $this->linkShipmentToCourier($shipment, $courier);

        $this->actingAs($courier->user)
            ->get($this->formUrl($shipment))
            ->assertOk();

        $response = $this->submit(
            $courier->user,
            $shipment
        );

        $incident = Incident::query()->sole();

        $response->assertRedirect(
            route('portal.incidents.show', $incident)
        );

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'shipment_id' => $shipment->id,
            'reported_by_user_id' => $courier->user_id,
        ]);
    }

    public function test_support_and_administration_can_report_incidents(): void
    {
        $shipment = Shipment::factory()->create();

        foreach ([
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ] as $roleName) {
            $user = $this->userWithRole($roleName);

            $this->actingAs($user)
                ->get($this->formUrl($shipment))
                ->assertOk();

            $response = $this->submit($user, $shipment);

            $incident = Incident::query()
                ->where('reported_by_user_id', $user->id)
                ->sole();

            $response->assertRedirect(
                route('portal.incidents.show', $incident)
            );
        }

        $this->assertDatabaseCount('incidents', 2);
    }

    public function test_unrelated_users_cannot_open_or_submit_the_form(): void
    {
        $shipment = Shipment::factory()->create();

        $otherShipment = Shipment::factory()->create();

        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create();

        $users = [
            $otherShipment->customer->user,
            $provider->user,
            $courier->user,
        ];

        foreach ($users as $user) {
            $this->actingAs($user)
                ->get($this->formUrl($shipment))
                ->assertForbidden();

            $this->submit($user, $shipment)
                ->assertForbidden();
        }

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_an_inactive_account_cannot_report_incidents(): void
    {
        $shipment = Shipment::factory()->create();

        $user = $shipment->customer->user;

        $user->update([
            'account_status_id' => AccountStatus::query()
                ->where('status_name', 'SUSPENDED')
                ->firstOrFail()
                ->id,
        ]);

        $this->actingAs($user)
            ->get($this->formUrl($shipment))
            ->assertForbidden();

        $this->submit($user, $shipment)
            ->assertForbidden();

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_inactive_operational_profiles_cannot_report_incidents(): void
    {
        $shipment = Shipment::factory()->create();

        $provider = DeliveryProvider::factory()->create();

        $this->linkShipmentToProvider($shipment, $provider);

        $provider->update(['is_active' => false]);

        $this->actingAs($provider->user)
            ->get($this->formUrl($shipment))
            ->assertForbidden();

        $this->submit($provider->user, $shipment)
            ->assertForbidden();

        $courier = Courier::factory()->create();

        $this->linkShipmentToCourier($shipment, $courier);

        $courier->update(['is_active' => false]);

        $this->actingAs($courier->user)
            ->get($this->formUrl($shipment))
            ->assertForbidden();

        $this->submit($courier->user, $shipment)
            ->assertForbidden();

        $courier->update(['is_active' => true]);

        $courier->deliveryProvider->update([
            'is_active' => false,
        ]);

        $this->actingAs($courier->user)
            ->get($this->formUrl($shipment))
            ->assertForbidden();

        $this->submit($courier->user, $shipment)
            ->assertForbidden();

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_incident_type_and_description_are_required(): void
    {
        $shipment = Shipment::factory()->create();

        $user = $shipment->customer->user;

        $this->submit($user, $shipment, [])
            ->assertRedirect($this->formUrl($shipment))
            ->assertSessionHasErrors([
                'incident_type',
                'description',
            ]);

        $this->submit($user, $shipment, [
            'incident_type' => 'DELAY',
            'description' => '   ',
        ])
            ->assertRedirect($this->formUrl($shipment))
            ->assertSessionHasErrors('description');

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_incident_type_and_description_values_are_validated(): void
    {
        $shipment = Shipment::factory()->create();

        $user = $shipment->customer->user;

        $cases = [
            [
                'payload' => [
                    'incident_type' => 'UNKNOWN_TYPE',
                    'description' => 'Descripción válida.',
                ],
                'field' => 'incident_type',
            ],
            [
                'payload' => [
                    'incident_type' => ['DELAY'],
                    'description' => 'Descripción válida.',
                ],
                'field' => 'incident_type',
            ],
            [
                'payload' => [
                    'incident_type' => 'DELAY',
                    'description' => str_repeat('a', 5001),
                ],
                'field' => 'description',
            ],
            [
                'payload' => [
                    'incident_type' => 'DELAY',
                    'description' => ['Texto inválido'],
                ],
                'field' => 'description',
            ],
        ];

        foreach ($cases as $case) {
            $this->submit(
                $user,
                $shipment,
                $case['payload']
            )
                ->assertRedirect($this->formUrl($shipment))
                ->assertSessionHasErrors($case['field']);
        }

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_the_form_cannot_override_the_shipment_reporter_or_initial_status(): void
    {
        $shipment = Shipment::factory()->create();

        $otherShipment = Shipment::factory()->create();

        $user = $shipment->customer->user;

        $otherUser = $otherShipment->customer->user;

        $closedStatus = IncidentStatus::query()
            ->where('status_name', 'CLOSED')
            ->firstOrFail();

        $this->submit($user, $shipment, [
            ...$this->validPayload(),
            'shipment_id' => $otherShipment->id,
            'reported_by_user_id' => $otherUser->id,
            'incident_status_id' => $closedStatus->id,
            'status' => 'CLOSED',
        ])->assertSessionHasNoErrors();

        $incident = Incident::query()
            ->with('incidentStatus')
            ->sole();

        $this->assertSame(
            (int) $shipment->id,
            (int) $incident->shipment_id
        );

        $this->assertSame(
            (int) $user->id,
            (int) $incident->reported_by_user_id
        );

        $this->assertSame(
            'OPEN',
            $incident->incidentStatus->status_name
        );
    }

    public function test_the_shipment_detail_contains_the_report_link(): void
    {
        $shipment = Shipment::factory()->create();

        $this->actingAs($shipment->customer->user)
            ->get(route('portal.shipments.show', $shipment))
            ->assertOk()
            ->assertSee('Reportar incidente')
            ->assertSee(
                $this->formUrl($shipment),
                escape: false
            );
    }

    public function test_an_inactive_provider_does_not_see_the_report_link(): void
    {
        $shipment = Shipment::factory()->create();

        $provider = DeliveryProvider::factory()->create();

        $this->linkShipmentToProvider($shipment, $provider);

        $provider->update(['is_active' => false]);

        /*
         * ShipmentPolicy permite consultar el envío relacionado.
         * IncidentPolicy impide crear incidentes con este perfil.
         */
        $this->actingAs($provider->user)
            ->get(route('portal.shipments.show', $shipment))
            ->assertOk()
            ->assertDontSee(
                $this->formUrl($shipment),
                escape: false
            );
    }

    public function test_a_service_rejection_returns_to_the_form_with_input(): void
    {
        $shipment = Shipment::factory()->create();

        $user = $shipment->customer->user;

        $message = 'The incident could not be registered.';

        $this->mock(
            CreateIncidentService::class,
            function ($mock) use ($message): void {
                $mock->shouldReceive('execute')
                    ->once()
                    ->andThrow(new DomainException($message));
            }
        );

        $this->submit($user, $shipment)
            ->assertRedirect($this->formUrl($shipment))
            ->assertSessionHasErrors([
                'incident' => $message,
            ])
            ->assertSessionHasInput(
                'incident_type',
                'DELAY'
            )
            ->assertSessionHasInput(
                'description',
                'El envío presenta un retraso.'
            );

        $this->assertDatabaseCount('incidents', 0);
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'incident_type' => 'DELAY',
            'description' => 'El envío presenta un retraso.',
        ];
    }

    private function formUrl(Shipment $shipment): string
    {
        return route(
            'portal.shipments.incidents.create',
            $shipment
        );
    }

    private function storeUrl(Shipment $shipment): string
    {
        return route(
            'portal.shipments.incidents.store',
            $shipment
        );
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function submit(
        User $user,
        Shipment $shipment,
        ?array $payload = null
    ): TestResponse {
        return $this->actingAs($user)
            ->from($this->formUrl($shipment))
            ->post(
                $this->storeUrl($shipment),
                $payload ?? $this->validPayload()
            );
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
}