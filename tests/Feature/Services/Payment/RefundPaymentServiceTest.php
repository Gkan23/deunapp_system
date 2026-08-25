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
use App\Services\Payment\RefundPaymentService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_refunds_a_cash_payment_without_a_reference(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario();

        $originalPaidAt = $payment->paid_at;

        $refundedPayment = app(
            RefundPaymentService::class
        )->execute(
            $payment,
            $performedBy,
            'The customer requested a full refund.'
        );

        $this->assertSame(
            'REFUNDED',
            $refundedPayment->paymentStatus->status_name
        );

        $this->assertNull(
            $refundedPayment->refund_reference
        );

        $this->assertSame(
            'The customer requested a full refund.',
            $refundedPayment->refund_reason
        );

        $this->assertNotNull(
            $refundedPayment->refunded_at
        );

        $this->assertTrue(
            $originalPaidAt->equalTo(
                $refundedPayment->paid_at
            )
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
            'PAYMENT_REFUNDED',
            $auditLog->action_type
        );

        $this->assertSame(
            'PAID',
            $auditLog->details['from_status']
        );

        $this->assertSame(
            'REFUNDED',
            $auditLog->details['to_status']
        );

        $this->assertSame(
            '120.00',
            $auditLog->details['amount']
        );

        $this->assertSame(
            'The customer requested a full refund.',
            $auditLog->details['refund_reason']
        );
    }

    public function test_it_refunds_an_electronic_payment_with_a_reference(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            paymentMethod: 'CARD',
            paymentReference: 'ORIGINAL-CARD-001'
        );

        $refundedPayment = app(
            RefundPaymentService::class
        )->execute(
            $payment,
            $performedBy,
            'The electronic payment must be returned.',
            '  REFUND-CARD-001  '
        );

        $this->assertSame(
            'REFUND-CARD-001',
            $refundedPayment->refund_reference
        );

        $this->assertSame(
            'ORIGINAL-CARD-001',
            $refundedPayment->payment_reference
        );

        $this->assertNotNull(
            $refundedPayment->refunded_at
        );

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            'REFUND-CARD-001',
            $auditLog->details['refund_reference']
        );
    }

    public function test_it_requires_a_reference_for_an_electronic_refund(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            paymentMethod: 'BANK_TRANSFER',
            paymentReference: 'TRANSFER-001'
        );

        $this->assertDomainException(
            fn () => app(
                RefundPaymentService::class
            )->execute(
                $payment,
                $performedBy,
                'The bank transfer must be refunded.',
                '   '
            ),
            'A refund reference is required for this payment method.'
        );

        $this->assertPaymentWasNotRefunded($payment);
    }

    public function test_it_rejects_a_duplicated_refund_reference(): void
    {
        [
            $firstPayment,
            $firstDeliveryService,
            $firstPerformedBy,
        ] = $this->createPaymentScenario(
            paymentMethod: 'CARD',
            paymentReference: 'CARD-CHARGE-001'
        );

        $service = app(RefundPaymentService::class);

        $service->execute(
            $firstPayment,
            $firstPerformedBy,
            'First electronic refund.',
            'SHARED-REFUND-REFERENCE'
        );

        [
            $secondPayment,
            $secondDeliveryService,
            $secondPerformedBy,
        ] = $this->createPaymentScenario(
            paymentMethod: 'MOBILE_WALLET',
            paymentReference: 'WALLET-CHARGE-001'
        );

        $this->assertDomainException(
            fn () => $service->execute(
                $secondPayment,
                $secondPerformedBy,
                'Second electronic refund.',
                'SHARED-REFUND-REFERENCE'
            ),
            'The refund reference has already been used.'
        );

        $this->assertSame(
            'PAID',
            $secondPayment->fresh()
                ->paymentStatus
                ->status_name
        );

        $this->assertNull(
            $secondPayment->fresh()->refunded_at
        );

        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_it_rejects_a_refund_reference_used_as_a_payment_reference(): void
    {
        $this->createPaymentScenario(
            paymentMethod: 'CARD',
            paymentReference: 'EXISTING-TRANSACTION-001'
        );

        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            paymentMethod: 'CARD',
            paymentReference: 'SECOND-TRANSACTION-001'
        );

        $this->assertDomainException(
            fn () => app(
                RefundPaymentService::class
            )->execute(
                $payment,
                $performedBy,
                'Refund reference collision.',
                'EXISTING-TRANSACTION-001'
            ),
            'The refund reference has already been used.'
        );

        $this->assertPaymentWasNotRefunded($payment);
    }

    public function test_it_rejects_a_pending_payment(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            paymentStatus: 'PENDING',
            hasPaidAt: false
        );

        $this->assertDomainException(
            fn () => app(
                RefundPaymentService::class
            )->execute(
                $payment,
                $performedBy,
                'A pending payment cannot be refunded.'
            ),
            'Only a paid payment can be refunded.'
        );

        $this->assertPaymentStatus($payment, 'PENDING');
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_it_rejects_a_failed_payment(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            paymentStatus: 'FAILED',
            hasPaidAt: false
        );

        $this->assertDomainException(
            fn () => app(
                RefundPaymentService::class
            )->execute(
                $payment,
                $performedBy,
                'A failed payment cannot be refunded.'
            ),
            'Only a paid payment can be refunded.'
        );

        $this->assertPaymentStatus($payment, 'FAILED');
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_it_prevents_refunding_an_already_refunded_payment(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            paymentStatus: 'REFUNDED',
            paymentReference: 'ORIGINAL-REFUNDED-PAYMENT',
            refundReference: 'EXISTING-REFUND'
        );

        $this->assertDomainException(
            fn () => app(
                RefundPaymentService::class
            )->execute(
                $payment,
                $performedBy,
                'Duplicated refund.',
                'ANOTHER-REFUND'
            ),
            'Only a paid payment can be refunded.'
        );

        $this->assertPaymentStatus($payment, 'REFUNDED');

        $this->assertSame(
            'EXISTING-REFUND',
            $payment->fresh()->refund_reference
        );

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_it_rejects_a_paid_payment_without_a_payment_date(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            hasPaidAt: false
        );

        $this->assertDomainException(
            fn () => app(
                RefundPaymentService::class
            )->execute(
                $payment,
                $performedBy,
                'The payment date is missing.'
            ),
            'The payment does not have a valid payment date.'
        );

        $this->assertPaymentWasNotRefunded($payment);
    }

    public function test_it_requires_a_refund_reason(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario();

        $this->assertDomainException(
            fn () => app(
                RefundPaymentService::class
            )->execute(
                $payment,
                $performedBy,
                '   '
            ),
            'The refund reason is required.'
        );

        $this->assertPaymentWasNotRefunded($payment);
    }

    public function test_it_rejects_a_refund_reference_longer_than_150_characters(): void
    {
        [
            $payment,
            $deliveryService,
            $performedBy,
        ] = $this->createPaymentScenario(
            paymentMethod: 'CARD',
            paymentReference: 'CARD-LONG-REFERENCE-TEST'
        );

        $this->assertDomainException(
            fn () => app(
                RefundPaymentService::class
            )->execute(
                $payment,
                $performedBy,
                'Testing an invalid refund reference.',
                str_repeat('R', 151)
            ),
            'The refund reference may not exceed 150 characters.'
        );

        $this->assertPaymentWasNotRefunded($payment);
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
        string $paymentStatus = 'PAID',
        ?string $paymentReference = null,
        ?string $refundReference = null,
        bool $hasPaidAt = true
    ): array {
        $provider = DeliveryProvider::factory()->create();

        $trip = Trip::factory()->create([
            'delivery_provider_id' => $provider->id,
            'status' => 'USED',
            'used_at' => now()->subHours(2),
        ]);

        $deliveryService = DeliveryService::factory()->create([
            'trip_id' => $trip->id,
            'status' => 'COMPLETED',
            'accepted_at' => now()->subHours(3),
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
            'cancelled_at' => null,
            'delivery_fee' => 120.00,
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
            'amount' => 120.00,
            'payment_reference' => $paymentReference,
            'refund_reference' => $refundReference,
            'refund_reason' => $paymentStatus === 'REFUNDED'
                ? 'Previously refunded payment.'
                : null,
            'paid_at' => $hasPaidAt
                ? now()->subMinutes(30)
                : null,
            'refunded_at' => $paymentStatus === 'REFUNDED'
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

    private function assertPaymentWasNotRefunded(
        Payment $payment
    ): void {
        $freshPayment = $payment->fresh();

        $this->assertSame(
            'PAID',
            $freshPayment->paymentStatus->status_name
        );

        $this->assertNull(
            $freshPayment->refund_reference
        );

        $this->assertNull(
            $freshPayment->refund_reason
        );

        $this->assertNull(
            $freshPayment->refunded_at
        );

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

