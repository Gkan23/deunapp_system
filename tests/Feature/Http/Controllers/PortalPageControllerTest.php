<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_can_view_the_login_page(): void
    {
        $this->get(
            route('login.page')
        )
            ->assertOk()
            ->assertSee('Iniciar sesión')
            ->assertSee('DeUnapp')
            ->assertSee(
                route('login'),
                escape: false
            );
    }

    public function test_a_verified_user_can_login_using_the_blade_form(): void
    {
        $user = User::factory()
            ->customer()
            ->create([
                'email' => 'customer@example.com',
                'password' => 'password',
                'email_verified_at' => now(),
            ]);

        $this->from(
            route('login.page')
        )
            ->post(
                route('login'),
                [
                    'email' => 'CUSTOMER@EXAMPLE.COM ',
                    'password' => 'password',
                    'remember' => true,
                ]
            )
            ->assertRedirect(
                route('dashboard')
            );

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_return_to_the_login_page(): void
    {
        User::factory()
            ->customer()
            ->create([
                'email' => 'customer@example.com',
                'password' => 'password',
            ]);

        $this->from(
            route('login.page')
        )
            ->post(
                route('login'),
                [
                    'email' => 'customer@example.com',
                    'password' => 'incorrect-password',
                ]
            )
            ->assertRedirect(
                route('login.page')
            )
            ->assertSessionHasErrors([
                'email',
            ]);

        $this->assertGuest();
    }

    public function test_a_guest_cannot_view_the_dashboard(): void
    {
        $this->get(
            route('dashboard')
        )
            ->assertRedirect('/login');
    }

    public function test_an_unverified_user_is_redirected_to_verification(): void
    {
        $user = User::factory()
            ->customer()
            ->unverified()
            ->create();

        $this->actingAs($user)
            ->get(
                route('dashboard')
            )
            ->assertRedirect(
                route('verification.notice')
            );
    }

    public function test_each_role_sees_its_dashboard_modules(): void
    {
        $scenarios = [
            'CUSTOMER' => 'Mis envíos',
            'DELIVERY_PROVIDER' => 'Repartidores',
            'COURIER' => 'Mis rutas',
            'SUPPORT_AGENT' => 'Tickets de soporte',
            'ADMINISTRATOR' =>
                'Administración de usuarios',
        ];

        $activeStatus = AccountStatus::query()
            ->where('status_name', 'ACTIVE')
            ->firstOrFail();

        foreach (
            $scenarios as $roleName => $expectedModule
        ) {
            $role = Role::query()
                ->where('role_name', $roleName)
                ->firstOrFail();

            $user = User::factory()->create([
                'role_id' => $role->id,
                'account_status_id' => $activeStatus->id,
                'email_verified_at' => now(),
            ]);

            $this->actingAs($user)
                ->get(
                    route('dashboard')
                )
                ->assertOk()
                ->assertSee($expectedModule)
                ->assertSee($user->name);
        }
    }

    public function test_a_user_can_logout_from_the_portal(): void
    {
        $user = User::factory()
            ->customer()
            ->create();

        $this->actingAs($user)
            ->post(
                route('logout')
            )
            ->assertRedirect(
                route('login.page')
            );

        $this->assertGuest();
    }
}