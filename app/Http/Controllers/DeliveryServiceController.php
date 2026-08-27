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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class DeliveryServiceController extends Controller
{
    /**
     * Muestra los servicios visibles para el usuario.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'viewAny',
            DeliveryService::class
        );

        $deliveryServices =
            $this->visibleServicesFor($user)
                ->with([
                    'customer',
                    'shipment.shipmentStatus',
                    'trip.deliveryProvider',
                    'trip.tripType',
                    'payment',
                    'rating',
                ])
                ->latest('id')
                ->get();

        return response()->json([
            'data' => $deliveryServices,
        ]);
    }

    /**
     * Muestra un servicio específico.
     */
    public function show(
        Request $request,
        DeliveryService $deliveryService
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'view',
            $deliveryService
        );

        $deliveryService->load([
            'customer',
            'shipment.shipmentStatus',
            'trip.deliveryProvider',
            'trip.tripType',
            'payment',
            'rating',
        ]);

        return response()->json([
            'delivery_service' => $deliveryService,
        ]);
    }

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

    /**
     * Limita la consulta según el rol y la relación
     * del usuario con cada servicio.
     */
    private function visibleServicesFor(
        User $user
    ): Builder {
        $query = DeliveryService::query();

        $roleName = $user->role()
            ->value('role_name');

        return match ($roleName) {
            /*
             * Soporte y administración pueden consultar
             * todos los servicios.
             */
            'SUPPORT_AGENT',
            'ADMINISTRATOR' => $query,

            /*
             * El cliente solamente consulta sus propios
             * servicios.
             */
            'CUSTOMER' => $query->whereHas(
                'customer',
                fn (Builder $customerQuery): Builder =>
                    $customerQuery->where(
                        'user_id',
                        $user->id
                    )
            ),

            /*
             * El proveedor consulta los servicios cuyo
             * viaje pertenece a su empresa.
             */
            'DELIVERY_PROVIDER' => $query->whereHas(
                'trip.deliveryProvider',
                fn (Builder $providerQuery): Builder =>
                    $providerQuery->where(
                        'user_id',
                        $user->id
                    )
            ),

            /*
             * El repartidor consulta los servicios de
             * envíos incluidos en sus rutas.
             */
            'COURIER' => $query->whereHas(
                'shipment.routeShipments.route.courier',
                fn (Builder $courierQuery): Builder =>
                    $courierQuery->where(
                        'user_id',
                        $user->id
                    )
            ),

            default => $query->whereRaw('1 = 0'),
        };
    }
}