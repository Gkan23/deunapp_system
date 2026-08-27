<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivateRouteRequest;
use App\Http\Requests\StoreRouteRequest;
use App\Models\Courier;
use App\Models\Route;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Route\ActivateRouteService;
use App\Services\Route\CreateRouteService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RouteController extends Controller
{
    /**
     * Crea una ruta planificada con sus envíos.
     */
    public function store(
        StoreRouteRequest $request,
        CreateRouteService $service
    ): JsonResponse {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        $courier = Courier::query()
            ->findOrFail(
                $validated['courier_id']
            );

        /*
         * Un proveedor solamente puede crear rutas
         * utilizando repartidores de su empresa.
         */
        if (
            ! $this->canUseCourier(
                $user,
                $courier
            )
        ) {
            abort(
                Response::HTTP_FORBIDDEN,
                'You cannot create a route for this courier.'
            );
        }

        /*
         * Recupera los envíos y conserva el orden
         * indicado en shipment_ids.
         */
        $shipmentsById = Shipment::query()
            ->whereIn(
                'id',
                $validated['shipment_ids']
            )
            ->get()
            ->keyBy('id');

        $shipments = collect(
            $validated['shipment_ids']
        )
            ->map(
                fn (int $shipmentId): Shipment =>
                    $shipmentsById->get(
                        $shipmentId
                    )
            )
            ->all();

        $routeDate = CarbonImmutable::parse(
            $validated['route_date']
        )->startOfDay();

        $estimatedDistance =
            isset(
                $validated[
                    'estimated_distance_km'
                ]
            )
                ? (float) $validated[
                    'estimated_distance_km'
                ]
                : null;

        try {
            $createdRoute = $service->handle(
                $courier,
                $shipments,
                $routeDate,
                $estimatedDistance
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' =>
                'Route created successfully.',
            'route' => $createdRoute,
        ], Response::HTTP_CREATED);
    }

    /**
     * Activa una ruta planificada.
     */
    public function activate(
        ActivateRouteRequest $request,
        Route $route,
        ActivateRouteService $service
    ): JsonResponse {
        try {
            $activatedRoute = $service->handle(
                $route
            );
        } catch (DomainException $exception) {
            /*
             * Las condiciones operativas inválidas,
             * como activar una ruta de otro día,
             * producen una respuesta 422.
             */
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' =>
                'Route activated successfully.',
            'route' => $activatedRoute,
        ]);
    }

    /**
     * Comprueba que el usuario pueda utilizar
     * el repartidor seleccionado.
     */
    private function canUseCourier(
        User $user,
        Courier $courier
    ): bool {
        $isAdministrator = $user->role()
            ->where(
                'role_name',
                'ADMINISTRATOR'
            )
            ->exists();

        if ($isAdministrator) {
            return true;
        }

        $isProvider = $user->role()
            ->where(
                'role_name',
                'DELIVERY_PROVIDER'
            )
            ->exists();

        if (! $isProvider) {
            return false;
        }

        return $courier->deliveryProvider()
            ->where('user_id', $user->id)
            ->exists();
    }
}