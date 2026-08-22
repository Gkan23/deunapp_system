<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentPersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('8#######'),
            'identity_number' => fake()->boolean(70)
                ? strtoupper(fake()->unique()->bothify('###-######-####?'))
                : null,
            'email' => fake()->optional()->safeEmail(),
            'person_type' => 'SENDER',
        ];
    }

    public function sender(): static
    {
        return $this->state(fn (array $attributes) => ['person_type' => 'SENDER']);
    }

    public function recipient(): static
    {
        return $this->state(fn (array $attributes) => ['person_type' => 'RECIPIENT']);
    }
}