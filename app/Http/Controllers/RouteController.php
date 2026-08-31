<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivateRouteRequest;
use App\Http\Requests\CancelRouteRequest;
use App\Http\Requests\CompleteRouteRequest;
use App\Http\Requests\StoreRouteRequest;
use App\Models\Courier;
use App\Models\Route;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Route\ActivateRouteService;
use App\Services\Route\CancelRouteService;
use App\Services\Route\CompleteRouteService;
use App\Services\Route\CreateRouteService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class RouteController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'viewAny',
            Route::class
        );

        $routes = $this
            ->visibleRoutesFor($user)
            ->with([
                'courier.deliveryProvider',
                'vehicle.vehicleType',
                'vehicle.vehicleStatus',
                'routeStatus',
                'routeShipments.shipment.shipmentStatus',
            ])
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $routes,
        ]);
    }

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

        $vehicle = null;

        if (
            isset($validated['vehicle_id'])
        ) {
            $vehicle = Vehicle::query()
                ->findOrFail(
                    $validated['vehicle_id']
                );
        }

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
                $estimatedDistance,
                $vehicle
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' =>
                    $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' =>
                'Route created successfully.',
            'route' => $createdRoute,
        ], Response::HTTP_CREATED);
    }

    public function show(
        Request $request,
        Route $route
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'view',
            $route
        );

        $route->load([
            'courier.deliveryProvider',
            'vehicle.vehicleType',
            'vehicle.vehicleStatus',
            'routeStatus',
            'routeShipments.shipment.shipmentStatus',
        ]);

        return response()->json([
            'route' => $route,
        ]);
    }

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
            return response()->json([
                'message' =>
                    $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' =>
                'Route activated successfully.',
            'route' => $activatedRoute,
        ]);
    }

    public function complete(
        CompleteRouteRequest $request,
        Route $route,
        CompleteRouteService $service
    ): JsonResponse {
        try {
            $completedRoute = $service->execute(
                $route
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' =>
                    $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' =>
                'Route completed successfully.',
            'route' => $completedRoute,
        ]);
    }

    public function cancel(
        CancelRouteRequest $request,
        Route $route,
        CancelRouteService $service
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $cancelledRoute = $service->execute(
                $route,
                $request->user(),
                $validated['reason']
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' =>
                    $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' =>
                'Route cancelled successfully.',
            'route' => $cancelledRoute,
        ]);
    }

    private function visibleRoutesFor(
        User $user
    ): Builder {
        $query = Route::query();

        $roleName = $user->role()
            ->value('role_name');

        return match ($roleName) {
            'SUPPORT_AGENT',
            'ADMINISTRATOR' => $query,

            'DELIVERY_PROVIDER' =>
                $query->whereHas(
                    'courier.deliveryProvider',
                    fn (
                        Builder $providerQuery
                    ): Builder =>
                        $providerQuery->where(
                            'user_id',
                            $user->id
                        )
                ),

            'COURIER' =>
                $query->whereHas(
                    'courier',
                    fn (
                        Builder $courierQuery
                    ): Builder =>
                        $courierQuery->where(
                            'user_id',
                            $user->id
                        )
                ),

            default =>
                $query->whereRaw('1 = 0'),
        };
    }

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

        return $courier
            ->deliveryProvider()
            ->where(
                'user_id',
                $user->id
            )
            ->exists();
    }
}