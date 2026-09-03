<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePortalShipmentRequest;
use App\Services\Shipment\CreateShipmentFromPortalService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class PortalShipmentController extends Controller
{
    /**
     * Registra un envío desde el formulario Blade.
     */
    public function store(
        StorePortalShipmentRequest $request,
        CreateShipmentFromPortalService $service
    ): RedirectResponse {
        $customer = $request
            ->user()
            ->customer()
            ->firstOrFail();

        try {
            $shipment = $service->handle(
                $customer,
                $request->validated()
            );
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'shipment' =>
                        $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'portal.shipments.show',
                $shipment
            )
            ->with(
                'status',
                'El envío fue registrado correctamente.'
            );
    }
}