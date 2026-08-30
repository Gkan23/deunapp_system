<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\AccountStatus;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CurrentUserEmailControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT_PASSWORD =
        'Current-Password-123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_change_an_email(): void
    {
        $this->putJson(
            route('current-user.email.update'),
            [
                'current_password' =>
                    self::CURRENT_PASSWORD,
                'email' => 'new@example.com',
            ]
        )->assertUnauthorized();
    }

    public function test_an_active_user_can_change_their_email(): void
    {
        Notification::fake();

        $user = $this->activeUser([
            'email' => 'old@example.com',
        ]);

        $this->actingAs($user)
            ->putJson(
                route('current-user.email.update'),
                [
                    'current_password' =>
                        self::CURRENT_PASSWORD,
                    'email' =>
                        ' NEW@EXAMPLE.COM ',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Email updated successfully. A verification link has been sent.'
            )
            ->assertJsonPath(
                'data.email',
                'new@example.com'
            )
            ->assertJsonPath(
                'data.email_verified',
                false
            )
            ->assertJsonPath(
                'data.email_verified_at',
                null
            );

        $updatedUser = $user->fresh();

        $this->assertSame(
            'new@example.com',
            $updatedUser->email
        );

        $this->assertNull(
            $updatedUser->email_verified_at
        );

        $this->assertAuthenticatedAs(
            $updatedUser
        );

        Notification::assertSentTo(
            $updatedUser,
            VerifyEmail::class
        );

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' => $user->id,
            'table_name' => 'users',
            'record_id' => $user->id,
            'action_type' => 'EMAIL_CHANGED',
        ]);
    }

    public function test_the_current_password_must_be_correct(): void
    {
        Notification::fake();

        $user = $this->activeUser([
            'email' => 'old@example.com',
        ]);

        $this->actingAs($user)
            ->putJson(
                route('current-user.email.update'),
                [
                    'current_password' =>
                        'Incorrect-Password-123',
                    'email' => 'new@example.com',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_password',
            ]);

        $this->assertSame(
            'old@example.com',
            $user->fresh()->email
        );

        Notification::assertNothingSent();
    }

    public function test_the_new_email_must_be_valid(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user)
            ->putJson(
                route('current-user.email.update'),
                [
                    'current_password' =>
                        self::CURRENT_PASSWORD,
                    'email' => 'invalid-email',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);
    }

    public function test_the_new_email_must_be_unique(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $user = $this->activeUser([
            'email' => 'current@example.com',
        ]);

        $this->actingAs($user)
            ->putJson(
                route('current-user.email.update'),
                [
                    'current_password' =>
                        self::CURRENT_PASSWORD,
                    'email' =>
                        'existing@example.com',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);

        $this->assertSame(
            'current@example.com',
            $user->fresh()->email
        );
    }

    public function test_the_new_email_must_be_different(): void
    {
        $user = $this->activeUser([
            'email' => 'current@example.com',
        ]);

        $this->actingAs($user)
            ->putJson(
                route('current-user.email.update'),
                [
                    'current_password' =>
                        self::CURRENT_PASSWORD,
                    'email' =>
                        'CURRENT@EXAMPLE.COM',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);

        $this->assertSame(
            'current@example.com',
            $user->fresh()->email
        );
    }

    public function test_an_inactive_user_cannot_change_their_email(): void
    {
        Notification::fake();

        $user = $this->activeUser([
            'email' => 'current@example.com',
        ]);

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $user->update([
            'account_status_id' =>
                $suspendedStatus->id,
        ]);

        $this->actingAs($user->fresh())
            ->putJson(
                route('current-user.email.update'),
                [
                    'current_password' =>
                        self::CURRENT_PASSWORD,
                    'email' => 'new@example.com',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            'current@example.com',
            $user->fresh()->email
        );

        Notification::assertNothingSent();
    }

    public function test_changing_the_email_requires_verification_again(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create();

        $customer->user->update([
            'password' => self::CURRENT_PASSWORD,
        ]);

        $this->assertNotNull(
            $customer
                ->user
                ->fresh()
                ->email_verified_at
        );

        $this->actingAs(
            $customer->user->fresh()
        )
            ->putJson(
                route('current-user.email.update'),
                [
                    'current_password' =>
                        self::CURRENT_PASSWORD,
                    'email' =>
                        'changed@example.com',
                ]
            )
            ->assertOk();

        $updatedUser = $customer
            ->user
            ->fresh();

        $this->assertNull(
            $updatedUser->email_verified_at
        );

        $this->actingAs($updatedUser)
            ->getJson(
                route('shipments.index')
            )
            ->assertForbidden();

        $this->assertTrue(
            Hash::check(
                self::CURRENT_PASSWORD,
                $updatedUser->password
            )
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function activeUser(
        array $attributes = []
    ): User {
        $activeStatus = AccountStatus::query()
            ->where('status_name', 'ACTIVE')
            ->firstOrFail();

        return User::factory()->create(
            array_merge(
                [
                    'account_status_id' =>
                        $activeStatus->id,
                    'password' =>
                        self::CURRENT_PASSWORD,
                ],
                $attributes
            )
        );
    }
}