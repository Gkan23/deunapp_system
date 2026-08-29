<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentUserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guest_cannot_access_current_user_endpoint(): void
    {
        $this->getJson(
            route('current-user.show')
        )->assertUnauthorized();
    }

    public function test_customer_receives_customer_profile(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->getJson(
                route('current-user.show')
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $customer->user_id
            )
            ->assertJsonPath(
                'data.role.role_name',
                'CUSTOMER'
            )
            ->assertJsonPath(
                'data.account_status.status_name',
                'ACTIVE'
            )
            ->assertJsonPath(
                'data.account_active',
                true
            )
            ->assertJsonPath(
                'data.profile_type',
                'CUSTOMER'
            )
            ->assertJsonPath(
                'data.profile.id',
                $customer->id
            )
            ->assertJsonPath(
                'data.profile.customer_type.id',
                $customer->customer_type_id
            );
    }

    public function test_provider_receives_delivery_provider_profile(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $this->actingAs($provider->user)
            ->getJson(
                route('current-user.show')
            )
            ->assertOk()
            ->assertJsonPath(
                'data.role.role_name',
                'DELIVERY_PROVIDER'
            )
            ->assertJsonPath(
                'data.profile_type',
                'DELIVERY_PROVIDER'
            )
            ->assertJsonPath(
                'data.profile.id',
                $provider->id
            )
            ->assertJsonPath(
                'data.profile.provider_type.id',
                $provider->provider_type_id
            )
            ->assertJsonPath(
                'data.profile.is_active',
                (bool) $provider->is_active
            );
    }

    public function test_courier_receives_courier_and_provider_profile(): void
    {
        $courier = Courier::factory()->create();

        $this->actingAs($courier->user)
            ->getJson(
                route('current-user.show')
            )
            ->assertOk()
            ->assertJsonPath(
                'data.role.role_name',
                'COURIER'
            )
            ->assertJsonPath(
                'data.profile_type',
                'COURIER'
            )
            ->assertJsonPath(
                'data.profile.id',
                $courier->id
            )
            ->assertJsonPath(
                'data.profile.delivery_provider.id',
                $courier->delivery_provider_id
            )
            ->assertJsonPath(
                'data.profile.delivery_provider.provider_type.id',
                $courier->deliveryProvider
                    ->provider_type_id
            );
    }

    public function test_support_agent_has_no_operational_profile(): void
    {
        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $this->actingAs($supportAgent)
            ->getJson(
                route('current-user.show')
            )
            ->assertOk()
            ->assertJsonPath(
                'data.role.role_name',
                'SUPPORT_AGENT'
            )
            ->assertJsonPath(
                'data.profile_type',
                null
            )
            ->assertJsonPath(
                'data.profile',
                null
            );
    }

    public function test_administrator_has_no_operational_profile(): void
    {
        $administrator = $this->createUserWithRole(
            'ADMINISTRATOR'
        );

        $this->actingAs($administrator)
            ->getJson(
                route('current-user.show')
            )
            ->assertOk()
            ->assertJsonPath(
                'data.role.role_name',
                'ADMINISTRATOR'
            )
            ->assertJsonPath(
                'data.profile_type',
                null
            )
            ->assertJsonPath(
                'data.profile',
                null
            );
    }

    public function test_inactive_user_can_view_their_account_status(): void
    {
        $customer = Customer::factory()->create();

        $customer->user->update([
            'account_status_id' => AccountStatus::query()
                ->where('status_name', 'SUSPENDED')
                ->firstOrFail()
                ->id,
        ]);

        $this->actingAs($customer->user->fresh())
            ->getJson(
                route('current-user.show')
            )
            ->assertOk()
            ->assertJsonPath(
                'data.account_status.status_name',
                'SUSPENDED'
            )
            ->assertJsonPath(
                'data.account_active',
                false
            )
            ->assertJsonPath(
                'data.profile.id',
                $customer->id
            );
    }

    public function test_sensitive_authentication_data_is_not_returned(): void
    {
        $user = $this->createUserWithRole(
            'ADMINISTRATOR'
        );

        $user->forceFill([
            'remember_token' => 'private-remember-token',
        ])->save();

        $response = $this->actingAs($user)
            ->getJson(
                route('current-user.show')
            )
            ->assertOk();

        $data = $response->json('data');

        $this->assertArrayNotHasKey(
            'password',
            $data
        );

        $this->assertArrayNotHasKey(
            'remember_token',
            $data
        );

        $this->assertSame(
            $user->email,
            $data['email']
        );
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