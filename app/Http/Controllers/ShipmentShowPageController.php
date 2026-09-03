<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShipmentShowPageController extends Controller
{
    /**
     * Muestra el detalle Blade de un envío.
     */
    public function __invoke(
        Request $request,
        Shipment $shipment
    ): View {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'view',
            $shipment
        );

        $shipment->load([
            'customer.user',
            'shipmentStatus',
            'sender',
            'recipient',
            'originAddress.municipality',
            'destinationAddress.municipality',
            'packages',
            'statusHistory.shipmentStatus',
            'statusHistory.changedBy',
            'deliveryService.serviceType',
            'deliveryService.tripType',
            'deliveryService.trip.deliveryProvider',
            'routeShipments.route.routeStatus',
            'routeShipments.route.courier.user',
            'deliveryProof',
        ]);

        return view('shipments.show', [
            'user' => $user,
            'shipment' => $shipment,
        ]);
    }
}