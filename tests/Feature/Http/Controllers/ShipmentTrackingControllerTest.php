<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\CourierLocation;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Route as DeliveryRoute;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentTrackingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_track_a_shipment(): void
    {
        $shipment = Shipment::factory()->create();

        $this->getJson(
            route(
                'shipments.tracking',
                $shipment
            )
        )->assertUnauthorized();
    }

    public function test_the_customer_receives_no_location_without_an_active_route(): void
    {
        $customer = Customer::factory()->create();

        $shipment = $this->createShipmentFor(
            $customer
        );

        $this->actingAs($customer->user)
            ->getJson(
                route(
                    'shipments.tracking',
                    $shipment
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shipment_id',
                $shipment->id
            )
            ->assertJsonPath(
                'data.tracking_available',
                false
            )
            ->assertJsonPath(
                'data.reason',
                'NO_ACTIVE_ROUTE'
            )
            ->assertJsonPath(
                'data.route',
                null
            )
            ->assertJsonPath(
                'data.location',
                null
            );
    }

    public function test_an_active_route_without_locations_is_reported_safely(): void
    {
        $customer = Customer::factory()->create();

        $shipment = $this->createShipmentFor(
            $customer
        );

        [$courier, $route] =
            $this->assignActiveRoute(
                $shipment
            );

        $this->actingAs($customer->user)
            ->getJson(
                route(
                    'shipments.tracking',
                    $shipment
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.tracking_available',
                false
            )
            ->assertJsonPath(
                'data.reason',
                'LOCATION_NOT_RECORDED'
            )
            ->assertJsonPath(
                'data.route.id',
                $route->id
            )
            ->assertJsonPath(
                'data.courier.id',
                $courier->id
            )
            ->assertJsonPath(
                'data.location',
                null
            );
    }

    public function test_the_latest_courier_location_is_returned_for_mapbox(): void
    {
        $customer = Customer::factory()->create();

        $shipment = $this->createShipmentFor(
            $customer
        );

        [$courier, $route] =
            $this->assignActiveRoute(
                $shipment
            );

        CourierLocation::query()->create([
            'courier_id' => $courier->id,
            'latitude' => 12.1000000,
            'longitude' => -86.2000000,
            'gps_accuracy' => 25.00,
            'recorded_at' =>
                now()->subMinutes(5),
        ]);

        $latestLocation =
            CourierLocation::query()->create([
                'courier_id' => $courier->id,
                'latitude' => 12.1363890,
                'longitude' => -86.2513890,
                'gps_accuracy' => 8.50,
                'recorded_at' => now(),
            ]);

        $this->actingAs($customer->user)
            ->getJson(
                route(
                    'shipments.tracking',
                    $shipment
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.tracking_available',
                true
            )
            ->assertJsonPath(
                'data.reason',
                null
            )
            ->assertJsonPath(
                'data.route.id',
                $route->id
            )
            ->assertJsonPath(
                'data.location.type',
                'Point'
            )
            ->assertJsonPath(
                'data.location.coordinates.0',
                -86.251389
            )
            ->assertJsonPath(
                'data.location.coordinates.1',
                12.136389
            )
            ->assertJsonPath(
                'data.location.longitude',
                -86.251389
            )
            ->assertJsonPath(
                'data.location.latitude',
                12.136389
            )
            ->assertJsonPath(
                'data.location.recorded_at',
                $latestLocation
                    ->recorded_at
                    ->toIso8601String()
            );
    }

    public function test_an_unrelated_customer_cannot_track_the_shipment(): void
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
            ->getJson(
                route(
                    'shipments.tracking',
                    $shipment
                )
            )
            ->assertForbidden();
    }

    public function test_the_assigned_courier_can_track_the_shipment(): void
    {
        $shipment = Shipment::factory()->create();

        [$courier] = $this->assignActiveRoute(
            $shipment
        );

        $this->actingAs($courier->user)
            ->getJson(
                route(
                    'shipments.tracking',
                    $shipment
                )
            )
            ->assertOk();
    }

    public function test_support_and_administration_can_track_a_shipment(): void
    {
        $shipment = Shipment::factory()->create();

        foreach ([
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ] as $roleName) {
            $user = User::factory()->create([
                'role_id' => Role::query()
                    ->where(
                        'role_name',
                        $roleName
                    )
                    ->firstOrFail()
                    ->id,
            ]);

            $this->actingAs($user)
                ->getJson(
                    route(
                        'shipments.tracking',
                        $shipment
                    )
                )
                ->assertOk();
        }
    }

    public function test_a_completed_delivery_assignment_does_not_expose_the_courier_location(): void
    {
        $customer = Customer::factory()->create();

        $shipment = $this->createShipmentFor(
            $customer
        );

        [$courier] = $this->assignActiveRoute(
            shipment: $shipment,
            deliveryStatus: 'DELIVERED'
        );

        CourierLocation::query()->create([
            'courier_id' => $courier->id,
            'latitude' => 12.1363890,
            'longitude' => -86.2513890,
            'gps_accuracy' => 8.50,
            'recorded_at' => now(),
        ]);

        $this->actingAs($customer->user)
            ->getJson(
                route(
                    'shipments.tracking',
                    $shipment
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.tracking_available',
                false
            )
            ->assertJsonPath(
                'data.reason',
                'NO_ACTIVE_ROUTE'
            )
            ->assertJsonPath(
                'data.location',
                null
            );
    }

    public function test_an_unverified_user_cannot_track_a_shipment(): void
    {
        $customer = Customer::factory()->create();

        $shipment = $this->createShipmentFor(
            $customer
        );

        $customer->user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this->actingAs(
            $customer->user->fresh()
        )
            ->getJson(
                route(
                    'shipments.tracking',
                    $shipment
                )
            )
            ->assertForbidden();
    }

    private function createShipmentFor(
        Customer $customer
    ): Shipment {
        return Shipment::factory()->create([
            'customer_id' => $customer->id,
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
        Shipment $shipment,
        string $deliveryStatus = 'IN_PROGRESS'
    ): array {
        $courier = Courier::factory()->create();

        $activeStatus = RouteStatus::query()
            ->where('status_name', 'ACTIVE')
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
                    $deliveryStatus,
            ]);

        return [
            $courier,
            $route,
            $routeShipment,
        ];
    }
}