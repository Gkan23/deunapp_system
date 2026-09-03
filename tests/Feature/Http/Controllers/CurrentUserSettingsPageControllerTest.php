<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CurrentUserSettingsPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->seed(CatalogSeeder::class);

        Notification::fake();
    }

    public function test_a_guest_cannot_view_account_settings(): void
    {
        $this->get(
            route('current-user.settings')
        )
            ->assertRedirect('/login');
    }

    public function test_each_active_role_can_view_account_settings(): void
    {
        $roleNames = [
            'CUSTOMER',
            'DELIVERY_PROVIDER',
            'COURIER',
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ];

        $activeStatus = AccountStatus::query()
            ->where('status_name', 'ACTIVE')
            ->firstOrFail();

        foreach ($roleNames as $roleName) {
            $role = Role::query()
                ->where('role_name', $roleName)
                ->firstOrFail();

            $user = User::factory()->create([
                'role_id' => $role->id,
                'account_status_id' =>
                    $activeStatus->id,
            ]);

            $this->actingAs($user)
                ->get(
                    route('current-user.settings')
                )
                ->assertOk()
                ->assertSee('Mi cuenta')
                ->assertSee($user->name)
                ->assertSee($user->email);
        }
    }

    public function test_an_inactive_account_cannot_view_settings(): void
    {
        $inactiveStatus = AccountStatus::query()
            ->where(
                'status_name',
                '!=',
                'ACTIVE'
            )
            ->firstOrFail();

        $user = User::factory()
            ->customer()
            ->create([
                'account_status_id' =>
                    $inactiveStatus->id,
            ]);

        $this->actingAs($user)
            ->get(
                route('current-user.settings')
            )
            ->assertForbidden();
    }

    public function test_an_unverified_user_can_view_account_settings(): void
    {
        $user = User::factory()
            ->customer()
            ->unverified()
            ->create();

        $this->actingAs($user)
            ->get(
                route('current-user.settings')
            )
            ->assertOk()
            ->assertSee(
                'Tu correo todavía no está verificado.'
            );
    }

    public function test_a_user_can_update_email_from_the_blade_form(): void
    {
        $user = User::factory()
            ->customer()
            ->create([
                'email' => 'old-email@example.com',
                'password' => 'password',
            ]);

        $this->actingAs($user)
            ->from(
                route('current-user.settings')
            )
            ->put(
                route(
                    'current-user.email.update'
                ),
                [
                    'current_password' =>
                        'password',
                    'email' =>
                        'NEW-EMAIL@EXAMPLE.COM ',
                ]
            )
            ->assertRedirect(
                route('verification.notice')
            )
            ->assertSessionHas('status');

        $updatedUser = $user->fresh();

        $this->assertSame(
            'new-email@example.com',
            $updatedUser->email
        );

        $this->assertNull(
            $updatedUser->email_verified_at
        );

        $this->assertAuthenticated();

        Notification::assertSentTo(
            $updatedUser,
            VerifyEmail::class
        );
    }

    public function test_current_password_is_required_to_update_email(): void
    {
        $user = User::factory()
            ->customer()
            ->create([
                'email' => 'original@example.com',
                'password' => 'password',
            ]);

        $this->actingAs($user)
            ->from(
                route('current-user.settings')
            )
            ->put(
                route(
                    'current-user.email.update'
                ),
                [
                    'current_password' =>
                        'incorrect-password',
                    'email' =>
                        'changed@example.com',
                ]
            )
            ->assertRedirect(
                route('current-user.settings')
            )
            ->assertSessionHasErrors([
                'current_password',
            ]);

        $this->assertSame(
            'original@example.com',
            $user->fresh()->email
        );
    }

    public function test_a_user_can_update_password_from_the_blade_form(): void
    {
        $user = User::factory()
            ->customer()
            ->create([
                'password' => 'old-password',
            ]);

        $this->actingAs($user)
            ->from(
                route('current-user.settings')
            )
            ->put(
                route(
                    'current-user.password.update'
                ),
                [
                    'current_password' =>
                        'old-password',
                    'password' =>
                        'new-password123',
                    'password_confirmation' =>
                        'new-password123',
                ]
            )
            ->assertRedirect(
                route('current-user.settings')
            )
            ->assertSessionHas('status');

        $this->assertTrue(
            Hash::check(
                'new-password123',
                $user->fresh()->password
            )
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'performed_by_user_id' =>
                    $user->id,
                'table_name' => 'users',
                'record_id' => $user->id,
                'action_type' =>
                    'PASSWORD_CHANGED',
            ]
        );
    }

    public function test_password_confirmation_is_validated_from_settings(): void
    {
        $user = User::factory()
            ->customer()
            ->create([
                'password' => 'old-password',
            ]);

        $this->actingAs($user)
            ->from(
                route('current-user.settings')
            )
            ->put(
                route(
                    'current-user.password.update'
                ),
                [
                    'current_password' =>
                        'old-password',
                    'password' =>
                        'new-password123',
                    'password_confirmation' =>
                        'different-password',
                ]
            )
            ->assertRedirect(
                route('current-user.settings')
            )
            ->assertSessionHasErrors([
                'password',
            ]);

        $this->assertTrue(
            Hash::check(
                'old-password',
                $user->fresh()->password
            )
        );
    }
}