<?php

namespace Database\Factories;

use App\Models\ProviderType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->deliveryProvider(),
            'provider_type_id' => ProviderType::query()->where('type_name', 'INDEPENDENT')->firstOrFail()->id,
            'business_name' => null,
            'identity_number' => strtoupper(fake()->unique()->bothify('###-######-####?')),
            'phone' => fake()->numerify('8#######'),
            'is_active' => true,
        ];
    }

    public function company(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_type_id' => ProviderType::query()->where('type_name', 'COMPANY')->firstOrFail()->id,
            'business_name' => fake()->company(),
        ]);
    }
}
