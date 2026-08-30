<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\CourierLocation;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierLocationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_view_the_latest_courier_location(): void
    {
        $courier = Courier::factory()->create();

        CourierLocation::factory()->create([
            'courier_id' => $courier->id,
        ]);

        $this->getJson(
            $this->endpoint($courier)
        )->assertUnauthorized();
    }

    public function test_a_provider_can_view_the_latest_location_of_their_courier(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
        ]);

        $olderLocation =
            CourierLocation::factory()->create([
                'courier_id' => $courier->id,
                'latitude' => 12.1000000,
                'longitude' => -86.2000000,
                'gps_accuracy' => 20.00,
                'recorded_at' =>
                    now()->subMinutes(10),
            ]);

        $latestLocation =
            CourierLocation::factory()->create([
                'courier_id' => $courier->id,
                'latitude' => 12.1363890,
                'longitude' => -86.2513890,
                'gps_accuracy' => 8.50,
                'recorded_at' => now(),
            ]);

        $this->actingAs($provider->user)
            ->getJson(
                $this->endpoint($courier)
            )
            ->assertOk()
            ->assertJsonPath(
                'data.courier.id',
                $courier->id
            )
            ->assertJsonPath(
                'data.location.id',
                $latestLocation->id
            )
            ->assertJsonPath(
                'data.location.latitude',
                12.136389
            )
            ->assertJsonPath(
                'data.location.longitude',
                -86.251389
            )
            ->assertJsonPath(
                'data.location.gps_accuracy',
                8.5
            );

        $this->assertNotSame(
            $olderLocation->id,
            $latestLocation->id
        );
    }

    public function test_a_courier_can_view_their_own_latest_location(): void
    {
        $courier = Courier::factory()->create();

        $location =
            CourierLocation::factory()->create([
                'courier_id' => $courier->id,
                'latitude' => 12.1450000,
                'longitude' => -86.2300000,
                'recorded_at' => now(),
            ]);

        $this->actingAs($courier->user)
            ->getJson(
                $this->endpoint($courier)
            )
            ->assertOk()
            ->assertJsonPath(
                'data.location.id',
                $location->id
            );
    }

    public function test_support_and_administration_can_view_courier_locations(): void
    {
        $courier = Courier::factory()->create();

        $location =
            CourierLocation::factory()->create([
                'courier_id' => $courier->id,
            ]);

        $authorizedUsers = [
            $this->userWithRole(
                'SUPPORT_AGENT'
            ),
            $this->userWithRole(
                'ADMINISTRATOR'
            ),
        ];

        foreach ($authorizedUsers as $user) {
            $this->actingAs($user)
                ->getJson(
                    $this->endpoint($courier)
                )
                ->assertOk()
                ->assertJsonPath(
                    'data.location.id',
                    $location->id
                );
        }
    }

    public function test_a_provider_cannot_view_another_providers_courier_location(): void
    {
        $owner = DeliveryProvider::factory()
            ->create();

        $otherProvider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $owner->id,
        ]);

        CourierLocation::factory()->create([
            'courier_id' => $courier->id,
        ]);

        $this->actingAs($otherProvider->user)
            ->getJson(
                $this->endpoint($courier)
            )
            ->assertForbidden();
    }

    public function test_a_customer_cannot_view_a_courier_location_directly(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $courier = Courier::factory()->create();

        CourierLocation::factory()->create([
            'courier_id' => $courier->id,
        ]);

        $this->actingAs($customer)
            ->getJson(
                $this->endpoint($courier)
            )
            ->assertForbidden();
    }

    public function test_a_suspended_user_cannot_view_a_courier_location(): void
    {
        $administrator = User::factory()
            ->administrator()
            ->create();

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $administrator->update([
            'account_status_id' =>
                $suspendedStatus->id,
        ]);

        $courier = Courier::factory()->create();

        CourierLocation::factory()->create([
            'courier_id' => $courier->id,
        ]);

        $this->actingAs(
            $administrator->fresh()
        )
            ->getJson(
                $this->endpoint($courier)
            )
            ->assertForbidden();
    }

    public function test_not_found_is_returned_when_the_courier_has_no_locations(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
        ]);

        $this->actingAs($provider->user)
            ->getJson(
                $this->endpoint($courier)
            )
            ->assertNotFound()
            ->assertJsonPath(
                'message',
                'No location has been recorded for this courier.'
            );
    }

    public function test_the_highest_id_is_used_when_locations_have_the_same_recorded_time(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
        ]);

        $recordedAt = now();

        CourierLocation::factory()->create([
            'courier_id' => $courier->id,
            'recorded_at' => $recordedAt,
        ]);

        $latestLocation =
            CourierLocation::factory()->create([
                'courier_id' => $courier->id,
                'recorded_at' => $recordedAt,
            ]);

        $this->actingAs($provider->user)
            ->getJson(
                $this->endpoint($courier)
            )
            ->assertOk()
            ->assertJsonPath(
                'data.location.id',
                $latestLocation->id
            );
    }

    private function userWithRole(
        string $roleName
    ): User {
        $role = Role::query()
            ->where('role_name', $roleName)
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }

    private function endpoint(
        Courier $courier
    ): string {
        return route(
            'couriers.locations.latest',
            $courier
        );
    }
}