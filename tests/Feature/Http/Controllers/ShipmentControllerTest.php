<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\Shipment;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_create_shipments(): void
    {
        $response = $this->postJson(
            route('shipments.store'),
            $this->validData()
        );

        $response->assertUnauthorized();
    }

    public function test_a_non_customer_cannot_create_shipments(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $response = $this
            ->actingAs($provider->user)
            ->postJson(
                route('shipments.store'),
                $this->validData()
            );

        $response->assertForbidden();
    }

    public function test_a_customer_can_create_a_shipment(): void
    {
        $customer = Customer::factory()->create();

        $response = $this
            ->actingAs($customer->user)
            ->postJson(
                route('shipments.store'),
                $this->validData()
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Shipment created successfully.'
            )
            ->assertJsonPath(
                'shipment.customer_id',
                $customer->id
            )
            ->assertJsonPath(
                'shipment.shipment_status.status_name',
                'REQUESTED'
            )
            ->assertJsonCount(
                1,
                'shipment.packages'
            );

        $shipmentId = $response->json(
            'shipment.id'
        );

        $this->assertDatabaseHas('shipments', [
            'id' => $shipmentId,
            'customer_id' => $customer->id,
        ]);

        $this->assertDatabaseHas(
            'shipment_status_history',
            [
                'shipment_id' => $shipmentId,
                'changed_by_user_id' =>
                    $customer->user_id,
            ]
        );
    }

    public function test_invalid_shipment_data_is_rejected(): void
    {
        $customer = Customer::factory()->create();
        $data = $this->validData();
        $data['packages'] = [];

        $shipmentCount = Shipment::query()->count();

        $response = $this
            ->actingAs($customer->user)
            ->postJson(
                route('shipments.store'),
                $data
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'packages',
            ]);

        /*
         * Solamente debe existir el envío utilizado por
         * validData() como fuente de relaciones.
         */
        $this->assertDatabaseCount(
            'shipments',
            $shipmentCount
        );
    }

    public function test_customer_id_cannot_be_spoofed(): void
    {
        $authenticatedCustomer =
            Customer::factory()->create();

        $otherCustomer =
            Customer::factory()->create();

        $data = $this->validData();

        /*
         * Este campo malicioso será eliminado por
         * StoreShipmentRequest::validated().
         */
        $data['customer_id'] = $otherCustomer->id;

        $response = $this
            ->actingAs($authenticatedCustomer->user)
            ->postJson(
                route('shipments.store'),
                $data
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'shipment.customer_id',
                $authenticatedCustomer->id
            );

        $shipmentId = $response->json(
            'shipment.id'
        );

        $this->assertDatabaseHas('shipments', [
            'id' => $shipmentId,
            'customer_id' =>
                $authenticatedCustomer->id,
        ]);

        $this->assertDatabaseMissing('shipments', [
            'id' => $shipmentId,
            'customer_id' => $otherCustomer->id,
        ]);
    }

    /**
     * Utiliza ShipmentFactory para obtener personas y
     * direcciones existentes en la base de pruebas.
     *
     * @return array<string, mixed>
     */
    private function validData(): array
    {
        $referenceShipment =
            Shipment::factory()->create();

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

            'scheduled_at' => now()
                ->addDay()
                ->toDateTimeString(),

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
}

