<?php

namespace Tests\Feature\Http\Controllers;

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
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_access_payment_endpoints(): void
    {
        $customer = Customer::factory()->create();
        $payment = $this->createPayment($customer);

        /*
         * Estas son solicitudes JSON.
         *
         * Por eso Laravel responde 401 cuando no hay un usuario
         * autenticado, en lugar de intentar redirigir a una ruta
         * web llamada "login".
         */
        $this->getJson(
            route('payments.index')
        )->assertUnauthorized();

        $this->getJson(
            route('payments.show', $payment)
        )->assertUnauthorized();

        $this->patchJson(
            route('payments.confirm', $payment),
            []
        )->assertUnauthorized();

        $this->patchJson(
            route('payments.refund', $payment),
            [
                'reason' => 'Refund requested.',
            ]
        )->assertUnauthorized();
    }

    public function test_a_customer_only_lists_their_own_payments(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $ownPayment = $this->createPayment($customer);

        $this->createPayment($otherCustomer);

        $response = $this
            ->actingAs($customer->user)
            ->getJson(route('payments.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $ownPayment->id
            );
    }

    public function test_a_provider_only_lists_linked_payments(): void
    {
        $provider = DeliveryProvider::factory()->create();
        $otherProvider = DeliveryProvider::factory()->create();

        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $linkedPayment = $this->createPayment(
            $customer,
            $provider
        );

        $this->createPayment(
            $otherCustomer,
            $otherProvider
        );

        $response = $this
            ->actingAs($provider->user)
            ->getJson(route('payments.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $linkedPayment->id
            );
    }

    public function test_support_and_administration_list_all_payments(): void
    {
        $firstCustomer = Customer::factory()->create();
        $secondCustomer = Customer::factory()->create();

        $this->createPayment($firstCustomer);
        $this->createPayment($secondCustomer);

        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $this
            ->actingAs($supportAgent)
            ->getJson(route('payments.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this
            ->actingAs($administrator)
            ->getJson(route('payments.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $courier = Courier::factory()->create();

        $this
            ->actingAs($courier->user)
            ->getJson(route('payments.index'))
            ->assertForbidden();
    }

    public function test_payment_details_respect_the_policy_scope(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $provider = DeliveryProvider::factory()->create();
        $otherProvider = DeliveryProvider::factory()->create();

        $payment = $this->createPayment(
            $customer,
            $provider
        );

        /*
         * El cliente propietario puede ver el pago.
         */
        $this
            ->actingAs($customer->user)
            ->getJson(
                route('payments.show', $payment)
            )
            ->assertOk()
            ->assertJsonPath(
                'payment.id',
                $payment->id
            );

        /*
         * Otro cliente no puede consultar el pago.
         */
        $this
            ->actingAs($otherCustomer->user)
            ->getJson(
                route('payments.show', $payment)
            )
            ->assertForbidden();

        /*
         * El proveedor vinculado mediante el viaje puede verlo.
         */
        $this
            ->actingAs($provider->user)
            ->getJson(
                route('payments.show', $payment)
            )
            ->assertOk();

        /*
         * Un proveedor no relacionado no puede verlo.
         */
        $this
            ->actingAs($otherProvider->user)
            ->getJson(
                route('payments.show', $payment)
            )
            ->assertForbidden();

        /*
         * Soporte y administración pueden consultar cualquier pago.
         */
        $this
            ->actingAs(
                $this->userWithRole('SUPPORT_AGENT')
            )
            ->getJson(
                route('payments.show', $payment)
            )
            ->assertOk();

        $this
            ->actingAs(
                $this->userWithRole('ADMINISTRATOR')
            )
            ->getJson(
                route('payments.show', $payment)
            )
            ->assertOk();
    }

    public function test_a_customer_can_confirm_their_own_payment(): void
    {
        $customer = Customer::factory()->create();

        $payment = $this->createPayment(
            customer: $customer,
            paymentMethod: 'CARD'
        );

        $response = $this
            ->actingAs($customer->user)
            ->patchJson(
                route('payments.confirm', $payment),
                [
                    'payment_reference' => 'CARD-WEB-001',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Payment confirmed successfully.'
            )
            ->assertJsonPath(
                'payment.payment_reference',
                'CARD-WEB-001'
            )
            ->assertJsonPath(
                'payment.payment_status.status_name',
                'PAID'
            );

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_reference' => 'CARD-WEB-001',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'record_id' => $payment->id,
            'action_type' => 'PAYMENT_CONFIRMED',
        ]);
    }

    public function test_an_administrator_can_confirm_any_payment(): void
    {
        $customer = Customer::factory()->create();
        $payment = $this->createPayment($customer);

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $response = $this
            ->actingAs($administrator)
            ->patchJson(
                route('payments.confirm', $payment),
                []
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'payment.payment_status.status_name',
                'PAID'
            );

        $this->assertDatabaseHas('audit_logs', [
            'record_id' => $payment->id,
            'action_type' => 'PAYMENT_CONFIRMED',
        ]);
    }

    public function test_unauthorized_users_cannot_confirm_payments(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $provider = DeliveryProvider::factory()->create();

        $payment = $this->createPayment(
            $customer,
            $provider
        );

        /*
         * Un cliente diferente al propietario no puede confirmar.
         */
        $this
            ->actingAs($otherCustomer->user)
            ->patchJson(
                route('payments.confirm', $payment),
                []
            )
            ->assertForbidden();

        /*
         * El proveedor puede consultar el pago relacionado,
         * pero no confirmarlo.
         */
        $this
            ->actingAs($provider->user)
            ->patchJson(
                route('payments.confirm', $payment),
                []
            )
            ->assertForbidden();

        /*
         * Soporte puede consultar pagos, pero no confirmarlos.
         */
        $this
            ->actingAs(
                $this->userWithRole('SUPPORT_AGENT')
            )
            ->patchJson(
                route('payments.confirm', $payment),
                []
            )
            ->assertForbidden();

        $this->assertSame(
            'PENDING',
            $payment->fresh()
                ->paymentStatus
                ->status_name
        );

        $this->assertDatabaseMissing('audit_logs', [
            'record_id' => $payment->id,
            'action_type' => 'PAYMENT_CONFIRMED',
        ]);
    }

    public function test_an_administrator_can_refund_a_paid_payment(): void
    {
        $customer = Customer::factory()->create();

        $payment = $this->createPayment(
            customer: $customer,
            paymentMethod: 'CARD',
            paymentStatus: 'PAID'
        );

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $response = $this
            ->actingAs($administrator)
            ->patchJson(
                route('payments.refund', $payment),
                [
                    'reason' => 'The payment must be returned.',
                    'refund_reference' => 'REFUND-WEB-001',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Payment refunded successfully.'
            )
            ->assertJsonPath(
                'payment.refund_reference',
                'REFUND-WEB-001'
            )
            ->assertJsonPath(
                'payment.refund_reason',
                'The payment must be returned.'
            )
            ->assertJsonPath(
                'payment.payment_status.status_name',
                'REFUNDED'
            );

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'refund_reference' => 'REFUND-WEB-001',
            'refund_reason' => 'The payment must be returned.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'record_id' => $payment->id,
            'action_type' => 'PAYMENT_REFUNDED',
        ]);
    }

    public function test_non_administrators_cannot_refund_payments(): void
    {
        $customer = Customer::factory()->create();
        $provider = DeliveryProvider::factory()->create();

        $payment = $this->createPayment(
            customer: $customer,
            provider: $provider,
            paymentStatus: 'PAID'
        );

        $payload = [
            'reason' => 'Unauthorized refund attempt.',
        ];

        /*
         * Aunque sea propietario, el cliente no puede ejecutar
         * directamente un reembolso.
         */
        $this
            ->actingAs($customer->user)
            ->patchJson(
                route('payments.refund', $payment),
                $payload
            )
            ->assertForbidden();

        /*
         * El proveedor vinculado tampoco puede reembolsarlo.
         */
        $this
            ->actingAs($provider->user)
            ->patchJson(
                route('payments.refund', $payment),
                $payload
            )
            ->assertForbidden();

        /*
         * Soporte puede consultar, pero no alterar registros
         * financieros.
         */
        $this
            ->actingAs(
                $this->userWithRole('SUPPORT_AGENT')
            )
            ->patchJson(
                route('payments.refund', $payment),
                $payload
            )
            ->assertForbidden();

        $this->assertSame(
            'PAID',
            $payment->fresh()
                ->paymentStatus
                ->status_name
        );

        $this->assertDatabaseMissing('audit_logs', [
            'record_id' => $payment->id,
            'action_type' => 'PAYMENT_REFUNDED',
        ]);
    }

    public function test_payment_action_data_is_validated(): void
    {
        $customer = Customer::factory()->create();

        $pendingPayment = $this->createPayment(
            customer: $customer,
            paymentMethod: 'CARD'
        );

        /*
         * Las referencias no pueden superar 150 caracteres.
         */
        $this
            ->actingAs($customer->user)
            ->patchJson(
                route(
                    'payments.confirm',
                    $pendingPayment
                ),
                [
                    'payment_reference' => str_repeat(
                        'P',
                        151
                    ),
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'payment_reference',
            ]);

        $paidPayment = $this->createPayment(
            customer: $customer,
            paymentStatus: 'PAID'
        );

        /*
         * Un reembolso siempre necesita un motivo.
         */
        $this
            ->actingAs(
                $this->userWithRole('ADMINISTRATOR')
            )
            ->patchJson(
                route('payments.refund', $paidPayment),
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reason',
            ]);
    }

    public function test_domain_errors_are_returned_as_unprocessable(): void
    {
        $customer = Customer::factory()->create();

        /*
         * Un pago que ya está pagado no puede confirmarse otra vez.
         */
        $paidPayment = $this->createPayment(
            customer: $customer,
            paymentStatus: 'PAID'
        );

        $this
            ->actingAs($customer->user)
            ->patchJson(
                route(
                    'payments.confirm',
                    $paidPayment
                ),
                []
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Only a pending payment can be confirmed.'
            );

        /*
         * Un pago pendiente no puede reembolsarse.
         */
        $pendingPayment = $this->createPayment(
            customer: $customer
        );

        $this
            ->actingAs(
                $this->userWithRole('ADMINISTRATOR')
            )
            ->patchJson(
                route(
                    'payments.refund',
                    $pendingPayment
                ),
                [
                    'reason' => 'Invalid refund attempt.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Only a paid payment can be refunded.'
            );
    }

    private function createPayment(
        Customer $customer,
        ?DeliveryProvider $provider = null,
        string $paymentMethod = 'CASH',
        string $paymentStatus = 'PENDING'
    ): Payment {
        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $serviceAttributes = [
            'shipment_id' => $shipment->id,
            'status' => 'REQUESTED',
            'delivery_fee' => 120.00,
        ];

        if ($provider !== null) {
            $trip = Trip::factory()->create([
                'delivery_provider_id' => $provider->id,
                'status' => 'USED',
                'used_at' => now()->subHour(),
            ]);

            $serviceAttributes = array_merge(
                $serviceAttributes,
                [
                    'trip_id' => $trip->id,
                    'trip_type_id' => $trip->trip_type_id,
                    'status' => 'ASSIGNED',
                    'accepted_at' => now()->subHours(2),
                ]
            );
        }

        $deliveryService = DeliveryService::factory()->create(
            $serviceAttributes
        );

        $method = PaymentMethod::query()
            ->where('method_name', $paymentMethod)
            ->firstOrFail();

        $status = PaymentStatus::query()
            ->where('status_name', $paymentStatus)
            ->firstOrFail();

        $requiresOriginalReference = in_array(
            $paymentMethod,
            [
                'CARD',
                'BANK_TRANSFER',
                'MOBILE_WALLET',
            ],
            true
        ) && $paymentStatus === 'PAID';

        return Payment::query()->create([
            'delivery_service_id' => $deliveryService->id,
            'payment_method_id' => $method->id,
            'payment_status_id' => $status->id,
            'amount' => 120.00,
            'payment_reference' => $requiresOriginalReference
                ? 'ORIGINAL-PAYMENT-'.$deliveryService->id
                : null,
            'refund_reference' => null,
            'refund_reason' => null,
            'paid_at' => $paymentStatus === 'PAID'
                ? now()->subMinutes(30)
                : null,
            'refunded_at' => null,
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
}