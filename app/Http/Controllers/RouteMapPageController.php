<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RouteMapPageController extends Controller
{
    /**
     * Muestra la página Blade del mapa de una ruta.
     */
    public function show(
        Request $request,
        Route $route
    ): View {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'view',
            $route
        );

        $route->load([
            'routeStatus',
            'courier.user',
            'vehicle.vehicleType',
            'vehicle.vehicleStatus',
        ]);

        return view('routes.map', [
            'deliveryRoute' => $route,
            'mapDataUrl' => route(
                'routes.map',
                $route
            ),
            'mapboxPublicToken' => (string) config(
                'services.mapbox.public_token',
                ''
            ),
        ]);
    }
}