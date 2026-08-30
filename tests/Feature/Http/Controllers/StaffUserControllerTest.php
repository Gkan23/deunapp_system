<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffUserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_create_staff_users(): void
    {
        $this->postJson(
            route('users.staff.store'),
            $this->validData()
        )->assertUnauthorized();
    }

    public function test_support_agents_cannot_create_staff_users(): void
    {
        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $this->actingAs($supportAgent)
            ->postJson(
                route('users.staff.store'),
                $this->validData()
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'new.staff@example.com',
        ]);
    }

    public function test_an_administrator_can_create_a_support_agent(): void
    {
        Notification::fake();

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this->actingAs($administrator)
            ->postJson(
                route('users.staff.store'),
                $this->validData()
            )
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Staff user created successfully.'
            )
            ->assertJsonPath(
                'data.email',
                'new.staff@example.com'
            )
            ->assertJsonPath(
                'data.role.role_name',
                'SUPPORT_AGENT'
            )
            ->assertJsonPath(
                'data.account_status.status_name',
                'ACTIVE'
            )
            ->assertJsonPath(
                'data.email_verified',
                false
            )
            ->assertJsonPath(
                'invitation.verification_email_sent',
                true
            )
            ->assertJsonPath(
                'invitation.password_setup_email_sent',
                true
            );

        $staffUser = User::query()
            ->where(
                'email',
                'new.staff@example.com'
            )
            ->firstOrFail();

        Notification::assertSentTo(
            $staffUser,
            VerifyEmail::class
        );

        Notification::assertSentTo(
            $staffUser,
            ResetPassword::class
        );

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' =>
                $administrator->id,
            'table_name' => 'users',
            'record_id' => $staffUser->id,
            'action_type' =>
                'STAFF_USER_CREATED',
        ]);

        $this->assertDatabaseHas(
            'password_reset_tokens',
            [
                'email' =>
                    'new.staff@example.com',
            ]
        );
    }

    public function test_an_administrator_can_create_another_administrator(): void
    {
        Notification::fake();

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $data = $this->validData([
            'email' => 'new.admin@example.com',
            'role' => 'administrator',
        ]);

        $this->actingAs($administrator)
            ->postJson(
                route('users.staff.store'),
                $data
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.role.role_name',
                'ADMINISTRATOR'
            );

        $this->assertDatabaseHas('users', [
            'email' => 'new.admin@example.com',
            'role_id' =>
                $this->role('ADMINISTRATOR')->id,
        ]);
    }

    public function test_staff_data_is_normalized(): void
    {
        Notification::fake();

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this->actingAs($administrator)
            ->postJson(
                route('users.staff.store'),
                $this->validData([
                    'name' => '  Internal User  ',
                    'email' =>
                        '  INTERNAL.USER@EXAMPLE.COM  ',
                    'role' => ' support_agent ',
                    'comment' =>
                        '  New support employee.  ',
                ])
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Internal User'
            )
            ->assertJsonPath(
                'data.email',
                'internal.user@example.com'
            )
            ->assertJsonPath(
                'data.role.role_name',
                'SUPPORT_AGENT'
            );

        $this->assertDatabaseHas('users', [
            'name' => 'Internal User',
            'email' =>
                'internal.user@example.com',
        ]);
    }

    public function test_required_staff_fields_are_validated(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this->actingAs($administrator)
            ->postJson(
                route('users.staff.store'),
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'role',
                'comment',
            ]);
    }

    public function test_only_staff_roles_can_be_created(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        foreach (
            [
                'CUSTOMER',
                'DELIVERY_PROVIDER',
                'COURIER',
            ] as $roleName
        ) {
            $this->actingAs($administrator)
                ->postJson(
                    route('users.staff.store'),
                    $this->validData([
                        'role' => $roleName,
                    ])
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'role',
                ]);
        }

        $this->assertDatabaseMissing('users', [
            'email' => 'new.staff@example.com',
        ]);
    }

    public function test_staff_email_must_be_unique(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $this->actingAs($administrator)
            ->postJson(
                route('users.staff.store'),
                $this->validData([
                    'email' =>
                        'existing@example.com',
                ])
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);
    }

    public function test_staff_users_are_created_without_operational_profiles(): void
    {
        Notification::fake();

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $response = $this
            ->actingAs($administrator)
            ->postJson(
                route('users.staff.store'),
                $this->validData()
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.profile_type',
                null
            )
            ->assertJsonPath(
                'data.profile',
                null
            );

        $responseData = $response->json('data');

        $this->assertArrayNotHasKey(
            'password',
            $responseData
        );

        $this->assertArrayNotHasKey(
            'remember_token',
            $responseData
        );

        $staffUser = User::query()
            ->where(
                'email',
                'new.staff@example.com'
            )
            ->firstOrFail();

        $this->assertDatabaseMissing(
            'customers',
            [
                'user_id' => $staffUser->id,
            ]
        );

        $this->assertDatabaseMissing(
            'delivery_providers',
            [
                'user_id' => $staffUser->id,
            ]
        );

        $this->assertDatabaseMissing(
            'couriers',
            [
                'user_id' => $staffUser->id,
            ]
        );
    }

    public function test_new_staff_users_are_unverified_but_active(): void
    {
        Notification::fake();

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this->actingAs($administrator)
            ->postJson(
                route('users.staff.store'),
                $this->validData()
            )
            ->assertCreated();

        $staffUser = User::query()
            ->where(
                'email',
                'new.staff@example.com'
            )
            ->firstOrFail();

        $this->assertNull(
            $staffUser->email_verified_at
        );

        $this->assertSame(
            'ACTIVE',
            $staffUser
                ->accountStatus
                ->status_name
        );
    }

    public function test_inactive_administrators_cannot_create_staff_users(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR',
            'SUSPENDED'
        );

        $this->actingAs($administrator)
            ->postJson(
                route('users.staff.store'),
                $this->validData()
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'new.staff@example.com',
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validData(
        array $overrides = []
    ): array {
        return array_merge([
            'name' => 'New Staff User',
            'email' => 'new.staff@example.com',
            'role' => 'SUPPORT_AGENT',
            'comment' =>
                'New internal employee.',
        ], $overrides);
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
            'role_id' =>
                $this->role($roleName)->id,
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
}