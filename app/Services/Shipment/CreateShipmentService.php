<?php

namespace App\Services\Shipment;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateShipmentService
{
    /**
     * Registra un envío, sus paquetes y su primer
     * cambio de estado dentro de una transacción.
     *
     * @param array<string, mixed> $data
     */
    public function handle(
        Customer $customer,
        array $data
    ): Shipment {
        return DB::transaction(
            function () use ($customer, $data): Shipment {
                $customer = Customer::query()
                    ->findOrFail($customer->id);

                $this->validateShipmentData($data);

                $requestedStatus = ShipmentStatus::query()
                    ->where('status_name', 'REQUESTED')
                    ->firstOrFail();

                $now = now();

                $shipment = Shipment::query()->create([
                    'tracking_code' =>
                        $this->generateTrackingCode(),

                    'customer_id' => $customer->id,
                    'sender_id' => $data['sender_id'],
                    'recipient_id' => $data['recipient_id'],

                    'origin_address_id' =>
                        $data['origin_address_id'],

                    'destination_address_id' =>
                        $data['destination_address_id'],

                    'origin_branch_id' =>
                        $data['origin_branch_id'] ?? null,

                    'destination_branch_id' =>
                        $data['destination_branch_id'] ?? null,

                    'shipment_status_id' =>
                        $requestedStatus->id,

                    'requested_at' => $now,

                    'scheduled_at' =>
                        $data['scheduled_at'] ?? null,

                    /*
                     * Estos valores serán establecidos por
                     * otros procesos del dominio.
                     */
                    'estimated_delivery_at' => null,
                    'delivered_at' => null,

                    'declared_value' =>
                        $data['declared_value'] ?? null,

                    'delivery_instructions' =>
                        $data['delivery_instructions'] ?? null,

                    'notes' => $data['notes'] ?? null,
                ]);

                $shipment->packages()->createMany(
                    $this->packageAttributes(
                        $data['packages']
                    )
                );

                /*
                 * El estado actual y su historial se guardan
                 * juntos dentro de la misma transacción.
                 */
                $shipment->statusHistory()->create([
                    'shipment_status_id' =>
                        $requestedStatus->id,

                    'changed_by_user_id' =>
                        $customer->user_id,

                    'comment' => 'Shipment requested.',
                    'changed_at' => $now,
                ]);

                return $shipment->refresh()->load([
                    'customer',
                    'sender',
                    'recipient',
                    'originAddress.municipality',
                    'destinationAddress.municipality',
                    'packages',
                    'shipmentStatus',
                    'statusHistory',
                ]);
            },
            attempts: 3
        );
    }

    /**
     * Conserva reglas esenciales incluso cuando el
     * servicio se utiliza fuera de una petición HTTP.
     *
     * @param array<string, mixed> $data
     */
    private function validateShipmentData(array $data): void
    {
        $packages = $data['packages'] ?? null;

        if (! is_array($packages) || $packages === []) {
            throw new DomainException(
                'A shipment must contain at least one package.'
            );
        }

        if (
            (int) $data['sender_id']
            === (int) $data['recipient_id']
        ) {
            throw new DomainException(
                'The sender and recipient must be different.'
            );
        }

        if (
            (int) $data['origin_address_id']
            === (int) $data['destination_address_id']
        ) {
            throw new DomainException(
                'The origin and destination addresses must be different.'
            );
        }

        $this->validateBranch(
            isset($data['origin_branch_id'])
                ? (int) $data['origin_branch_id']
                : null,
            (int) $data['origin_address_id'],
            'origin'
        );

        $this->validateBranch(
            isset($data['destination_branch_id'])
                ? (int) $data['destination_branch_id']
                : null,
            (int) $data['destination_address_id'],
            'destination'
        );
    }

    /**
     * Comprueba que la sucursal esté activa y que
     * pertenezca a la dirección correspondiente.
     */
    private function validateBranch(
        ?int $branchId,
        int $addressId,
        string $type
    ): void {
        if ($branchId === null) {
            return;
        }

        $branch = Branch::query()->find($branchId);

        if ($branch === null || ! $branch->is_active) {
            throw new DomainException(
                "The {$type} branch must be active."
            );
        }

        if ((int) $branch->address_id !== $addressId) {
            throw new DomainException(
                "The {$type} branch must belong to the {$type} address."
            );
        }
    }

    /**
     * Selecciona exclusivamente los campos que pueden
     * almacenarse en cada paquete.
     *
     * @param array<int, array<string, mixed>> $packages
     * @return array<int, array<string, mixed>>
     */
    private function packageAttributes(
        array $packages
    ): array {
        return array_map(
            static fn (array $package): array => [
                'weight' => $package['weight'] ?? null,
                'height' => $package['height'] ?? null,
                'width' => $package['width'] ?? null,
                'length' => $package['length'] ?? null,

                'content_description' =>
                    $package['content_description'] ?? null,

                'is_fragile' =>
                    (bool) ($package['is_fragile'] ?? false),

                'declared_value' =>
                    $package['declared_value'] ?? null,
            ],
            $packages
        );
    }

    /**
     * Un ULID permite generar códigos ordenables y con
     * una probabilidad extremadamente baja de repetición.
     *
     * Ejemplo:
     * DEU-01K4M8R9FV4C5ZQK1W1S8D7P3A
     */
    private function generateTrackingCode(): string
    {
        return 'DEU-' . Str::upper(
            (string) Str::ulid()
        );
    }
}
