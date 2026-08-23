<?php

namespace Tests\Feature\Services\Shipment;

use App\Models\DeliveryProof;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\User;
use App\Services\Shipment\UpdateShipmentStatusService;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateShipmentStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_updates_status_and_creates_history(): void
    {
        $shipment = Shipment::factory()->create();
        $user = User::factory()->create();

        $pickedUp = $this->findShipmentStatus('PICKED_UP');

        $updatedShipment = app(
            UpdateShipmentStatusService::class
        )->handle(
            $shipment,
            $pickedUp,
            $user,
            'Paquete recogido por el repartidor.'
        );

        $this->assertSame(
            $pickedUp->id,
            $updatedShipment->shipment_status_id
        );

        $this->assertSame(
            'PICKED_UP',
            $updatedShipment->shipmentStatus->status_name
        );

        $this->assertNull(
            $updatedShipment->delivered_at
        );

        $this->assertDatabaseHas(
            'shipment_status_history',
            [
                'shipment_id' => $shipment->id,
                'shipment_status_id' => $pickedUp->id,
                'changed_by_user_id' => $user->id,
                'comment' => 'Paquete recogido por el repartidor.',
            ]
        );

        $this->assertDatabaseCount(
            'shipment_status_history',
            1
        );
    }

    public function test_it_rejects_skipping_statuses(): void
    {
        $shipment = Shipment::factory()->create();
        $user = User::factory()->create();

        $inTransit = $this->findShipmentStatus(
            'IN_TRANSIT'
        );

        $this->assertDomainException(
            'The transition from REQUESTED to IN_TRANSIT is not allowed.',
            fn () => app(
                UpdateShipmentStatusService::class
            )->handle(
                $shipment,
                $inTransit,
                $user
            )
        );

        $shipment->refresh();

        $this->assertSame(
            'REQUESTED',
            $shipment->shipmentStatus->status_name
        );

        $this->assertDatabaseCount(
            'shipment_status_history',
            0
        );
    }

    public function test_it_rejects_the_same_status(): void
    {
        $shipment = Shipment::factory()->create();

        $requested = $this->findShipmentStatus(
            'REQUESTED'
        );

        $this->assertDomainException(
            'The shipment already has the requested status.',
            fn () => app(
                UpdateShipmentStatusService::class
            )->handle(
                $shipment,
                $requested
            )
        );

        $shipment->refresh();

        $this->assertSame(
            'REQUESTED',
            $shipment->shipmentStatus->status_name
        );

        $this->assertDatabaseCount(
            'shipment_status_history',
            0
        );
    }

    public function test_cancelled_is_a_terminal_status(): void
    {
        $shipment = Shipment::factory()->create();
        $user = User::factory()->create();

        $cancelled = $this->findShipmentStatus(
            'CANCELLED'
        );

        $pickedUp = $this->findShipmentStatus(
            'PICKED_UP'
        );

        $statusService = app(
            UpdateShipmentStatusService::class
        );

        $statusService->handle(
            $shipment,
            $cancelled,
            $user,
            'Envío cancelado por el cliente.'
        );

        $this->assertDomainException(
            'The transition from CANCELLED to PICKED_UP is not allowed.',
            fn () => $statusService->handle(
                $shipment,
                $pickedUp,
                $user
            )
        );

        $shipment->refresh();

        $this->assertSame(
            'CANCELLED',
            $shipment->shipmentStatus->status_name
        );

        $this->assertDatabaseHas(
            'shipment_status_history',
            [
                'shipment_id' => $shipment->id,
                'shipment_status_id' => $cancelled->id,
                'changed_by_user_id' => $user->id,
                'comment' => 'Envío cancelado por el cliente.',
            ]
        );

        $this->assertDatabaseCount(
            'shipment_status_history',
            1
        );
    }

    public function test_it_rejects_delivery_without_proof(): void
    {
        $shipment = Shipment::factory()->create();

        $outForDelivery = $this->findShipmentStatus(
            'OUT_FOR_DELIVERY'
        );

        $shipment->update([
            'shipment_status_id' => $outForDelivery->id,
        ]);

        $delivered = $this->findShipmentStatus(
            'DELIVERED'
        );

        $this->assertDomainException(
            'Delivery proof is required before marking the shipment as delivered.',
            fn () => app(
                UpdateShipmentStatusService::class
            )->handle(
                $shipment,
                $delivered
            )
        );

        $shipment->refresh();

        $this->assertSame(
            'OUT_FOR_DELIVERY',
            $shipment->shipmentStatus->status_name
        );

        $this->assertNull(
            $shipment->delivered_at
        );

        $this->assertDatabaseCount(
            'shipment_status_history',
            0
        );
    }

    public function test_it_rejects_incomplete_delivery_proof(): void
    {
        $shipment = Shipment::factory()->create();

        $outForDelivery = $this->findShipmentStatus(
            'OUT_FOR_DELIVERY'
        );

        $shipment->update([
            'shipment_status_id' => $outForDelivery->id,
        ]);

        DeliveryProof::query()->create([
            'shipment_id' => $shipment->id,
            'photo_url' => null,
            'signature_url' => null,
            'receiver_name' => 'Juan Pérez',
            'receiver_identity_number' => null,
            'latitude' => null,
            'longitude' => null,
            'recorded_at' => now(),
        ]);

        $delivered = $this->findShipmentStatus(
            'DELIVERED'
        );

        $this->assertDomainException(
            'The delivery proof is incomplete.',
            fn () => app(
                UpdateShipmentStatusService::class
            )->handle(
                $shipment,
                $delivered
            )
        );

        $shipment->refresh();

        $this->assertSame(
            'OUT_FOR_DELIVERY',
            $shipment->shipmentStatus->status_name
        );

        $this->assertNull(
            $shipment->delivered_at
        );

        $this->assertDatabaseCount(
            'shipment_status_history',
            0
        );
    }

    public function test_it_marks_a_shipment_as_delivered_with_valid_proof(): void
    {
        $shipment = Shipment::factory()->create();
        $user = User::factory()->create();

        $outForDelivery = $this->findShipmentStatus(
            'OUT_FOR_DELIVERY'
        );

        $shipment->update([
            'shipment_status_id' => $outForDelivery->id,
        ]);

        DeliveryProof::query()->create([
            'shipment_id' => $shipment->id,
            'photo_url' => 'proofs/test-delivery.jpg',
            'signature_url' => null,
            'receiver_name' => 'Juan Pérez',
            'receiver_identity_number' => null,
            'latitude' => '13.0919444',
            'longitude' => '-86.3538889',
            'recorded_at' => now(),
        ]);

        $delivered = $this->findShipmentStatus(
            'DELIVERED'
        );

        $updatedShipment = app(
            UpdateShipmentStatusService::class
        )->handle(
            $shipment,
            $delivered,
            $user,
            'Entrega confirmada.'
        );

        $this->assertSame(
            'DELIVERED',
            $updatedShipment->shipmentStatus->status_name
        );

        $this->assertNotNull(
            $updatedShipment->delivered_at
        );

        $this->assertDatabaseHas(
            'shipments',
            [
                'id' => $shipment->id,
                'shipment_status_id' => $delivered->id,
            ]
        );

        $this->assertDatabaseHas(
            'shipment_status_history',
            [
                'shipment_id' => $shipment->id,
                'shipment_status_id' => $delivered->id,
                'changed_by_user_id' => $user->id,
                'comment' => 'Entrega confirmada.',
            ]
        );

        $this->assertDatabaseCount(
            'shipment_status_history',
            1
        );
    }

    private function findShipmentStatus(
        string $statusName
    ): ShipmentStatus {
        return ShipmentStatus::query()
            ->where('status_name', $statusName)
            ->firstOrFail();
    }

    private function assertDomainException(
        string $expectedMessage,
        callable $callback
    ): void {
        try {
            $callback();
        } catch (DomainException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );

            return;
        }

        $this->fail(
            'The expected DomainException was not thrown.'
        );
    }
}
