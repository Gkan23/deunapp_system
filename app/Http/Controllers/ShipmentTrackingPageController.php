<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShipmentTrackingPageController extends Controller
{
    /**
     * Muestra la página de seguimiento del envío.
     */
    public function __invoke(
        Request $request,
        Shipment $shipment
    ): View {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'track',
            $shipment
        );

        $shipment->load([
            'shipmentStatus',
            'originAddress.municipality',
            'destinationAddress.municipality',
            'sender',
            'recipient',
        ]);

        return view('shipments.tracking', [
            'user' => $user,
            'shipment' => $shipment,
            'mapboxToken' => (string) config(
                'services.mapbox.public_token',
                ''
            ),
            'trackingDataUrl' => route(
                'shipments.tracking',
                $shipment
            ),
        ]);
    }
}