<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteDeliveryRequest;
use App\Models\DeliveryService;
use App\Services\Delivery\CompleteDeliveryService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DeliveryServiceController extends Controller
{
    /**
     * Completa un servicio y registra su prueba
     * de entrega de manera atómica.
     */
    public function complete(
        CompleteDeliveryRequest $request,
        DeliveryService $deliveryService,
        CompleteDeliveryService $service
    ): JsonResponse {
        $validated = $request->validated();

        /*
         * Solamente enviamos al servicio los campos
         * correspondientes a DeliveryProof.
         */
        $proofData = [
            'photo_url' =>
                $validated['photo_url'] ?? null,
            'signature_url' =>
                $validated['signature_url'] ?? null,
            'receiver_name' =>
                $validated['receiver_name'],
            'receiver_identity_number' =>
                $validated[
                    'receiver_identity_number'
                ] ?? null,
            'latitude' =>
                $validated['latitude'] ?? null,
            'longitude' =>
                $validated['longitude'] ?? null,
        ];

        try {
            $completedService = $service->handle(
                $deliveryService,
                $proofData,
                $request->user(),
                $validated['comment'] ?? null
            );
        } catch (DomainException $exception) {
            /*
             * Las condiciones inválidas del dominio,
             * como completar un servicio que no está
             * iniciado, responden con HTTP 422.
             */
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' =>
                'Delivery completed successfully.',
            'delivery_service' => $completedService,
        ]);
    }
}