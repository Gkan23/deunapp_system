<?php

namespace Tests\Feature\Policies;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TripPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_supported_roles_can_access_the_trip_list(): void
    {
        $provider = DeliveryProvider::factory()
            ->create()
            ->user;

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $administrator = $this->createUserWithRole(
            'ADMINISTRATOR'
        );

        foreach ([
            $provider,
            $supportAgent,
            $administrator,
        ] as $user) {
            $this->assertTrue(
                $this->allows(
                    $user,
                    'viewAny',
                    Trip::class
                )
            );
        }

        foreach ([
            Customer::factory()->create()->user,
            Courier::factory()->create()->user,
        ] as $user) {
            $this->assertFalse(
                $this->allows(
                    $user,
                    'viewAny',
                    Trip::class
                )
            );
        }
    }

    public function test_inactive_account_cannot_access_trips(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $trip = $this->tripFor($provider);

        $provider->user->update([
            'account_status_id' => AccountStatus::query()
                ->where('status_name', 'SUSPENDED')
                ->firstOrFail()
                ->id,
        ]);

        $user = $provider->user->fresh();

        $this->assertFalse(
            $this->allows(
                $user,
                'viewAny',
                Trip::class
            )
        );

        $this->assertFalse(
            $this->allows($user, 'view', $trip)
        );
    }

    public function test_inactive_provider_profile_cannot_access_trips(): void
    {
        $provider = DeliveryProvider::factory()->create([
            'is_active' => false,
        ]);

        $trip = $this->tripFor($provider);

        $this->assertFalse(
            $this->allows(
                $provider->user,
                'viewAny',
                Trip::class
            )
        );

        $this->assertFalse(
            $this->allows(
                $provider->user,
                'view',
                $trip
            )
        );
    }

    public function test_provider_can_view_their_own_trip(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $trip = $this->tripFor($provider);

        $this->assertTrue(
            $this->allows(
                $provider->user,
                'view',
                $trip
            )
        );
    }

    public function test_provider_cannot_view_another_providers_trip(): void
    {
        $owner = DeliveryProvider::factory()->create();

        $unrelatedProvider = DeliveryProvider::factory()
            ->create();

        $trip = $this->tripFor($owner);

        $this->assertFalse(
            $this->allows(
                $unrelatedProvider->user,
                'view',
                $trip
            )
        );
    }

    public function test_provider_courier_cannot_view_trip_inventory(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
        ]);

        $trip = $this->tripFor($provider);

        $this->assertFalse(
            $this->allows(
                $courier->user,
                'view',
                $trip
            )
        );
    }

    public function test_support_and_administration_can_view_trips(): void
    {
        $trip = $this->tripFor(
            DeliveryProvider::factory()->create()
        );

        foreach ([
            $this->createUserWithRole('SUPPORT_AGENT'),
            $this->createUserWithRole('ADMINISTRATOR'),
        ] as $user) {
            $this->assertTrue(
                $this->allows(
                    $user,
                    'view',
                    $trip
                )
            );
        }
    }

    public function test_trips_cannot_be_modified_directly(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $trip = $this->tripFor($provider);

        $this->assertFalse(
            $this->allows(
                $provider->user,
                'create',
                Trip::class
            )
        );

        foreach ([
            'update',
            'delete',
            'restore',
            'forceDelete',
        ] as $ability) {
            $this->assertFalse(
                $this->allows(
                    $provider->user,
                    $ability,
                    $trip
                )
            );
        }
    }

    private function tripFor(
        DeliveryProvider $provider
    ): Trip {
        return Trip::factory()->create([
            'delivery_provider_id' => $provider->id,
            'status' => 'AVAILABLE',
            'used_at' => null,
        ]);
    }

    private function createUserWithRole(
        string $roleName
    ): User {
        return User::factory()->create([
            'role_id' => Role::query()
                ->where('role_name', $roleName)
                ->firstOrFail()
                ->id,
        ]);
    }

    private function allows(
        User $user,
        string $ability,
        mixed $arguments
    ): bool {
        return Gate::forUser($user)->allows(
            $ability,
            $arguments
        );
    }
}