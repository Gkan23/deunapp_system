<?php

namespace Tests\Feature\Services\Payment;

use App\Models\AuditLog;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentStatus;
use App\Models\Trip;
use App\Models\User;
use App\Services\Payment\ConfirmPaymentService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_confirms_a_cash_payment_without_a_reference(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            paymentMethod: 'CASH'
        );

        $confirmedPayment = app(
            ConfirmPaymentService::class
        )->execute(
            $payment,
            $performedBy
        );

        $this->assertSame(
            'PAID',
            $confirmedPayment->paymentStatus->status_name
        );

        $this->assertNull(
            $confirmedPayment->payment_reference
        );

        $this->assertNotNull(
            $confirmedPayment->paid_at
        );

        $this->assertSame(
            $deliveryService->id,
            $confirmedPayment->delivery_service_id
        );

        $this->assertDatabaseCount('audit_logs', 1);

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            $performedBy->id,
            $auditLog->performed_by_user_id
        );

        $this->assertSame('payments', $auditLog->table_name);
        $this->assertSame($payment->id, $auditLog->record_id);

        $this->assertSame(
            'PAYMENT_CONFIRMED',
            $auditLog->action_type
        );

        $this->assertSame(
            'PENDING',
            $auditLog->details['from_status']
        );

        $this->assertSame(
            'PAID',
            $auditLog->details['to_status']
        );

        $this->assertSame(
            '120.00',
            $auditLog->details['amount']
        );

        $this->assertNull(
            $auditLog->details['payment_reference']
        );
    }

    public function test_it_confirms_an_electronic_payment_with_a_reference(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            paymentMethod: 'CARD'
        );

        $confirmedPayment = app(
            ConfirmPaymentService::class
        )->execute(
            $payment,
            $performedBy,
            '  CARD-PAYMENT-001  '
        );

        $this->assertSame(
            'PAID',
            $confirmedPayment->paymentStatus->status_name
        );

        $this->assertSame(
            'CARD-PAYMENT-001',
            $confirmedPayment->payment_reference
        );

        $this->assertNotNull(
            $confirmedPayment->paid_at
        );

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_reference' => 'CARD-PAYMENT-001',
        ]);

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            'CARD',
            $auditLog->details['payment_method']
        );

        $this->assertSame(
            'CARD-PAYMENT-001',
            $auditLog->details['payment_reference']
        );
    }

    public function test_it_requires_a_reference_for_an_electronic_payment(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            paymentMethod: 'BANK_TRANSFER'
        );

        $this->assertDomainException(
            fn () => app(
                ConfirmPaymentService::class
            )->execute(
                $payment,
                $performedBy,
                '   '
            ),
            'A payment reference is required for this payment method.'
        );

        $this->assertPaymentWasNotConfirmed($payment);
    }

    public function test_it_rejects_a_duplicated_payment_reference(): void
    {
        [
            $firstPayment,
            $firstDeliveryService,
            $firstPerformedBy,
        ] = $this->createPaymentScenario(
            paymentMethod: 'CARD'
        );

        $service = app(ConfirmPaymentService::class);

        $service->execute(
            $firstPayment,
            $firstPerformedBy,
            'SHARED-PAYMENT-REFERENCE'
        );

        [
            $secondPayment,
            $secondDeliveryService,
            $secondPerformedBy,
        ] = $this->createPaymentScenario(
            paymentMethod: 'MOBILE_WALLET'
        );

        $this->assertDomainException(
            fn () => $service->execute(
                $secondPayment,
                $secondPerformedBy,
                'SHARED-PAYMENT-REFERENCE'
            ),
            'The payment reference has already been used.'
        );

        $this->assertSame(
            'PENDING',
            $secondPayment->fresh()
                ->paymentStatus
                ->status_name
        );

        $this->assertNull(
            $secondPayment->fresh()->paid_at
        );

        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_it_rejects_an_already_paid_payment(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            paymentMethod: 'CARD',
            paymentStatus: 'PAID',
            reference: 'ALREADY-PAID-001'
        );

        $this->assertDomainException(
            fn () => app(
                ConfirmPaymentService::class
            )->execute(
                $payment,
                $performedBy,
                'NEW-REFERENCE'
            ),
            'Only a pending payment can be confirmed.'
        );

        $this->assertSame(
            'ALREADY-PAID-001',
            $payment->fresh()->payment_reference
        );

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_it_rejects_a_failed_payment(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            paymentStatus: 'FAILED'
        );

        $this->assertDomainException(
            fn () => app(
                ConfirmPaymentService::class
            )->execute(
                $payment,
                $performedBy
            ),
            'Only a pending payment can be confirmed.'
        );

        $this->assertPaymentStatus($payment, 'FAILED');
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_it_rejects_a_refunded_payment(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            paymentStatus: 'REFUNDED'
        );

        $this->assertDomainException(
            fn () => app(
                ConfirmPaymentService::class
            )->execute(
                $payment,
                $performedBy
            ),
            'Only a pending payment can be confirmed.'
        );

        $this->assertPaymentStatus($payment, 'REFUNDED');
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_it_rejects_an_incorrect_payment_amount(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            amount: 100.00,
            deliveryFee: 120.00
        );

        $this->assertDomainException(
            fn () => app(
                ConfirmPaymentService::class
            )->execute(
                $payment,
                $performedBy
            ),
            'The payment amount does not match the delivery service fee.'
        );

        $this->assertPaymentWasNotConfirmed($payment);
    }

    public function test_it_rejects_a_service_without_a_delivery_fee(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            amount: 120.00,
            deliveryFee: null
        );

        $this->assertDomainException(
            fn () => app(
                ConfirmPaymentService::class
            )->execute(
                $payment,
                $performedBy
            ),
            'The delivery service fee must be defined before confirming the payment.'
        );

        $this->assertPaymentWasNotConfirmed($payment);
    }

    public function test_it_rejects_a_payment_for_a_cancelled_service(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            serviceStatus: 'CANCELLED'
        );

        $this->assertDomainException(
            fn () => app(
                ConfirmPaymentService::class
            )->execute(
                $payment,
                $performedBy
            ),
            'A payment for a cancelled delivery service cannot be confirmed.'
        );

        $this->assertPaymentWasNotConfirmed($payment);
    }

    /**
     * @return array{
     *     0: Payment,
     *     1: DeliveryService,
     *     2: User
     * }
     */
    private function createPaymentScenario(
        string $paymentMethod = 'CASH',
        string $paymentStatus = 'PENDING',
        string $serviceStatus = 'ASSIGNED',
        float $amount = 120.00,
        ?float $deliveryFee = 120.00,
        ?string $reference = null
    ): array {
        $provider = DeliveryProvider::factory()->create();

        $trip = Trip::factory()->create([
            'delivery_provider_id' => $provider->id,
            'status' => 'USED',
            'used_at' => now()->subHour(),
        ]);

        $deliveryService = DeliveryService::factory()->create([
            'trip_id' => $trip->id,
            'status' => $serviceStatus,
            'accepted_at' => now()->subHours(2),
            'started_at' => $serviceStatus === 'IN_PROGRESS'
                ? now()->subHour()
                : null,
            'completed_at' => $serviceStatus === 'COMPLETED'
                ? now()->subMinutes(15)
                : null,
            'cancelled_at' => $serviceStatus === 'CANCELLED'
                ? now()->subMinutes(15)
                : null,
            'delivery_fee' => $deliveryFee,
        ]);

        $payment = Payment::query()->create([
            'delivery_service_id' => $deliveryService->id,
            'payment_method_id' => PaymentMethod::query()
                ->where('method_name', $paymentMethod)
                ->firstOrFail()
                ->id,
            'payment_status_id' => $this
                ->findPaymentStatus($paymentStatus)
                ->id,
            'amount' => $amount,
            'payment_reference' => $reference,
            'paid_at' => $paymentStatus === 'PAID'
                ? now()->subMinutes(10)
                : null,
        ]);

        return [
            $payment,
            $deliveryService,
            User::factory()->create(),
        ];
    }

    private function findPaymentStatus(
        string $statusName
    ): PaymentStatus {
        return PaymentStatus::query()
            ->where('status_name', $statusName)
            ->firstOrFail();
    }

    private function assertPaymentWasNotConfirmed(
        Payment $payment
    ): void {
        $freshPayment = $payment->fresh();

        $this->assertSame(
            'PENDING',
            $freshPayment->paymentStatus->status_name
        );

        $this->assertNull($freshPayment->paid_at);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function assertPaymentStatus(
        Payment $payment,
        string $expectedStatus
    ): void {
        $this->assertSame(
            $expectedStatus,
            $payment->fresh()->paymentStatus->status_name
        );
    }

    private function assertDomainException(
        Closure $callback,
        string $expectedMessage
    ): void {
        try {
            $callback();

            $this->fail('A DomainException was expected.');
        } catch (DomainException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );
        }
    }
}

