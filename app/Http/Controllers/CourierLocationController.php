<?php

namespace App\Http\Controllers;

use App\Models\Courier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CourierLocationController extends Controller
{
    /**
     * Muestra la última ubicación registrada
     * de un repartidor autorizado.
     */
    public function latest(
        Request $request,
        Courier $courier
    ): JsonResponse {
        Gate::forUser($request->user())
            ->authorize('view', $courier);

        $courier->load([
            'user',
            'deliveryProvider',
        ]);

        $location = $courier
            ->locations()
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();

        if ($location === null) {
            return response()->json([
                'message' =>
                    'No location has been recorded for this courier.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'courier' => [
                    'id' => $courier->id,
                    'user_id' =>
                        $courier->user_id,
                    'name' =>
                        $courier->user->name,
                    'delivery_provider_id' =>
                        $courier
                            ->delivery_provider_id,
                    'is_active' =>
                        (bool) $courier->is_active,
                    'is_available' =>
                        (bool) $courier
                            ->is_available,
                ],
                'location' => [
                    'id' => $location->id,
                    'latitude' =>
                        (float) $location
                            ->latitude,
                    'longitude' =>
                        (float) $location
                            ->longitude,
                    'gps_accuracy' =>
                        $location->gps_accuracy
                            === null
                                ? null
                                : (float) $location
                                    ->gps_accuracy,
                    'recorded_at' =>
                        $location->recorded_at,
                ],
            ],
        ]);
    }
}