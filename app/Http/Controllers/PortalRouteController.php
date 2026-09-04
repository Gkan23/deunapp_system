<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivateRouteRequest;
use App\Models\Route as DeliveryRoute;
use App\Services\Route\ActivateRouteService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class PortalRouteController extends Controller
{
    /**
     * Activar una ruta desde el portal Blade.
     *
     * ActivateRouteRequest comprueba la autorización.
     * ActivateRouteService aplica las reglas de negocio.
     */
    public function activate(
        ActivateRouteRequest $request,
        DeliveryRoute $route,
        ActivateRouteService $service
    ): RedirectResponse {
        try {
            $service->handle($route);
        } catch (DomainException $exception) {
            return redirect()
                ->route('portal.routes.show', $route)
                ->withErrors([
                    'activation' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('portal.routes.show', $route)
            ->with(
                'status',
                'La ruta fue activada correctamente.'
            );
    }
}