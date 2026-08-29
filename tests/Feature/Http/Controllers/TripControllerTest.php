<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_access_trip_endpoints(): void
    {
        $trip = $this->tripFor(
            DeliveryProvider::factory()->create()
        );

        $this->getJson(
            route('trips.index')
        )->assertUnauthorized();

        $this->getJson(
            route('trips.show', $trip)
        )->assertUnauthorized();
    }

    public function test_provider_only_lists_their_own_trips(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $otherProvider = DeliveryProvider::factory()
            ->create();

        $ownTrip = $this->tripFor($provider);

        $this->tripFor($otherProvider);

        $this->actingAs($provider->user)
            ->getJson(route('trips.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $ownTrip->id
            )
            ->assertJsonPath('meta.total', 1);
    }

    public function test_trip_list_contains_inventory_summary(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $this->tripFor(
            $provider,
            'AVAILABLE'
        );

        $this->tripFor(
            $provider,
            'AVAILABLE'
        );

        $this->tripFor(
            $provider,
            'USED'
        );

        $this->actingAs($provider->user)
            ->getJson(route('trips.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.available', 2)
            ->assertJsonPath('meta.used', 1);
    }

    public function test_support_and_administration_list_all_trips(): void
    {
        $this->tripFor(
            DeliveryProvider::factory()->create()
        );

        $this->tripFor(
            DeliveryProvider::factory()->create()
        );

        $users = [
            $this->createUserWithRole('SUPPORT_AGENT'),
            $this->createUserWithRole('ADMINISTRATOR'),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)
                ->getJson(route('trips.index'))
                ->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonPath('meta.total', 2);
        }
    }

    public function test_provider_can_view_their_own_trip(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $trip = $this->tripFor($provider);

        $this->actingAs($provider->user)
            ->getJson(
                route('trips.show', $trip)
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $trip->id
            )
            ->assertJsonPath(
                'data.delivery_provider_id',
                $provider->id
            );
    }

    public function test_provider_cannot_view_another_providers_trip(): void
    {
        $owner = DeliveryProvider::factory()->create();

        $unrelatedProvider = DeliveryProvider::factory()
            ->create();

        $trip = $this->tripFor($owner);

        $this->actingAs($unrelatedProvider->user)
            ->getJson(
                route('trips.show', $trip)
            )
            ->assertForbidden();
    }

    public function test_support_and_administration_can_view_trip_details(): void
    {
        $trip = $this->tripFor(
            DeliveryProvider::factory()->create()
        );

        $users = [
            $this->createUserWithRole('SUPPORT_AGENT'),
            $this->createUserWithRole('ADMINISTRATOR'),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)
                ->getJson(
                    route('trips.show', $trip)
                )
                ->assertOk()
                ->assertJsonPath(
                    'data.id',
                    $trip->id
                );
        }
    }

    public function test_unauthorized_users_cannot_access_trips(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $trip = $this->tripFor($provider);

        $customer = Customer::factory()
            ->create()
            ->user;

        $courier = Courier::factory()
            ->create([
                'delivery_provider_id' => $provider->id,
            ])
            ->user;

        foreach ([
            $customer,
            $courier,
        ] as $user) {
            $this->actingAs($user)
                ->getJson(route('trips.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->getJson(
                    route('trips.show', $trip)
                )
                ->assertForbidden();
        }

        $provider->user->update([
            'account_status_id' => AccountStatus::query()
                ->where('status_name', 'SUSPENDED')
                ->firstOrFail()
                ->id,
        ]);

        $this->actingAs($provider->user->fresh())
            ->getJson(route('trips.index'))
            ->assertForbidden();
    }

    private function tripFor(
        DeliveryProvider $provider,
        string $status = 'AVAILABLE'
    ): Trip {
        return Trip::factory()->create([
            'delivery_provider_id' => $provider->id,
            'status' => $status,
            'used_at' => $status === 'USED'
                ? now()
                : null,
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
}