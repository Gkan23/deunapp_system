<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourierLocationRequest;
use App\Services\Courier\StoreCourierLocationService;
use DomainException;
use Illuminate\Http\JsonResponse;

class CurrentCourierLocationController extends Controller
{
    /**
     * Registra una ubicación del repartidor autenticado.
     */
    public function store(
        StoreCourierLocationRequest $request,
        StoreCourierLocationService $service
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $location = $service->execute(
                performedBy: $request->user(),
                latitude: (float) $validated[
                    'latitude'
                ],
                longitude: (float) $validated[
                    'longitude'
                ],
                gpsAccuracy: isset(
                    $validated['gps_accuracy']
                )
                    ? (float) $validated[
                        'gps_accuracy'
                    ]
                    : null
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' =>
                    $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' =>
                'Courier location recorded successfully.',
            'data' => [
                'id' => $location->id,
                'courier_id' =>
                    $location->courier_id,
                'latitude' =>
                    (float) $location->latitude,
                'longitude' =>
                    (float) $location->longitude,
                'gps_accuracy' =>
                    $location->gps_accuracy === null
                        ? null
                        : (float) $location
                            ->gps_accuracy,
                'recorded_at' =>
                    $location->recorded_at,
            ],
        ], 201);
    }
}