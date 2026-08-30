<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_access_user_administration(): void
    {
        $targetUser = Customer::factory()
            ->create()
            ->user;

        $this->getJson(
            route('users.index')
        )->assertUnauthorized();

        $this->getJson(
            route('users.show', $targetUser)
        )->assertUnauthorized();
    }

    public function test_operational_users_cannot_access_user_administration(): void
    {
        $customer = Customer::factory()->create();

        $targetUser = DeliveryProvider::factory()
            ->create()
            ->user;

        $this->actingAs($customer->user)
            ->getJson(
                route('users.index')
            )
            ->assertForbidden();

        $this->actingAs($customer->user)
            ->getJson(
                route('users.show', $targetUser)
            )
            ->assertForbidden();
    }

    public function test_support_can_list_users(): void
    {
        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $customer = Customer::factory()->create();

        $this->actingAs($supportAgent)
            ->getJson(
                route('users.index')
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                2
            )
            ->assertJsonFragment([
                'email' => $supportAgent->email,
            ])
            ->assertJsonFragment([
                'email' => $customer->user->email,
            ]);
    }

    public function test_an_administrator_can_list_users(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $provider = DeliveryProvider::factory()
            ->create();

        $this->actingAs($administrator)
            ->getJson(
                route('users.index')
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                2
            )
            ->assertJsonFragment([
                'email' => $administrator->email,
            ])
            ->assertJsonFragment([
                'email' => $provider->user->email,
            ]);
    }

    public function test_the_user_list_can_be_filtered(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        Customer::factory()->create([
            'user_id' => User::factory()
                ->customer()
                ->create([
                    'name' => 'Search Customer',
                    'email' =>
                        'search-customer@example.com',
                ])
                ->id,
        ]);

        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $supportAgent->update([
            'name' => 'Search Support',
        ]);

        $this->actingAs($administrator)
            ->getJson(
                route('users.index', [
                    'search' => 'search-customer',
                    'role' => 'customer',
                    'account_status' => 'active',
                ])
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                1
            )
            ->assertJsonPath(
                'data.0.email',
                'search-customer@example.com'
            )
            ->assertJsonPath(
                'data.0.role.role_name',
                'CUSTOMER'
            )
            ->assertJsonPath(
                'data.0.account_status.status_name',
                'ACTIVE'
            );
    }

    public function test_user_list_filters_are_validated(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this->actingAs($administrator)
            ->getJson(
                route('users.index', [
                    'role' => 'UNKNOWN',
                    'account_status' =>
                        'UNKNOWN',
                    'per_page' => 101,
                ])
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
                'account_status',
                'per_page',
            ]);
    }

    public function test_staff_can_view_a_user_with_their_profile(): void
    {
        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $provider = DeliveryProvider::factory()
            ->company()
            ->create();

        $response = $this
            ->actingAs($supportAgent)
            ->getJson(
                route(
                    'users.show',
                    $provider->user
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $provider->user_id
            )
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
                'data.profile.provider_type.type_name',
                'COMPANY'
            );

        $data = $response->json('data');

        $this->assertArrayNotHasKey(
            'password',
            $data
        );

        $this->assertArrayNotHasKey(
            'remember_token',
            $data
        );
    }

    public function test_inactive_staff_cannot_access_user_administration(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $targetUser = Customer::factory()
            ->create()
            ->user;

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $administrator->update([
            'account_status_id' =>
                $suspendedStatus->id,
        ]);

        $this->actingAs(
            $administrator->fresh()
        )
            ->getJson(
                route('users.index')
            )
            ->assertForbidden();

        $this->actingAs(
            $administrator->fresh()
        )
            ->getJson(
                route('users.show', $targetUser)
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