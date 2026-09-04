<?php

namespace App\Http\Controllers;

use App\Models\Route as DeliveryRoute;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RouteShowPageController extends Controller
{
    /**
     * Mostrar el detalle de una ruta sin modificar su estado.
     */
    public function __invoke(
        Request $request,
        DeliveryRoute $route
    ): View {
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'view',
            $route
        );

        $user->loadMissing([
            'role',
            'accountStatus',
        ]);

        $route->load([
            'routeStatus',
            'courier.user',
            'courier.deliveryProvider.user',
            'vehicle.vehicleType',
            'vehicle.vehicleStatus',
            'routeShipments' => fn ($query) => $query
                ->orderBy('delivery_order')
                ->orderBy('id'),
            'routeShipments.shipment',
        ]);

        /*
         * Se comprueba el acceso individual al envío.
         *
         * Los datos adicionales se cargan únicamente para
         * los envíos que el usuario puede consultar.
         */
        $stops = $route->routeShipments
            ->map(function ($assignment) use ($user): array {
                $shipment = $assignment->shipment;

                $canViewShipment = $shipment !== null
                    && Gate::forUser($user)->allows(
                        'view',
                        $shipment
                    );

                if ($canViewShipment) {
                    $shipment->loadMissing([
                        'shipmentStatus',
                        'recipient',
                        'destinationAddress.municipality',
                    ]);

                    $shipment->loadCount('packages');
                }

                return [
                    'assignment' => $assignment,
                    'shipment' => $canViewShipment
                        ? $shipment
                        : null,
                ];
            });

        return view('routes.show', [
            'user' => $user,
            'roleName' => $user->role?->role_name,
            'deliveryRoute' => $route,
            'stops' => $stops,
            'totalShipments' => $route->routeShipments->count(),
            'deliveredShipments' => $route->routeShipments
                ->where('delivery_status', 'DELIVERED')
                ->count(),
            'failedShipments' => $route->routeShipments
                ->where('delivery_status', 'FAILED')
                ->count(),
        ]);
    }
}