<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShipmentRequest;
use App\Services\Shipment\CreateShipmentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ShipmentController extends Controller
{
    /**
     * Registra un nuevo envío para el cliente autenticado.
     *
     * StoreShipmentRequest valida los datos y comprueba
     * la autorización mediante ShipmentPolicy.
     */
    public function store(
        StoreShipmentRequest $request,
        CreateShipmentService $service
    ): JsonResponse {
        /*
         * El cliente se obtiene desde el usuario autenticado.
         * No aceptamos customer_id desde la petición.
         */
        $customer = $request->user()
            ->customer()
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
}
