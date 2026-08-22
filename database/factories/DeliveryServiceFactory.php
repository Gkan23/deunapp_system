<?php

namespace Database\Factories;

use App\Models\DeliveryService;
use App\Models\ServiceType;
use App\Models\Shipment;
use App\Models\TripType;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryServiceFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (DeliveryService $service): void {
            $service->customer_id = Shipment::query()
                ->findOrFail($service->shipment_id)
                ->customer_id;
        });
    }

    public function definition(): array
    {
        return [
            'trip_id' => null,
            'shipment_id' => Shipment::factory(),
            'customer_id' => null,
            'service_type_id' => ServiceType::query()->where('service_name', 'STANDARD')->firstOrFail()->id,
            'trip_type_id' => TripType::query()->where('type_name', 'LOCAL')->firstOrFail()->id,
            'status' => 'REQUESTED',
            'requested_at' => now(),
            'accepted_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'delivery_fee' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

