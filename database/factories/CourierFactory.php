<?php

namespace Database\Factories;

use App\Models\DeliveryProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'delivery_provider_id' => DeliveryProvider::factory(),
            'user_id' => User::factory()->courier(),
            'license_number' => fake()->unique()->bothify('NIC-####-??'),
            'is_available' => true,
            'is_active' => true,
        ];
    }
}
