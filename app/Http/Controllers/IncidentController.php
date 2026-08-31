<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\UpdateIncidentStatusRequest;
use App\Models\Incident;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Incident\CreateIncidentService;
use App\Services\Incident\UpdateIncidentStatusService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class IncidentController extends Controller
{
    /**
     * Relaciones necesarias para presentar una incidencia.
     *
     * @var array<int, string>
     */
    private const RELATIONS = [
        'reportedBy',
        'incidentType',
        'incidentStatus',
        'shipment.customer',
        'shipment.deliveryService.trip.deliveryProvider',
    ];

    /**
     * Mostrar las incidencias visibles.
     */
    public function index(
        Request $request
    ): JsonResponse {
        Gate::forUser(
            $request->user()
        )->authorize(
            'viewAny',
            Incident::class
        );

        $incidents = $this->visibleIncidentsFor(
            $request->user()
        )
            ->with(self::RELATIONS)
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $incidents,
        ]);
    }

    /**
     * Registra una incidencia para un envío.
     */
    public function store(
        StoreIncidentRequest $request,
        Shipment $shipment,
        CreateIncidentService $service
    ): JsonResponse {
        /*
         * El Form Request comprueba que el rol pueda
         * crear incidencias. Esta segunda autorización
         * comprueba que pueda consultar el envío.
         */
        Gate::forUser(
            $request->user()
        )->authorize(
            'view',
            $shipment
        );

        $validated = $request->validated();

        try {
            $incident = $service->execute(
                shipment: $shipment,
                reportedBy: $request->user(),
                incidentTypeName:
                    $validated['incident_type'],
                description:
                    $validated['description']
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' =>
                    $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' =>
                'Incident created successfully.',
            'incident' => $incident,
        ], Response::HTTP_CREATED);
    }

    /**
     * Mostrar una incidencia específica.
     */
    public function show(
        Request $request,
        Incident $incident
    ): JsonResponse {
        Gate::forUser(
            $request->user()
        )->authorize(
            'view',
            $incident
        );

        $incident->load(self::RELATIONS);

        return response()->json([
            'incident' => $incident,
        ]);
    }

    /**
     * Cambiar el estado de una incidencia.
     */
    public function updateStatus(
        UpdateIncidentStatusRequest $request,
        Incident $incident,
        UpdateIncidentStatusService $service
    ): JsonResponse {
        $targetStatus = $request->validated(
            'status'
        );

        $ability = match ($targetStatus) {
            'IN_REVIEW' => 'review',
            'RESOLVED' => 'resolve',
            'CLOSED' => 'close',
            default => 'review',
        };

        Gate::forUser(
            $request->user()
        )->authorize(
            $ability,
            $incident
        );

        try {
            $updatedIncident = $service->execute(
                incident: $incident,
                targetStatusName:
                    $targetStatus,
                performedBy:
                    $request->user(),
                comment:
                    $request->validated(
                        'comment'
                    )
            );

            return response()->json([
                'message' =>
                    'Incident status updated successfully.',
                'incident' =>
                    $updatedIncident,
            ]);
        } catch (DomainException $exception) {
            return response()->json([
                'message' =>
                    $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Construye la consulta según el usuario.
     */
    private function visibleIncidentsFor(
        User $user
    ): Builder {
        $query = Incident::query();

        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return $query;
        }

        if ($this->hasRole(
            $user,
            'CUSTOMER'
        )) {
            return $query->whereHas(
                'shipment.customer',
                fn (
                    Builder $customerQuery
                ): Builder =>
                    $customerQuery->where(
                        'user_id',
                        $user->id
                    )
            );
        }

        if ($this->hasRole(
            $user,
            'DELIVERY_PROVIDER'
        )) {
            return $query->whereHas(
                'shipment.deliveryService.trip.deliveryProvider',
                fn (
                    Builder $providerQuery
                ): Builder =>
                    $providerQuery->where(
                        'user_id',
                        $user->id
                    )
            );
        }

        if ($this->hasRole(
            $user,
            'COURIER'
        )) {
            return $query->whereHas(
                'shipment.routeShipments.route.courier',
                fn (
                    Builder $courierQuery
                ): Builder =>
                    $courierQuery->where(
                        'user_id',
                        $user->id
                    )
            );
        }

        return $query->whereRaw('1 = 0');
    }

    private function hasRole(
        User $user,
        string $role
    ): bool {
        return $user->role()
            ->where(
                'role_name',
                $role
            )
            ->exists();
    }

    /**
     * @param array<int, string> $roles
     */
    private function hasAnyRole(
        User $user,
        array $roles
    ): bool {
        return $user->role()
            ->whereIn(
                'role_name',
                $roles
            )
            ->exists();
    }
}