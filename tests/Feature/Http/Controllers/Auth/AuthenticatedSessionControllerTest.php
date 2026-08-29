<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\AccountStatus;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_active_user_can_log_in(): void
    {
        $user = $this->activeUser([
            'email' => 'login@example.com',
            'password' => 'secret-password',
        ]);

        $this->postJson(
            route('login'),
            [
                'email' => 'login@example.com',
                'password' => 'secret-password',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Logged in successfully.'
            )
            ->assertJsonPath(
                'data.id',
                $user->id
            )
            ->assertJsonPath(
                'data.email',
                'login@example.com'
            )
            ->assertJsonPath(
                'data.role.role_name',
                'ADMINISTRATOR'
            )
            ->assertJsonPath(
                'data.account_status.status_name',
                'ACTIVE'
            );

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_is_normalized_during_login(): void
    {
        $user = $this->activeUser([
            'email' => 'normalized@example.com',
            'password' => 'secret-password',
        ]);

        $this->postJson(
            route('login'),
            [
                'email' => ' NORMALIZED@EXAMPLE.COM ',
                'password' => 'secret-password',
                'remember' => true,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $user->id
            );

        $this->assertAuthenticatedAs($user);
    }

    public function test_incorrect_credentials_are_rejected(): void
    {
        $user = $this->activeUser([
            'email' => 'credentials@example.com',
            'password' => 'correct-password',
        ]);

        $scenarios = [
            [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ],
            [
                'email' => 'unknown@example.com',
                'password' => 'correct-password',
            ],
        ];

        foreach ($scenarios as $credentials) {
            $this->postJson(
                route('login'),
                $credentials
            )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'email',
                ])
                ->assertJsonPath(
                    'errors.email.0',
                    'The provided credentials are incorrect.'
                );

            $this->assertGuest();
        }
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        $user = $this->activeUser([
            'email' => 'suspended@example.com',
            'password' => 'secret-password',
        ]);

        $user->update([
            'account_status_id' => AccountStatus::query()
                ->where('status_name', 'SUSPENDED')
                ->firstOrFail()
                ->id,
        ]);

        $this->postJson(
            route('login'),
            [
                'email' => 'suspended@example.com',
                'password' => 'secret-password',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ])
            ->assertJsonPath(
                'errors.email.0',
                'The account is not active.'
            );

        $this->assertGuest();
    }

    public function test_login_data_is_validated(): void
    {
        $this->postJson(
            route('login'),
            [
                'email' => 'invalid-email',
                'password' => '',
                'remember' => 'invalid-boolean',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'password',
                'remember',
            ]);

        $this->assertGuest();
    }

    public function test_login_response_does_not_expose_sensitive_data(): void
    {
        $user = $this->activeUser([
            'email' => 'safe-response@example.com',
            'password' => 'secret-password',
        ]);

        $user->forceFill([
            'remember_token' => 'private-token',
        ])->save();

        $response = $this->postJson(
            route('login'),
            [
                'email' => $user->email,
                'password' => 'secret-password',
            ]
        )->assertOk();

        $data = $response->json('data');

        $this->assertArrayNotHasKey(
            'password',
            $data
        );

        $this->assertArrayNotHasKey(
            'remember_token',
            $data
        );
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user)
            ->postJson(route('logout'))
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Logged out successfully.'
            );

        $this->assertGuest();
    }

    public function test_guest_cannot_access_logout_endpoint(): void
    {
        $this->postJson(
            route('logout')
        )->assertUnauthorized();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function activeUser(
        array $attributes = []
    ): User {
        $administratorRole = Role::query()
            ->where(
                'role_name',
                'ADMINISTRATOR'
            )
            ->firstOrFail();

        $activeStatus = AccountStatus::query()
            ->where(
                'status_name',
                'ACTIVE'
            )
            ->firstOrFail();

        return User::factory()->create(
            array_merge([
                'role_id' => $administratorRole->id,
                'account_status_id' => $activeStatus->id,
            ], $attributes)
        );
    }
}