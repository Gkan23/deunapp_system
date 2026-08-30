<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\Route as DeliveryRoute;
use App\Models\RouteStatus;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentCourierLocationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_record_a_courier_location(): void
    {
        $this->postJson(
            $this->endpoint(),
            $this->validPayload()
        )->assertUnauthorized();
    }

    public function test_a_courier_with_an_active_route_can_record_a_location(): void
    {
        $courier = $this->createCourierWithActiveRoute();

        $this->actingAs($courier->user)
            ->postJson(
                $this->endpoint(),
                $this->validPayload()
            )
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Courier location recorded successfully.'
            )
            ->assertJsonPath(
                'data.courier_id',
                $courier->id
            )
            ->assertJsonPath(
                'data.latitude',
                12.136389
            )
            ->assertJsonPath(
                'data.longitude',
                -86.251389
            )
            ->assertJsonPath(
                'data.gps_accuracy',
                8.5
            );

        $this->assertDatabaseHas(
            'courier_locations',
            [
                'courier_id' => $courier->id,
                'latitude' => 12.1363890,
                'longitude' => -86.2513890,
                'gps_accuracy' => 8.50,
            ]
        );
    }

    public function test_gps_accuracy_is_optional(): void
    {
        $courier = $this->createCourierWithActiveRoute();

        $payload = $this->validPayload();

        unset($payload['gps_accuracy']);

        $this->actingAs($courier->user)
            ->postJson(
                $this->endpoint(),
                $payload
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.gps_accuracy',
                null
            );

        $this->assertDatabaseHas(
            'courier_locations',
            [
                'courier_id' => $courier->id,
                'gps_accuracy' => null,
            ]
        );
    }

    public function test_a_courier_without_an_active_route_cannot_record_a_location(): void
    {
        $courier = Courier::factory()->create([
            'is_active' => true,
            'is_available' => true,
        ]);

        $this->actingAs($courier->user)
            ->postJson(
                $this->endpoint(),
                $this->validPayload()
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The courier must have an active route before recording a location.'
            );

        $this->assertDatabaseCount(
            'courier_locations',
            0
        );
    }

    public function test_non_courier_roles_cannot_record_courier_locations(): void
    {
        $users = [
            User::factory()
                ->customer()
                ->create(),
            User::factory()
                ->deliveryProvider()
                ->create(),
            User::factory()
                ->administrator()
                ->create(),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)
                ->postJson(
                    $this->endpoint(),
                    $this->validPayload()
                )
                ->assertForbidden();
        }

        $this->assertDatabaseCount(
            'courier_locations',
            0
        );
    }

    public function test_a_suspended_courier_cannot_record_a_location(): void
    {
        $courier = $this->createCourierWithActiveRoute();

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $courier->user->update([
            'account_status_id' =>
                $suspendedStatus->id,
        ]);

        $this->actingAs(
            $courier->user->fresh()
        )
            ->postJson(
                $this->endpoint(),
                $this->validPayload()
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'courier_locations',
            0
        );
    }

    public function test_an_unverified_courier_cannot_record_a_location(): void
    {
        $courier = $this->createCourierWithActiveRoute();

        $courierUser = $courier->user;

        $courierUser->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this->actingAs($courierUser->fresh())
            ->postJson(
                $this->endpoint(),
                $this->validPayload()
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'courier_locations',
            0
        );
    }

    public function test_an_inactive_courier_cannot_record_a_location(): void
    {
        $courier = $this->createCourierWithActiveRoute();

        $courier->update([
            'is_active' => false,
        ]);

        $this->actingAs($courier->user)
            ->postJson(
                $this->endpoint(),
                $this->validPayload()
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'courier_locations',
            0
        );
    }

    public function test_a_courier_from_an_inactive_provider_cannot_record_a_location(): void
    {
        $courier = $this->createCourierWithActiveRoute();

        $courier->deliveryProvider->update([
            'is_active' => false,
        ]);

        $this->actingAs($courier->user)
            ->postJson(
                $this->endpoint(),
                $this->validPayload()
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'courier_locations',
            0
        );
    }

    public function test_latitude_and_longitude_are_required(): void
    {
        $courier = $this->createCourierWithActiveRoute();

        $this->actingAs($courier->user)
            ->postJson(
                $this->endpoint(),
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'latitude',
                'longitude',
            ]);
    }

    public function test_coordinate_ranges_are_validated(): void
    {
        $courier = $this->createCourierWithActiveRoute();

        $this->actingAs($courier->user)
            ->postJson(
                $this->endpoint(),
                [
                    'latitude' => 91,
                    'longitude' => -181,
                    'gps_accuracy' => 8.5,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'latitude',
                'longitude',
            ]);
    }

    public function test_gps_accuracy_is_validated(): void
    {
        $courier = $this->createCourierWithActiveRoute();

        $this->actingAs($courier->user)
            ->postJson(
                $this->endpoint(),
                [
                    'latitude' => 12.136389,
                    'longitude' => -86.251389,
                    'gps_accuracy' => -1,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'gps_accuracy',
            ]);
    }

    private function createCourierWithActiveRoute(): Courier
    {
        $courier = Courier::factory()->create([
            'is_active' => true,
            'is_available' => false,
        ]);

        $activeStatus = RouteStatus::query()
            ->where('status_name', 'ACTIVE')
            ->firstOrFail();

        DeliveryRoute::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' =>
                $activeStatus->id,
            'route_date' => today(),
            'started_at' => now(),
            'finished_at' => null,
            'estimated_distance_km' => 10.50,
        ]);

        return $courier;
    }

    /**
     * @return array<string, float>
     */
    private function validPayload(): array
    {
        return [
            'latitude' => 12.136389,
            'longitude' => -86.251389,
            'gps_accuracy' => 8.50,
        ];
    }

    private function endpoint(): string
    {
        return route(
            'current-user.courier-locations.store'
        );
    }
}