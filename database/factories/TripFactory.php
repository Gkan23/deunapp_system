<?php

namespace Database\Factories;

use App\Models\DeliveryProvider;
use App\Models\TripType;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripFactory extends Factory
{
    public function definition(): array
    {
        return [
            'delivery_provider_id' => DeliveryProvider::factory(),
            'trip_type_id' => TripType::query()->where('type_name', 'LOCAL')->firstOrFail()->id,
            'status' => 'AVAILABLE',
            'used_at' => null,
        ];
    }

    public function intermunicipal(): static
    {
        return $this->state(fn (array $attributes) => [
            'trip_type_id' => TripType::query()->where('type_name', 'INTERMUNICIPAL')->firstOrFail()->id,
        ]);
    }
}
