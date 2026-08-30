<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCourierAvailabilityRequest;
use App\Services\Courier\UpdateCourierAvailabilityService;
use DomainException;
use Illuminate\Http\JsonResponse;

class CurrentCourierAvailabilityController extends Controller
{
    /**
     * Cambia la disponibilidad del repartidor autenticado.
     */
    public function update(
        UpdateCourierAvailabilityRequest $request,
        UpdateCourierAvailabilityService $service
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $courier = $service->execute(
                performedBy: $request->user(),
                isAvailable: (bool) $validated[
                    'is_available'
                ],
                comment: $validated['comment']
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' =>
                    $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' =>
                'Courier availability updated successfully.',
            'data' => [
                'id' => $courier->id,
                'user_id' => $courier->user_id,
                'delivery_provider_id' =>
                    $courier
                        ->delivery_provider_id,
                'is_active' =>
                    (bool) $courier->is_active,
                'is_available' =>
                    (bool) $courier
                        ->is_available,
                'updated_at' =>
                    $courier->updated_at,
            ],
        ]);
    }
}
