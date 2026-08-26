<?php

namespace Tests\Feature\Services\Rating;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Rating;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\Trip;
use App\Services\Rating\CreateRatingService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateRatingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_creates_a_rating_atomically(): void
    {
        [
            $customer,
            $shipment,
            $deliveryService,
            $provider,
        ] = $this->createCompletedScenario();

        $rating = app(CreateRatingService::class)->execute(
            deliveryService: $deliveryService,
            customer: $customer,
            punctualityScore: 5,
            customerServiceScore: 4,
            packageConditionScore: 3,
            comment: '  The delivery service was satisfactory.  '
        );

        $this->assertSame(
            $deliveryService->id,
            $rating->delivery_service_id
        );

        $this->assertSame(
            $customer->id,
            $rating->customer_id
        );

        $this->assertSame(5, $rating->punctuality_score);
        $this->assertSame(4, $rating->customer_service_score);
        $this->assertSame(3, $rating->package_condition_score);
        $this->assertSame('4.00', $rating->overall_score);

        $this->assertSame(
            'The delivery service was satisfactory.',
            $rating->comment
        );

        $this->assertNotNull($rating->rated_at);
        $this->assertDatabaseCount('ratings', 1);
        $this->assertDatabaseCount('audit_logs', 1);

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            $customer->user_id,
            $auditLog->performed_by_user_id
        );

        $this->assertSame('ratings', $auditLog->table_name);
        $this->assertSame($rating->id, $auditLog->record_id);
        $this->assertSame('RATING_CREATED', $auditLog->action_type);

        $this->assertSame(
            $deliveryService->id,
            $auditLog->details['delivery_service_id']
        );

        $this->assertSame(
            $shipment->id,
            $auditLog->details['shipment_id']
        );

        $this->assertSame(
            $provider->id,
            $auditLog->details['delivery_provider_id']
        );

        $this->assertSame(
            '4.00',
            $auditLog->details['overall_score']
        );
    }

    public function test_it_rounds_the_overall_score_to_two_decimals(): void
    {
        [
            $customer,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $rating = app(CreateRatingService::class)->execute(
            $deliveryService,
            $customer,
            5,
            5,
            4
        );

        $this->assertSame(
            '4.67',
            $rating->overall_score
        );

        $this->assertNull($rating->comment);
    }

    public function test_it_rejects_a_customer_who_does_not_own_the_service(): void
    {
        [
            $customer,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $otherCustomer = Customer::factory()->create();

        $this->assertDomainException(
            fn () => app(CreateRatingService::class)->execute(
                $deliveryService,
                $otherCustomer,
                5,
                5,
                5
            ),
            'Only the customer who owns the delivery service can rate it.'
        );

        $this->assertRatingWasNotCreated();
    }

    public function test_it_rejects_a_service_that_is_not_completed(): void
    {
        [
            $customer,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $deliveryService->update([
            'status' => 'IN_PROGRESS',
            'completed_at' => null,
        ]);

        $this->assertDomainException(
            fn () => app(CreateRatingService::class)->execute(
                $deliveryService,
                $customer,
                5,
                5,
                5
            ),
            'Only a completed delivery service can be rated.'
        );

        $this->assertRatingWasNotCreated();
    }

    public function test_it_rejects_a_completed_service_without_a_completion_date(): void
    {
        [
            $customer,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $deliveryService->update([
            'completed_at' => null,
        ]);

        $this->assertDomainException(
            fn () => app(CreateRatingService::class)->execute(
                $deliveryService,
                $customer,
                5,
                5,
                5
            ),
            'The completed delivery service does not have a completion date.'
        );

        $this->assertRatingWasNotCreated();
    }

    public function test_it_rejects_a_completed_service_without_an_assigned_trip(): void
    {
        [
            $customer,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $deliveryService->update([
            'trip_id' => null,
        ]);

        $this->assertDomainException(
            fn () => app(CreateRatingService::class)->execute(
                $deliveryService,
                $customer,
                5,
                5,
                5
            ),
            'A completed delivery service must have an assigned trip before it can be rated.'
        );

        $this->assertRatingWasNotCreated();
    }

    public function test_it_rejects_a_shipment_that_is_not_delivered(): void
    {
        [
            $customer,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $shipment->update([
            'shipment_status_id' => $this
                ->findShipmentStatus('IN_TRANSIT')
                ->id,
            'delivered_at' => null,
        ]);

        $this->assertDomainException(
            fn () => app(CreateRatingService::class)->execute(
                $deliveryService,
                $customer,
                5,
                5,
                5
            ),
            'Only a delivered shipment can be rated.'
        );

        $this->assertRatingWasNotCreated();
    }

    public function test_it_rejects_a_delivered_shipment_without_a_delivery_date(): void
    {
        [
            $customer,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $shipment->update([
            'delivered_at' => null,
        ]);

        $this->assertDomainException(
            fn () => app(CreateRatingService::class)->execute(
                $deliveryService,
                $customer,
                5,
                5,
                5
            ),
            'The delivered shipment does not have a delivery date.'
        );

        $this->assertRatingWasNotCreated();
    }

    public function test_it_rejects_a_score_lower_than_one(): void
    {
        [
            $customer,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $this->assertDomainException(
            fn () => app(CreateRatingService::class)->execute(
                $deliveryService,
                $customer,
                0,
                5,
                5
            ),
            'Every rating score must be between 1 and 5.'
        );

        $this->assertRatingWasNotCreated();
    }

    public function test_it_rejects_a_score_greater_than_five(): void
    {
        [
            $customer,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $this->assertDomainException(
            fn () => app(CreateRatingService::class)->execute(
                $deliveryService,
                $customer,
                5,
                6,
                5
            ),
            'Every rating score must be between 1 and 5.'
        );

        $this->assertRatingWasNotCreated();
    }

    public function test_it_prevents_duplicate_ratings(): void
    {
        [
            $customer,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $service = app(CreateRatingService::class);

        $service->execute(
            $deliveryService,
            $customer,
            5,
            5,
            5,
            'First rating.'
        );

        $this->assertDomainException(
            fn () => $service->execute(
                $deliveryService,
                $customer,
                1,
                1,
                1,
                'Duplicated rating.'
            ),
            'The delivery service has already been rated.'
        );

        $this->assertDatabaseCount('ratings', 1);
        $this->assertDatabaseCount('audit_logs', 1);

        $rating = Rating::query()->firstOrFail();

        $this->assertSame(5, $rating->punctuality_score);
        $this->assertSame('5.00', $rating->overall_score);
        $this->assertSame('First rating.', $rating->comment);
    }

    /**
     * @return array{
     *     0: Customer,
     *     1: Shipment,
     *     2: DeliveryService,
     *     3: DeliveryProvider
     * }
     */
    private function createCompletedScenario(): array
    {
        $customer = Customer::factory()->create();
        $provider = DeliveryProvider::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
            'shipment_status_id' => $this
                ->findShipmentStatus('DELIVERED')
                ->id,
            'delivered_at' => now()->subMinutes(30),
        ]);

        $trip = Trip::factory()->create([
            'delivery_provider_id' => $provider->id,
            'status' => 'USED',
            'used_at' => now()->subHours(2),
        ]);

        $deliveryService = DeliveryService::factory()->create([
            'shipment_id' => $shipment->id,
            'customer_id' => $customer->id,
            'trip_id' => $trip->id,
            'status' => 'COMPLETED',
            'accepted_at' => now()->subHours(3),
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subMinutes(30),
            'cancelled_at' => null,
            'delivery_fee' => 120.00,
        ]);

        return [
            $customer,
            $shipment,
            $deliveryService,
            $provider,
        ];
    }

    private function findShipmentStatus(
        string $statusName
    ): ShipmentStatus {
        return ShipmentStatus::query()
            ->where('status_name', $statusName)
            ->firstOrFail();
    }

    private function assertRatingWasNotCreated(): void
    {
        $this->assertDatabaseCount('ratings', 0);
        $this->assertDatabaseCount('audit_logs', 0);
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
