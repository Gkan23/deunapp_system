<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\DeliveryProvider;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DeliveryProviderRegistrationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_an_independent_provider_can_register(): void
    {
        Notification::fake();

        $response = $this->postJson(
            route('provider.register'),
            $this->validData()
        )
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Delivery provider registration submitted successfully.'
            )
            ->assertJsonPath(
                'data.user.email',
                'provider@example.com'
            )
            ->assertJsonPath(
                'data.user.role',
                'DELIVERY_PROVIDER'
            )
            ->assertJsonPath(
                'data.user.account_status',
                'PENDING'
            )
            ->assertJsonPath(
                'data.provider.provider_type',
                'INDEPENDENT'
            )
            ->assertJsonPath(
                'data.provider.identity_number',
                'PROVIDER-001'
            )
            ->assertJsonPath(
                'data.provider.is_active',
                true
            );

        $responseData = $response->json(
            'data.user'
        );

        $this->assertArrayNotHasKey(
            'password',
            $responseData
        );

        $user = User::query()
            ->where(
                'email',
                'provider@example.com'
            )
            ->firstOrFail();

        $this->assertDatabaseHas(
            'delivery_providers',
            [
                'user_id' => $user->id,
                'identity_number' =>
                    'PROVIDER-001',
                'business_name' => null,
                'is_active' => true,
            ]
        );

        $provider = $user->deliveryProvider;

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' => $user->id,
            'table_name' =>
                'delivery_providers',
            'record_id' => $provider->id,
            'action_type' =>
                'PROVIDER_REGISTERED',
        ]);
    }

    public function test_a_company_provider_can_register(): void
    {
        Notification::fake();

        $this->postJson(
            route('provider.register'),
            $this->validData([
                'email' => 'company@example.com',
                'provider_type' => 'company',
                'business_name' =>
                    'DeUnapp Logistics',
                'identity_number' =>
                    'COMPANY-001',
            ])
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.provider.provider_type',
                'COMPANY'
            )
            ->assertJsonPath(
                'data.provider.business_name',
                'DeUnapp Logistics'
            );
    }

    public function test_registration_sends_email_verification(): void
    {
        Notification::fake();

        $this->postJson(
            route('provider.register'),
            $this->validData()
        )->assertCreated();

        $user = User::query()
            ->where(
                'email',
                'provider@example.com'
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

    public function test_provider_registration_does_not_start_a_session(): void
    {
        Notification::fake();

        $this->postJson(
            route('provider.register'),
            $this->validData()
        )->assertCreated();

        $this->assertGuest();
    }

    public function test_provider_registration_fields_are_required(): void
    {
        $this->postJson(
            route('provider.register'),
            []
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
                'provider_type',
                'identity_number',
                'phone',
            ]);
    }

    public function test_password_confirmation_is_required(): void
    {
        $data = $this->validData();

        unset($data['password_confirmation']);

        $this->postJson(
            route('provider.register'),
            $data
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password',
            ]);
    }

    public function test_provider_type_must_exist(): void
    {
        $this->postJson(
            route('provider.register'),
            $this->validData([
                'provider_type' =>
                    'UNKNOWN_PROVIDER',
            ])
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'provider_type',
            ]);
    }

    public function test_company_providers_require_a_business_name(): void
    {
        $this->postJson(
            route('provider.register'),
            $this->validData([
                'provider_type' => 'COMPANY',
                'business_name' => '   ',
            ])
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'business_name',
            ]);
    }

    public function test_independent_providers_do_not_keep_a_business_name(): void
    {
        Notification::fake();

        $this->postJson(
            route('provider.register'),
            $this->validData([
                'business_name' =>
                    'Should Be Removed',
            ])
        )->assertCreated();

        $provider = DeliveryProvider::query()
            ->where(
                'identity_number',
                'PROVIDER-001'
            )
            ->firstOrFail();

        $this->assertNull(
            $provider->business_name
        );
    }

    public function test_provider_email_must_be_unique(): void
    {
        User::factory()->create([
            'email' => 'provider@example.com',
        ]);

        $this->postJson(
            route('provider.register'),
            $this->validData()
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);
    }

    public function test_provider_identity_number_must_be_unique(): void
    {
        DeliveryProvider::factory()->create([
            'identity_number' =>
                'PROVIDER-001',
        ]);

        $this->postJson(
            route('provider.register'),
            $this->validData([
                'email' =>
                    'another-provider@example.com',
            ])
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'identity_number',
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
            'name' => 'Delivery Provider',
            'email' => 'provider@example.com',
            'password' => 'password123',
            'password_confirmation' =>
                'password123',
            'provider_type' => 'INDEPENDENT',
            'business_name' => null,
            'identity_number' => 'PROVIDER-001',
            'phone' => '88888888',
        ], $overrides);
    }
}