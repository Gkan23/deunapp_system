<?php

namespace Database\Factories;

use App\Models\CustomerType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'customer_type_id' => CustomerType::query()->where('type_name', 'INDIVIDUAL')->firstOrFail()->id,
            'identity_number' => strtoupper(fake()->unique()->bothify('###-######-####?')),
            'company_name' => null,
            'phone' => fake()->numerify('8#######'),
        ];
    }

    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_type_id' => CustomerType::query()->where('type_name', 'BUSINESS')->firstOrFail()->id,
            'company_name' => fake()->company(),
        ]);
    }
}

