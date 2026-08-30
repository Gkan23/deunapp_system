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

class CurrentCourierAvailabilityControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_change_courier_availability(): void
    {
        $this->patchJson(
            $this->endpoint(),
            [
                'is_available' => true,
                'comment' =>
                    'Ready to receive routes.',
            ]
        )->assertUnauthorized();
    }

    public function test_an_active_courier_can_become_available(): void
    {
        $courier = Courier::factory()->create([
            'is_active' => true,
            'is_available' => false,
        ]);

        $this->actingAs($courier->user)
            ->patchJson(
                $this->endpoint(),
                [
                    'is_available' => true,
                    'comment' =>
                        'Ready to receive routes.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Courier availability updated successfully.'
            )
            ->assertJsonPath(
                'data.id',
                $courier->id
            )
            ->assertJsonPath(
                'data.is_active',
                true
            )
            ->assertJsonPath(
                'data.is_available',
                true
            );

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' =>
                $courier->user_id,
            'table_name' => 'couriers',
            'record_id' => $courier->id,
            'action_type' =>
                'COURIER_AVAILABILITY_CHANGED',
        ]);
    }

    public function test_an_available_courier_can_become_unavailable(): void
    {
        $courier = Courier::factory()->create([
            'is_active' => true,
            'is_available' => true,
        ]);

        $this->actingAs($courier->user)
            ->patchJson(
                $this->endpoint(),
                [
                    'is_available' => false,
                    'comment' =>
                        'Courier finished their workday.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.is_available',
                false
            );

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'is_active' => true,
            'is_available' => false,
        ]);
    }

    public function test_a_courier_with_an_active_route_cannot_become_available(): void
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
            'estimated_distance_km' => 15.25,
        ]);

        $this->actingAs($courier->user)
            ->patchJson(
                $this->endpoint(),
                [
                    'is_available' => true,
                    'comment' =>
                        'Attempted availability change.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'A courier with an active route cannot become available.'
            );

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'is_available' => false,
        ]);
    }

    public function test_the_requested_availability_must_be_different(): void
    {
        $courier = Courier::factory()->create([
            'is_active' => true,
            'is_available' => false,
        ]);

        $this->actingAs($courier->user)
            ->patchJson(
                $this->endpoint(),
                [
                    'is_available' => false,
                    'comment' =>
                        'No availability change.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The courier is already unavailable.'
            );
    }

    public function test_non_courier_roles_cannot_change_courier_availability(): void
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
                ->patchJson(
                    $this->endpoint(),
                    [
                        'is_available' => true,
                        'comment' =>
                            'Unauthorized availability change.',
                    ]
                )
                ->assertForbidden();
        }
    }

    public function test_a_suspended_courier_account_cannot_change_availability(): void
    {
        $courier = Courier::factory()->create([
            'is_active' => true,
            'is_available' => false,
        ]);

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
            ->patchJson(
                $this->endpoint(),
                [
                    'is_available' => true,
                    'comment' =>
                        'Suspended account attempt.',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'is_available' => false,
        ]);
    }

    public function test_an_unverified_courier_cannot_change_availability(): void
    {
        $courier = Courier::factory()->create([
            'is_active' => true,
            'is_available' => false,
        ]);

        $courierUser = $courier->user;

        $courierUser->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this->actingAs($courierUser->fresh())
            ->patchJson(
                $this->endpoint(),
                [
                    'is_available' => true,
                    'comment' =>
                        'Unverified account attempt.',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $courierUser->id,
            'email_verified_at' => null,
        ]);

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'is_available' => false,
        ]);
    }

    public function test_an_inactive_courier_cannot_change_availability(): void
    {
        $courier = Courier::factory()->create([
            'is_active' => false,
            'is_available' => false,
        ]);

        $this->actingAs($courier->user)
            ->patchJson(
                $this->endpoint(),
                [
                    'is_available' => true,
                    'comment' =>
                        'Inactive courier attempt.',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'is_available' => false,
        ]);
    }

    public function test_a_courier_from_an_inactive_provider_cannot_change_availability(): void
    {
        $provider = DeliveryProvider::factory()
            ->create([
                'is_active' => false,
            ]);

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
            'is_active' => true,
            'is_available' => false,
        ]);

        $this->actingAs($courier->user)
            ->patchJson(
                $this->endpoint(),
                [
                    'is_available' => true,
                    'comment' =>
                        'Inactive provider attempt.',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'is_available' => false,
        ]);
    }

    public function test_availability_and_comment_are_required(): void
    {
        $courier = Courier::factory()->create([
            'is_active' => true,
            'is_available' => false,
        ]);

        $this->actingAs($courier->user)
            ->patchJson(
                $this->endpoint(),
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'is_available',
                'comment',
            ]);
    }

    public function test_availability_and_comment_values_are_validated(): void
    {
        $courier = Courier::factory()->create([
            'is_active' => true,
            'is_available' => false,
        ]);

        $this->actingAs($courier->user)
            ->patchJson(
                $this->endpoint(),
                [
                    'is_available' =>
                        'INVALID_AVAILABILITY',
                    'comment' =>
                        str_repeat('A', 501),
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'is_available',
                'comment',
            ]);
    }

    private function endpoint(): string
    {
        return route(
            'current-user.courier-availability.update'
        );
    }
}