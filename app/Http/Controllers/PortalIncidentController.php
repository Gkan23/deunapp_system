<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\UpdateIncidentStatusRequest;
use App\Models\Incident;
use App\Models\Shipment;
use App\Services\Incident\CreateIncidentService;
use App\Services\Incident\UpdateIncidentStatusService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PortalIncidentController extends Controller
{
    /**
     * Registrar un incidente desde el formulario Blade.
     *
     * StoreIncidentRequest autoriza la creación de incidentes.
     * Aquí se comprueba además el acceso al envío seleccionado.
     */
    public function store(
        StoreIncidentRequest $request,
        Shipment $shipment,
        CreateIncidentService $service
    ): RedirectResponse {
        Gate::forUser($request->user())->authorize(
            'view',
            $shipment
        );

        $validated = $request->validated();

        try {
            $incident = $service->execute(
                shipment: $shipment,
                reportedBy: $request->user(),
                incidentTypeName: $validated['incident_type'],
                description: $validated['description']
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route(
                    'portal.shipments.incidents.create',
                    $shipment
                )
                ->withInput(
                    $request->only([
                        'incident_type',
                        'description',
                    ])
                )
                ->withErrors([
                    'incident' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'portal.incidents.show',
                $incident
            )
            ->with(
                'status',
                'El incidente fue reportado correctamente.'
            );
    }

    /**
     * Actualizar el estado desde el detalle del incidente.
     */
    public function updateStatus(
        UpdateIncidentStatusRequest $request,
        Incident $incident,
        UpdateIncidentStatusService $service
    ): RedirectResponse {
        $validated = $request->validated();

        $targetStatus = $validated['status'];

        $ability = match ($targetStatus) {
            'IN_REVIEW' => 'review',
            'RESOLVED' => 'resolve',
            'CLOSED' => 'close',
            default => 'review',
        };

        Gate::forUser($request->user())->authorize(
            $ability,
            $incident
        );

        try {
            $service->execute(
                incident: $incident,
                targetStatusName: $targetStatus,
                performedBy: $request->user(),
                comment: $validated['comment'] ?? null
            );
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'status' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'portal.incidents.show',
                $incident
            )
            ->with(
                'status',
                'El estado del incidente fue actualizado correctamente.'
            );
    }
}