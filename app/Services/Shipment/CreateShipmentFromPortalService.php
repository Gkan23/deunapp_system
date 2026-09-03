<?php

namespace App\Services\Shipment;

use App\Models\Address;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\ShipmentPerson;
use Illuminate\Support\Facades\DB;

final class CreateShipmentFromPortalService
{
    public function __construct(
        private readonly CreateShipmentService
            $createShipmentService
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function handle(
        Customer $customer,
        array $data
    ): Shipment {
        return DB::transaction(
            function () use (
                $customer,
                $data
            ): Shipment {
                $sender = ShipmentPerson::query()
                    ->create(
                        $this->personAttributes(
                            $data['sender'],
                            'SENDER'
                        )
                    );

                $recipient = ShipmentPerson::query()
                    ->create(
                        $this->personAttributes(
                            $data['recipient'],
                            'RECIPIENT'
                        )
                    );

                $originAddress = Address::query()
                    ->create(
                        $this->addressAttributes(
                            $data['origin_address']
                        )
                    );

                $destinationAddress =
                    Address::query()->create(
                        $this->addressAttributes(
                            $data[
                                'destination_address'
                            ]
                        )
                    );

                return $this
                    ->createShipmentService
                    ->handle(
                        $customer,
                        [
                            'sender_id' =>
                                $sender->id,

                            'recipient_id' =>
                                $recipient->id,

                            'origin_address_id' =>
                                $originAddress->id,

                            'destination_address_id' =>
                                $destinationAddress->id,

                            'origin_branch_id' => null,

                            'destination_branch_id' =>
                                null,

                            'scheduled_at' =>
                                $data[
                                    'scheduled_at'
                                ] ?? null,

                            'declared_value' =>
                                $data[
                                    'declared_value'
                                ] ?? null,

                            'delivery_instructions' =>
                                $data[
                                    'delivery_instructions'
                                ] ?? null,

                            'notes' =>
                                $data['notes'] ?? null,

                            'packages' =>
                                $data['packages'],
                        ]
                    );
            },
            attempts: 3
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function personAttributes(
        array $data,
        string $personType
    ): array {
        return [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],

            'identity_number' =>
                $this->nullableString(
                    $data['identity_number']
                        ?? null
                ),

            'email' => $this->nullableString(
                $data['email'] ?? null
            ),

            'person_type' => $personType,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function addressAttributes(
        array $data
    ): array {
        return [
            'municipality_id' =>
                $data['municipality_id'],

            'address_line' =>
                $data['address_line'],

            'reference_note' =>
                $this->nullableString(
                    $data['reference_note']
                        ?? null
                ),

            'latitude' =>
                $data['latitude'] ?? null,

            'longitude' =>
                $data['longitude'] ?? null,
        ];
    }

    private function nullableString(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}