<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\AccountStatus;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_registration_sends_an_email_verification_notification(): void
    {
        Notification::fake();

        $this->postJson(
            route('register'),
            $this->registrationPayload()
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.user.email_verified',
                false
            )
            ->assertJsonPath(
                'data.user.email_verified_at',
                null
            );

        $user = User::query()
            ->where(
                'email',
                'verification@example.com'
            )
            ->firstOrFail();

        Notification::assertSentTo(
            $user,
            VerifyEmail::class
        );

        $this->assertNull(
            $user->email_verified_at
        );
    }

    public function test_guests_cannot_access_verification_endpoints(): void
    {
        $this->getJson(
            route('verification.notice')
        )->assertUnauthorized();

        $this->postJson(
            route('verification.send')
        )->assertUnauthorized();
    }

    public function test_an_unverified_user_can_view_the_verification_prompt(): void
    {
        $user = User::factory()
            ->unverified()
            ->create();

        $this->actingAs($user)
            ->getJson(
                route('verification.notice')
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'The email address must be verified.'
            )
            ->assertJsonPath(
                'data.email',
                $user->email
            )
            ->assertJsonPath(
                'data.email_verified',
                false
            )
            ->assertJsonPath(
                'data.email_verified_at',
                null
            );
    }

    public function test_an_active_user_can_request_another_verification_link(): void
    {
        Notification::fake();

        $user = User::factory()
            ->unverified()
            ->create();

        $this->actingAs($user)
            ->postJson(
                route('verification.send')
            )
            ->assertOk()
            ->assertExactJson([
                'message' =>
                    'Email verification link sent successfully.',
            ]);

        Notification::assertSentTo(
            $user,
            VerifyEmail::class
        );
    }

    public function test_a_verified_user_does_not_receive_another_link(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(
                route('verification.send')
            )
            ->assertOk()
            ->assertExactJson([
                'message' =>
                    'The email address is already verified.',
            ]);

        Notification::assertNothingSent();
    }

    public function test_an_inactive_user_cannot_request_a_verification_link(): void
    {
        Notification::fake();

        $user = User::factory()
            ->unverified()
            ->create();

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $user->update([
            'account_status_id' =>
                $suspendedStatus->id,
        ]);

        $this->actingAs($user->fresh())
            ->postJson(
                route('verification.send')
            )
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Only an active account can request an email verification link.'
            );

        Notification::assertNothingSent();
    }

    public function test_a_valid_signed_url_verifies_the_email(): void
    {
        $user = User::factory()
            ->unverified()
            ->create();

        $verificationUrl = $this
            ->verificationUrl($user);

        $this->actingAs($user)
            ->getJson($verificationUrl)
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Email verified successfully.'
            )
            ->assertJsonPath(
                'data.email',
                $user->email
            )
            ->assertJsonPath(
                'data.email_verified',
                true
            );

        $updatedUser = $user->fresh();

        $this->assertNotNull(
            $updatedUser->email_verified_at
        );

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' => $user->id,
            'table_name' => 'users',
            'record_id' => $user->id,
            'action_type' => 'EMAIL_VERIFIED',
        ]);
    }

    public function test_an_invalid_signature_cannot_verify_the_email(): void
    {
        $user = User::factory()
            ->unverified()
            ->create();

        /*
         * Esta URL tiene los parámetros correctos,
         * pero no contiene la firma generada por Laravel.
         */
        $invalidUrl = route(
            'verification.verify',
            [
                'id' => $user->id,
                'hash' => sha1(
                    $user
                        ->getEmailForVerification()
                ),
            ]
        );

        $this->actingAs($user)
            ->getJson($invalidUrl)
            ->assertForbidden();

        $this->assertNull(
            $user->fresh()->email_verified_at
        );

        $this->assertDatabaseMissing('audit_logs', [
            'performed_by_user_id' => $user->id,
            'action_type' => 'EMAIL_VERIFIED',
        ]);
    }

    public function test_a_user_cannot_use_another_users_verification_link(): void
    {
        $targetUser = User::factory()
            ->unverified()
            ->create();

        $otherUser = User::factory()
            ->unverified()
            ->create();

        $verificationUrl = $this
            ->verificationUrl($targetUser);

        $this->actingAs($otherUser)
            ->getJson($verificationUrl)
            ->assertForbidden();

        $this->assertNull(
            $targetUser
                ->fresh()
                ->email_verified_at
        );

        $this->assertNull(
            $otherUser
                ->fresh()
                ->email_verified_at
        );

        $this->assertDatabaseMissing('audit_logs', [
            'performed_by_user_id' =>
                $targetUser->id,
            'action_type' => 'EMAIL_VERIFIED',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'performed_by_user_id' =>
                $otherUser->id,
            'action_type' => 'EMAIL_VERIFIED',
        ]);
    }

    public function test_operational_routes_require_a_verified_email(): void
    {
        $customer = Customer::factory()->create();

        /*
         * email_verified_at no forma parte de $fillable.
         * Usamos forceFill para preparar explícitamente
         * el escenario de un usuario sin verificar.
         */
        $customer->user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $unverifiedUser = $customer
            ->user
            ->fresh();

        $this->assertNull(
            $unverifiedUser->email_verified_at
        );

        $this->actingAs($unverifiedUser)
            ->getJson(
                route('shipments.index')
            )
            ->assertForbidden();

        /*
         * Simulamos que el usuario confirmó
         * correctamente su correo electrónico.
         */
        $unverifiedUser->forceFill([
            'email_verified_at' => now(),
        ])->save();

        $verifiedUser = $unverifiedUser->fresh();

        $this->assertNotNull(
            $verifiedUser->email_verified_at
        );

        $this->actingAs($verifiedUser)
            ->getJson(
                route('shipments.index')
            )
            ->assertOk();
    }

    /**
     * Datos válidos para registrar un cliente.
     *
     * @return array<string, string>
     */
    private function registrationPayload(): array
    {
        return [
            'name' => 'Verification Customer',
            'email' =>
                'verification@example.com',
            'password' =>
                'Verification-Password-123',
            'password_confirmation' =>
                'Verification-Password-123',
            'customer_type' => 'INDIVIDUAL',
            'identity_number' =>
                '001-290806-5555V',
            'phone' => '8888-5555',
        ];
    }

    /**
     * Genera una URL temporal firmada para el usuario.
     */
    private function verificationUrl(
        User $user
    ): string {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->getKey(),
                'hash' => sha1(
                    $user
                        ->getEmailForVerification()
                ),
            ]
        );
    }
}