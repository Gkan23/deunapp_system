<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->seed(CatalogSeeder::class);

        Notification::fake();
    }

    public function test_a_guest_can_view_the_customer_registration_page(): void
    {
        $this->get(
            route('register.page')
        )
            ->assertOk()
            ->assertSee(
                'Crear cuenta de cliente'
            )
            ->assertSee(
                route('register'),
                escape: false
            )
            ->assertSee('INDIVIDUAL')
            ->assertSee('BUSINESS');
    }

    public function test_a_guest_can_view_the_provider_registration_page(): void
    {
        $this->get(
            route('provider.register.page')
        )
            ->assertOk()
            ->assertSee(
                'Registro de proveedor'
            )
            ->assertSee(
                route('provider.register'),
                escape: false
            )
            ->assertSee('INDEPENDENT')
            ->assertSee('COMPANY');
    }

    public function test_authenticated_users_cannot_open_registration_pages(): void
    {
        $user = User::factory()
            ->customer()
            ->create();

        $this->actingAs($user)
            ->get(
                route('register.page')
            )
            ->assertRedirect();

        $this->get(
            route('provider.register.page')
        )
            ->assertRedirect();
    }

    public function test_a_customer_can_register_using_the_blade_form(): void
    {
        $this->from(
            route('register.page')
        )
            ->post(
                route('register'),
                [
                    'name' => 'Customer Test',
                    'email' =>
                        'customer-registration@example.com',
                    'password' => 'password123',
                    'password_confirmation' =>
                        'password123',
                    'customer_type' => 'INDIVIDUAL',
                    'identity_number' =>
                        'CUSTOMER-IDENTITY-001',
                    'company_name' => null,
                    'phone' => '88881111',
                ]
            )
            ->assertRedirect(
                route('verification.notice')
            )
            ->assertSessionHas('status');

        $user = User::query()
            ->with([
                'role',
                'accountStatus',
                'customer.customerType',
            ])
            ->where(
                'email',
                'customer-registration@example.com'
            )
            ->firstOrFail();

        $this->assertAuthenticatedAs($user);

        $this->assertSame(
            'CUSTOMER',
            $user->role->role_name
        );

        $this->assertSame(
            'INDIVIDUAL',
            $user
                ->customer
                ->customerType
                ->type_name
        );

        $this->assertDatabaseHas(
            'customers',
            [
                'user_id' => $user->id,
                'identity_number' =>
                    'CUSTOMER-IDENTITY-001',
                'phone' => '88881111',
            ]
        );
    }

    public function test_a_business_customer_requires_a_company_name(): void
    {
        $this->from(
            route('register.page')
        )
            ->post(
                route('register'),
                [
                    'name' => 'Business Customer',
                    'email' =>
                        'business-customer@example.com',
                    'password' => 'password123',
                    'password_confirmation' =>
                        'password123',
                    'customer_type' => 'BUSINESS',
                    'identity_number' =>
                        'BUSINESS-CUSTOMER-001',
                    'company_name' => null,
                    'phone' => '88882222',
                ]
            )
            ->assertRedirect(
                route('register.page')
            )
            ->assertSessionHasErrors([
                'company_name',
            ]);

        $this->assertGuest();

        $this->assertDatabaseMissing(
            'users',
            [
                'email' =>
                    'business-customer@example.com',
            ]
        );
    }

    public function test_a_provider_can_submit_the_blade_registration_form(): void
    {
        $this->from(
            route('provider.register.page')
        )
            ->post(
                route('provider.register'),
                [
                    'name' => 'Provider Test',
                    'email' =>
                        'provider-registration@example.com',
                    'password' => 'password123',
                    'password_confirmation' =>
                        'password123',
                    'provider_type' => 'INDEPENDENT',
                    'business_name' => null,
                    'identity_number' =>
                        'PROVIDER-IDENTITY-001',
                    'phone' => '88883333',
                ]
            )
            ->assertRedirect(
                route('login.page')
            )
            ->assertSessionHas('status');

        $user = User::query()
            ->with([
                'role',
                'accountStatus',
                'deliveryProvider.providerType',
            ])
            ->where(
                'email',
                'provider-registration@example.com'
            )
            ->firstOrFail();

        $this->assertGuest();

        $this->assertSame(
            'DELIVERY_PROVIDER',
            $user->role->role_name
        );

        $this->assertSame(
            'INDEPENDENT',
            $user
                ->deliveryProvider
                ->providerType
                ->type_name
        );

        $this->assertDatabaseHas(
            'delivery_providers',
            [
                'user_id' => $user->id,
                'identity_number' =>
                    'PROVIDER-IDENTITY-001',
                'phone' => '88883333',
                'is_active' => true,
            ]
        );
    }

    public function test_a_company_provider_requires_a_business_name(): void
    {
        $this->from(
            route('provider.register.page')
        )
            ->post(
                route('provider.register'),
                [
                    'name' => 'Company Provider',
                    'email' =>
                        'company-provider@example.com',
                    'password' => 'password123',
                    'password_confirmation' =>
                        'password123',
                    'provider_type' => 'COMPANY',
                    'business_name' => null,
                    'identity_number' =>
                        'PROVIDER-COMPANY-001',
                    'phone' => '88884444',
                ]
            )
            ->assertRedirect(
                route('provider.register.page')
            )
            ->assertSessionHasErrors([
                'business_name',
            ]);

        $this->assertGuest();

        $this->assertDatabaseMissing(
            'users',
            [
                'email' =>
                    'company-provider@example.com',
            ]
        );
    }

    public function test_password_confirmation_is_validated_by_the_form(): void
    {
        $this->from(
            route('register.page')
        )
            ->post(
                route('register'),
                [
                    'name' => 'Password Test',
                    'email' =>
                        'password-test@example.com',
                    'password' => 'password123',
                    'password_confirmation' =>
                        'different-password',
                    'customer_type' => 'INDIVIDUAL',
                    'identity_number' =>
                        'PASSWORD-TEST-001',
                    'company_name' => null,
                    'phone' => '88885555',
                ]
            )
            ->assertRedirect(
                route('register.page')
            )
            ->assertSessionHasErrors([
                'password',
            ]);

        $this->assertGuest();

        $this->assertDatabaseMissing(
            'users',
            [
                'email' =>
                    'password-test@example.com',
            ]
        );
    }
}