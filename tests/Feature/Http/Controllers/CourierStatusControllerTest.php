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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourierStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_change_a_courier_status(): void
    {
        $courier = Courier::factory()->create();

        $this->patchJson(
            $this->endpoint($courier),
            [
                'is_active' => false,
                'comment' =>
                    'The courier left the provider.',
            ]
        )->assertUnauthorized();
    }

    public function test_the_provider_can_deactivate_their_courier(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        DB::table('sessions')->insert([
            'id' => 'courier-test-session',
            'user_id' => $courier->user_id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test-session',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($provider->user)
            ->patchJson(
                $this->endpoint($courier),
                [
                    'is_active' => false,
                    'comment' =>
                        'The courier no longer works for the provider.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Courier status updated successfully.'
            )
            ->assertJsonPath(
                'data.id',
                $courier->id
            )
            ->assertJsonPath(
                'data.is_active',
                false
            )
            ->assertJsonPath(
                'data.is_available',
                false
            );

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'is_active' => false,
            'is_available' => false,
        ]);

        $this->assertDatabaseMissing('sessions', [
            'id' => 'courier-test-session',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' =>
                $provider->user_id,
            'table_name' => 'couriers',
            'record_id' => $courier->id,
            'action_type' =>
                'COURIER_STATUS_CHANGED',
        ]);
    }

    public function test_the_provider_can_reactivate_their_courier(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
            'is_active' => false,
            'is_available' => false,
        ]);

        $this->actingAs($provider->user)
            ->patchJson(
                $this->endpoint($courier),
                [
                    'is_active' => true,
                    'comment' =>
                        'The courier returned to work.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.is_active',
                true
            )
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

    public function test_a_courier_with_an_active_route_cannot_be_deactivated(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
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
            'estimated_distance_km' => 12.50,
        ]);

        $this->actingAs($provider->user)
            ->patchJson(
                $this->endpoint($courier),
                [
                    'is_active' => false,
                    'comment' =>
                        'Attempted deactivation.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The courier cannot be deactivated while having an active route.'
            );

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'is_active' => true,
        ]);
    }

    public function test_a_provider_cannot_change_another_providers_courier(): void
    {
        $owner = DeliveryProvider::factory()
            ->create();

        $otherProvider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $owner->id,
        ]);

        $this->actingAs($otherProvider->user)
            ->patchJson(
                $this->endpoint($courier),
                [
                    'is_active' => false,
                    'comment' =>
                        'Unauthorized deactivation.',
                ]
            )
            ->assertForbidden();
    }

    public function test_a_courier_cannot_change_their_own_status(): void
    {
        $courier = Courier::factory()
            ->create([
                'is_active' => true,
            ]);

        $this->actingAs($courier->user)
            ->patchJson(
                $this->endpoint($courier),
                [
                    'is_active' => false,
                    'comment' =>
                        'Self-deactivation attempt.',
                ]
            )
            ->assertForbidden();
    }

    public function test_an_administrator_cannot_change_a_courier_status(): void
    {
        $administrator = User::factory()
            ->administrator()
            ->create();

        $courier = Courier::factory()
            ->create([
                'is_active' => true,
            ]);

        $this->actingAs($administrator)
            ->patchJson(
                $this->endpoint($courier),
                [
                    'is_active' => false,
                    'comment' =>
                        'Administrative deactivation.',
                ]
            )
            ->assertForbidden();
    }

    public function test_a_suspended_provider_account_cannot_change_a_courier_status(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
            'is_active' => true,
        ]);

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $provider->user->update([
            'account_status_id' =>
                $suspendedStatus->id,
        ]);

        $this->actingAs(
            $provider->user->fresh()
        )
            ->patchJson(
                $this->endpoint($courier),
                [
                    'is_active' => false,
                    'comment' =>
                        'Suspended account attempt.',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'is_active' => true,
        ]);
    }

    public function test_an_inactive_provider_profile_cannot_change_a_courier_status(): void
    {
        $provider = DeliveryProvider::factory()
            ->create([
                'is_active' => false,
            ]);

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
            'is_active' => true,
        ]);

        $this->actingAs($provider->user)
            ->patchJson(
                $this->endpoint($courier),
                [
                    'is_active' => false,
                    'comment' =>
                        'Inactive provider attempt.',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'is_active' => true,
        ]);
    }

    public function test_the_requested_status_must_be_different(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
            'is_active' => true,
        ]);

        $this->actingAs($provider->user)
            ->patchJson(
                $this->endpoint($courier),
                [
                    'is_active' => true,
                    'comment' =>
                        'No status change.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The courier is already active.'
            );

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'is_active' => true,
        ]);
    }

    public function test_status_and_comment_are_required(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
        ]);

        $this->actingAs($provider->user)
            ->patchJson(
                $this->endpoint($courier),
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'is_active',
                'comment',
            ]);
    }

    public function test_status_and_comment_values_are_validated(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
        ]);

        $this->actingAs($provider->user)
            ->patchJson(
                $this->endpoint($courier),
                [
                    'is_active' =>
                        'INVALID_STATUS',
                    'comment' =>
                        str_repeat('A', 501),
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'is_active',
                'comment',
            ]);
    }

    private function endpoint(
        Courier $courier
    ): string {
        return route(
            'couriers.status.update',
            $courier
        );
    }
}