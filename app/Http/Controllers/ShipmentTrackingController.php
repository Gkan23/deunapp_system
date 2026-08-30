<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\User;
use App\Services\Shipment\GetShipmentTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShipmentTrackingController extends Controller
{
    /**
     * Devuelve la ubicación más reciente relacionada
     * con el envío autorizado.
     */
    public function show(
        Request $request,
        Shipment $shipment,
        GetShipmentTrackingService $service
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'track',
            $shipment
        );

        return response()->json([
            'data' => $service->execute(
                $shipment
            ),
        ]);
    }
}