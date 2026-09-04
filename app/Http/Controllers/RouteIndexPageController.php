<?php

namespace App\Http\Controllers;

use App\Models\Route as DeliveryRoute;
use App\Models\RouteStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RouteIndexPageController extends Controller
{
    /**
     * Mostrar las rutas accesibles para el usuario.
     */
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'viewAny',
            DeliveryRoute::class
        );

        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'status' => [
                'nullable',
                'string',
                Rule::exists(
                    'route_statuses',
                    'status_name'
                ),
            ],
            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when(
                    $request->filled('date_from'),
                    ['after_or_equal:date_from']
                ),
            ],
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
        ]);

        $user->loadMissing([
            'role',
            'accountStatus',
        ]);

        $search = trim($validated['search'] ?? '');
        $selectedStatus = $validated['status'] ?? '';
        $dateFrom = $validated['date_from'] ?? '';
        $dateTo = $validated['date_to'] ?? '';

        $visibleQuery = $this->visibleRoutesFor($user);

        /*
         * Los contadores representan todas las rutas
         * accesibles, antes de aplicar los filtros.
         */
        $totalRoutes = (clone $visibleQuery)->count();

        $plannedRoutes = (clone $visibleQuery)
            ->whereHas(
                'routeStatus',
                fn (Builder $query) => $query->where(
                    'status_name',
                    'PLANNED'
                )
            )
            ->count();

        $activeRoutes = (clone $visibleQuery)
            ->whereHas(
                'routeStatus',
                fn (Builder $query) => $query->where(
                    'status_name',
                    'ACTIVE'
                )
            )
            ->count();

        $query = (clone $visibleQuery)
            ->with([
                'routeStatus',
                'courier.user',
                'courier.deliveryProvider.user',
                'vehicle.vehicleType',
            ])
            ->withCount('routeShipments');

        if ($search !== '') {
            /*
             * Agrupar los OR evita que la búsqueda
             * salga del alcance autorizado.
             */
            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->whereHas(
                        'courier.user',
                        fn (Builder $userQuery) => $userQuery->where(
                            'name',
                            'like',
                            '%'.$search.'%'
                        )
                    )
                    ->orWhereHas(
                        'vehicle',
                        fn (Builder $vehicleQuery) => $vehicleQuery->where(
                            'plate_number',
                            'like',
                            '%'.$search.'%'
                        )
                    );

                if (ctype_digit($search)) {
                    $query->orWhere('id', $search);
                }
            });
        }

        if ($selectedStatus !== '') {
            $query->whereHas(
                'routeStatus',
                fn (Builder $statusQuery) => $statusQuery->where(
                    'status_name',
                    $selectedStatus
                )
            );
        }

        if ($dateFrom !== '') {
            $query->whereDate(
                'route_date',
                '>=',
                $dateFrom
            );
        }

        if ($dateTo !== '') {
            $query->whereDate(
                'route_date',
                '<=',
                $dateTo
            );
        }

        $routes = $query
            ->orderByDesc('route_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('routes.index', [
            'user' => $user,
            'roleName' => $user->role?->role_name,
            'routes' => $routes,
            'statuses' => RouteStatus::query()
                ->orderBy('id')
                ->get(),
            'search' => $search,
            'selectedStatus' => $selectedStatus,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'totalRoutes' => $totalRoutes,
            'plannedRoutes' => $plannedRoutes,
            'activeRoutes' => $activeRoutes,
        ]);
    }

    /**
     * Mantener el mismo alcance del controlador JSON.
     */
    private function visibleRoutesFor(User $user): Builder
    {
        $query = DeliveryRoute::query();

        $roleName = $user->role?->role_name;

        return match ($roleName) {
            'SUPPORT_AGENT',
            'ADMINISTRATOR' => $query,

            'DELIVERY_PROVIDER' => $query->whereHas(
                'courier.deliveryProvider',
                fn (Builder $providerQuery) => $providerQuery->where(
                    'user_id',
                    $user->id
                )
            ),

            'COURIER' => $query->whereHas(
                'courier',
                fn (Builder $courierQuery) => $courierQuery->where(
                    'user_id',
                    $user->id
                )
            ),

            default => $query->whereRaw('1 = 0'),
        };
    }
}