<?php

namespace Tests\Feature;

use App\Models\DeliveryService;
use App\Models\RouteShipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    /***
     * Run DatabaseSeeder before each test.
     */
    protected $seed = true;

    public function test_expected_catalogs_are_seeded(): void
    {
        $this->assertDatabaseCount('roles', 5);
        $this->assertDatabaseCount('account_statuses', 4);
        $this->assertDatabaseCount('customer_types', 2);
        $this->assertDatabaseCount('provider_types', 2);
        $this->assertDatabaseCount('trip_types', 2);

        $this->assertDatabaseHas('roles', [
            'role_name' => 'CUSTOMER',
        ]);

        $this->assertDatabaseHas('roles', [
            'role_name' => 'DELIVERY_PROVIDER',
        ]);

        $this->assertDatabaseHas('trip_types', [
            'type_name' => 'LOCAL',
        ]);

        $this->assertDatabaseHas('trip_types', [
            'type_name' => 'INTERMUNICIPAL',
        ]);

        $this->assertDatabaseHas('recharge_packages', [
            'package_name' => 'LOCAL_10',
            'trip_quantity' => 10,
        ]);

        $this->assertDatabaseHas('shipment_statuses', [
            'status_name' => 'REQUESTED',
        ]);
    }

    public function test_demo_scenario_is_created_consistently(): void
    {
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('delivery_providers', 1);
        $this->assertDatabaseCount('couriers', 1);
        $this->assertDatabaseCount('vehicles', 1);
        $this->assertDatabaseCount('shipments', 1);
        $this->assertDatabaseCount('delivery_services', 1);
        $this->assertDatabaseCount('recharges', 1);
        $this->assertDatabaseCount('trips', 10);
        $this->assertDatabaseCount('trip_transactions', 2);
        $this->assertDatabaseCount('routes', 1);
        $this->assertDatabaseCount('route_shipments', 1);
        $this->assertDatabaseCount('payments', 1);

        $this->assertDatabaseHas('recharges', [
            'payment_reference' => 'DEMO-RECHARGE-001',
            'trip_quantity' => 10,
        ]);

        $this->assertDatabaseHas('trip_transactions', [
            'transaction_type' => 'CREDIT',
            'quantity' => 10,
        ]);

        $this->assertDatabaseHas('trip_transactions', [
            'transaction_type' => 'DEBIT',
            'quantity' => 1,
        ]);

        $this->assertDatabaseHas('payments', [
            'payment_reference' => 'DEMO-PAYMENT-001',
        ]);
    }

    public function test_demo_relationships_are_consistent(): void
    {
        $service = DeliveryService::query()
            ->with([
                'customer',
                'shipment.customer',
                'shipment.packages',
                'trip.deliveryProvider',
                'payment',
            ])
            ->firstOrFail();

        $this->assertTrue(
            $service->customer->is($service->shipment->customer)
        );

        $this->assertSame(
            $service->trip_type_id,
            $service->trip->trip_type_id
        );

        $this->assertSame('USED', $service->trip->status);
        $this->assertNotNull($service->payment);

        $this->assertGreaterThanOrEqual(
            1,
            $service->shipment->packages->count()
        );

        $routeShipment = RouteShipment::query()
            ->with('route.courier.deliveryProvider')
            ->where('shipment_id', $service->shipment_id)
            ->firstOrFail();

        $this->assertTrue(
            $routeShipment->route
                ->courier
                ->deliveryProvider
                ->is($service->trip->deliveryProvider)
        );
    }
}

