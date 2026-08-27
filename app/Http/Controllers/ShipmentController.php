<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelShipmentRequest;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\UpdateShipmentStatusRequest;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\User;
use App\Services\Shipment\CreateShipmentService;
use App\Services\Shipment\UpdateShipmentStatusService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ShipmentController extends Controller
{
    /**
     * Muestra los envíos visibles para el usuario.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'viewAny',
            Shipment::class
        );

        /*
         * Shipment solamente contiene las relaciones
         * customer y shipmentStatus utilizadas aquí.
         *
         * No se incluye shipmentType porque esa relación
         * no existe en el modelo Shipment.
         */
        $shipments = $this->visibleShipmentsFor($user)
            ->with([
                'customer',
                'shipmentStatus',
            ])
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $shipments,
        ]);
    }

    /**
     * Registra un envío para el cliente autenticado.
     */
    public function store(
        StoreShipmentRequest $request,
        CreateShipmentService $service
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $customer = $user->customer()
            ->firstOrFail();

        $shipment = $service->handle(
            $customer,
            $request->validated()
        );

        return response()->json([
            'message' => 'Shipment created successfully.',
            'shipment' => $shipment,
        ], Response::HTTP_CREATED);
    }

    /**
     * Muestra un envío específico.
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

        /*
         * Solamente se cargan relaciones que realmente
         * existen en el modelo Shipment.
         */
        $shipment->load([
            'customer',
            'shipmentStatus',
        ]);

        return response()->json([
            'shipment' => $shipment,
        ]);
    }

    /**
     * Actualiza el estado de un envío.
     */
    public function updateStatus(
        UpdateShipmentStatusRequest $request,
        Shipment $shipment,
        UpdateShipmentStatusService $service
    ): JsonResponse {
        $validated = $request->validated();

        /*
         * UpdateShipmentStatusRequest ya comprobó que
         * el estado recibido existe en la base de datos.
         */
        $newStatus = ShipmentStatus::query()
            ->findOrFail(
                $validated['shipment_status_id']
            );

        try {
            $updatedShipment = $service->handle(
                $shipment,
                $newStatus,
                $request->user(),
                $validated['comment'] ?? null
            );
        } catch (DomainException $exception) {
            /*
             * Las transiciones inválidas son errores de
             * dominio y responden con HTTP 422.
             */
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' =>
                'Shipment status updated successfully.',
            'shipment' => $updatedShipment,
        ]);
    }

    /**
     * Cancela un envío.
     */
    public function cancel(
        CancelShipmentRequest $request,
        Shipment $shipment,
        UpdateShipmentStatusService $service
    ): JsonResponse {
        $validated = $request->validated();

        /*
         * Como este endpoint representa una cancelación,
         * el estado CANCELLED se obtiene del catálogo.
         */
        $cancelledStatus = ShipmentStatus::query()
            ->where('status_name', 'CANCELLED')
            ->firstOrFail();

        try {
            $cancelledShipment = $service->handle(
                $shipment,
                $cancelledStatus,
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
                'Shipment cancelled successfully.',
            'shipment' => $cancelledShipment,
        ]);
    }

    /**
     * Construye la consulta de envíos visibles
     * según el rol del usuario autenticado.
     */
    private function visibleShipmentsFor(
        User $user
    ): Builder {
        $query = Shipment::query();

        $roleName = $user->role()
            ->value('role_name');

        return match ($roleName) {
            /*
             * Administración y soporte pueden consultar
             * todos los envíos.
             */
            'ADMINISTRATOR',
            'SUPPORT_AGENT' => $query,

            /*
             * El cliente solamente consulta los envíos
             * asociados con su perfil.
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
             * El proveedor solamente consulta envíos
             * vinculados con uno de sus viajes.
             */
            'DELIVERY_PROVIDER' => $query->whereHas(
                'deliveryService.trip.deliveryProvider',
                fn (Builder $providerQuery): Builder =>
                    $providerQuery->where(
                        'user_id',
                        $user->id
                    )
            ),

            /*
             * El repartidor solamente consulta envíos
             * agregados a una de sus rutas.
             */
            'COURIER' => $query->whereHas(
                'routeShipments.route.courier',
                fn (Builder $courierQuery): Builder =>
                    $courierQuery->where(
                        'user_id',
                        $user->id
                    )
            ),

            /*
             * Un rol desconocido no recibe registros.
             */
            default => $query->whereRaw('1 = 0'),
        };
    }
}
