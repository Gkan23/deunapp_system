<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\IncidentStatus;
use App\Models\IncidentType;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IncidentIndexPageController extends Controller
{
    /**
     * Muestra los incidentes visibles para el usuario.
     */
    public function __invoke(
        Request $request
    ): View {
        Gate::authorize(
            'viewAny',
            Incident::class
        );

        $user = $request->user()->loadMissing([
            'role',
            'accountStatus',
        ]);

        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:200',
            ],
            'status' => [
                'nullable',
                'string',
                Rule::exists(
                    'incident_statuses',
                    'status_name'
                ),
            ],
            'type' => [
                'nullable',
                'string',
                Rule::exists(
                    'incident_types',
                    'type_name'
                ),
            ],
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
        ]);

        $search = trim(
            $validated['search'] ?? ''
        );

        $selectedStatus = $validated['status'] ?? '';
        $selectedType = $validated['type'] ?? '';

        $visibleQuery = $this->visibleIncidentsFor(
            $user
        );

        $totalIncidents = (
            clone $visibleQuery
        )->count();

        $pendingIncidents = (
            clone $visibleQuery
        )
            ->whereHas(
                'incidentStatus',
                fn (Builder $query) => $query->whereIn(
                    'status_name',
                    [
                        'OPEN',
                        'IN_REVIEW',
                    ]
                )
            )
            ->count();

        $finishedIncidents = (
            clone $visibleQuery
        )
            ->whereHas(
                'incidentStatus',
                fn (Builder $query) => $query->whereIn(
                    'status_name',
                    [
                        'RESOLVED',
                        'CLOSED',
                    ]
                )
            )
            ->count();

        $incidentsQuery = (clone $visibleQuery)
            ->with([
                'shipment:id,tracking_code',
                'reportedBy:id,name',
                'incidentType:id,type_name',
                'incidentStatus:id,status_name',
            ])
            ->latest('reported_at')
            ->latest('id');

        /*
         * La búsqueda se agrupa para que el OR
         * no permita consultar incidentes ajenos.
         */
        if ($search !== '') {
            $incidentsQuery->where(
                function (
                    Builder $query
                ) use ($search): void {
                    $query
                        ->where(
                            'description',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhereHas(
                            'shipment',
                            fn (Builder $shipmentQuery) =>
                                $shipmentQuery->where(
                                    'tracking_code',
                                    'like',
                                    '%'.$search.'%'
                                )
                        );
                }
            );
        }

        if ($selectedStatus !== '') {
            $incidentsQuery->whereHas(
                'incidentStatus',
                fn (Builder $query) => $query->where(
                    'status_name',
                    $selectedStatus
                )
            );
        }

        if ($selectedType !== '') {
            $incidentsQuery->whereHas(
                'incidentType',
                fn (Builder $query) => $query->where(
                    'type_name',
                    $selectedType
                )
            );
        }

        $incidents = $incidentsQuery
            ->paginate(15)
            ->withQueryString();

        return view(
            'incidents.index',
            [
                'user' => $user,
                'roleName' => $user->role?->role_name,
                'incidents' => $incidents,
                'statuses' => IncidentStatus::query()
                    ->orderBy('status_name')
                    ->get(),
                'types' => IncidentType::query()
                    ->orderBy('type_name')
                    ->get(),
                'search' => $search,
                'selectedStatus' => $selectedStatus,
                'selectedType' => $selectedType,
                'totalIncidents' => $totalIncidents,
                'pendingIncidents' => $pendingIncidents,
                'finishedIncidents' => $finishedIncidents,
            ]
        );
    }

    /**
     * Reproduce el alcance de consulta del backend.
     */
    private function visibleIncidentsFor(
        User $user
    ): Builder {
        $query = Incident::query();

        $roleName = $user->role?->role_name;

        return match ($roleName) {
            'SUPPORT_AGENT',
            'ADMINISTRATOR' => $query,

            'CUSTOMER' => $query->whereHas(
                'shipment.customer',
                fn (Builder $customerQuery) =>
                    $customerQuery->where(
                        'user_id',
                        $user->id
                    )
            ),

            'DELIVERY_PROVIDER' => $query->whereHas(
                'shipment.deliveryService.trip.deliveryProvider',
                fn (Builder $providerQuery) =>
                    $providerQuery->where(
                        'user_id',
                        $user->id
                    )
            ),

            'COURIER' => $query->whereHas(
                'shipment.routeShipments.route.courier',
                fn (Builder $courierQuery) =>
                    $courierQuery->where(
                        'user_id',
                        $user->id
                    )
            ),

            default => $query->whereRaw('1 = 0'),
        };
    }
}