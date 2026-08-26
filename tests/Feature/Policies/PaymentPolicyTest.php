<?php

namespace Tests\Feature\Policies;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentStatus;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PaymentPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_supported_roles_can_access_the_payment_list(): void
    {
        $customer = Customer::factory()->create()->user;
        $provider = DeliveryProvider::factory()->create()->user;
        $supportAgent = $this->userWithRole('SUPPORT_AGENT');
        $administrator = $this->userWithRole('ADMINISTRATOR');
        $courier = Courier::factory()->create()->user;

        foreach ([
            $customer,
            $provider,
            $supportAgent,
            $administrator,
        ] as $user) {
            $this->assertTrue(
                $this->allows(
                    $user,
                    'viewAny',
                    Payment::class
                )
            );
        }

        $this->assertFalse(
            $this->allows(
                $courier,
                'viewAny',
                Payment::class
            )
        );
    }

    public function test_an_inactive_user_cannot_access_payments(): void
    {
        $customer = Customer::factory()->create();
        $payment = $this->paymentFor($customer);

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $customer->user->update([
            'account_status_id' => $suspendedStatus->id,
        ]);

        $user = $customer->user->fresh();

        $this->assertFalse(
            $this->allows($user, 'view', $payment)
        );

        $this->assertFalse(
            $this->allows($user, 'confirm', $payment)
        );
    }

    public function test_a_customer_can_view_their_own_payment(): void
    {
        $customer = Customer::factory()->create();
        $payment = $this->paymentFor($customer);

        $this->assertTrue(
            $this->allows(
                $customer->user,
                'view',
                $payment
            )
        );
    }

    public function test_a_customer_cannot_view_another_customers_payment(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $payment = $this->paymentFor($otherCustomer);

        $this->assertFalse(
            $this->allows(
                $customer->user,
                'view',
                $payment
            )
        );
    }

    public function test_the_customer_can_confirm_but_cannot_refund_their_payment(): void
    {
        $customer = Customer::factory()->create();
        $payment = $this->paymentFor($customer);
        $user = $customer->user;

        $this->assertTrue(
            $this->allows($user, 'confirm', $payment)
        );

        $this->assertFalse(
            $this->allows($user, 'refund', $payment)
        );
    }

    public function test_the_linked_provider_can_only_view_the_payment(): void
    {
        $customer = Customer::factory()->create();
        $provider = DeliveryProvider::factory()->create();

        $payment = $this->paymentFor(
            $customer,
            $provider
        );

        $user = $provider->user;

        $this->assertTrue(
            $this->allows($user, 'view', $payment)
        );

        $this->assertFalse(
            $this->allows($user, 'confirm', $payment)
        );

        $this->assertFalse(
            $this->allows($user, 'refund', $payment)
        );
    }

    public function test_an_unrelated_provider_cannot_view_the_payment(): void
    {
        $customer = Customer::factory()->create();
        $linkedProvider = DeliveryProvider::factory()->create();
        $unrelatedProvider = DeliveryProvider::factory()->create();

        $payment = $this->paymentFor(
            $customer,
            $linkedProvider
        );

        $this->assertFalse(
            $this->allows(
                $unrelatedProvider->user,
                'view',
                $payment
            )
        );
    }

    public function test_a_support_agent_can_only_view_payments(): void
    {
        $supportAgent = $this->userWithRole('SUPPORT_AGENT');

        $payment = $this->paymentFor(
            Customer::factory()->create()
        );

        $this->assertTrue(
            $this->allows($supportAgent, 'view', $payment)
        );

        $this->assertFalse(
            $this->allows($supportAgent, 'confirm', $payment)
        );

        $this->assertFalse(
            $this->allows($supportAgent, 'refund', $payment)
        );
    }

    public function test_an_administrator_can_confirm_and_refund_payments(): void
    {
        $administrator = $this->userWithRole('ADMINISTRATOR');

        $payment = $this->paymentFor(
            Customer::factory()->create()
        );

        $this->assertTrue(
            $this->allows(
                $administrator,
                'view',
                $payment
            )
        );

        $this->assertTrue(
            $this->allows(
                $administrator,
                'confirm',
                $payment
            )
        );

        $this->assertTrue(
            $this->allows(
                $administrator,
                'refund',
                $payment
            )
        );
    }

    public function test_direct_creation_update_and_deletion_are_denied(): void
    {
        $customer = Customer::factory()->create();
        $payment = $this->paymentFor($customer);
        $user = $customer->user;

        $this->assertFalse(
            $this->allows(
                $user,
                'create',
                Payment::class
            )
        );

        foreach ([
            'update',
            'delete',
            'restore',
            'forceDelete',
        ] as $ability) {
            $this->assertFalse(
                $this->allows($user, $ability, $payment)
            );
        }
    }

    private function paymentFor(
        Customer $customer,
        ?DeliveryProvider $provider = null
    ): Payment {
        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $serviceAttributes = [
            'shipment_id' => $shipment->id,
            'status' => 'REQUESTED',
            'delivery_fee' => 100,
        ];

        if ($provider !== null) {
            $trip = Trip::factory()->create([
                'delivery_provider_id' => $provider->id,
                'status' => 'USED',
                'used_at' => now(),
            ]);

            $serviceAttributes = array_merge(
                $serviceAttributes,
                [
                    'trip_id' => $trip->id,
                    'trip_type_id' => $trip->trip_type_id,
                    'status' => 'ASSIGNED',
                    'accepted_at' => now(),
                ]
            );
        }

        $service = DeliveryService::factory()->create(
            $serviceAttributes
        );

        $paymentMethod = PaymentMethod::query()
            ->where('method_name', 'CARD')
            ->firstOrFail();

        $paymentStatus = PaymentStatus::query()
            ->where('status_name', 'PENDING')
            ->firstOrFail();

        return Payment::query()->create([
            'delivery_service_id' => $service->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_status_id' => $paymentStatus->id,
            'amount' => 100,
            'payment_reference' => null,
            'paid_at' => null,
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::query()
            ->where('role_name', $roleName)
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }

    private function allows(
        User $user,
        string $ability,
        mixed $arguments
    ): bool {
        return Gate::forUser($user)->allows(
            $ability,
            $arguments
        );
    }
}

