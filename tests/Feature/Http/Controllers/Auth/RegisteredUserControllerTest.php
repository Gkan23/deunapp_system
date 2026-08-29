<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisteredUserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_can_register_an_individual_customer(): void
    {
        $response = $this->postJson(
            route('register'),
            $this->individualPayload()
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Customer registered successfully.'
            )
            ->assertJsonPath(
                'data.user.email',
                'customer@example.com'
            )
            ->assertJsonPath(
                'data.user.role',
                'CUSTOMER'
            )
            ->assertJsonPath(
                'data.user.account_status',
                'ACTIVE'
            )
            ->assertJsonPath(
                'data.customer.customer_type',
                'INDIVIDUAL'
            )
            ->assertJsonPath(
                'data.customer.company_name',
                null
            );

        $user = User::query()
            ->where(
                'email',
                'customer@example.com'
            )
            ->firstOrFail();

        $this->assertAuthenticatedAs($user);

        $this->assertTrue(
            Hash::check(
                'Customer-Password-123',
                $user->password
            )
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'customer@example.com',
        ]);

        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'identity_number' => '001-290806-1000A',
            'company_name' => null,
            'phone' => '8888-1111',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' => $user->id,
            'table_name' => 'customers',
            'record_id' => $user->customer->id,
            'action_type' => 'CUSTOMER_REGISTERED',
        ]);
    }

    public function test_a_guest_can_register_a_business_customer(): void
    {
        $payload = [
            'name' => 'DeUnapp Business',
            'email' => 'business@example.com',
            'password' => 'Business-Password-123',
            'password_confirmation' =>
                'Business-Password-123',
            'customer_type' => 'business',
            'identity_number' => 'RUC-2026-001',
            'company_name' => 'DeUnapp Company',
            'phone' => '8888-2222',
        ];

        $this->postJson(
            route('register'),
            $payload
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.user.email',
                'business@example.com'
            )
            ->assertJsonPath(
                'data.customer.customer_type',
                'BUSINESS'
            )
            ->assertJsonPath(
                'data.customer.company_name',
                'DeUnapp Company'
            );

        $user = User::query()
            ->where(
                'email',
                'business@example.com'
            )
            ->firstOrFail();

        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'identity_number' => 'RUC-2026-001',
            'company_name' => 'DeUnapp Company',
            'phone' => '8888-2222',
        ]);
    }

    public function test_optional_customer_fields_are_normalized_to_null(): void
    {
        $payload = $this->individualPayload();

        $payload['identity_number'] = '   ';
        $payload['phone'] = '   ';

        $this->postJson(
            route('register'),
            $payload
        )->assertCreated();

        $user = User::query()
            ->where(
                'email',
                'customer@example.com'
            )
            ->firstOrFail();

        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'identity_number' => null,
            'company_name' => null,
            'phone' => null,
        ]);
    }

    public function test_the_name_is_required(): void
    {
        $payload = $this->individualPayload();

        unset($payload['name']);

        $this->postJson(
            route('register'),
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_the_email_must_be_valid(): void
    {
        $payload = $this->individualPayload();

        $payload['email'] = 'invalid-email';

        $this->postJson(
            route('register'),
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_email_must_be_unique(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
        ]);

        $this->postJson(
            route('register'),
            $this->individualPayload()
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_the_password_must_be_confirmed(): void
    {
        $payload = $this->individualPayload();

        $payload['password_confirmation'] =
            'Different-Password-123';

        $this->postJson(
            route('register'),
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password',
            ]);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_the_password_must_have_eight_characters(): void
    {
        $payload = $this->individualPayload();

        $payload['password'] = 'Short1';
        $payload['password_confirmation'] =
            'Short1';

        $this->postJson(
            route('register'),
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password',
            ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_customer_type_must_exist(): void
    {
        $payload = $this->individualPayload();

        $payload['customer_type'] = 'UNKNOWN';

        $this->postJson(
            route('register'),
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer_type',
            ]);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_a_business_customer_requires_a_company_name(): void
    {
        $payload = $this->individualPayload();

        $payload['customer_type'] = 'BUSINESS';

        unset($payload['company_name']);

        $this->postJson(
            route('register'),
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'company_name',
            ]);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('customers', 0);
    }

    /**
     * @return array<string, string>
     */
    private function individualPayload(): array
    {
        return [
            'name' => 'Test Customer',
            'email' => ' CUSTOMER@EXAMPLE.COM ',
            'password' => 'Customer-Password-123',
            'password_confirmation' =>
                'Customer-Password-123',
            'customer_type' => 'individual',
            'identity_number' => '001-290806-1000A',
            'phone' => '8888-1111',
        ];
    }
}