<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\User;
use App\Services\Route\GetRouteMapDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RouteMapController extends Controller
{
    /**
     * Devuelve los datos geográficos de una ruta.
     */
    public function show(
        Request $request,
        Route $route,
        GetRouteMapDataService $service
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'view',
            $route
        );

        return response()->json([
            'data' => $service->execute(
                $route
            ),
        ]);
    }
}