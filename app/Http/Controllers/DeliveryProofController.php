<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProof;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeliveryProofController extends Controller
{
    /**
     * Muestra la prueba de entrega de un envío.
     */
    public function show(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'view',
            $shipment
        );

        $deliveryProof = $shipment
            ->deliveryProof()
            ->first();

        if ($deliveryProof === null) {
            return response()->json([
                'message' =>
                    'No delivery proof has been recorded for this shipment.',
            ], 404);
        }

        return response()->json([
            'data' => $this->deliveryProofData(
                $deliveryProof
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryProofData(
        DeliveryProof $deliveryProof
    ): array {
        return [
            'id' => $deliveryProof->id,
            'shipment_id' =>
                $deliveryProof->shipment_id,
            'photo_url' =>
                $deliveryProof->photo_url,
            'signature_url' =>
                $deliveryProof->signature_url,
            'receiver_name' =>
                $deliveryProof->receiver_name,
            'receiver_identity_number' =>
                $deliveryProof
                    ->receiver_identity_number,
            'latitude' =>
                $deliveryProof->latitude === null
                    ? null
                    : (float) $deliveryProof->latitude,
            'longitude' =>
                $deliveryProof->longitude === null
                    ? null
                    : (float) $deliveryProof->longitude,
            'recorded_at' =>
                $deliveryProof
                    ->recorded_at
                    ->toIso8601String(),
        ];
    }
}