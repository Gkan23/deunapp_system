<?php

namespace Tests\Feature\Http\Requests;

use App\Http\Requests\StoreShipmentRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\Shipment;
use Database\Seeders\CatalogSeeder;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreShipmentRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_only_an_authorized_customer_can_submit_the_request(): void
    {
        $customer = Customer::factory()->create();
        $provider = DeliveryProvider::factory()->create();

        $customerRequest = new StoreShipmentRequest();

        $customerRequest->setUserResolver(
            fn () => $customer->user
        );

        $providerRequest = new StoreShipmentRequest();

        $providerRequest->setUserResolver(
            fn () => $provider->user
        );

        $this->assertTrue(
            $customerRequest->authorize()
        );

        $this->assertFalse(
            $providerRequest->authorize()
        );
    }

    public function test_valid_shipment_data_passes_validation(): void
    {
        $validator = $this->validator(
            $this->validData()
        );

        $this->assertFalse(
            $validator->fails(),
            implode(', ', $validator->errors()->all())
        );
    }

    public function test_main_shipment_fields_are_required(): void
    {
        $validator = $this->validator([]);

        $this->assertTrue($validator->fails());

        $errors = $validator->errors()->toArray();

        $this->assertArrayHasKey('sender_id', $errors);
        $this->assertArrayHasKey('recipient_id', $errors);
        $this->assertArrayHasKey(
            'origin_address_id',
            $errors
        );
        $this->assertArrayHasKey(
            'destination_address_id',
            $errors
        );
        $this->assertArrayHasKey('packages', $errors);
    }

    public function test_sender_recipient_and_addresses_must_be_different(): void
    {
        $data = $this->validData();

        $data['recipient_id'] = $data['sender_id'];
        $data['destination_address_id'] =
            $data['origin_address_id'];

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());

        $errors = $validator->errors()->toArray();

        $this->assertArrayHasKey('recipient_id', $errors);
        $this->assertArrayHasKey(
            'destination_address_id',
            $errors
        );
    }

    public function test_related_identifiers_must_exist(): void
    {
        $data = $this->validData();

        $data['sender_id'] = 999999;
        $data['recipient_id'] = 999998;
        $data['origin_address_id'] = 999997;
        $data['destination_address_id'] = 999996;

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());

        $errors = $validator->errors()->toArray();

        $this->assertArrayHasKey('sender_id', $errors);
        $this->assertArrayHasKey('recipient_id', $errors);
        $this->assertArrayHasKey(
            'origin_address_id',
            $errors
        );
        $this->assertArrayHasKey(
            'destination_address_id',
            $errors
        );
    }

    public function test_a_shipment_requires_at_least_one_package(): void
    {
        $data = $this->validData();
        $data['packages'] = [];

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'packages',
            $validator->errors()->toArray()
        );
    }

    public function test_package_fields_are_validated(): void
    {
        $data = $this->validData();

        $data['packages'][0] = [
            'weight' => -1,
            'height' => 0,
            'width' => 0,
            'length' => -10,
            'content_description' => '',
            'is_fragile' => 'invalid',
            'declared_value' => -100,
        ];

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());

        $errors = $validator->errors()->toArray();

        $this->assertArrayHasKey(
            'packages.0.weight',
            $errors
        );
        $this->assertArrayHasKey(
            'packages.0.height',
            $errors
        );
        $this->assertArrayHasKey(
            'packages.0.width',
            $errors
        );
        $this->assertArrayHasKey(
            'packages.0.length',
            $errors
        );
        $this->assertArrayHasKey(
            'packages.0.content_description',
            $errors
        );
        $this->assertArrayHasKey(
            'packages.0.is_fragile',
            $errors
        );
        $this->assertArrayHasKey(
            'packages.0.declared_value',
            $errors
        );
    }

    public function test_inactive_branches_are_rejected(): void
    {
        $data = $this->validData();

        $shipment = Shipment::factory()->create();

        $inactiveBranch = Branch::query()->create([
            'address_id' => $shipment->origin_address_id,
            'branch_name' => 'Inactive branch',
            'phone' => null,
            'email' => null,
            'is_active' => false,
        ]);

        $data['origin_branch_id'] = $inactiveBranch->id;

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'origin_branch_id',
            $validator->errors()->toArray()
        );
    }

    public function test_scheduled_date_cannot_be_in_the_past(): void
    {
        $data = $this->validData();

        $data['scheduled_at'] = now()
            ->subDay()
            ->toDateTimeString();

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'scheduled_at',
            $validator->errors()->toArray()
        );
    }

    /**
     * Crea información válida utilizando relaciones
     * reales generadas por ShipmentFactory.
     *
     * @return array<string, mixed>
     */
    private function validData(): array
    {
        $shipment = Shipment::factory()->create();

        return [
            'sender_id' => $shipment->sender_id,
            'recipient_id' => $shipment->recipient_id,
            'origin_address_id' =>
                $shipment->origin_address_id,
            'destination_address_id' =>
                $shipment->destination_address_id,
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

    /**
     * Ejecuta únicamente las reglas del Form Request.
     *
     * Esto permite probar la validación sin necesitar
     * todavía un controlador ni una ruta HTTP.
     *
     * @param array<string, mixed> $data
     */
    private function validator(
        array $data
    ): ValidatorContract {
        $request = new StoreShipmentRequest();

        return Validator::make(
            $data,
            $request->rules(),
            $request->messages(),
            $request->attributes()
        );
    }
}

