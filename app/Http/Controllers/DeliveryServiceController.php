<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignDeliveryServiceRequest;
use App\Http\Requests\CompleteDeliveryRequest;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\User;
use App\Services\Delivery\AssignAvailableTripService;
use App\Services\Delivery\CompleteDeliveryService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DeliveryServiceController extends Controller
{
    /**
     * Asigna un viaje disponible al servicio.
     */
    public function assign(
        AssignDeliveryServiceRequest $request,
        DeliveryService $deliveryService,
        AssignAvailableTripService $service
    ): JsonResponse {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        $isAdministrator = $user->role()
            ->where(
                'role_name',
                'ADMINISTRATOR'
            )
            ->exists();

        /*
         * El administrador selecciona el proveedor.
         * El proveedor autenticado utiliza su propio perfil.
         */
        if ($isAdministrator) {
            $deliveryProvider =
                DeliveryProvider::query()
                    ->findOrFail(
                        $validated[
                            'delivery_provider_id'
                        ]
                    );
        } else {
            $deliveryProvider = $user
                ->deliveryProvider()
                ->firstOrFail();
        }

        try {
            $assignedService = $service->handle(
                $deliveryService,
                $deliveryProvider
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' =>
                'Delivery service assigned successfully.',
            'delivery_service' => $assignedService,
        ]);
    }

    /**
     * Completa el servicio y registra la prueba
     * de entrega de manera atómica.
     */
    public function complete(
        CompleteDeliveryRequest $request,
        DeliveryService $deliveryService,
        CompleteDeliveryService $service
    ): JsonResponse {
        $validated = $request->validated();

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