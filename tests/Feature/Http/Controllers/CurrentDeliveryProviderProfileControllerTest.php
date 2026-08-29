<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentDeliveryProviderProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_update_a_provider_profile(): void
    {
        $this->patchJson(
            route(
                'current-user.provider-profile.update'
            ),
            [
                'name' => 'Updated Provider',
            ]
        )->assertUnauthorized();
    }

    public function test_an_independent_provider_can_update_their_profile(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $this->actingAs($provider->user)
            ->patchJson(
                route(
                    'current-user.provider-profile.update'
                ),
                [
                    'name' => 'Updated Provider',
                    'identity_number' =>
                        'PROVIDER-2026-001',
                    'phone' => '8888-5555',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Delivery provider profile updated successfully.'
            )
            ->assertJsonPath(
                'data.name',
                'Updated Provider'
            )
            ->assertJsonPath(
                'data.profile.identity_number',
                'PROVIDER-2026-001'
            )
            ->assertJsonPath(
                'data.profile.phone',
                '8888-5555'
            )
            ->assertJsonPath(
                'data.profile.provider_type.type_name',
                'INDEPENDENT'
            );

        $this->assertDatabaseHas('users', [
            'id' => $provider->user_id,
            'name' => 'Updated Provider',
        ]);

        $this->assertDatabaseHas(
            'delivery_providers',
            [
                'id' => $provider->id,
                'identity_number' =>
                    'PROVIDER-2026-001',
                'phone' => '8888-5555',
                'business_name' => null,
            ]
        );

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' =>
                $provider->user_id,
            'table_name' =>
                'delivery_providers',
            'record_id' => $provider->id,
            'action_type' =>
                'PROVIDER_PROFILE_UPDATED',
        ]);
    }

    public function test_optional_fields_can_be_cleared(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $this->actingAs($provider->user)
            ->patchJson(
                route(
                    'current-user.provider-profile.update'
                ),
                [
                    'identity_number' => '   ',
                    'phone' => '   ',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.profile.identity_number',
                null
            )
            ->assertJsonPath(
                'data.profile.phone',
                null
            );

        $this->assertDatabaseHas(
            'delivery_providers',
            [
                'id' => $provider->id,
                'identity_number' => null,
                'phone' => null,
            ]
        );
    }

    public function test_a_company_provider_can_update_the_business_name(): void
    {
        $provider = DeliveryProvider::factory()
            ->company()
            ->create();

        $this->actingAs($provider->user)
            ->patchJson(
                route(
                    'current-user.provider-profile.update'
                ),
                [
                    'business_name' =>
                        'Updated Delivery Company',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.profile.business_name',
                'Updated Delivery Company'
            )
            ->assertJsonPath(
                'data.profile.provider_type.type_name',
                'COMPANY'
            );

        $this->assertDatabaseHas(
            'delivery_providers',
            [
                'id' => $provider->id,
                'business_name' =>
                    'Updated Delivery Company',
            ]
        );
    }

    public function test_an_independent_provider_can_change_to_a_company(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $this->actingAs($provider->user)
            ->patchJson(
                route(
                    'current-user.provider-profile.update'
                ),
                [
                    'provider_type' => 'company',
                    'business_name' =>
                        'New Delivery Company',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.profile.provider_type.type_name',
                'COMPANY'
            )
            ->assertJsonPath(
                'data.profile.business_name',
                'New Delivery Company'
            );

        $this->assertDatabaseHas(
            'delivery_providers',
            [
                'id' => $provider->id,
                'business_name' =>
                    'New Delivery Company',
            ]
        );
    }

    public function test_a_company_provider_requires_a_business_name(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $this->actingAs($provider->user)
            ->patchJson(
                route(
                    'current-user.provider-profile.update'
                ),
                [
                    'provider_type' => 'COMPANY',
                    'business_name' => '   ',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'business_name',
            ]);

        $provider->refresh();

        $this->assertSame(
            'INDEPENDENT',
            $provider->providerType->type_name
        );
    }

    public function test_the_provider_type_must_exist(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $this->actingAs($provider->user)
            ->patchJson(
                route(
                    'current-user.provider-profile.update'
                ),
                [
                    'provider_type' => 'UNKNOWN',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'provider_type',
            ]);
    }

    public function test_the_identity_number_must_be_unique(): void
    {
        $firstProvider = DeliveryProvider::factory()
            ->create([
                'identity_number' =>
                    'PROVIDER-IDENTITY-001',
            ]);

        $secondProvider = DeliveryProvider::factory()
            ->create([
                'identity_number' =>
                    'PROVIDER-IDENTITY-002',
            ]);

        $this->actingAs($secondProvider->user)
            ->patchJson(
                route(
                    'current-user.provider-profile.update'
                ),
                [
                    'identity_number' =>
                        $firstProvider->identity_number,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'identity_number',
            ]);

        $this->assertDatabaseHas(
            'delivery_providers',
            [
                'id' => $secondProvider->id,
                'identity_number' =>
                    'PROVIDER-IDENTITY-002',
            ]
        );
    }

    public function test_a_customer_cannot_update_a_provider_profile(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->patchJson(
                route(
                    'current-user.provider-profile.update'
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

    public function test_inactive_providers_and_accounts_cannot_update_the_profile(): void
    {
        $inactiveProvider =
            DeliveryProvider::factory()->create([
                'is_active' => false,
            ]);

        $this->actingAs($inactiveProvider->user)
            ->patchJson(
                route(
                    'current-user.provider-profile.update'
                ),
                [
                    'name' => 'Unauthorized Provider',
                ]
            )
            ->assertForbidden();

        $suspendedProvider =
            DeliveryProvider::factory()->create();

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $suspendedProvider->user->update([
            'account_status_id' =>
                $suspendedStatus->id,
        ]);

        $this->actingAs(
            $suspendedProvider->user->fresh()
        )
            ->patchJson(
                route(
                    'current-user.provider-profile.update'
                ),
                [
                    'name' => 'Unauthorized Account',
                ]
            )
            ->assertForbidden();

        $this->assertNotSame(
            'Unauthorized Provider',
            $inactiveProvider->user->fresh()->name
        );

        $this->assertNotSame(
            'Unauthorized Account',
            $suspendedProvider->user->fresh()->name
        );

        $this->assertDatabaseMissing('audit_logs', [
            'performed_by_user_id' =>
                $inactiveProvider->user_id,
            'action_type' =>
                'PROVIDER_PROFILE_UPDATED',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'performed_by_user_id' =>
                $suspendedProvider->user_id,
            'action_type' =>
                'PROVIDER_PROFILE_UPDATED',
        ]);
    }
}