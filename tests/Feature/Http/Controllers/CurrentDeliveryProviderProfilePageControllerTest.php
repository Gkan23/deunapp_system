<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentDeliveryProviderProfilePageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_view_the_provider_profile_page(): void
    {
        $this->get(
            route(
                'current-user.provider-profile.edit'
            )
        )->assertRedirect(
            route('login.page')
        );
    }

    public function test_an_active_provider_can_view_their_profile_page(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $this->actingAs($provider->user)
            ->get(
                route(
                    'current-user.provider-profile.edit'
                )
            )
            ->assertOk()
            ->assertSee('Perfil de proveedor')
            ->assertSee($provider->user->name)
            ->assertSee(
                (string) $provider->identity_number
            )
            ->assertSee(
                route(
                    'current-user.provider-profile.update'
                ),
                escape: false
            );
    }

    public function test_the_page_contains_the_available_provider_types(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $this->actingAs($provider->user)
            ->get(
                route(
                    'current-user.provider-profile.edit'
                )
            )
            ->assertOk()
            ->assertSee('INDEPENDENT')
            ->assertSee('COMPANY');
    }

    public function test_an_unverified_provider_can_view_the_profile_page(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $provider->user->update([
            'email_verified_at' => null,
        ]);

        $this->actingAs(
            $provider->user->fresh()
        )
            ->get(
                route(
                    'current-user.provider-profile.edit'
                )
            )
            ->assertOk();
    }

    public function test_a_customer_cannot_view_the_provider_profile_page(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->get(
                route(
                    'current-user.provider-profile.edit'
                )
            )
            ->assertForbidden();
    }

    public function test_inactive_providers_and_accounts_cannot_view_the_page(): void
    {
        $inactiveProvider =
            DeliveryProvider::factory()->create([
                'is_active' => false,
            ]);

        $this->actingAs($inactiveProvider->user)
            ->get(
                route(
                    'current-user.provider-profile.edit'
                )
            )
            ->assertForbidden();

        $suspendedProvider =
            DeliveryProvider::factory()->create();

        $suspendedStatus = AccountStatus::query()
            ->where(
                'status_name',
                'SUSPENDED'
            )
            ->firstOrFail();

        $suspendedProvider->user->update([
            'account_status_id' =>
                $suspendedStatus->id,
        ]);

        $this->actingAs(
            $suspendedProvider->user->fresh()
        )
            ->get(
                route(
                    'current-user.provider-profile.edit'
                )
            )
            ->assertForbidden();
    }

    public function test_a_provider_can_update_their_profile_using_the_form(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $this->actingAs($provider->user)
            ->patch(
                route(
                    'current-user.provider-profile.update'
                ),
                [
                    'name' => 'Proveedor actualizado',
                    'provider_type' => 'INDEPENDENT',
                    'identity_number' =>
                        'PROVIDER-PAGE-001',
                    'business_name' => null,
                    'phone' => '88885555',
                ]
            )
            ->assertRedirect(
                route(
                    'current-user.provider-profile.edit'
                )
            )
            ->assertSessionHas(
                'status',
                'Perfil de proveedor actualizado correctamente.'
            );

        $this->assertDatabaseHas('users', [
            'id' => $provider->user_id,
            'name' => 'Proveedor actualizado',
        ]);

        $this->assertDatabaseHas(
            'delivery_providers',
            [
                'id' => $provider->id,
                'identity_number' =>
                    'PROVIDER-PAGE-001',
                'business_name' => null,
                'phone' => '88885555',
            ]
        );
    }

    public function test_a_company_provider_requires_a_business_name(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $this->actingAs($provider->user)
            ->from(
                route(
                    'current-user.provider-profile.edit'
                )
            )
            ->patch(
                route(
                    'current-user.provider-profile.update'
                ),
                [
                    'provider_type' => 'COMPANY',
                    'business_name' => '   ',
                ]
            )
            ->assertRedirect(
                route(
                    'current-user.provider-profile.edit'
                )
            )
            ->assertSessionHasErrors([
                'business_name',
            ]);

        $provider->refresh();

        $this->assertSame(
            'INDEPENDENT',
            $provider->providerType->type_name
        );
    }
}