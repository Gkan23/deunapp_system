<?php

namespace App\Http\Controllers;

use App\Http\Requests\FailDeliveryAttemptRequest;
use App\Models\RouteShipment;
use App\Services\Delivery\FailDeliveryAttemptService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RouteShipmentController extends Controller
{
    /**
     * Registra un intento de entrega fallido.
     */
    public function failAttempt(
        FailDeliveryAttemptRequest $request,
        RouteShipment $routeShipment,
        FailDeliveryAttemptService $service
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $incident = $service->execute(
                routeShipment: $routeShipment,
                reportedBy: $request->user(),
                incidentTypeName:
                    $validated['incident_type'],
                description:
                    $validated['description']
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' =>
                'Failed delivery attempt registered successfully.',
            'incident' => $incident,
        ]);
    }
}
