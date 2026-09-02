<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->seed(CatalogSeeder::class);

        Notification::fake();
    }

    public function test_a_guest_can_view_the_forgot_password_page(): void
    {
        $this->get(
            route('password.request')
        )
            ->assertOk()
            ->assertSee(
                '¿Olvidaste tu contraseña?'
            )
            ->assertSee(
                route('password.email'),
                escape: false
            );
    }

    public function test_an_authenticated_user_cannot_open_password_recovery_pages(): void
    {
        $user = User::factory()
            ->customer()
            ->create();

        $this->actingAs($user)
            ->get(
                route('password.request')
            )
            ->assertRedirect();

        $this->get(
            route(
                'password.reset',
                'TEST-TOKEN'
            )
        )
            ->assertRedirect();
    }

    public function test_an_active_user_can_request_a_reset_link_using_the_form(): void
    {
        $user = User::factory()
            ->customer()
            ->create([
                'email' => 'reset@example.com',
            ]);

        $this->from(
            route('password.request')
        )
            ->post(
                route('password.email'),
                [
                    'email' => 'RESET@EXAMPLE.COM ',
                ]
            )
            ->assertRedirect(
                route('password.request')
            )
            ->assertSessionHas('status');

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class
        );
    }

    public function test_an_unknown_email_receives_the_same_form_response(): void
    {
        $this->from(
            route('password.request')
        )
            ->post(
                route('password.email'),
                [
                    'email' =>
                        'unknown@example.com',
                ]
            )
            ->assertRedirect(
                route('password.request')
            )
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_a_guest_can_view_the_reset_password_page(): void
    {
        $this->get(
            route(
                'password.reset',
                [
                    'token' => 'TEST-RESET-TOKEN',
                    'email' => 'reset@example.com',
                ]
            )
        )
            ->assertOk()
            ->assertSee(
                'Crear nueva contraseña'
            )
            ->assertSee(
                'TEST-RESET-TOKEN'
            )
            ->assertSee(
                'reset@example.com'
            );
    }

    public function test_a_user_can_reset_the_password_using_the_blade_form(): void
    {
        $user = User::factory()
            ->customer()
            ->create([
                'email' => 'reset-success@example.com',
                'password' => 'old-password',
            ]);

        $token = Password::broker()
            ->createToken($user);

        $this->from(
            route(
                'password.reset',
                [
                    'token' => $token,
                    'email' => $user->email,
                ]
            )
        )
            ->post(
                route('password.store'),
                [
                    'token' => $token,
                    'email' => $user->email,
                    'password' => 'new-password123',
                    'password_confirmation' =>
                        'new-password123',
                ]
            )
            ->assertRedirect(
                route('login.page')
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
                'performed_by_user_id' => $user->id,
                'table_name' => 'users',
                'record_id' => $user->id,
                'action_type' => 'PASSWORD_RESET',
            ]
        );
    }

    public function test_an_invalid_token_returns_to_the_reset_form(): void
    {
        $user = User::factory()
            ->customer()
            ->create([
                'email' =>
                    'invalid-token@example.com',
            ]);

        $endpoint = route(
            'password.reset',
            [
                'token' => 'INVALID-TOKEN',
                'email' => $user->email,
            ]
        );

        $this->from($endpoint)
            ->post(
                route('password.store'),
                [
                    'token' => 'INVALID-TOKEN',
                    'email' => $user->email,
                    'password' => 'new-password123',
                    'password_confirmation' =>
                        'new-password123',
                ]
            )
            ->assertRedirect($endpoint)
            ->assertSessionHasErrors([
                'token',
            ]);
    }

    public function test_password_confirmation_is_required_when_resetting(): void
    {
        $user = User::factory()
            ->customer()
            ->create([
                'email' =>
                    'confirmation@example.com',
                'password' => 'old-password',
            ]);

        $token = Password::broker()
            ->createToken($user);

        $endpoint = route(
            'password.reset',
            [
                'token' => $token,
                'email' => $user->email,
            ]
        );

        $this->from($endpoint)
            ->post(
                route('password.store'),
                [
                    'token' => $token,
                    'email' => $user->email,
                    'password' => 'new-password123',
                    'password_confirmation' =>
                        'different-password',
                ]
            )
            ->assertRedirect($endpoint)
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