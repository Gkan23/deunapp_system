<?php

namespace App\Services\Delivery;

use App\Models\Address;
use App\Models\TripType;

final class ResolveTripTypeService
{
    /**
     * Determine the trip type from the shipment addresses.
     */
    public function handle(
        Address $originAddress,
        Address $destinationAddress
    ): TripType {
        $typeName = (int) $originAddress->municipality_id
            === (int) $destinationAddress->municipality_id
                ? 'LOCAL'
                : 'INTERMUNICIPAL';

        return TripType::query()
            ->where('type_name', $typeName)
            ->firstOrFail();
    }
}
