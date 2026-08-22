<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Shipment;
use App\Models\ShipmentPerson;
use App\Models\ShipmentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ShipmentFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Shipment $shipment): void {
            Package::factory()->for($shipment)->create();
        });
    }

    public function definition(): array
    {
        return [
            'tracking_code' => 'DUNA-'.Str::upper(fake()->unique()->bothify('########??')),
            'customer_id' => Customer::factory(),
            'sender_id' => ShipmentPerson::factory()->sender(),
            'recipient_id' => ShipmentPerson::factory()->recipient(),
            'origin_address_id' => Address::factory(),
            'destination_address_id' => Address::factory(),
            'origin_branch_id' => null,
            'destination_branch_id' => null,
            'shipment_status_id' => ShipmentStatus::query()->where('status_name', 'REQUESTED')->firstOrFail()->id,
            'requested_at' => now(),
            'scheduled_at' => null,
            'estimated_delivery_at' => null,
            'delivered_at' => null,
            'declared_value' => fake()->randomFloat(2, 100, 5000),
            'delivery_instructions' => fake()->optional()->sentence(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

