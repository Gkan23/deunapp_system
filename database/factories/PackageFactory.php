<?php

namespace Database\Factories;

use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PackageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'weight' => fake()->randomFloat(2, 0.10, 25),
            'height' => fake()->randomFloat(2, 5, 100),
            'width' => fake()->randomFloat(2, 5, 100),
            'length' => fake()->randomFloat(2, 5, 100),
            'content_description' => fake()->sentence(),
            'is_fragile' => fake()->boolean(20),
            'declared_value' => fake()->randomFloat(2, 50, 5000),
        ];
    }
}

