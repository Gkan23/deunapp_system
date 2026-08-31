<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProof;
use App\Models\Role;
use App\Models\Route as DeliveryRoute;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryProofControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_view_a_delivery_proof(): void
    {
        $shipment = Shipment::factory()->create();

        $this->getJson(
            route(
                'shipments.delivery-proof.show',
                $shipment
            )
        )->assertUnauthorized();
    }

    public function test_the_customer_can_view_their_delivery_proof(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $deliveryProof = $this->createProof(
            $shipment
        );

        $this->actingAs($customer->user)
            ->getJson(
                route(
                    'shipments.delivery-proof.show',
                    $shipment
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $deliveryProof->id
            )
            ->assertJsonPath(
                'data.shipment_id',
                $shipment->id
            )
            ->assertJsonPath(
                'data.photo_url',
                'https://example.test/proofs/package.jpg'
            )
            ->assertJsonPath(
                'data.signature_url',
                'https://example.test/proofs/signature.png'
            )
            ->assertJsonPath(
                'data.receiver_name',
                'María López'
            )
            ->assertJsonPath(
                'data.receiver_identity_number',
                '001-010101-0001A'
            )
            ->assertJsonPath(
                'data.latitude',
                12.136389
            )
            ->assertJsonPath(
                'data.longitude',
                -86.251389
            )
            ->assertJsonPath(
                'data.recorded_at',
                $deliveryProof
                    ->recorded_at
                    ->toIso8601String()
            );
    }

    public function test_a_missing_delivery_proof_returns_not_found(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($customer->user)
            ->getJson(
                route(
                    'shipments.delivery-proof.show',
                    $shipment
                )
            )
            ->assertNotFound()
            ->assertJsonPath(
                'message',
                'No delivery proof has been recorded for this shipment.'
            );
    }

    public function test_an_unrelated_customer_cannot_view_the_delivery_proof(): void
    {
        $owner = Customer::factory()->create();

        $unrelatedCustomer =
            Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $owner->id,
        ]);

        $this->createProof($shipment);

        $this->actingAs(
            $unrelatedCustomer->user
        )
            ->getJson(
                route(
                    'shipments.delivery-proof.show',
                    $shipment
                )
            )
            ->assertForbidden();
    }

    public function test_the_assigned_courier_can_view_the_delivery_proof(): void
    {
        $shipment = Shipment::factory()->create();

        $courier = Courier::factory()->create();

        $this->assignCourier(
            $shipment,
            $courier
        );

        $deliveryProof = $this->createProof(
            $shipment
        );

        $this->actingAs($courier->user)
            ->getJson(
                route(
                    'shipments.delivery-proof.show',
                    $shipment
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $deliveryProof->id
            );
    }

    public function test_support_and_administration_can_view_a_delivery_proof(): void
    {
        $shipment = Shipment::factory()->create();

        $deliveryProof = $this->createProof(
            $shipment
        );

        foreach ([
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ] as $roleName) {
            $user = User::factory()->create([
                'role_id' => Role::query()
                    ->where(
                        'role_name',
                        $roleName
                    )
                    ->firstOrFail()
                    ->id,
            ]);

            $this->actingAs($user)
                ->getJson(
                    route(
                        'shipments.delivery-proof.show',
                        $shipment
                    )
                )
                ->assertOk()
                ->assertJsonPath(
                    'data.id',
                    $deliveryProof->id
                );
        }
    }

    public function test_nullable_proof_fields_are_returned_as_null(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        DeliveryProof::query()->create([
            'shipment_id' => $shipment->id,
            'photo_url' => null,
            'signature_url' =>
                'https://example.test/proofs/signature.png',
            'receiver_name' => 'Carlos Pérez',
            'receiver_identity_number' => null,
            'latitude' => null,
            'longitude' => null,
            'recorded_at' => now(),
        ]);

        $this->actingAs($customer->user)
            ->getJson(
                route(
                    'shipments.delivery-proof.show',
                    $shipment
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.photo_url',
                null
            )
            ->assertJsonPath(
                'data.receiver_identity_number',
                null
            )
            ->assertJsonPath(
                'data.latitude',
                null
            )
            ->assertJsonPath(
                'data.longitude',
                null
            );
    }

    public function test_an_unverified_customer_cannot_view_a_delivery_proof(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->createProof($shipment);

        $customer->user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this->actingAs(
            $customer->user->fresh()
        )
            ->getJson(
                route(
                    'shipments.delivery-proof.show',
                    $shipment
                )
            )
            ->assertForbidden();
    }

    private function createProof(
        Shipment $shipment
    ): DeliveryProof {
        return DeliveryProof::query()->create([
            'shipment_id' => $shipment->id,
            'photo_url' =>
                'https://example.test/proofs/package.jpg',
            'signature_url' =>
                'https://example.test/proofs/signature.png',
            'receiver_name' => 'María López',
            'receiver_identity_number' =>
                '001-010101-0001A',
            'latitude' => 12.1363890,
            'longitude' => -86.2513890,
            'recorded_at' => now(),
        ]);
    }

    private function assignCourier(
        Shipment $shipment,
        Courier $courier
    ): RouteShipment {
        $activeStatus = RouteStatus::query()
            ->where('status_name', 'ACTIVE')
            ->firstOrFail();

        $route = DeliveryRoute::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' =>
                $activeStatus->id,
            'route_date' => today(),
            'started_at' => now(),
            'finished_at' => null,
            'estimated_distance_km' => 10.00,
        ]);

        return RouteShipment::query()->create([
            'route_id' => $route->id,
            'shipment_id' => $shipment->id,
            'delivery_order' => 1,
            'delivery_status' => 'DELIVERED',
        ]);
    }
}