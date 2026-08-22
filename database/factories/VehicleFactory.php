<?php

namespace Database\Factories;

use App\Models\Courier;
use App\Models\VehicleStatus;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'courier_id' => Courier::factory(),
            'vehicle_type_id' => VehicleType::query()->where('type_name', 'MOTORCYCLE')->firstOrFail()->id,
            'vehicle_status_id' => VehicleStatus::query()->where('status_name', 'AVAILABLE')->firstOrFail()->id,
            'plate_number' => fake()->unique()->bothify('M ######'),
            'brand' => fake()->randomElement(['Honda', 'Yamaha', 'Suzuki', 'Bajaj']),
            'model' => fake()->bothify('Modelo-##'),
            'color' => fake()->safeColorName(),
        ];
    }
}

