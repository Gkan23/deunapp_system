<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Role;
use App\Models\Route as DeliveryRoute;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentPackageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_view_shipment_packages(): void
    {
        $shipment = $this
            ->createShipmentWithoutPackages();

        $this->getJson(
            route(
                'shipments.packages.index',
                $shipment
            )
        )->assertUnauthorized();
    }

    public function test_the_customer_can_view_their_shipment_packages(): void
    {
        $customer = Customer::factory()->create();

        $shipment =
            $this->createShipmentWithoutPackages([
                'customer_id' => $customer->id,
            ]);

        $firstPackage = Package::query()->create([
            'shipment_id' => $shipment->id,
            'weight' => 2.50,
            'height' => 20.00,
            'width' => 15.00,
            'length' => 30.00,
            'content_description' =>
                'Electronic accessories.',
            'is_fragile' => true,
            'declared_value' => 1500.00,
        ]);

        $secondPackage = Package::query()->create([
            'shipment_id' => $shipment->id,
            'weight' => 3.25,
            'height' => 25.00,
            'width' => 20.00,
            'length' => 35.00,
            'content_description' =>
                'Clothing items.',
            'is_fragile' => false,
            'declared_value' => 500.00,
        ]);

        $this->actingAs($customer->user)
            ->getJson(
                route(
                    'shipments.packages.index',
                    $shipment
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shipment.id',
                $shipment->id
            )
            ->assertJsonPath(
                'data.shipment.tracking_code',
                $shipment->tracking_code
            )
            ->assertJsonPath(
                'data.summary.package_count',
                2
            )
            ->assertJsonPath(
                'data.summary.fragile_package_count',
                1
            )
            ->assertJsonPath(
                'data.summary.total_weight',
                5.75
            )
            ->assertJsonPath(
                'data.summary.total_declared_value',
                2000
            )
            ->assertJsonCount(
                2,
                'data.packages'
            )
            ->assertJsonPath(
                'data.packages.0.id',
                $firstPackage->id
            )
            ->assertJsonPath(
                'data.packages.0.weight',
                2.5
            )
            ->assertJsonPath(
                'data.packages.0.dimensions.height',
                20
            )
            ->assertJsonPath(
                'data.packages.0.dimensions.width',
                15
            )
            ->assertJsonPath(
                'data.packages.0.dimensions.length',
                30
            )
            ->assertJsonPath(
                'data.packages.0.content_description',
                'Electronic accessories.'
            )
            ->assertJsonPath(
                'data.packages.0.is_fragile',
                true
            )
            ->assertJsonPath(
                'data.packages.0.declared_value',
                1500
            )
            ->assertJsonPath(
                'data.packages.1.id',
                $secondPackage->id
            )
            ->assertJsonPath(
                'data.packages.1.weight',
                3.25
            )
            ->assertJsonPath(
                'data.packages.1.is_fragile',
                false
            );
    }

    public function test_an_empty_shipment_returns_an_empty_package_list(): void
    {
        $customer = Customer::factory()->create();

        $shipment =
            $this->createShipmentWithoutPackages([
                'customer_id' => $customer->id,
            ]);

        $this->actingAs($customer->user)
            ->getJson(
                route(
                    'shipments.packages.index',
                    $shipment
                )
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.packages'
            )
            ->assertJsonPath(
                'data.packages',
                []
            )
            ->assertJsonPath(
                'data.summary.package_count',
                0
            )
            ->assertJsonPath(
                'data.summary.fragile_package_count',
                0
            )
            ->assertJsonPath(
                'data.summary.total_weight',
                0
            )
            ->assertJsonPath(
                'data.summary.total_declared_value',
                0
            );
    }

    public function test_nullable_package_values_are_returned_as_null(): void
    {
        $customer = Customer::factory()->create();

        $shipment =
            $this->createShipmentWithoutPackages([
                'customer_id' => $customer->id,
            ]);

        $package = Package::query()->create([
            'shipment_id' => $shipment->id,
            'weight' => null,
            'height' => null,
            'width' => null,
            'length' => null,
            'content_description' => null,
            'is_fragile' => false,
            'declared_value' => null,
        ]);

        $this->actingAs($customer->user)
            ->getJson(
                route(
                    'shipments.packages.index',
                    $shipment
                )
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.packages'
            )
            ->assertJsonPath(
                'data.packages.0.id',
                $package->id
            )
            ->assertJsonPath(
                'data.packages.0.weight',
                null
            )
            ->assertJsonPath(
                'data.packages.0.dimensions.height',
                null
            )
            ->assertJsonPath(
                'data.packages.0.dimensions.width',
                null
            )
            ->assertJsonPath(
                'data.packages.0.dimensions.length',
                null
            )
            ->assertJsonPath(
                'data.packages.0.content_description',
                null
            )
            ->assertJsonPath(
                'data.packages.0.declared_value',
                null
            )
            ->assertJsonPath(
                'data.summary.total_weight',
                0
            )
            ->assertJsonPath(
                'data.summary.total_declared_value',
                0
            );
    }

    public function test_an_unrelated_customer_cannot_view_the_packages(): void
    {
        $owner = Customer::factory()->create();

        $unrelatedCustomer =
            Customer::factory()->create();

        $shipment =
            $this->createShipmentWithoutPackages([
                'customer_id' => $owner->id,
            ]);

        Package::factory()->create([
            'shipment_id' => $shipment->id,
        ]);

        $this->actingAs(
            $unrelatedCustomer->user
        )
            ->getJson(
                route(
                    'shipments.packages.index',
                    $shipment
                )
            )
            ->assertForbidden();
    }

    public function test_the_assigned_courier_can_view_the_packages(): void
    {
        $shipment = $this
            ->createShipmentWithoutPackages();

        $courier = Courier::factory()->create();

        $this->assignCourier(
            $shipment,
            $courier
        );

        $package = Package::factory()->create([
            'shipment_id' => $shipment->id,
        ]);

        $this->actingAs($courier->user)
            ->getJson(
                route(
                    'shipments.packages.index',
                    $shipment
                )
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.packages'
            )
            ->assertJsonPath(
                'data.packages.0.id',
                $package->id
            );
    }

    public function test_support_and_administration_can_view_the_packages(): void
    {
        $shipment = $this
            ->createShipmentWithoutPackages();

        $package = Package::factory()->create([
            'shipment_id' => $shipment->id,
        ]);

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
                        'shipments.packages.index',
                        $shipment
                    )
                )
                ->assertOk()
                ->assertJsonCount(
                    1,
                    'data.packages'
                )
                ->assertJsonPath(
                    'data.packages.0.id',
                    $package->id
                );
        }
    }

    public function test_an_unverified_customer_cannot_view_packages(): void
    {
        $customer = Customer::factory()->create();

        $shipment =
            $this->createShipmentWithoutPackages([
                'customer_id' => $customer->id,
            ]);

        Package::factory()->create([
            'shipment_id' => $shipment->id,
        ]);

        $customer->user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this->actingAs(
            $customer->user->fresh()
        )
            ->getJson(
                route(
                    'shipments.packages.index',
                    $shipment
                )
            )
            ->assertForbidden();
    }

    /**
     * Crea un envío y elimina los paquetes que
     * ShipmentFactory genera automáticamente.
     *
     * @param array<string, mixed> $attributes
     */
    private function createShipmentWithoutPackages(
        array $attributes = []
    ): Shipment {
        $shipment = Shipment::factory()->create(
            $attributes
        );

        $shipment->packages()->delete();

        return $shipment;
    }

    private function assignCourier(
        Shipment $shipment,
        Courier $courier
    ): RouteShipment {
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

        return RouteShipment::query()->create([
            'route_id' => $route->id,
            'shipment_id' => $shipment->id,
            'delivery_order' => 1,
            'delivery_status' => 'IN_PROGRESS',
        ]);
    }
}