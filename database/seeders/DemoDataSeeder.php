<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Municipality;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentStatus;
use App\Models\Recharge;
use App\Models\RechargePackage;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\ServiceType;
use App\Models\Shipment;
use App\Models\ShipmentPerson;
use App\Models\ShipmentStatus;
use App\Models\ShipmentStatusHistory;
use App\Models\Trip;
use App\Models\TripTransaction;
use App\Models\TripType;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $customer = Customer::factory()->create();
            $provider = DeliveryProvider::factory()->create();
            $courier = Courier::factory()->for($provider)->create();

            Vehicle::factory()->for($courier)->create();

            $municipality = Municipality::query()
                ->where('municipality_name', 'Estelí')
                ->firstOrFail();

            $origin = Address::factory()->create(['municipality_id' => $municipality->id]);
            $destination = Address::factory()->create(['municipality_id' => $municipality->id]);
            $sender = ShipmentPerson::factory()->sender()->create();
            $recipient = ShipmentPerson::factory()->recipient()->create();
            $requestedStatus = ShipmentStatus::query()->where('status_name', 'REQUESTED')->firstOrFail();

            $shipment = Shipment::factory()->for($customer)->create([
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'origin_address_id' => $origin->id,
                'destination_address_id' => $destination->id,
                'shipment_status_id' => $requestedStatus->id,
            ]);

            Package::factory()->for($shipment)->create();

            ShipmentStatusHistory::query()->create([
                'shipment_id' => $shipment->id,
                'shipment_status_id' => $requestedStatus->id,
                'changed_by_user_id' => $customer->user_id,
                'comment' => 'Estado inicial del envío demo.',
                'changed_at' => now(),
            ]);

            $tripType = TripType::query()->where('type_name', 'LOCAL')->firstOrFail();
            $rechargePackage = RechargePackage::query()->where('package_name', 'LOCAL_10')->firstOrFail();

            $recharge = Recharge::query()->create([
                'delivery_provider_id' => $provider->id,
                'recharge_package_id' => $rechargePackage->id,
                'trip_type_id' => $tripType->id,
                'trip_quantity' => $rechargePackage->trip_quantity,
                'commission_amount' => $rechargePackage->commissionRule->commission_amount,
                'amount' => $rechargePackage->price,
                'payment_reference' => 'DEMO-RECHARGE-001',
                'recharged_at' => now(),
            ]);

            $trips = Trip::factory()
                ->count($recharge->trip_quantity)
                ->for($provider)
                ->create(['trip_type_id' => $tripType->id]);

            TripTransaction::query()->create([
                'delivery_provider_id' => $provider->id,
                'recharge_id' => $recharge->id,
                'trip_id' => null,
                'transaction_type' => 'CREDIT',
                'quantity' => $recharge->trip_quantity,
                'description' => 'Crédito de viajes generado por la recarga demo.',
                'transaction_at' => now(),
            ]);

            $trip = $trips->firstOrFail();
            $serviceType = ServiceType::query()->where('service_name', 'STANDARD')->firstOrFail();

            $service = DeliveryService::factory()->for($customer)->create([
                'trip_id' => $trip->id,
                'shipment_id' => $shipment->id,
                'service_type_id' => $serviceType->id,
                'trip_type_id' => $tripType->id,
                'status' => 'ASSIGNED',
                'accepted_at' => now(),
                'delivery_fee' => 120.00,
            ]);

            $trip->update(['status' => 'USED', 'used_at' => now()]);

            TripTransaction::query()->create([
                'delivery_provider_id' => $provider->id,
                'recharge_id' => null,
                'trip_id' => $trip->id,
                'transaction_type' => 'DEBIT',
                'quantity' => 1,
                'description' => 'Viaje consumido por el servicio demo.',
                'transaction_at' => now(),
            ]);

            $route = Route::query()->create([
                'courier_id' => $courier->id,
                'route_status_id' => RouteStatus::query()->where('status_name', 'PLANNED')->firstOrFail()->id,
                'route_date' => today(),
                'started_at' => null,
                'finished_at' => null,
                'estimated_distance_km' => 8.50,
            ]);

            RouteShipment::query()->create([
                'route_id' => $route->id,
                'shipment_id' => $shipment->id,
                'delivery_order' => 1,
                'delivery_status' => 'PENDING',
            ]);

            Payment::query()->create([
                'delivery_service_id' => $service->id,
                'payment_method_id' => PaymentMethod::query()->where('method_name', 'CASH')->firstOrFail()->id,
                'payment_status_id' => PaymentStatus::query()->where('status_name', 'PENDING')->firstOrFail()->id,
                'amount' => $service->delivery_fee,
                'payment_reference' => 'DEMO-PAYMENT-001',
                'paid_at' => null,
            ]);
        });
    }
}
