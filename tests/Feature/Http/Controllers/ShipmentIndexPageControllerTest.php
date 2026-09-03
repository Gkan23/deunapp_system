<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Role;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentIndexPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_view_the_shipment_page(): void
    {
        $this->get(
            route('portal.shipments.index')
        )->assertRedirect(
            route('login.page')
        );
    }

    public function test_an_unverified_user_is_redirected_to_verification(): void
    {
        $customer = Customer::factory()->create();

        $user = $customer->user;

        /*
         * email_verified_at no pertenece a $fillable,
         * por eso debe modificarse con forceFill().
         */
        $user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $user = $user->fresh();

        $this->assertFalse(
            $user->hasVerifiedEmail()
        );

        $this->actingAs($user)
            ->get(
                route('portal.shipments.index')
            )
            ->assertRedirect(
                route('verification.notice')
            );
    }

    public function test_a_customer_only_sees_their_shipments(): void
    {
        $customer = Customer::factory()->create();

        $otherCustomer =
            Customer::factory()->create();

        $ownShipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
            'tracking_code' => 'DUNA-OWN-SHIPMENT',
        ]);

        $otherShipment =
            Shipment::factory()->create([
                'customer_id' =>
                    $otherCustomer->id,
                'tracking_code' =>
                    'DUNA-OTHER-SHIPMENT',
            ]);

        $this->actingAs($customer->user)
            ->get(
                route('portal.shipments.index')
            )
            ->assertOk()
            ->assertSee(
                $ownShipment->tracking_code
            )
            ->assertDontSee(
                $otherShipment->tracking_code
            );
    }

    public function test_shipments_can_be_filtered_by_tracking_and_status(): void
    {
        $customer = Customer::factory()->create();

        $deliveredStatus =
            ShipmentStatus::query()
                ->where(
                    'status_name',
                    'DELIVERED'
                )
                ->firstOrFail();

        $matchingShipment =
            Shipment::factory()->create([
                'customer_id' => $customer->id,
                'tracking_code' =>
                    'DUNA-FILTER-001',
                'shipment_status_id' =>
                    $deliveredStatus->id,
            ]);

        $otherShipment =
            Shipment::factory()->create([
                'customer_id' => $customer->id,
                'tracking_code' =>
                    'DUNA-OTHER-001',
            ]);

        $this->actingAs($customer->user)
            ->get(
                route(
                    'portal.shipments.index',
                    [
                        'search' => 'FILTER',
                        'status' => 'DELIVERED',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $matchingShipment->tracking_code
            )
            ->assertDontSee(
                $otherShipment->tracking_code
            )
            ->assertSee(
                'value="FILTER"',
                escape: false
            )
            ->assertSee(
                'value="DELIVERED"',
                escape: false
            );
    }

    public function test_the_page_displays_an_empty_state(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->get(
                route('portal.shipments.index')
            )
            ->assertOk()
            ->assertSee(
                'No se encontraron envíos'
            )
            ->assertSee('0');
    }

    public function test_an_inactive_account_cannot_view_the_page(): void
    {
        $customer = Customer::factory()->create();

        $inactiveStatus = AccountStatus::query()
            ->where(
                'status_name',
                '!=',
                'ACTIVE'
            )
            ->firstOrFail();

        $customer->user->update([
            'account_status_id' =>
                $inactiveStatus->id,
        ]);

        $this->actingAs(
            $customer->user->fresh()
        )
            ->get(
                route('portal.shipments.index')
            )
            ->assertForbidden();
    }

    public function test_a_provider_only_sees_linked_shipments(): void
    {
        $provider =
            DeliveryProvider::factory()->create();

        $linkedShipment =
            Shipment::factory()->create([
                'tracking_code' =>
                    'DUNA-PROVIDER-LINKED',
            ]);

        $unrelatedShipment =
            Shipment::factory()->create([
                'tracking_code' =>
                    'DUNA-PROVIDER-HIDDEN',
            ]);

        $this->linkProviderToShipment(
            $provider,
            $linkedShipment
        );

        $this->actingAs($provider->user)
            ->get(
                route('portal.shipments.index')
            )
            ->assertOk()
            ->assertSee(
                $linkedShipment->tracking_code
            )
            ->assertDontSee(
                $unrelatedShipment->tracking_code
            );
    }

    public function test_a_courier_only_sees_assigned_shipments(): void
    {
        $courier = Courier::factory()->create();

        $assignedShipment =
            Shipment::factory()->create([
                'tracking_code' =>
                    'DUNA-COURIER-ASSIGNED',
            ]);

        $unassignedShipment =
            Shipment::factory()->create([
                'tracking_code' =>
                    'DUNA-COURIER-HIDDEN',
            ]);

        $this->assignCourierToShipment(
            $courier,
            $assignedShipment
        );

        $this->actingAs($courier->user)
            ->get(
                route('portal.shipments.index')
            )
            ->assertOk()
            ->assertSee(
                $assignedShipment->tracking_code
            )
            ->assertDontSee(
                $unassignedShipment->tracking_code
            );
    }

    public function test_a_support_agent_sees_all_shipments(): void
    {
        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $firstShipment =
            Shipment::factory()->create([
                'tracking_code' =>
                    'DUNA-SUPPORT-FIRST',
            ]);

        $secondShipment =
            Shipment::factory()->create([
                'tracking_code' =>
                    'DUNA-SUPPORT-SECOND',
            ]);

        $this->actingAs($supportAgent)
            ->get(
                route('portal.shipments.index')
            )
            ->assertOk()
            ->assertSee(
                $firstShipment->tracking_code
            )
            ->assertSee(
                $secondShipment->tracking_code
            );
    }

    public function test_an_administrator_sees_all_shipments(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $firstShipment =
            Shipment::factory()->create([
                'tracking_code' =>
                    'DUNA-ADMIN-FIRST',
            ]);

        $secondShipment =
            Shipment::factory()->create([
                'tracking_code' =>
                    'DUNA-ADMIN-SECOND',
            ]);

        $this->actingAs($administrator)
            ->get(
                route('portal.shipments.index')
            )
            ->assertOk()
            ->assertSee(
                $firstShipment->tracking_code
            )
            ->assertSee(
                $secondShipment->tracking_code
            );
    }

    private function linkProviderToShipment(
        DeliveryProvider $provider,
        Shipment $shipment
    ): void {
        $trip = Trip::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
            'status' => 'USED',
            'used_at' => now(),
        ]);

        DeliveryService::factory()->create([
            'shipment_id' => $shipment->id,
            'trip_id' => $trip->id,
            'trip_type_id' =>
                $trip->trip_type_id,
            'status' => 'ASSIGNED',
            'accepted_at' => now(),
        ]);
    }

    private function assignCourierToShipment(
        Courier $courier,
        Shipment $shipment
    ): void {
        $plannedStatus = RouteStatus::query()
            ->where(
                'status_name',
                'PLANNED'
            )
            ->firstOrFail();

        $route = Route::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' =>
                $plannedStatus->id,
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
}