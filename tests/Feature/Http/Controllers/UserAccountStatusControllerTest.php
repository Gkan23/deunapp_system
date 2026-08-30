<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserAccountStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_change_account_statuses(): void
    {
        $targetUser = $this->userWithRole(
            'CUSTOMER'
        );

        $this->patchJson(
            route(
                'users.account-status.update',
                $targetUser
            ),
            [
                'status' => 'SUSPENDED',
                'comment' => 'Temporary suspension.',
            ]
        )->assertUnauthorized();
    }

    public function test_support_agents_cannot_change_account_statuses(): void
    {
        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $targetUser = $this->userWithRole(
            'CUSTOMER'
        );

        $this->actingAs($supportAgent)
            ->patchJson(
                route(
                    'users.account-status.update',
                    $targetUser
                ),
                [
                    'status' => 'SUSPENDED',
                    'comment' =>
                        'Temporary suspension.',
                ]
            )
            ->assertForbidden();

        $this->assertUserStatus(
            $targetUser,
            'ACTIVE'
        );
    }

    public function test_an_administrator_can_suspend_an_active_account(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $targetUser = $this->userWithRole(
            'CUSTOMER'
        );

        DB::table('sessions')->insert([
            'id' => 'target-user-session',
            'user_id' => $targetUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test-payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.account-status.update',
                    $targetUser
                ),
                [
                    'status' => 'suspended',
                    'comment' =>
                        'Suspicious account activity.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'User account status updated successfully.'
            )
            ->assertJsonPath(
                'data.id',
                $targetUser->id
            )
            ->assertJsonPath(
                'data.account_status.status_name',
                'SUSPENDED'
            )
            ->assertJsonPath(
                'data.account_active',
                false
            );

        $this->assertUserStatus(
            $targetUser,
            'SUSPENDED'
        );

        $this->assertDatabaseMissing(
            'sessions',
            [
                'id' => 'target-user-session',
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
                    'ACCOUNT_STATUS_CHANGED',
            ]
        );
    }

    public function test_an_administrator_can_block_a_pending_account(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $targetUser = $this->userWithRole(
            'CUSTOMER',
            'PENDING'
        );

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.account-status.update',
                    $targetUser
                ),
                [
                    'status' => 'blocked',
                    'comment' =>
                        'Registration was rejected.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.account_status.status_name',
                'BLOCKED'
            );

        $this->assertUserStatus(
            $targetUser,
            'BLOCKED'
        );
    }

    public function test_an_administrator_can_reactivate_suspended_and_blocked_accounts(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        foreach (
            [
                'SUSPENDED',
                'BLOCKED',
            ] as $currentStatus
        ) {
            $targetUser = $this->userWithRole(
                'CUSTOMER',
                $currentStatus
            );

            $this->actingAs($administrator)
                ->patchJson(
                    route(
                        'users.account-status.update',
                        $targetUser
                    ),
                    [
                        'status' => 'active',
                        'comment' =>
                            'Account access restored.',
                    ]
                )
                ->assertOk()
                ->assertJsonPath(
                    'data.account_status.status_name',
                    'ACTIVE'
                )
                ->assertJsonPath(
                    'data.account_active',
                    true
                );

            $this->assertUserStatus(
                $targetUser,
                'ACTIVE'
            );
        }
    }

    public function test_an_administrator_cannot_change_their_own_status(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.account-status.update',
                    $administrator
                ),
                [
                    'status' => 'SUSPENDED',
                    'comment' =>
                        'Self suspension attempt.',
                ]
            )
            ->assertForbidden();

        $this->assertUserStatus(
            $administrator,
            'ACTIVE'
        );
    }

    public function test_account_status_input_is_validated(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $targetUser = $this->userWithRole(
            'CUSTOMER'
        );

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.account-status.update',
                    $targetUser
                ),
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.account-status.update',
                    $targetUser
                ),
                [
                    'status' => 'UNKNOWN_STATUS',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.account-status.update',
                    $targetUser
                ),
                [
                    'status' => 'BLOCKED',
                    'comment' => '   ',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'comment',
            ]);

        $this->assertUserStatus(
            $targetUser,
            'ACTIVE'
        );
    }

    public function test_invalid_account_status_transitions_are_rejected(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $targetUser = $this->userWithRole(
            'CUSTOMER',
            'PENDING'
        );

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.account-status.update',
                    $targetUser
                ),
                [
                    'status' => 'SUSPENDED',
                    'comment' =>
                        'Invalid transition attempt.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The account status transition from PENDING to SUSPENDED is not allowed.'
            );

        $this->assertUserStatus(
            $targetUser,
            'PENDING'
        );
    }

    public function test_the_current_account_status_cannot_be_requested_again(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $targetUser = $this->userWithRole(
            'CUSTOMER',
            'ACTIVE'
        );

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.account-status.update',
                    $targetUser
                ),
                [
                    'status' => 'ACTIVE',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The user account is already in the requested status.'
            );

        $this->assertUserStatus(
            $targetUser,
            'ACTIVE'
        );
    }

    public function test_an_inactive_administrator_cannot_change_account_statuses(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR',
            'SUSPENDED'
        );

        $targetUser = $this->userWithRole(
            'CUSTOMER'
        );

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'users.account-status.update',
                    $targetUser
                ),
                [
                    'status' => 'SUSPENDED',
                    'comment' =>
                        'Unauthorized suspension.',
                ]
            )
            ->assertForbidden();

        $this->assertUserStatus(
            $targetUser,
            'ACTIVE'
        );
    }

    private function userWithRole(
        string $roleName,
        string $accountStatusName = 'ACTIVE'
    ): User {
        $role = Role::query()
            ->where(
                'role_name',
                $roleName
            )
            ->firstOrFail();

        $accountStatus = AccountStatus::query()
            ->where(
                'status_name',
                $accountStatusName
            )
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'account_status_id' =>
                $accountStatus->id,
        ]);
    }

    private function assertUserStatus(
        User $user,
        string $expectedStatus
    ): void {
        $status = AccountStatus::query()
            ->where(
                'status_name',
                $expectedStatus
            )
            ->firstOrFail();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'account_status_id' => $status->id,
        ]);
    }
}