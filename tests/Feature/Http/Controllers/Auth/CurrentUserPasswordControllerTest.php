<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\AccountStatus;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CurrentUserPasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT_PASSWORD = 'Current-Password-123';

    private const NEW_PASSWORD = 'New-Password-456';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_change_a_password(): void
    {
        $this->putJson(
            route('current-user.password.update'),
            $this->validPayload()
        )->assertUnauthorized();
    }

    public function test_an_active_user_can_change_their_password(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user)
            ->putJson(
                route('current-user.password.update'),
                $this->validPayload()
            )
            ->assertOk()
            ->assertExactJson([
                'message' => 'Password updated successfully.',
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

        $this->assertAuthenticatedAs($updatedUser);

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' => $user->id,
            'table_name' => 'users',
            'record_id' => $user->id,
            'action_type' => 'PASSWORD_CHANGED',
        ]);
    }

    public function test_the_current_password_must_be_correct(): void
    {
        $user = $this->activeUser();

        $payload = $this->validPayload();

        $payload['current_password'] = 'Incorrect-Password-123';

        $this->actingAs($user)
            ->putJson(
                route('current-user.password.update'),
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_password',
            ]);

        $this->assertTrue(
            Hash::check(
                self::CURRENT_PASSWORD,
                $user->fresh()->password
            )
        );

        $this->assertDatabaseMissing('audit_logs', [
            'performed_by_user_id' => $user->id,
            'action_type' => 'PASSWORD_CHANGED',
        ]);
    }

    public function test_the_current_password_is_required(): void
    {
        $user = $this->activeUser();

        $payload = $this->validPayload();

        unset($payload['current_password']);

        $this->actingAs($user)
            ->putJson(
                route('current-user.password.update'),
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_password',
            ]);
    }

    public function test_the_new_password_must_be_confirmed(): void
    {
        $user = $this->activeUser();

        $payload = $this->validPayload();

        $payload['password_confirmation'] =
            'Different-Password-789';

        $this->actingAs($user)
            ->putJson(
                route('current-user.password.update'),
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

        $payload = [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => 'Short1',
            'password_confirmation' => 'Short1',
        ];

        $this->actingAs($user)
            ->putJson(
                route('current-user.password.update'),
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password',
            ]);
    }

    public function test_the_new_password_must_be_different(): void
    {
        $user = $this->activeUser();

        $payload = [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => self::CURRENT_PASSWORD,
            'password_confirmation' =>
                self::CURRENT_PASSWORD,
        ];

        $this->actingAs($user)
            ->putJson(
                route('current-user.password.update'),
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password',
            ]);

        $this->assertDatabaseMissing('audit_logs', [
            'performed_by_user_id' => $user->id,
            'action_type' => 'PASSWORD_CHANGED',
        ]);
    }

    public function test_an_inactive_user_cannot_change_their_password(): void
    {
        $user = $this->activeUser();

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $user->update([
            'account_status_id' => $suspendedStatus->id,
        ]);

        $this->actingAs($user->fresh())
            ->putJson(
                route('current-user.password.update'),
                $this->validPayload()
            )
            ->assertForbidden();

        $this->assertTrue(
            Hash::check(
                self::CURRENT_PASSWORD,
                $user->fresh()->password
            )
        );

        $this->assertDatabaseMissing('audit_logs', [
            'performed_by_user_id' => $user->id,
            'action_type' => 'PASSWORD_CHANGED',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
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