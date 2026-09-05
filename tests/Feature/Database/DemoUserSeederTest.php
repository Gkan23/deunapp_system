<?php

namespace Tests\Feature\Database;

use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_reusable_active_demo_users(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->seed(DemoUserSeeder::class);
        $this->seed(DemoUserSeeder::class);

        $expectedUsers = [
            'administrador@deunapp.com' => 'ADMINISTRATOR',
            'cliente@deunapp.com' => 'CUSTOMER',
            'proveedor@deunapp.com' => 'DELIVERY_PROVIDER',
            'repartidor@deunapp.com' => 'COURIER',
            'soporte@deunapp.com' => 'SUPPORT_AGENT',
        ];

        foreach ($expectedUsers as $email => $roleName) {
            $user = User::query()
                ->where('email', $email)
                ->firstOrFail();

            $this->assertSame(
                $roleName,
                $user->role->role_name
            );

            $this->assertSame(
                'ACTIVE',
                $user->accountStatus->status_name
            );

            $this->assertNotNull(
                $user->email_verified_at
            );

            $this->assertTrue(
                Hash::check(
                    'DeUnapp123!',
                    $user->password
                )
            );
        }

        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('delivery_providers', 1);
        $this->assertDatabaseCount('couriers', 1);

        $provider = DeliveryProvider::query()
            ->whereHas(
                'user',
                fn ($query) => $query->where(
                    'email',
                    'proveedor@deunapp.com'
                )
            )
            ->firstOrFail();

        $courier = Courier::query()
            ->whereHas(
                'user',
                fn ($query) => $query->where(
                    'email',
                    'repartidor@deunapp.com'
                )
            )
            ->firstOrFail();

        $this->assertSame(
            $provider->id,
            $courier->delivery_provider_id
        );
    }
}