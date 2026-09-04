<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateIncidentStatusRequest;
use App\Models\Incident;
use App\Services\Incident\UpdateIncidentStatusService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PortalIncidentController extends Controller
{
    /**
     * Cambia el estado desde el formulario Blade.
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