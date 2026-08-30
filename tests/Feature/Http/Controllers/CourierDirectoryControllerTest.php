<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierDirectoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_access_courier_directory(): void
    {
        $courier = Courier::factory()->create();

        $this->getJson(
            route('couriers.index')
        )->assertUnauthorized();

        $this->getJson(
            route('couriers.show', $courier)
        )->assertUnauthorized();
    }

    public function test_a_provider_only_lists_their_couriers(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $otherProvider =
            DeliveryProvider::factory()
                ->create();

        $firstCourier = Courier::factory()
            ->create([
                'delivery_provider_id' =>
                    $provider->id,
            ]);

        $secondCourier = Courier::factory()
            ->create([
                'delivery_provider_id' =>
                    $provider->id,
            ]);

        $otherCourier = Courier::factory()
            ->create([
                'delivery_provider_id' =>
                    $otherProvider->id,
            ]);

        $this->actingAs($provider->user)
            ->getJson(
                route('couriers.index')
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                2
            )
            ->assertJsonFragment([
                'id' => $firstCourier->id,
            ])
            ->assertJsonFragment([
                'id' => $secondCourier->id,
            ])
            ->assertJsonMissing([
                'id' => $otherCourier->id,
            ]);
    }

    public function test_support_and_administration_list_all_couriers(): void
    {
        Courier::factory()->count(2)->create();

        foreach (
            [
                'SUPPORT_AGENT',
                'ADMINISTRATOR',
            ] as $roleName
        ) {
            $staffUser = $this->userWithRole(
                $roleName
            );

            $this->actingAs($staffUser)
                ->getJson(
                    route('couriers.index')
                )
                ->assertOk()
                ->assertJsonPath(
                    'meta.total',
                    2
                );
        }
    }

    public function test_courier_list_can_be_filtered(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $matchingUser = User::factory()
            ->courier()
            ->create([
                'name' => 'Search Courier',
                'email' =>
                    'search.courier@example.com',
            ]);

        Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
            'user_id' => $matchingUser->id,
            'license_number' =>
                'SEARCH-LICENSE',
            'is_active' => true,
            'is_available' => false,
        ]);

        Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        $this->actingAs($provider->user)
            ->getJson(
                route('couriers.index', [
                    'search' => 'search',
                    'is_active' => 'true',
                    'is_available' => 'false',
                ])
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                1
            )
            ->assertJsonPath(
                'data.0.user.email',
                'search.courier@example.com'
            )
            ->assertJsonPath(
                'data.0.license_number',
                'SEARCH-LICENSE'
            );
    }

    public function test_courier_filters_are_validated(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $this->actingAs($provider->user)
            ->getJson(
                route('couriers.index', [
                    'is_active' => 'unknown',
                    'is_available' => 'unknown',
                    'per_page' => 101,
                ])
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'is_active',
                'is_available',
                'per_page',
            ]);
    }

    public function test_a_provider_can_view_their_courier(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
        ]);

        $this->actingAs($provider->user)
            ->getJson(
                route(
                    'couriers.show',
                    $courier
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $courier->id
            )
            ->assertJsonPath(
                'data.delivery_provider.id',
                $provider->id
            );
    }

    public function test_a_provider_cannot_view_another_providers_courier(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $otherProvider =
            DeliveryProvider::factory()
                ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $otherProvider->id,
        ]);

        $this->actingAs($provider->user)
            ->getJson(
                route(
                    'couriers.show',
                    $courier
                )
            )
            ->assertForbidden();
    }

    public function test_a_courier_can_view_their_own_profile(): void
    {
        $courier = Courier::factory()->create();

        $this->actingAs($courier->user)
            ->getJson(
                route(
                    'couriers.show',
                    $courier
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $courier->id
            );
    }

    public function test_a_courier_cannot_view_another_courier(): void
    {
        $courier = Courier::factory()->create();

        $otherCourier =
            Courier::factory()->create();

        $this->actingAs($courier->user)
            ->getJson(
                route(
                    'couriers.show',
                    $otherCourier
                )
            )
            ->assertForbidden();
    }

    public function test_support_and_administration_can_view_any_courier(): void
    {
        $courier = Courier::factory()->create();

        foreach (
            [
                'SUPPORT_AGENT',
                'ADMINISTRATOR',
            ] as $roleName
        ) {
            $staffUser = $this->userWithRole(
                $roleName
            );

            $this->actingAs($staffUser)
                ->getJson(
                    route(
                        'couriers.show',
                        $courier
                    )
                )
                ->assertOk()
                ->assertJsonPath(
                    'data.id',
                    $courier->id
                );
        }
    }

    public function test_inactive_users_cannot_access_the_courier_directory(): void
    {
        $staffUser = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $courier = Courier::factory()->create();

        $suspendedStatus =
            AccountStatus::query()
                ->where(
                    'status_name',
                    'SUSPENDED'
                )
                ->firstOrFail();

        $staffUser->update([
            'account_status_id' =>
                $suspendedStatus->id,
        ]);

        $this->actingAs($staffUser->fresh())
            ->getJson(
                route('couriers.index')
            )
            ->assertForbidden();

        $this->actingAs($staffUser->fresh())
            ->getJson(
                route(
                    'couriers.show',
                    $courier
                )
            )
            ->assertForbidden();
    }

    private function userWithRole(
        string $roleName
    ): User {
        $role = Role::query()
            ->where(
                'role_name',
                $roleName
            )
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}