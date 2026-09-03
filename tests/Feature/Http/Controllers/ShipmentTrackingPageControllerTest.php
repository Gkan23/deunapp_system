<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
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

class ShipmentTrackingPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_view_the_tracking_page(): void
    {
        $shipment = Shipment::factory()->create();

        $this->get(
            route(
                'portal.shipments.tracking',
                $shipment
            )
        )->assertRedirect(
            route('login.page')
        );
    }

    public function test_an_unverified_customer_cannot_view_the_tracking_page(): void
    {
        $customer = Customer::factory()->create();

        $shipment = $this->createShipmentFor(
            $customer
        );

        $customer->user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $user = $customer->user->fresh();

        $this->assertFalse(
            $user->hasVerifiedEmail()
        );

        $this->actingAs($user)
            ->get(
                route(
                    'portal.shipments.tracking',
                    $shipment
                )
            )
            ->assertRedirect(
                route('verification.notice')
            );
    }

    public function test_the_customer_can_view_their_tracking_page(): void
    {
        $customer = Customer::factory()->create();

        $shipment = $this->createShipmentFor(
            $customer
        );

        $this->actingAs($customer->user)
            ->get(
                route(
                    'portal.shipments.tracking',
                    $shipment
                )
            )
            ->assertOk()
            ->assertViewIs(
                'shipments.tracking'
            )
            ->assertViewHas(
                'shipment',
                fn (
                    Shipment $viewShipment
                ): bool =>
                    $viewShipment->is(
                        $shipment
                    )
            )
            ->assertSee(
                $shipment->tracking_code
            )
            ->assertSee(
                'Seguimiento en tiempo real'
            )
            ->assertSee(
                'Origen y destino'
            );
    }

    public function test_an_unrelated_customer_cannot_view_the_tracking_page(): void
    {
        $owner = Customer::factory()->create();

        $unrelatedCustomer =
            Customer::factory()->create();

        $shipment = $this->createShipmentFor(
            $owner
        );

        $this->actingAs(
            $unrelatedCustomer->user
        )
            ->get(
                route(
                    'portal.shipments.tracking',
                    $shipment
                )
            )
            ->assertForbidden();
    }

    public function test_a_provider_can_only_view_linked_shipment_tracking(): void
    {
        $provider =
            DeliveryProvider::factory()->create();

        $unrelatedProvider =
            DeliveryProvider::factory()->create();

        $shipment = Shipment::factory()->create([
            'tracking_code' =>
                'DEU-PROVIDER-TRACKING',
        ]);

        $this->linkProviderToShipment(
            $provider,
            $shipment
        );

        $this->actingAs($provider->user)
            ->get(
                route(
                    'portal.shipments.tracking',
                    $shipment
                )
            )
            ->assertOk()
            ->assertSee(
                'DEU-PROVIDER-TRACKING'
            );

        $this->actingAs(
            $unrelatedProvider->user
        )
            ->get(
                route(
                    'portal.shipments.tracking',
                    $shipment
                )
            )
            ->assertForbidden();
    }

    public function test_the_assigned_courier_can_view_the_tracking_page(): void
    {
        $shipment = Shipment::factory()->create([
            'tracking_code' =>
                'DEU-COURIER-TRACKING',
        ]);

        [$courier] = $this->assignActiveRoute(
            $shipment
        );

        $this->actingAs($courier->user)
            ->get(
                route(
                    'portal.shipments.tracking',
                    $shipment
                )
            )
            ->assertOk()
            ->assertSee(
                'DEU-COURIER-TRACKING'
            );
    }

    public function test_an_unassigned_courier_cannot_view_the_tracking_page(): void
    {
        $shipment = Shipment::factory()->create();

        $this->assignActiveRoute(
            $shipment
        );

        $unassignedCourier =
            Courier::factory()->create();

        $this->actingAs(
            $unassignedCourier->user
        )
            ->get(
                route(
                    'portal.shipments.tracking',
                    $shipment
                )
            )
            ->assertForbidden();
    }

    public function test_support_and_administration_can_view_the_tracking_page(): void
    {
        $shipment = Shipment::factory()->create([
            'tracking_code' =>
                'DEU-OPERATION-TRACKING',
        ]);

        foreach ([
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ] as $roleName) {
            $user = $this->userWithRole(
                $roleName
            );

            $this->actingAs($user)
                ->get(
                    route(
                        'portal.shipments.tracking',
                        $shipment
                    )
                )
                ->assertOk()
                ->assertSee(
                    'DEU-OPERATION-TRACKING'
                );
        }
    }

    public function test_the_page_contains_the_map_configuration(): void
    {
        config()->set(
            'services.mapbox.public_token',
            'pk.test-public-token'
        );

        $customer = Customer::factory()->create();

        $shipment = $this->createShipmentFor(
            $customer
        );

        $shipment->originAddress->update([
            'address_line' =>
                'Origen de prueba',
            'latitude' => 12.1363890,
            'longitude' => -86.2513890,
        ]);

        $shipment->destinationAddress->update([
            'address_line' =>
                'Destino de prueba',
            'latitude' => 12.1463890,
            'longitude' => -86.2613890,
        ]);

        $response = $this
            ->actingAs($customer->user)
            ->get(
                route(
                    'portal.shipments.tracking',
                    $shipment
                )
            );

        $response
            ->assertOk()
            ->assertSee(
                'id="shipment-tracking-application"',
                escape: false
            )
            ->assertSee(
                'id="shipment-tracking-map"',
                escape: false
            )
            ->assertSee(
                'data-mapbox-token="pk.test-public-token"',
                escape: false
            )
            ->assertSee(
                route(
                    'shipments.tracking',
                    $shipment
                ),
                escape: false
            )
            ->assertSee(
                'Origen de prueba'
            )
            ->assertSee(
                'Destino de prueba'
            )
            ->assertSee(
                'Actualización automática'
            )
            ->assertSee(
                route(
                    'portal.shipments.show',
                    $shipment
                ),
                escape: false
            );
    }

    private function createShipmentFor(
        Customer $customer
    ): Shipment {
        return Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);
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

    /**
     * @return array{
     *     0: Courier,
     *     1: DeliveryRoute,
     *     2: RouteShipment
     * }
     */
    private function assignActiveRoute(
        Shipment $shipment
    ): array {
        $courier = Courier::factory()->create();

        $activeStatus = RouteStatus::query()
            ->where(
                'status_name',
                'ACTIVE'
            )
            ->firstOrFail();

        $route = DeliveryRoute::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' =>
                $activeStatus->id,
            'route_date' => today(),
            'started_at' => now(),
            'finished_at' => null,
            'estimated_distance_km' => 10.00,
        ]);

        $routeShipment =
            RouteShipment::query()->create([
                'route_id' => $route->id,
                'shipment_id' =>
                    $shipment->id,
                'delivery_order' => 1,
                'delivery_status' =>
                    'IN_PROGRESS',
            ]);

        return [
            $courier,
            $route,
            $routeShipment,
        ];
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