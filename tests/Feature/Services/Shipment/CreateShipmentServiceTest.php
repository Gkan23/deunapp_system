<?php

namespace Tests\Feature\Services\Shipment;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Shipment;
use App\Services\Shipment\CreateShipmentService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateShipmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private CreateShipmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);

        $this->service = app(
            CreateShipmentService::class
        );
    }

    public function test_it_creates_a_requested_shipment_for_the_customer(): void
    {
        $customer = Customer::factory()->create();

        $shipment = $this->service->handle(
            $customer,
            $this->validData()
        );

        $this->assertSame(
            $customer->id,
            $shipment->customer_id
        );

        $this->assertSame(
            'REQUESTED',
            $shipment->shipmentStatus->status_name
        );

        $this->assertNotNull($shipment->requested_at);
        $this->assertNull($shipment->delivered_at);

        $this->assertStringStartsWith(
            'DEU-',
            $shipment->tracking_code
        );

        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'customer_id' => $customer->id,
            'shipment_status_id' =>
                $shipment->shipment_status_id,
        ]);
    }

    public function test_it_creates_all_packages_for_the_shipment(): void
    {
        $customer = Customer::factory()->create();
        $data = $this->validData();

        $data['packages'][] = [
            'weight' => 1.25,
            'height' => 10,
            'width' => 8,
            'length' => 15,
            'content_description' =>
                'Electronic cables',
            'is_fragile' => false,
            'declared_value' => 50,
        ];

        $shipment = $this->service->handle(
            $customer,
            $data
        );

        $this->assertCount(2, $shipment->packages);

        $this->assertDatabaseHas('packages', [
            'shipment_id' => $shipment->id,
            'content_description' =>
                'Books and documents',
            'is_fragile' => false,
        ]);

        $this->assertDatabaseHas('packages', [
            'shipment_id' => $shipment->id,
            'content_description' =>
                'Electronic cables',
            'is_fragile' => false,
        ]);
    }

    public function test_it_creates_the_initial_status_history(): void
    {
        $customer = Customer::factory()->create();

        $shipment = $this->service->handle(
            $customer,
            $this->validData()
        );

        $history = $shipment->statusHistory()
            ->firstOrFail();

        $this->assertSame(
            $shipment->shipment_status_id,
            $history->shipment_status_id
        );

        $this->assertSame(
            $customer->user_id,
            $history->changed_by_user_id
        );

        $this->assertSame(
            'Shipment requested.',
            $history->comment
        );

        $this->assertNotNull($history->changed_at);

        $this->assertDatabaseHas(
            'shipment_status_history',
            [
                'shipment_id' => $shipment->id,
                'shipment_status_id' =>
                    $shipment->shipment_status_id,
                'changed_by_user_id' =>
                    $customer->user_id,
            ]
        );
    }

    public function test_tracking_codes_are_unique(): void
    {
        $customer = Customer::factory()->create();
        $data = $this->validData();

        $firstShipment = $this->service->handle(
            $customer,
            $data
        );

        $secondShipment = $this->service->handle(
            $customer,
            $data
        );

        $this->assertNotSame(
            $firstShipment->tracking_code,
            $secondShipment->tracking_code
        );

        $this->assertDatabaseHas('shipments', [
            'tracking_code' =>
                $firstShipment->tracking_code,
        ]);

        $this->assertDatabaseHas('shipments', [
            'tracking_code' =>
                $secondShipment->tracking_code,
        ]);
    }

    public function test_it_rejects_shipments_without_packages(): void
    {
        $customer = Customer::factory()->create();
        $data = $this->validData();
        $data['packages'] = [];

        $shipmentCount = Shipment::query()->count();

        $this->assertDomainException(
            fn () => $this->service->handle(
                $customer,
                $data
            ),
            'A shipment must contain at least one package.'
        );

        $this->assertDatabaseCount(
            'shipments',
            $shipmentCount
        );
    }

    public function test_it_rejects_equal_people_and_addresses(): void
    {
        $customer = Customer::factory()->create();

        $samePeople = $this->validData();
        $samePeople['recipient_id'] =
            $samePeople['sender_id'];

        $this->assertDomainException(
            fn () => $this->service->handle(
                $customer,
                $samePeople
            ),
            'The sender and recipient must be different.'
        );

        $sameAddresses = $this->validData();
        $sameAddresses['destination_address_id'] =
            $sameAddresses['origin_address_id'];

        $this->assertDomainException(
            fn () => $this->service->handle(
                $customer,
                $sameAddresses
            ),
            'The origin and destination addresses must be different.'
        );
    }

    public function test_it_rejects_invalid_branches(): void
    {
        $customer = Customer::factory()->create();
        $data = $this->validData();

        $inactiveBranch = Branch::query()->create([
            'address_id' => $data['origin_address_id'],
            'branch_name' => 'Inactive branch',
            'phone' => null,
            'email' => null,
            'is_active' => false,
        ]);

        $data['origin_branch_id'] =
            $inactiveBranch->id;

        $this->assertDomainException(
            fn () => $this->service->handle(
                $customer,
                $data
            ),
            'The origin branch must be active.'
        );

        $mismatchedData = $this->validData();

        $mismatchedBranch = Branch::query()->create([
            'address_id' =>
                $mismatchedData['destination_address_id'],
            'branch_name' => 'Mismatched branch',
            'phone' => null,
            'email' => null,
            'is_active' => true,
        ]);

        $mismatchedData['origin_branch_id'] =
            $mismatchedBranch->id;

        $this->assertDomainException(
            fn () => $this->service->handle(
                $customer,
                $mismatchedData
            ),
            'The origin branch must belong to the origin address.'
        );
    }

    public function test_it_preserves_optional_shipment_information(): void
    {
        $customer = Customer::factory()->create();
        $data = $this->validData();

        $scheduledAt = now()
            ->addDays(2)
            ->startOfSecond();

        $data['scheduled_at'] =
            $scheduledAt->toDateTimeString();

        $data['declared_value'] = 450.75;

        $data['delivery_instructions'] =
            'Call before arriving.';

        $data['notes'] =
            'The recipient is available after midday.';

        $shipment = $this->service->handle(
            $customer,
            $data
        );

        $this->assertTrue(
            $shipment->scheduled_at->equalTo(
                $scheduledAt
            )
        );

        $this->assertEquals(
            450.75,
            $shipment->declared_value
        );

        $this->assertSame(
            'Call before arriving.',
            $shipment->delivery_instructions
        );

        $this->assertSame(
            'The recipient is available after midday.',
            $shipment->notes
        );
    }

    /**
     * Genera datos válidos utilizando las relaciones
     * creadas por ShipmentFactory.
     *
     * @return array<string, mixed>
     */
    private function validData(): array
    {
        $referenceShipment = Shipment::factory()->create();

        return [
            'sender_id' =>
                $referenceShipment->sender_id,

            'recipient_id' =>
                $referenceShipment->recipient_id,

            'origin_address_id' =>
                $referenceShipment->origin_address_id,

            'destination_address_id' =>
                $referenceShipment
                    ->destination_address_id,

            'origin_branch_id' => null,
            'destination_branch_id' => null,
            'scheduled_at' => null,
            'declared_value' => 150.50,

            'delivery_instructions' =>
                'Call the recipient before delivery.',

            'notes' => null,

            'packages' => [
                [
                    'weight' => 2.50,
                    'height' => 20,
                    'width' => 15,
                    'length' => 30,
                    'content_description' =>
                        'Books and documents',
                    'is_fragile' => false,
                    'declared_value' => 150.50,
                ],
            ],
        ];
    }

    private function assertDomainException(
        Closure $callback,
        string $message
    ): void {
        try {
            $callback();

            $this->fail(
                'A DomainException was not thrown.'
            );
        } catch (DomainException $exception) {
            $this->assertSame(
                $message,
                $exception->getMessage()
            );
        }
    }
}
