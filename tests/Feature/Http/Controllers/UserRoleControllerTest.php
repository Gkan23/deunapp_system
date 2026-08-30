<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserRoleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_change_user_roles(): void
    {
        $targetUser = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $this->patchJson(
            route(
                'users.role.update',
                $targetUser
            ),
            [
                'role' => 'ADMINISTRATOR',
                'comment' => 'Role promotion.',
            ]
        )->assertUnauthorized();
    }

    public function test_support_agents_cannot_change_user_roles(): void
    {
        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $targetUser = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $this->actingAs($supportAgent)
            ->patchJson(
                route(
                    'users.role.update',
                    $targetUser
                ),
                [
                    'role' => 'ADMINISTRATOR',
                    'comment' => 'Role promotion.',
                ]
            )
            ->assertForbidden();

        $this->assertUserRole(
            $targetUser,
            'SUPPORT_AGENT'
        );
    }

    public function test_an_administrator_can_change_a_staff_role(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $targetUser = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        DB::table('sessions')->insert([
            'id' => 'role-target-session',
            'user_id' => $targetUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test-payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.role.update',
                    $targetUser
                ),
                [
                    'role' => 'administrator',
                    'comment' =>
                        'Promotion approved.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'User role updated successfully.'
            )
            ->assertJsonPath(
                'data.role.role_name',
                'ADMINISTRATOR'
            );

        $this->assertUserRole(
            $targetUser,
            'ADMINISTRATOR'
        );

        $this->assertDatabaseMissing(
            'sessions',
            [
                'id' => 'role-target-session',
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'performed_by_user_id' =>
                    $administrator->id,
                'table_name' => 'users',
                'record_id' => $targetUser->id,
                'action_type' =>
                    'USER_ROLE_CHANGED',
            ]
        );
    }

    public function test_an_administrator_cannot_change_their_own_role(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.role.update',
                    $administrator
                ),
                [
                    'role' => 'SUPPORT_AGENT',
                    'comment' =>
                        'Self demotion attempt.',
                ]
            )
            ->assertForbidden();

        $this->assertUserRole(
            $administrator,
            'ADMINISTRATOR'
        );
    }

    public function test_role_change_data_is_validated(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $targetUser = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.role.update',
                    $targetUser
                ),
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
                'comment',
            ]);

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.role.update',
                    $targetUser
                ),
                [
                    'role' => 'UNKNOWN_ROLE',
                    'comment' => 'Invalid role.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
            ]);

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.role.update',
                    $targetUser
                ),
                [
                    'role' => 'ADMINISTRATOR',
                    'comment' => '   ',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'comment',
            ]);
    }

    public function test_the_current_role_cannot_be_requested_again(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $targetUser = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.role.update',
                    $targetUser
                ),
                [
                    'role' => 'SUPPORT_AGENT',
                    'comment' =>
                        'Repeated role request.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The user already has the requested role.'
            );

        $this->assertUserRole(
            $targetUser,
            'SUPPORT_AGENT'
        );
    }

    public function test_operational_roles_require_the_corresponding_profile(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $targetUser = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        foreach (
            [
                'CUSTOMER',
                'DELIVERY_PROVIDER',
                'COURIER',
            ] as $roleName
        ) {
            $this->actingAs($administrator)
                ->patchJson(
                    route(
                        'users.role.update',
                        $targetUser
                    ),
                    [
                        'role' => $roleName,
                        'comment' =>
                            'Operational assignment.',
                    ]
                )
                ->assertUnprocessable()
                ->assertJsonPath(
                    'message',
                    sprintf(
                        'The user must have a %s profile before receiving this role.',
                        $roleName
                    )
                );
        }

        $this->assertUserRole(
            $targetUser,
            'SUPPORT_AGENT'
        );
    }

    public function test_a_user_can_receive_the_role_matching_their_profile(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $customer = Customer::factory()->create();

        $supportRole = $this->role(
            'SUPPORT_AGENT'
        );

        $customer->user->update([
            'role_id' => $supportRole->id,
        ]);

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.role.update',
                    $customer->user
                ),
                [
                    'role' => 'CUSTOMER',
                    'comment' =>
                        'Customer profile confirmed.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.role.role_name',
                'CUSTOMER'
            )
            ->assertJsonPath(
                'data.profile_type',
                'CUSTOMER'
            );

        $this->assertUserRole(
            $customer->user,
            'CUSTOMER'
        );
    }

    public function test_operational_profiles_cannot_receive_staff_roles(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $customer = Customer::factory()->create();

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.role.update',
                    $customer->user
                ),
                [
                    'role' => 'SUPPORT_AGENT',
                    'comment' =>
                        'Invalid staff assignment.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'A user with an operational profile cannot be assigned a staff role.'
            );

        $this->assertUserRole(
            $customer->user,
            'CUSTOMER'
        );
    }

    public function test_inactive_administrators_cannot_change_user_roles(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR',
            'SUSPENDED'
        );

        $targetUser = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.role.update',
                    $targetUser
                ),
                [
                    'role' => 'ADMINISTRATOR',
                    'comment' =>
                        'Unauthorized role change.',
                ]
            )
            ->assertForbidden();

        $this->assertUserRole(
            $targetUser,
            'SUPPORT_AGENT'
        );
    }

    private function userWithRole(
        string $roleName,
        string $accountStatusName = 'ACTIVE'
    ): User {
        $accountStatus = AccountStatus::query()
            ->where(
                'status_name',
                $accountStatusName
            )
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $this->role(
                $roleName
            )->id,
            'account_status_id' =>
                $accountStatus->id,
        ]);
    }

    private function role(string $roleName): Role
    {
        return Role::query()
            ->where(
                'role_name',
                $roleName
            )
            ->firstOrFail();
    }

    private function assertUserRole(
        User $user,
        string $expectedRole
    ): void {
        $role = $this->role($expectedRole);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role_id' => $role->id,
        ]);
    }
}