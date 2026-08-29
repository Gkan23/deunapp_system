<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentCustomerProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_update_a_customer_profile(): void
    {
        $this->patchJson(
            route('current-user.profile.update'),
            [
                'name' => 'Updated Customer',
            ]
        )->assertUnauthorized();
    }

    public function test_an_individual_customer_can_update_their_profile(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->patchJson(
                route('current-user.profile.update'),
                [
                    'name' => 'Updated Customer',
                    'identity_number' =>
                        '001-290806-9999Z',
                    'phone' => '8888-9999',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Customer profile updated successfully.'
            )
            ->assertJsonPath(
                'data.name',
                'Updated Customer'
            )
            ->assertJsonPath(
                'data.profile.identity_number',
                '001-290806-9999Z'
            )
            ->assertJsonPath(
                'data.profile.phone',
                '8888-9999'
            )
            ->assertJsonPath(
                'data.profile.customer_type.type_name',
                'INDIVIDUAL'
            );

        $this->assertDatabaseHas('users', [
            'id' => $customer->user_id,
            'name' => 'Updated Customer',
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'identity_number' =>
                '001-290806-9999Z',
            'phone' => '8888-9999',
            'company_name' => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' =>
                $customer->user_id,
            'table_name' => 'customers',
            'record_id' => $customer->id,
            'action_type' =>
                'CUSTOMER_PROFILE_UPDATED',
        ]);
    }

    public function test_optional_fields_can_be_cleared(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->patchJson(
                route('current-user.profile.update'),
                [
                    'identity_number' => '   ',
                    'phone' => '   ',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.profile.identity_number',
                null
            )
            ->assertJsonPath(
                'data.profile.phone',
                null
            );

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'identity_number' => null,
            'phone' => null,
        ]);
    }

    public function test_a_business_customer_can_update_the_company_name(): void
    {
        $customer = Customer::factory()
            ->business()
            ->create();

        $this->actingAs($customer->user)
            ->patchJson(
                route('current-user.profile.update'),
                [
                    'company_name' =>
                        'Updated Business Company',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.profile.company_name',
                'Updated Business Company'
            )
            ->assertJsonPath(
                'data.profile.customer_type.type_name',
                'BUSINESS'
            );

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'company_name' =>
                'Updated Business Company',
        ]);
    }

    public function test_an_individual_can_change_to_a_business_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->patchJson(
                route('current-user.profile.update'),
                [
                    'customer_type' => 'business',
                    'company_name' =>
                        'New Business Company',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.profile.customer_type.type_name',
                'BUSINESS'
            )
            ->assertJsonPath(
                'data.profile.company_name',
                'New Business Company'
            );

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'company_name' =>
                'New Business Company',
        ]);
    }

    public function test_a_business_customer_requires_a_company_name(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->patchJson(
                route('current-user.profile.update'),
                [
                    'customer_type' => 'BUSINESS',
                    'company_name' => '   ',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'company_name',
            ]);

        $customer->refresh();

        $this->assertSame(
            'INDIVIDUAL',
            $customer->customerType->type_name
        );
    }

    public function test_the_customer_type_must_exist(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->patchJson(
                route('current-user.profile.update'),
                [
                    'customer_type' => 'UNKNOWN',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer_type',
            ]);
    }

    public function test_the_identity_number_must_be_unique(): void
    {
        $firstCustomer = Customer::factory()->create([
            'identity_number' =>
                '001-290806-1111A',
        ]);

        $secondCustomer = Customer::factory()->create([
            'identity_number' =>
                '001-290806-2222B',
        ]);

        $this->actingAs($secondCustomer->user)
            ->patchJson(
                route('current-user.profile.update'),
                [
                    'identity_number' =>
                        $firstCustomer
                            ->identity_number,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'identity_number',
            ]);

        $this->assertDatabaseHas('customers', [
            'id' => $secondCustomer->id,
            'identity_number' =>
                '001-290806-2222B',
        ]);
    }

    public function test_a_provider_cannot_update_a_customer_profile(): void
    {
        $provider = DeliveryProvider::factory()
            ->create();

        $this->actingAs($provider->user)
            ->patchJson(
                route('current-user.profile.update'),
                [
                    'name' => 'Unauthorized Update',
                ]
            )
            ->assertForbidden();

        $this->assertNotSame(
            'Unauthorized Update',
            $provider->user->fresh()->name
        );
    }

    public function test_an_inactive_customer_cannot_update_their_profile(): void
    {
        $customer = Customer::factory()->create();

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $customer->user->update([
            'account_status_id' =>
                $suspendedStatus->id,
        ]);

        $this->actingAs(
            $customer->user->fresh()
        )
            ->patchJson(
                route('current-user.profile.update'),
                [
                    'name' => 'Unauthorized Update',
                ]
            )
            ->assertForbidden();

        $this->assertNotSame(
            'Unauthorized Update',
            $customer->user->fresh()->name
        );

        $this->assertDatabaseMissing('audit_logs', [
            'performed_by_user_id' =>
                $customer->user_id,
            'action_type' =>
                'CUSTOMER_PROFILE_UPDATED',
        ]);
    }
}