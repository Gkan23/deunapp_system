<?php

namespace Database\Factories;

use App\Models\AccountStatus;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role_id' => $this->catalogId(Role::class, 'role_name', 'CUSTOMER'),
            'account_status_id' => $this->catalogId(AccountStatus::class, 'status_name', 'ACTIVE'),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }

    public function customer(): static
    {
        return $this->withRole('CUSTOMER');
    }

    public function deliveryProvider(): static
    {
        return $this->withRole('DELIVERY_PROVIDER');
    }

    public function courier(): static
    {
        return $this->withRole('COURIER');
    }

    public function administrator(): static
    {
        return $this->withRole('ADMINISTRATOR');
    }

    private function withRole(string $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => $this->catalogId(Role::class, 'role_name', $role),
        ]);
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    private function catalogId(string $model, string $column, string $value): int
    {
        return $model::query()->where($column, $value)->firstOrFail()->getKey();
    }
}
