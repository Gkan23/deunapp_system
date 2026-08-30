<?php

namespace Tests\Feature\Policies;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_support_and_administration_can_access_the_user_list(): void
    {
        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        foreach ([
            $supportAgent,
            $administrator,
        ] as $user) {
            $this->assertTrue(
                Gate::forUser($user)->allows(
                    'viewAny',
                    User::class
                )
            );
        }
    }

    public function test_operational_roles_cannot_access_the_user_list(): void
    {
        $customer = Customer::factory()
            ->create()
            ->user;

        $provider = DeliveryProvider::factory()
            ->create()
            ->user;

        $courier = Courier::factory()
            ->create()
            ->user;

        foreach ([
            $customer,
            $provider,
            $courier,
        ] as $user) {
            $this->assertFalse(
                Gate::forUser($user)->allows(
                    'viewAny',
                    User::class
                )
            );
        }
    }

    public function test_support_can_view_any_user_account(): void
    {
        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $targetUser = Customer::factory()
            ->create()
            ->user;

        $this->assertTrue(
            Gate::forUser($supportAgent)->allows(
                'view',
                $targetUser
            )
        );
    }

    public function test_an_administrator_can_view_any_user_account(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $targetUser = DeliveryProvider::factory()
            ->create()
            ->user;

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'view',
                $targetUser
            )
        );
    }

    public function test_an_administrator_can_change_another_users_status(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $targetUser = Customer::factory()
            ->create()
            ->user;

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'changeAccountStatus',
                $targetUser
            )
        );
    }

    public function test_an_administrator_cannot_change_their_own_status(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this->assertFalse(
            Gate::forUser($administrator)->allows(
                'changeAccountStatus',
                $administrator
            )
        );
    }

    public function test_support_cannot_change_account_statuses(): void
    {
        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $targetUser = Customer::factory()
            ->create()
            ->user;

        $this->assertFalse(
            Gate::forUser($supportAgent)->allows(
                'changeAccountStatus',
                $targetUser
            )
        );
    }

    public function test_an_inactive_staff_user_cannot_access_user_administration(): void
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

        $administrator = $administrator->fresh();

        $this->assertFalse(
            Gate::forUser($administrator)->allows(
                'viewAny',
                User::class
            )
        );

        $this->assertFalse(
            Gate::forUser($administrator)->allows(
                'view',
                $targetUser
            )
        );

        $this->assertFalse(
            Gate::forUser($administrator)->allows(
                'changeAccountStatus',
                $targetUser
            )
        );
    }

    public function test_direct_modification_and_deletion_are_denied(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $targetUser = Customer::factory()
            ->create()
            ->user;

        $this->assertFalse(
            Gate::forUser($administrator)->allows(
                'create',
                User::class
            )
        );

        foreach ([
            'update',
            'delete',
            'restore',
            'forceDelete',
        ] as $ability) {
            $this->assertFalse(
                Gate::forUser($administrator)->allows(
                    $ability,
                    $targetUser
                )
            );
        }
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
