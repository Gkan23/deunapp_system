<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\DeliveryProvider;
use App\Models\Municipality;
use App\Models\Shipment;
use App\Models\ShipmentPerson;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalShipmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_view_the_shipment_form(): void
    {
        $this->get(
            route('portal.shipments.create')
        )->assertRedirect(
            route('login.page')
        );
    }

    public function test_a_guest_cannot_submit_the_shipment_form(): void
    {
        $this->post(
            route('portal.shipments.store'),
            []
        )->assertRedirect(
            route('login.page')
        );

        $this->assertDatabaseCount(
            'shipments',
            0
        );
    }

    public function test_an_unverified_customer_cannot_use_the_form(): void
    {
        $customer =
            \App\Models\Customer::factory()
                ->create();

        $user = $customer->user;

        $user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $user = $user->fresh();

        $this->actingAs($user)
            ->get(
                route('portal.shipments.create')
            )
            ->assertRedirect(
                route('verification.notice')
            );

        $this->actingAs($user)
            ->post(
                route('portal.shipments.store'),
                $this->validData()
            )
            ->assertRedirect(
                route('verification.notice')
            );

        $this->assertDatabaseCount(
            'shipments',
            0
        );
    }

    public function test_a_customer_can_view_the_shipment_form(): void
    {
        $customer =
            \App\Models\Customer::factory()
                ->create();

        $municipality =
            Municipality::query()
                ->where('is_active', true)
                ->firstOrFail();

        $this->actingAs($customer->user)
            ->get(
                route('portal.shipments.create')
            )
            ->assertOk()
            ->assertViewIs('shipments.create')
            ->assertViewHas('municipalities')
            ->assertSee('Registrar envío')
            ->assertSee('Remitente')
            ->assertSee('Destinatario')
            ->assertSee(
                $municipality->municipality_name
            )
            ->assertSee(
                route('portal.shipments.store'),
                escape: false
            );
    }

    public function test_a_non_customer_cannot_use_the_shipment_form(): void
    {
        $provider =
            DeliveryProvider::factory()->create();

        $this->actingAs($provider->user)
            ->get(
                route('portal.shipments.create')
            )
            ->assertForbidden();

        $this->actingAs($provider->user)
            ->post(
                route('portal.shipments.store'),
                $this->validData()
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'shipments',
            0
        );
    }

    public function test_a_customer_can_register_a_shipment_from_the_portal(): void
    {
        $customer =
            \App\Models\Customer::factory()
                ->create();

        $response = $this
            ->actingAs($customer->user)
            ->post(
                route('portal.shipments.store'),
                $this->validData()
            );

        $shipment = Shipment::query()
            ->with([
                'shipmentStatus',
                'packages',
                'statusHistory',
            ])
            ->firstOrFail();

        $response
            ->assertRedirect(
                route(
                    'portal.shipments.show',
                    $shipment
                )
            )
            ->assertSessionHas(
                'status',
                'El envío fue registrado correctamente.'
            );

        $this->assertSame(
            $customer->id,
            $shipment->customer_id
        );

        $this->assertSame(
            'REQUESTED',
            $shipment
                ->shipmentStatus
                ->status_name
        );

        $this->assertStringStartsWith(
            'DEU-',
            $shipment->tracking_code
        );

        $this->assertCount(
            1,
            $shipment->packages
        );

        $this->assertCount(
            1,
            $shipment->statusHistory
        );

        $sender = ShipmentPerson::query()
            ->where('person_type', 'SENDER')
            ->firstOrFail();

        $recipient = ShipmentPerson::query()
            ->where(
                'person_type',
                'RECIPIENT'
            )
            ->firstOrFail();

        $this->assertSame(
            $sender->id,
            $shipment->sender_id
        );

        $this->assertSame(
            $recipient->id,
            $shipment->recipient_id
        );

        $this->assertDatabaseHas(
            'shipment_people',
            [
                'id' => $sender->id,
                'first_name' => 'Ana',
                'last_name' => 'Mendoza',
                'phone' => '88881111',
                'email' => 'ana@example.com',
                'person_type' => 'SENDER',
            ]
        );

        $this->assertDatabaseHas(
            'shipment_people',
            [
                'id' => $recipient->id,
                'first_name' => 'Carlos',
                'last_name' => 'López',
                'phone' => '88882222',
                'email' =>
                    'carlos@example.com',
                'person_type' => 'RECIPIENT',
            ]
        );

        $this->assertDatabaseHas(
            'addresses',
            [
                'id' =>
                    $shipment->origin_address_id,
                'address_line' =>
                    'Avenida Central 100',
                'reference_note' =>
                    'Frente al parque',
            ]
        );

        $this->assertDatabaseHas(
            'addresses',
            [
                'id' =>
                    $shipment
                        ->destination_address_id,
                'address_line' =>
                    'Barrio Nuevo 250',
                'reference_note' =>
                    'Casa de portón azul',
            ]
        );

        $this->assertDatabaseHas(
            'packages',
            [
                'shipment_id' => $shipment->id,
                'content_description' =>
                    'Documentos y libros',
                'is_fragile' => true,
                'declared_value' => 350.00,
            ]
        );

        $this->assertDatabaseHas(
            'shipment_status_history',
            [
                'shipment_id' => $shipment->id,
                'changed_by_user_id' =>
                    $customer->user_id,
            ]
        );
    }

    public function test_required_form_fields_are_validated(): void
    {
        $customer =
            \App\Models\Customer::factory()
                ->create();

        $response = $this
            ->actingAs($customer->user)
            ->from(
                route('portal.shipments.create')
            )
            ->post(
                route('portal.shipments.store'),
                []
            );

        $response
            ->assertRedirect(
                route('portal.shipments.create')
            )
            ->assertSessionHasErrors([
                'sender',
                'recipient',
                'origin_address',
                'destination_address',
                'packages',
            ]);

        $this->assertDatabaseCount(
            'shipments',
            0
        );

        $this->assertDatabaseCount(
            'shipment_people',
            0
        );

        $this->assertDatabaseCount(
            'addresses',
            0
        );
    }

    public function test_an_inactive_municipality_is_rejected(): void
    {
        $customer =
            \App\Models\Customer::factory()
                ->create();

        $inactiveMunicipality =
            Municipality::query()->create([
                'department_name' =>
                    'Departamento de prueba',
                'municipality_name' =>
                    'Municipio inactivo',
                'is_active' => false,
            ]);

        $data = $this->validData();

        $data[
            'origin_address'
        ][
            'municipality_id'
        ] = $inactiveMunicipality->id;

        $response = $this
            ->actingAs($customer->user)
            ->from(
                route('portal.shipments.create')
            )
            ->post(
                route('portal.shipments.store'),
                $data
            );

        $response
            ->assertRedirect(
                route('portal.shipments.create')
            )
            ->assertSessionHasErrors([
                'origin_address.municipality_id',
            ]);

        $this->assertDatabaseCount(
            'shipments',
            0
        );

        $this->assertDatabaseCount(
            'shipment_people',
            0
        );

        $this->assertDatabaseCount(
            'addresses',
            0
        );
    }

    public function test_a_past_scheduled_date_is_rejected(): void
    {
        $customer =
            \App\Models\Customer::factory()
                ->create();

        $data = $this->validData();

        $data['scheduled_at'] = now()
            ->subDay()
            ->format('Y-m-d\TH:i');

        $response = $this
            ->actingAs($customer->user)
            ->from(
                route('portal.shipments.create')
            )
            ->post(
                route('portal.shipments.store'),
                $data
            );

        $response
            ->assertRedirect(
                route('portal.shipments.create')
            )
            ->assertSessionHasErrors([
                'scheduled_at',
            ]);

        $this->assertDatabaseCount(
            'shipments',
            0
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validData(): array
    {
        $municipalities =
            Municipality::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->take(2)
                ->get();

        $originMunicipality =
            $municipalities->firstOrFail();

        $destinationMunicipality =
            $municipalities->get(1)
            ?? $originMunicipality;

        return [
            'sender' => [
                'first_name' => 'Ana',
                'last_name' => 'Mendoza',
                'phone' => '88881111',
                'identity_number' =>
                    '001-010101-0001A',
                'email' => 'ana@example.com',
            ],

            'recipient' => [
                'first_name' => 'Carlos',
                'last_name' => 'López',
                'phone' => '88882222',
                'identity_number' =>
                    '001-020202-0002B',
                'email' =>
                    'carlos@example.com',
            ],

            'origin_address' => [
                'municipality_id' =>
                    $originMunicipality->id,
                'address_line' =>
                    'Avenida Central 100',
                'reference_note' =>
                    'Frente al parque',
            ],

            'destination_address' => [
                'municipality_id' =>
                    $destinationMunicipality->id,
                'address_line' =>
                    'Barrio Nuevo 250',
                'reference_note' =>
                    'Casa de portón azul',
            ],

            'scheduled_at' => now()
                ->addDay()
                ->format('Y-m-d\TH:i'),

            'declared_value' => 350.00,

            'delivery_instructions' =>
                'Llamar antes de entregar.',

            'notes' =>
                'Manipular cuidadosamente.',

            'packages' => [
                [
                    'content_description' =>
                        'Documentos y libros',
                    'weight' => 2.50,
                    'height' => 20,
                    'width' => 15,
                    'length' => 30,
                    'declared_value' => 350.00,
                    'is_fragile' => true,
                ],
            ],
        ];
    }
}