<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentCustomerProfilePageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_view_the_customer_profile_page(): void
    {
        $this->get(
            route('current-user.profile.edit')
        )
            ->assertRedirect(
                route('login.page')
            );
    }

    public function test_a_customer_can_view_their_profile_page(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->get(
                route('current-user.profile.edit')
            )
            ->assertOk()
            ->assertSee('Perfil de cliente')
            ->assertSee($customer->user->name)
            ->assertSee(
                (string) $customer->identity_number
            )
            ->assertSee(
                route('current-user.profile.update'),
                escape: false
            );
    }

    public function test_the_page_contains_the_available_customer_types(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->get(
                route('current-user.profile.edit')
            )
            ->assertOk()
            ->assertSee('INDIVIDUAL')
            ->assertSee('BUSINESS');
    }

    public function test_an_unverified_customer_can_view_the_profile_page(): void
    {
        $user = User::factory()
            ->customer()
            ->unverified()
            ->create();

        Customer::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(
                route('current-user.profile.edit')
            )
            ->assertOk();
    }

    public function test_a_non_customer_cannot_view_the_customer_profile_page(): void
    {
        $providerUser = User::factory()
            ->deliveryProvider()
            ->create();

        $this->actingAs($providerUser)
            ->get(
                route('current-user.profile.edit')
            )
            ->assertForbidden();
    }

    public function test_an_inactive_customer_cannot_view_the_profile_page(): void
    {
        $customer = Customer::factory()->create();

        $inactiveStatus = AccountStatus::query()
            ->where(
                'status_name',
                '!=',
                'ACTIVE'
            )
            ->firstOrFail();

        $customer->user->update([
            'account_status_id' =>
                $inactiveStatus->id,
        ]);

        $this->actingAs($customer->user)
            ->get(
                route('current-user.profile.edit')
            )
            ->assertForbidden();
    }

    public function test_a_customer_can_update_their_profile_using_the_form(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->patch(
                route('current-user.profile.update'),
                [
                    'name' => 'Cliente actualizado',
                    'customer_type' => 'INDIVIDUAL',
                    'identity_number' =>
                        'CUSTOMER-PROFILE-001',
                    'company_name' => null,
                    'phone' => '88884444',
                ]
            )
            ->assertRedirect(
                route('current-user.profile.edit')
            )
            ->assertSessionHas(
                'status',
                'Perfil actualizado correctamente.'
            );

        $this->assertDatabaseHas('users', [
            'id' => $customer->user_id,
            'name' => 'Cliente actualizado',
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'identity_number' =>
                'CUSTOMER-PROFILE-001',
            'company_name' => null,
            'phone' => '88884444',
        ]);
    }

    public function test_a_business_customer_requires_a_company_name(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->from(
                route('current-user.profile.edit')
            )
            ->patch(
                route('current-user.profile.update'),
                [
                    'customer_type' => 'BUSINESS',
                    'company_name' => '   ',
                ]
            )
            ->assertRedirect(
                route('current-user.profile.edit')
            )
            ->assertSessionHasErrors([
                'company_name',
            ]);

        $customer->refresh();

        $this->assertNull(
            $customer->company_name
        );
    }
}