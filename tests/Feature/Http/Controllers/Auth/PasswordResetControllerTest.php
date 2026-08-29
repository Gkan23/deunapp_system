<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\AccountStatus;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT_PASSWORD =
        'Current-Password-123';

    private const NEW_PASSWORD =
        'New-Password-456';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_an_active_user_can_request_a_reset_link(): void
    {
        Notification::fake();

        $user = $this->activeUser();

        $this->postJson(
            route('password.email'),
            [
                'email' => strtoupper($user->email),
            ]
        )
            ->assertOk()
            ->assertExactJson([
                'message' =>
                    'If an eligible account exists, a password reset link has been sent.',
            ]);

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            fn (
                ResetPasswordNotification $notification
            ): bool => $notification->token !== ''
        );

        $this->assertDatabaseHas(
            'password_reset_tokens',
            [
                'email' => $user->email,
            ]
        );
    }

    public function test_an_unknown_email_receives_the_generic_response(): void
    {
        Notification::fake();

        $this->postJson(
            route('password.email'),
            [
                'email' => 'unknown@example.com',
            ]
        )
            ->assertOk()
            ->assertExactJson([
                'message' =>
                    'If an eligible account exists, a password reset link has been sent.',
            ]);

        Notification::assertNothingSent();

        $this->assertDatabaseCount(
            'password_reset_tokens',
            0
        );
    }

    public function test_an_inactive_user_does_not_receive_a_reset_link(): void
    {
        Notification::fake();

        $user = $this->activeUser();

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $user->update([
            'account_status_id' =>
                $suspendedStatus->id,
        ]);

        $this->postJson(
            route('password.email'),
            [
                'email' => $user->email,
            ]
        )
            ->assertOk()
            ->assertExactJson([
                'message' =>
                    'If an eligible account exists, a password reset link has been sent.',
            ]);

        Notification::assertNothingSent();

        $this->assertDatabaseMissing(
            'password_reset_tokens',
            [
                'email' => $user->email,
            ]
        );
    }

    public function test_the_reset_link_email_must_be_valid(): void
    {
        Notification::fake();

        $this->postJson(
            route('password.email'),
            [
                'email' => 'invalid-email',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);

        Notification::assertNothingSent();
    }

    public function test_the_reset_link_endpoint_returns_the_token_and_email(): void
    {
        $this->getJson(
            route(
                'password.reset',
                [
                    'token' => 'test-reset-token',
                    'email' =>
                        'CUSTOMER@EXAMPLE.COM',
                ]
            )
        )
            ->assertOk()
            ->assertJsonPath(
                'data.token',
                'test-reset-token'
            )
            ->assertJsonPath(
                'data.email',
                'customer@example.com'
            );
    }

    public function test_a_valid_token_resets_the_password(): void
    {
        $user = $this->activeUser();

        $token = Password::broker()
            ->createToken($user);

        $this->postJson(
            route('password.store'),
            $this->resetPayload(
                $user,
                $token
            )
        )
            ->assertOk()
            ->assertExactJson([
                'message' =>
                    'Password reset successfully.',
            ]);

        $updatedUser = $user->fresh();

        $this->assertTrue(
            Hash::check(
                self::NEW_PASSWORD,
                $updatedUser->password
            )
        );

        $this->assertFalse(
            Hash::check(
                self::CURRENT_PASSWORD,
                $updatedUser->password
            )
        );

        $this->assertDatabaseMissing(
            'password_reset_tokens',
            [
                'email' => $user->email,
            ]
        );

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' => $user->id,
            'table_name' => 'users',
            'record_id' => $user->id,
            'action_type' => 'PASSWORD_RESET',
        ]);
    }

    public function test_an_invalid_token_cannot_reset_the_password(): void
    {
        $user = $this->activeUser();

        $this->postJson(
            route('password.store'),
            $this->resetPayload(
                $user,
                'invalid-reset-token'
            )
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'token',
            ]);

        $this->assertTrue(
            Hash::check(
                self::CURRENT_PASSWORD,
                $user->fresh()->password
            )
        );

        $this->assertDatabaseMissing('audit_logs', [
            'performed_by_user_id' => $user->id,
            'action_type' => 'PASSWORD_RESET',
        ]);
    }

    public function test_the_new_password_must_be_confirmed(): void
    {
        $user = $this->activeUser();

        $token = Password::broker()
            ->createToken($user);

        $payload = $this->resetPayload(
            $user,
            $token
        );

        $payload['password_confirmation'] =
            'Different-Password-789';

        $this->postJson(
            route('password.store'),
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password',
            ]);

        $this->assertTrue(
            Hash::check(
                self::CURRENT_PASSWORD,
                $user->fresh()->password
            )
        );
    }

    public function test_the_new_password_must_have_eight_characters(): void
    {
        $user = $this->activeUser();

        $token = Password::broker()
            ->createToken($user);

        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Short1',
            'password_confirmation' => 'Short1',
        ];

        $this->postJson(
            route('password.store'),
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password',
            ]);

        $this->assertTrue(
            Hash::check(
                self::CURRENT_PASSWORD,
                $user->fresh()->password
            )
        );
    }

    public function test_an_inactive_user_cannot_reset_the_password(): void
    {
        $user = $this->activeUser();

        $token = Password::broker()
            ->createToken($user);

        $blockedStatus = AccountStatus::query()
            ->where('status_name', 'BLOCKED')
            ->firstOrFail();

        $user->update([
            'account_status_id' =>
                $blockedStatus->id,
        ]);

        $this->postJson(
            route('password.store'),
            $this->resetPayload(
                $user,
                $token
            )
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'token',
            ]);

        $this->assertTrue(
            Hash::check(
                self::CURRENT_PASSWORD,
                $user->fresh()->password
            )
        );

        $this->assertDatabaseMissing('audit_logs', [
            'performed_by_user_id' => $user->id,
            'action_type' => 'PASSWORD_RESET',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function resetPayload(
        User $user,
        string $token
    ): array {
        return [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' =>
                self::NEW_PASSWORD,
        ];
    }

    private function activeUser(): User
    {
        $activeStatus = AccountStatus::query()
            ->where('status_name', 'ACTIVE')
            ->firstOrFail();

        return User::factory()->create([
            'account_status_id' => $activeStatus->id,
            'password' => self::CURRENT_PASSWORD,
        ]);
    }
}