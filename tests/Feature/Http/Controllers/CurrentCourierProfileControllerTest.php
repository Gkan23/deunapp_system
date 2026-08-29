<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentCourierProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_update_a_courier_profile(): void
    {
        $this->patchJson(
            route(
                'current-user.courier-profile.update'
            ),
            [
                'name' => 'Updated Courier',
            ]
        )->assertUnauthorized();
    }

    public function test_an_active_courier_can_update_their_profile(): void
    {
        $courier = Courier::factory()->create();

        $this->actingAs($courier->user)
            ->patchJson(
                route(
                    'current-user.courier-profile.update'
                ),
                [
                    'name' => 'Updated Courier',
                    'license_number' =>
                        'NIC-UPDATED-2026',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Courier profile updated successfully.'
            )
            ->assertJsonPath(
                'data.name',
                'Updated Courier'
            )
            ->assertJsonPath(
                'data.profile.license_number',
                'NIC-UPDATED-2026'
            )
            ->assertJsonPath(
                'data.profile.delivery_provider.id',
                $courier->delivery_provider_id
            );

        $this->assertDatabaseHas('users', [
            'id' => $courier->user_id,
            'name' => 'Updated Courier',
        ]);

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'license_number' =>
                'NIC-UPDATED-2026',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' =>
                $courier->user_id,
            'table_name' => 'couriers',
            'record_id' => $courier->id,
            'action_type' =>
                'COURIER_PROFILE_UPDATED',
        ]);
    }

    public function test_the_license_number_can_be_cleared(): void
    {
        $courier = Courier::factory()->create();

        $this->actingAs($courier->user)
            ->patchJson(
                route(
                    'current-user.courier-profile.update'
                ),
                [
                    'license_number' => '   ',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.profile.license_number',
                null
            );

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'license_number' => null,
        ]);
    }

    public function test_a_courier_can_keep_their_current_license_number(): void
    {
        $courier = Courier::factory()->create([
            'license_number' => 'NIC-CURRENT-001',
        ]);

        $this->actingAs($courier->user)
            ->patchJson(
                route(
                    'current-user.courier-profile.update'
                ),
                [
                    'license_number' =>
                        'NIC-CURRENT-001',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.profile.license_number',
                'NIC-CURRENT-001'
            );

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'license_number' =>
                'NIC-CURRENT-001',
        ]);
    }

    public function test_the_license_number_must_be_unique(): void
    {
        $firstCourier = Courier::factory()->create([
            'license_number' => 'NIC-UNIQUE-001',
        ]);

        $secondCourier = Courier::factory()->create([
            'license_number' => 'NIC-UNIQUE-002',
        ]);

        $this->actingAs($secondCourier->user)
            ->patchJson(
                route(
                    'current-user.courier-profile.update'
                ),
                [
                    'license_number' =>
                        $firstCourier->license_number,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'license_number',
            ]);

        $this->assertDatabaseHas('couriers', [
            'id' => $secondCourier->id,
            'license_number' =>
                'NIC-UNIQUE-002',
        ]);
    }

    public function test_the_license_number_cannot_exceed_fifty_characters(): void
    {
        $courier = Courier::factory()->create();

        $this->actingAs($courier->user)
            ->patchJson(
                route(
                    'current-user.courier-profile.update'
                ),
                [
                    'license_number' => str_repeat(
                        'A',
                        51
                    ),
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'license_number',
            ]);
    }

    public function test_the_name_cannot_be_empty(): void
    {
        $courier = Courier::factory()->create();

        $originalName = $courier->user->name;

        $this->actingAs($courier->user)
            ->patchJson(
                route(
                    'current-user.courier-profile.update'
                ),
                [
                    'name' => '   ',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);

        $this->assertSame(
            $originalName,
            $courier->user->fresh()->name
        );
    }

    public function test_a_customer_cannot_update_a_courier_profile(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->patchJson(
                route(
                    'current-user.courier-profile.update'
                ),
                [
                    'name' => 'Unauthorized Update',
                ]
            )
            ->assertForbidden();

        $this->assertNotSame(
            'Unauthorized Update',
            $customer->user->fresh()->name
        );
    }

    public function test_operational_fields_cannot_be_changed_from_the_profile(): void
    {
        $courier = Courier::factory()->create([
            'is_available' => true,
            'is_active' => true,
        ]);

        $originalProviderId =
            $courier->delivery_provider_id;

        $otherProvider =
            DeliveryProvider::factory()->create();

        $this->actingAs($courier->user)
            ->patchJson(
                route(
                    'current-user.courier-profile.update'
                ),
                [
                    'name' => 'Allowed Name Update',
                    'delivery_provider_id' =>
                        $otherProvider->id,
                    'is_available' => false,
                    'is_active' => false,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Allowed Name Update'
            );

        $courier->refresh();

        $this->assertSame(
            $originalProviderId,
            $courier->delivery_provider_id
        );

        $this->assertTrue(
            (bool) $courier->is_available
        );

        $this->assertTrue(
            (bool) $courier->is_active
        );
    }

    public function test_inactive_couriers_accounts_and_providers_cannot_update_the_profile(): void
    {
        $inactiveCourier = Courier::factory()->create([
            'is_active' => false,
        ]);

        $this->actingAs($inactiveCourier->user)
            ->patchJson(
                route(
                    'current-user.courier-profile.update'
                ),
                [
                    'name' => 'Inactive Courier',
                ]
            )
            ->assertForbidden();

        $suspendedCourier =
            Courier::factory()->create();

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $suspendedCourier->user->update([
            'account_status_id' =>
                $suspendedStatus->id,
        ]);

        $this->actingAs(
            $suspendedCourier->user->fresh()
        )
            ->patchJson(
                route(
                    'current-user.courier-profile.update'
                ),
                [
                    'name' => 'Suspended Courier',
                ]
            )
            ->assertForbidden();

        $inactiveProviderCourier =
            Courier::factory()->create();

        $inactiveProviderCourier
            ->deliveryProvider
            ->update([
                'is_active' => false,
            ]);

        $this->actingAs(
            $inactiveProviderCourier->user
        )
            ->patchJson(
                route(
                    'current-user.courier-profile.update'
                ),
                [
                    'name' =>
                        'Inactive Provider Courier',
                ]
            )
            ->assertForbidden();

        $this->assertNotSame(
            'Inactive Courier',
            $inactiveCourier->user->fresh()->name
        );

        $this->assertNotSame(
            'Suspended Courier',
            $suspendedCourier->user->fresh()->name
        );

        $this->assertNotSame(
            'Inactive Provider Courier',
            $inactiveProviderCourier
                ->user
                ->fresh()
                ->name
        );
    }
}