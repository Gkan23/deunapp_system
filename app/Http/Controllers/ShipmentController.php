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
use App\Services\Shipment\VisibleShipmentsQuery;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ShipmentController extends Controller
{
    /**
     * Muestra los envíos visibles para el usuario.
     */
    public function index(
        Request $request,
        VisibleShipmentsQuery $visibleShipments
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'viewAny',
            Shipment::class
        );

        $shipments = $visibleShipments
            ->for($user)
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
            'message' =>
                'Shipment created successfully.',
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
            return response()->json([
                'message' =>
                    $exception->getMessage(),
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

        $cancelledStatus = ShipmentStatus::query()
            ->where(
                'status_name',
                'CANCELLED'
            )
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
                'message' =>
                    $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' =>
                'Shipment cancelled successfully.',
            'shipment' => $cancelledShipment,
        ]);
    }
}