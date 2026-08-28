<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateIncidentStatusRequest;
use App\Models\Incident;
use App\Models\User;
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
     * Mostrar las incidencias visibles para el usuario autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        /*
         * IncidentPolicy::viewAny() verifica que el rol
         * pueda acceder al listado de incidencias.
         */
        Gate::forUser($request->user())->authorize(
            'viewAny',
            Incident::class
        );

        /*
         * visibleIncidentsFor() limita los resultados según
         * el cliente, proveedor, repartidor o personal interno.
         */
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
     * Mostrar una incidencia específica.
     */
    public function show(
        Request $request,
        Incident $incident
    ): JsonResponse {
        /*
         * IncidentPolicy::view() verifica que el usuario
         * esté relacionado con la incidencia.
         */
        Gate::forUser($request->user())->authorize(
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
        /*
         * En este punto los datos ya fueron validados por
         * UpdateIncidentStatusRequest.
         */
        $targetStatus = $request->validated('status');

        /*
         * Cada estado utiliza una ability específica
         * definida en IncidentPolicy.
         */
        $ability = match ($targetStatus) {
            'IN_REVIEW' => 'review',
            'RESOLVED' => 'resolve',
            'CLOSED' => 'close',

            /*
             * OPEN existe en el catálogo, pero no representa
             * una acción directa del controlador. El permiso
             * mínimo será review y el servicio comprobará
             * si la transición solicitada es válida.
             */
            default => 'review',
        };

        /*
         * Esta autorización ocurre después de la validación.
         *
         * Soporte y administración pueden continuar.
         * Cliente, proveedor y repartidor reciben 403.
         */
        Gate::forUser($request->user())->authorize(
            $ability,
            $incident
        );

        try {
            /*
             * El servicio valida la transición y crea
             * el registro de auditoría dentro de una transacción.
             */
            $updatedIncident = $service->execute(
                incident: $incident,
                targetStatusName: $targetStatus,
                performedBy: $request->user(),
                comment: $request->validated('comment')
            );

            return response()->json([
                'message' => 'Incident status updated successfully.',
                'incident' => $updatedIncident,
            ]);
        } catch (DomainException $exception) {
            /*
             * Las reglas de negocio inválidas se devuelven
             * como respuestas HTTP 422.
             */
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Construir la consulta según el rol y las relaciones
     * del usuario autenticado.
     */
    private function visibleIncidentsFor(User $user): Builder
    {
        $query = Incident::query();

        /*
         * Soporte y administración pueden consultar
         * todas las incidencias.
         */
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return $query;
        }

        /*
         * El cliente solamente puede consultar incidencias
         * pertenecientes a sus envíos.
         */
        if ($this->hasRole($user, 'CUSTOMER')) {
            return $query->whereHas(
                'shipment.customer',
                fn (Builder $customerQuery): Builder =>
                    $customerQuery->where(
                        'user_id',
                        $user->id
                    )
            );
        }

        /*
         * El proveedor solamente puede consultar incidencias
         * de servicios asociados a sus viajes.
         */
        if ($this->hasRole($user, 'DELIVERY_PROVIDER')) {
            return $query->whereHas(
                'shipment.deliveryService.trip.deliveryProvider',
                fn (Builder $providerQuery): Builder =>
                    $providerQuery->where(
                        'user_id',
                        $user->id
                    )
            );
        }

        /*
         * El repartidor solamente puede consultar incidencias
         * de envíos incluidos en sus rutas.
         */
        if ($this->hasRole($user, 'COURIER')) {
            return $query->whereHas(
                'shipment.routeShipments.route.courier',
                fn (Builder $courierQuery): Builder =>
                    $courierQuery->where(
                        'user_id',
                        $user->id
                    )
            );
        }

        /*
         * Si el usuario no pertenece a un rol admitido,
         * la consulta no devuelve resultados.
         */
        return $query->whereRaw('1 = 0');
    }

    /**
     * Comprobar un rol específico.
     */
    private function hasRole(
        User $user,
        string $role
    ): bool {
        return $user->role()
            ->where('role_name', $role)
            ->exists();
    }

    /**
     * Comprobar si el usuario tiene alguno de los roles.
     *
     * @param array<int, string> $roles
     */
    private function hasAnyRole(
        User $user,
        array $roles
    ): bool {
        return $user->role()
            ->whereIn('role_name', $roles)
            ->exists();
    }
}