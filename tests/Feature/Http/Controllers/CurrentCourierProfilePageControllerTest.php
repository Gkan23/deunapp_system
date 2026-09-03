<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentCourierProfilePageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_view_the_courier_profile_page(): void
    {
        $this->get(
            route(
                'current-user.courier-profile.edit'
            )
        )->assertRedirect(
            route('login.page')
        );
    }

    public function test_an_active_courier_can_view_their_profile_page(): void
    {
        $courier = Courier::factory()->create([
            'license_number' => 'NIC-PAGE-001',
        ]);

        $this->actingAs($courier->user)
            ->get(
                route(
                    'current-user.courier-profile.edit'
                )
            )
            ->assertOk()
            ->assertSee('Perfil de repartidor')
            ->assertSee($courier->user->name)
            ->assertSee('NIC-PAGE-001')
            ->assertSee(
                route(
                    'current-user.courier-profile.update'
                ),
                escape: false
            );
    }

    public function test_the_page_displays_read_only_operational_information(): void
    {
        $courier = Courier::factory()->create([
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->actingAs($courier->user)
            ->get(
                route(
                    'current-user.courier-profile.edit'
                )
            )
            ->assertOk()
            ->assertSee('INDEPENDENT')
            ->assertSee('Disponible')
            ->assertSee('Activo')
            ->assertDontSee(
                'name="is_available"',
                escape: false
            )
            ->assertDontSee(
                'name="is_active"',
                escape: false
            )
            ->assertDontSee(
                'name="delivery_provider_id"',
                escape: false
            );
    }

    public function test_an_unverified_courier_can_view_the_profile_page(): void
    {
        $courier = Courier::factory()->create();

        $courier->user->update([
            'email_verified_at' => null,
        ]);

        $this->actingAs(
            $courier->user->fresh()
        )
            ->get(
                route(
                    'current-user.courier-profile.edit'
                )
            )
            ->assertOk();
    }

    public function test_a_customer_cannot_view_the_courier_profile_page(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->get(
                route(
                    'current-user.courier-profile.edit'
                )
            )
            ->assertForbidden();
    }

    public function test_inactive_couriers_accounts_and_providers_cannot_view_the_page(): void
    {
        $inactiveCourier = Courier::factory()->create([
            'is_active' => false,
        ]);

        $this->actingAs($inactiveCourier->user)
            ->get(
                route(
                    'current-user.courier-profile.edit'
                )
            )
            ->assertForbidden();

        $suspendedCourier =
            Courier::factory()->create();

        $suspendedStatus = AccountStatus::query()
            ->where(
                'status_name',
                'SUSPENDED'
            )
            ->firstOrFail();

        $suspendedCourier->user->update([
            'account_status_id' =>
                $suspendedStatus->id,
        ]);

        $this->actingAs(
            $suspendedCourier->user->fresh()
        )
            ->get(
                route(
                    'current-user.courier-profile.edit'
                )
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
            ->get(
                route(
                    'current-user.courier-profile.edit'
                )
            )
            ->assertForbidden();
    }

    public function test_a_courier_can_update_their_profile_using_the_form(): void
    {
        $courier = Courier::factory()->create();

        $this->actingAs($courier->user)
            ->patch(
                route(
                    'current-user.courier-profile.update'
                ),
                [
                    'name' => 'Repartidor actualizado',
                    'license_number' =>
                        'NIC-PAGE-UPDATED',
                ]
            )
            ->assertRedirect(
                route(
                    'current-user.courier-profile.edit'
                )
            )
            ->assertSessionHas(
                'status',
                'Perfil de repartidor actualizado correctamente.'
            );

        $this->assertDatabaseHas('users', [
            'id' => $courier->user_id,
            'name' => 'Repartidor actualizado',
        ]);

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'license_number' =>
                'NIC-PAGE-UPDATED',
        ]);
    }

    public function test_the_license_number_must_be_unique_when_using_the_form(): void
    {
        $firstCourier = Courier::factory()->create([
            'license_number' => 'NIC-PAGE-UNIQUE-001',
        ]);

        $secondCourier = Courier::factory()->create([
            'license_number' => 'NIC-PAGE-UNIQUE-002',
        ]);

        $this->actingAs($secondCourier->user)
            ->from(
                route(
                    'current-user.courier-profile.edit'
                )
            )
            ->patch(
                route(
                    'current-user.courier-profile.update'
                ),
                [
                    'license_number' =>
                        $firstCourier->license_number,
                ]
            )
            ->assertRedirect(
                route(
                    'current-user.courier-profile.edit'
                )
            )
            ->assertSessionHasErrors([
                'license_number',
            ]);

        $this->assertDatabaseHas('couriers', [
            'id' => $secondCourier->id,
            'license_number' =>
                'NIC-PAGE-UNIQUE-002',
        ]);
    }
}