<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShipmentRequest;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Shipment\CreateShipmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class ShipmentController extends Controller
{
    /**
     * Muestra únicamente los envíos visibles para
     * el usuario autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Shipment::class);

        $query = $this->visibleShipmentsFor(
            $request->user()
        );

        $shipments = $query
            ->with([
                'customer',
                'shipmentStatus',
                'originAddress.municipality',
                'destinationAddress.municipality',
            ])
            ->latest('requested_at')
            ->paginate(15);

        return response()->json($shipments);
    }

    /**
     * Registra un nuevo envío para el cliente autenticado.
     */
    public function store(
        StoreShipmentRequest $request,
        CreateShipmentService $service
    ): JsonResponse {
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

    /**
     * Muestra el detalle de un envío específico.
     *
     * ShipmentPolicy comprueba que el usuario esté
     * relacionado con el envío.
     */
    public function show(Shipment $shipment): JsonResponse
    {
        Gate::authorize('view', $shipment);

        $shipment->load([
            'customer',
            'sender',
            'recipient',
            'originAddress.municipality',
            'destinationAddress.municipality',
            'originBranch',
            'destinationBranch',
            'packages',
            'shipmentStatus',
            'statusHistory',
            'deliveryService.trip.deliveryProvider',
            'routeShipments.route.courier',
        ]);

        return response()->json([
            'shipment' => $shipment,
        ]);
    }

    /**
     * Construye la consulta según el rol autenticado.
     *
     * La autorización viewAny permite entrar al listado,
     * pero este filtro decide cuáles registros aparecen.
     */
    private function visibleShipmentsFor(
        User $user
    ): Builder {
        $query = Shipment::query();

        $roleName = $user->role()
            ->value('role_name');

        return match ($roleName) {
            'CUSTOMER' => $query->whereHas(
                'customer',
                fn (Builder $query) => $query->where(
                    'user_id',
                    $user->id
                )
            ),

            'DELIVERY_PROVIDER' => $query->whereHas(
                'deliveryService.trip.deliveryProvider',
                fn (Builder $query) => $query->where(
                    'user_id',
                    $user->id
                )
            ),

            'COURIER' => $query->whereHas(
                'routeShipments.route.courier',
                fn (Builder $query) => $query->where(
                    'user_id',
                    $user->id
                )
            ),

            'SUPPORT_AGENT',
            'ADMINISTRATOR' => $query,

            /*
             * Protección adicional para cualquier rol
             * inesperado o sin configurar.
             */
            default => $query->whereRaw('1 = 0'),
        };
    }
}

