<?php

namespace Database\Factories;

use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'municipality_id' => Municipality::query()->inRandomOrder()->firstOrFail()->id,
            'address_line' => fake()->streetAddress(),
            'reference_note' => fake()->optional()->sentence(),
            'latitude' => fake()->latitude(12.9, 13.3),
            'longitude' => fake()->longitude(-86.6, -86.1),
        ];
    }
}
