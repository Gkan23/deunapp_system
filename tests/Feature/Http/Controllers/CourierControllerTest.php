<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CourierControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_create_couriers(): void
    {
        $this->postJson(
            route('couriers.store'),
            $this->validData()
        )->assertUnauthorized();
    }

    public function test_customers_cannot_create_couriers(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->postJson(
                route('couriers.store'),
                $this->validData()
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'courier@example.com',
        ]);
    }

    public function test_administrators_cannot_create_provider_couriers(): void
    {
        $administrator = User::factory()
            ->administrator()
            ->create();

        $this->actingAs($administrator)
            ->postJson(
                route('couriers.store'),
                $this->validData()
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'courier@example.com',
        ]);
    }

    public function test_unverified_providers_cannot_create_couriers(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $provider->user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this->actingAs(
            $provider->user->fresh()
        )
            ->postJson(
                route('couriers.store'),
                $this->validData()
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'courier@example.com',
        ]);
    }

    public function test_inactive_provider_accounts_cannot_create_couriers(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $suspendedStatus =
            AccountStatus::query()
                ->where(
                    'status_name',
                    'SUSPENDED'
                )
                ->firstOrFail();

        $provider->user->update([
            'account_status_id' =>
                $suspendedStatus->id,
        ]);

        $this->actingAs(
            $provider->user->fresh()
        )
            ->postJson(
                route('couriers.store'),
                $this->validData()
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'courier@example.com',
        ]);
    }

    public function test_inactive_provider_profiles_cannot_create_couriers(): void
    {
        $provider = DeliveryProvider::factory()
            ->create([
                'is_active' => false,
            ]);

        $this->actingAs($provider->user)
            ->postJson(
                route('couriers.store'),
                $this->validData()
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'courier@example.com',
        ]);
    }

    public function test_an_active_provider_can_create_a_courier(): void
    {
        Notification::fake();

        $provider = DeliveryProvider::factory()
            ->create();

        $response = $this
            ->actingAs($provider->user)
            ->postJson(
                route('couriers.store'),
                $this->validData()
            )
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Courier created successfully.'
            )
            ->assertJsonPath(
                'data.user.email',
                'courier@example.com'
            )
            ->assertJsonPath(
                'data.user.role',
                'COURIER'
            )
            ->assertJsonPath(
                'data.user.account_status',
                'ACTIVE'
            )
            ->assertJsonPath(
                'data.courier.delivery_provider_id',
                $provider->id
            )
            ->assertJsonPath(
                'data.courier.license_number',
                'LICENSE-001'
            )
            ->assertJsonPath(
                'data.courier.is_available',
                false
            )
            ->assertJsonPath(
                'data.courier.is_active',
                true
            );

        $courierUser = User::query()
            ->where(
                'email',
                'courier@example.com'
            )
            ->firstOrFail();

        $this->assertDatabaseHas('couriers', [
            'user_id' => $courierUser->id,
            'delivery_provider_id' =>
                $provider->id,
            'license_number' => 'LICENSE-001',
            'is_available' => false,
            'is_active' => true,
        ]);

        $courier = $courierUser->courier;

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' =>
                $provider->user_id,
            'table_name' => 'couriers',
            'record_id' => $courier->id,
            'action_type' =>
                'COURIER_CREATED',
        ]);

        $responseData = $response->json(
            'data.user'
        );

        $this->assertArrayNotHasKey(
            'password',
            $responseData
        );
    }

    public function test_courier_invitation_emails_are_sent(): void
    {
        Notification::fake();

        $provider = DeliveryProvider::factory()
            ->create();

        $this->actingAs($provider->user)
            ->postJson(
                route('couriers.store'),
                $this->validData()
            )
            ->assertCreated()
            ->assertJsonPath(
                'invitation.verification_email_sent',
                true
            )
            ->assertJsonPath(
                'invitation.password_setup_email_sent',
                true
            );

        $courierUser = User::query()
            ->where(
                'email',
                'courier@example.com'
            )
            ->firstOrFail();

        Notification::assertSentTo(
            $courierUser,
            VerifyEmail::class
        );

        Notification::assertSentTo(
            $courierUser,
            ResetPassword::class
        );

        $this->assertDatabaseHas(
            'password_reset_tokens',
            [
                'email' =>
                    'courier@example.com',
            ]
        );
    }

    public function test_creating_a_courier_keeps_the_provider_authenticated(): void
    {
        Notification::fake();

        $provider = DeliveryProvider::factory()
            ->create();

        $this->actingAs($provider->user)
            ->postJson(
                route('couriers.store'),
                $this->validData()
            )
            ->assertCreated();

        $this->assertAuthenticatedAs(
            $provider->user
        );
    }

    public function test_courier_data_is_normalized_and_license_is_optional(): void
    {
        Notification::fake();

        $provider = DeliveryProvider::factory()
            ->create();

        $this->actingAs($provider->user)
            ->postJson(
                route('couriers.store'),
                $this->validData([
                    'name' => '  New Courier  ',
                    'email' =>
                        '  NEW.COURIER@EXAMPLE.COM  ',
                    'license_number' => '   ',
                    'comment' =>
                        '  New team member.  ',
                ])
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.user.name',
                'New Courier'
            )
            ->assertJsonPath(
                'data.user.email',
                'new.courier@example.com'
            )
            ->assertJsonPath(
                'data.courier.license_number',
                null
            );

        $this->assertDatabaseHas('couriers', [
            'delivery_provider_id' =>
                $provider->id,
            'license_number' => null,
        ]);
    }

    public function test_required_courier_fields_are_validated(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $this->actingAs($provider->user)
            ->postJson(
                route('couriers.store'),
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'comment',
            ]);
    }

    public function test_courier_email_must_be_unique(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        User::factory()->create([
            'email' => 'courier@example.com',
        ]);

        $this->actingAs($provider->user)
            ->postJson(
                route('couriers.store'),
                $this->validData()
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);
    }

    public function test_courier_license_number_must_be_unique(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        Courier::factory()->create([
            'license_number' => 'LICENSE-001',
        ]);

        $this->actingAs($provider->user)
            ->postJson(
                route('couriers.store'),
                $this->validData([
                    'email' =>
                        'another-courier@example.com',
                ])
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'license_number',
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
            'name' => 'New Courier',
            'email' => 'courier@example.com',
            'license_number' => 'LICENSE-001',
            'comment' => 'New team member.',
        ], $overrides);
    }
}