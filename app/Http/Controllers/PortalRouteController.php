<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivateRouteRequest;
use App\Http\Requests\CancelRouteRequest;
use App\Http\Requests\CompleteRouteRequest;
use App\Models\Route as DeliveryRoute;
use App\Services\Route\ActivateRouteService;
use App\Services\Route\CancelRouteService;
use App\Services\Route\CompleteRouteService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class PortalRouteController extends Controller
{
    /**
     * Activar una ruta desde el portal Blade.
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
                ->route(
                    'portal.routes.show',
                    $route
                )
                ->withErrors([
                    'activation' =>
                        $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'portal.routes.show',
                $route
            )
            ->with(
                'status',
                'La ruta fue activada correctamente.'
            );
    }

    /**
     * Completar una ruta desde el portal Blade.
     */
    public function complete(
        CompleteRouteRequest $request,
        DeliveryRoute $route,
        CompleteRouteService $service
    ): RedirectResponse {
        try {
            $service->execute($route);
        } catch (DomainException $exception) {
            return redirect()
                ->route(
                    'portal.routes.show',
                    $route
                )
                ->withErrors([
                    'completion' =>
                        $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'portal.routes.show',
                $route
            )
            ->with(
                'status',
                'La ruta fue completada correctamente.'
            );
    }

    /**
     * Cancelar una ruta desde el portal Blade.
     */
    public function cancel(
        CancelRouteRequest $request,
        DeliveryRoute $route,
        CancelRouteService $service
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $service->execute(
                route: $route,
                cancelledBy: $request->user(),
                reason: $validated['reason']
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route(
                    'portal.routes.show',
                    $route
                )
                ->withInput()
                ->withErrors([
                    'cancellation' =>
                        $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'portal.routes.show',
                $route
            )
            ->with(
                'status',
                'La ruta fue cancelada correctamente.'
            );
    }
}