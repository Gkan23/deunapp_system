<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
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

class IncidentIndexPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);

        $this->withoutVite();
    }

    public function test_a_guest_cannot_view_the_incident_page(): void
    {
        $this->get(
            route('portal.incidents.index')
        )->assertRedirect(
            route('login.page')
        );
    }

    public function test_an_unverified_user_is_redirected_to_verification(): void
    {
        $customer = Customer::factory()->create();

        $user = $customer->user;

        $user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this->actingAs($user)
            ->get(
                route('portal.incidents.index')
            )
            ->assertRedirect(
                route('verification.notice')
            );
    }

    public function test_a_customer_only_sees_incidents_from_their_shipments(): void
    {
        $customer = Customer::factory()->create();

        $ownShipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $otherShipment = Shipment::factory()->create();

        $ownIncident = $this->createIncident(
            $ownShipment,
            'CUSTOMER-VISIBLE-INCIDENT'
        );

        $otherIncident = $this->createIncident(
            $otherShipment,
            'CUSTOMCUSTOMER-HIDDEN-INCIDENT',
            statusName: 'CLOSED'
        );

        $this->actingAs($customer->user)
            ->get(
                route('portal.incidents.index')
            )
            ->assertOk()
            ->assertViewIs('incidents.index')
            ->assertSee($ownIncident->description)
            ->assertDontSee($otherIncident->description)
            ->assertViewHas('totalIncidents', 1)
            ->assertViewHas('pendingIncidents', 1)
            ->assertViewHas('finishedIncidents', 0);
    }

    public function test_a_provider_only_sees_incidents_related_to_their_trips(): void
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
            $ownShipment,
            'PROVIDER-VISIBLE-INCIDENT'
        );

        $otherIncident = $this->createIncident(
            $otherShipment,
            'PROVIDER-HIDDEN-INCIDENT'
        );

        $this->actingAs($provider->user)
            ->get(
                route('portal.incidents.index')
            )
            ->assertOk()
            ->assertSee($ownIncident->description)
            ->assertDontSee($otherIncident->description)
            ->assertViewHas('totalIncidents', 1);
    }

    public function test_a_courier_only_sees_incidents_from_shipments_in_their_routes(): void
    {
        $courier = Courier::factory()->create();
        $otherCourier = Courier::factory()->create();

        $ownShipment = Shipment::factory()->create();
        $otherShipment = Shipment::factory()->create();

        /*
         * Dos rutas del mismo repartidor incluyen el envío.
         * La consulta no debe duplicar el incidente.
         */
        $this->linkShipmentToCourier(
            $ownShipment,
            $courier
        );

        $this->linkShipmentToCourier(
            $ownShipment,
            $courier
        );

        $this->linkShipmentToCourier(
            $otherShipment,
            $otherCourier
        );

        $ownIncident = $this->createIncident(
            $ownShipment,
            'COURIER-VISIBLE-INCIDENT'
        );

        $otherIncident = $this->createIncident(
            $otherShipment,
            'COURIER-HIDDEN-INCIDENT'
        );

        $this->actingAs($courier->user)
            ->get(
                route('portal.incidents.index')
            )
            ->assertOk()
            ->assertSee($ownIncident->description)
            ->assertDontSee($otherIncident->description)
            ->assertViewHas('totalIncidents', 1)
            ->assertViewHas(
                'incidents',
                fn ($incidents) => $incidents->total() === 1
            );
    }

    public function test_support_and_administration_can_view_all_incidents(): void
    {
        $firstIncident = $this->createIncident(
            Shipment::factory()->create(),
            'STAFF-FIRST-INCIDENT'
        );

        $secondIncident = $this->createIncident(
            Shipment::factory()->create(),
            'STAFF-SECOND-INCIDENT',
            statusName: 'RESOLVED'
        );

        foreach (
            [
                'SUPPORT_AGENT',
                'ADMINISTRATOR',
            ] as $roleName
        ) {
            $user = $this->userWithRole($roleName);

            $this->actingAs($user)
                ->get(
                    route('portal.incidents.index')
                )
                ->assertOk()
                ->assertSee($firstIncident->description)
                ->assertSee($secondIncident->description)
                ->assertViewHas('totalIncidents', 2)
                ->assertViewHas('pendingIncidents', 1)
                ->assertViewHas('finishedIncidents', 1);
        }
    }

    public function test_search_status_and_type_filters_preserve_visibility(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $matchingIncident = $this->createIncident(
            $shipment,
            'Needle matching incident',
            statusName: 'IN_REVIEW',
            typeName: 'DELAY'
        );

        $wrongStatus = $this->createIncident(
            $shipment,
            'Needle wrong status',
            statusName: 'OPEN',
            typeName: 'DELAY'
        );

        $wrongType = $this->createIncident(
            $shipment,
            'Needle wrong type',
            statusName: 'IN_REVIEW',
            typeName: 'DAMAGED_PACKAGE'
        );

        $wrongSearch = $this->createIncident(
            $shipment,
            'A completely different description',
            statusName: 'IN_REVIEW',
            typeName: 'DELAY'
        );

        $foreignIncident = $this->createIncident(
            Shipment::factory()->create(),
            'Needle foreign incident',
            statusName: 'IN_REVIEW',
            typeName: 'DELAY'
        );

        $this->actingAs($customer->user)
            ->get(
                route(
                    'portal.incidents.index',
                    [
                        'search' => 'Needle',
                        'status' => 'IN_REVIEW',
                        'type' => 'DELAY',
                    ]
                )
            )
            ->assertOk()
            ->assertSee($matchingIncident->description)
            ->assertDontSee($wrongStatus->description)
            ->assertDontSee($wrongType->description)
            ->assertDontSee($wrongSearch->description)
            ->assertDontSee($foreignIncident->description)
            ->assertViewHas(
                'incidents',
                fn ($incidents) => $incidents->total() === 1
            )
            ->assertViewHas('totalIncidents', 4);
    }

    public function test_incidents_can_be_searched_by_tracking_code(): void
    {
        $customer = Customer::factory()->create();

        $matchingShipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
            'tracking_code' => 'DUNA-INCIDENT-SEARCH-001',
        ]);

        $otherShipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
            'tracking_code' => 'DUNA-INCIDENT-SEARCH-002',
        ]);

        $matchingIncident = $this->createIncident(
            $matchingShipment,
            'TRACKING-MATCH-INCIDENT'
        );

        $otherIncident = $this->createIncident(
            $otherShipment,
            'TRACKING-OTHER-INCIDENT'
        );

        $this->actingAs($customer->user)
            ->get(
                route(
                    'portal.incidents.index',
                    [
                        'search' => $matchingShipment->tracking_code,
                    ]
                )
            )
            ->assertOk()
            ->assertSee($matchingIncident->description)
            ->assertDontSee($otherIncident->description)
            ->assertViewHas(
                'incidents',
                fn ($incidents) => $incidents->total() === 1
            );
    }

    public function test_the_page_displays_an_empty_state(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->get(
                route('portal.incidents.index')
            )
            ->assertOk()
            ->assertSee('No se encontraron incidentes')
            ->assertViewHas('totalIncidents', 0)
            ->assertViewHas('pendingIncidents', 0)
            ->assertViewHas('finishedIncidents', 0);
    }

    public function test_inactive_accounts_cannot_view_the_page(): void
    {
        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        foreach (
            [
                'CUSTOMER',
                'DELIVERY_PROVIDER',
                'COURIER',
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
                    route('portal.incidents.index')
                )
                ->assertForbidden();
        }
    }

    public function test_incidents_are_paginated(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        for ($number = 1; $number <= 16; $number++) {
            $this->createIncident(
                $shipment,
                'Pagination incident '.$number
            );
        }

        $this->actingAs($customer->user)
            ->get(
                route('portal.incidents.index')
            )
            ->assertOk()
            ->assertViewHas(
                'incidents',
                fn ($incidents) =>
                    $incidents->total() === 16
                    && $incidents->count() === 15
                    && $incidents->perPage() === 15
            );

        $this->get(
            route(
                'portal.incidents.index',
                [
                    'page' => 2,
                ]
            )
        )
            ->assertOk()
            ->assertViewHas(
                'incidents',
                fn ($incidents) =>
                    $incidents->total() === 16
                    && $incidents->count() === 1
                    && $incidents->currentPage() === 2
            );
    }

    public function test_each_supported_role_has_the_portal_link_in_the_dashboard(): void
    {
        foreach (
            [
                'CUSTOMER',
                'DELIVERY_PROVIDER',
                'COURIER',
                'SUPPORT_AGENT',
                'ADMINISTRATOR',
            ] as $roleName
        ) {
            $user = $this->userWithRole($roleName);

            $this->actingAs($user)
                ->get(
                    route('dashboard')
                )
                ->assertOk()
                ->assertSee('Incidentes')
                ->assertSee(
                    route('portal.incidents.index'),
                    escape: false
                );
        }
    }

    private function createIncident(
        Shipment $shipment,
        string $description,
        string $statusName = 'OPEN',
        string $typeName = 'DELAY'
    ): Incident {
        return Incident::query()->create([
            'shipment_id' => $shipment->id,
            'reported_by_user_id' =>
                $shipment->customer->user_id,
            'incident_type_id' => IncidentType::query()
                ->where('type_name', $typeName)
                ->firstOrFail()
                ->id,
            'incident_status_id' => IncidentStatus::query()
                ->where('status_name', $statusName)
                ->firstOrFail()
                ->id,
            'description' => $description,
            'reported_at' => now(),
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