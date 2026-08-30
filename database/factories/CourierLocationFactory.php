<?php

namespace Database\Factories;

use App\Models\Courier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\CourierLocation>
 */
class CourierLocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'courier_id' =>
                Courier::factory(),
            'latitude' =>
                fake()->latitude(),
            'longitude' =>
                fake()->longitude(),
            'gps_accuracy' =>
                fake()->randomFloat(
                    2,
                    1,
                    100
                ),
            'recorded_at' => now(),
        ];
    }
}
