<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\IncidentStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IncidentShowPageController extends Controller
{
    /**
     * Transiciones utilizadas para presentar el formulario.
     *
     * UpdateIncidentStatusService realiza la validación
     * definitiva al guardar.
     *
     * @var array<string, array<int, string>>
     */
    private const STATUS_TRANSITIONS = [
        'OPEN' => [
            'IN_REVIEW',
        ],
        'IN_REVIEW' => [
            'RESOLVED',
        ],
        'RESOLVED' => [
            'IN_REVIEW',
            'CLOSED',
        ],
        'CLOSED' => [],
    ];

    /**
     * Muestra el detalle de una incidencia.
     */
    public function __invoke(
        Request $request,
        Incident $incident
    ): View {
        Gate::forUser($request->user())->authorize(
            'view',
            $incident
        );

        $user = $request->user()->loadMissing([
            'role',
            'accountStatus',
        ]);

        $incident->load([
            'shipment',
            'reportedBy',
            'incidentType',
            'incidentStatus',
        ]);

        $currentStatusName = $incident
            ->incidentStatus
            ?->status_name ?? 'UNKNOWN';

        $allowedTargets = self::STATUS_TRANSITIONS[
            $currentStatusName
        ] ?? [];

        $canManageStatus =
            Gate::forUser($user)->allows(
                'review',
                $incident
            )
            || Gate::forUser($user)->allows(
                'resolve',
                $incident
            )
            || Gate::forUser($user)->allows(
                'close',
                $incident
            );

        $availableStatuses = IncidentStatus::query()
            ->whereIn(
                'status_name',
                $allowedTargets
            )
            ->orderBy('id')
            ->get()
            ->filter(
                function (
                    IncidentStatus $status
                ) use ($user, $incident): bool {
                    $ability = match ($status->status_name) {
                        'IN_REVIEW' => 'review',
                        'RESOLVED' => 'resolve',
                        'CLOSED' => 'close',
                        default => null,
                    };

                    return $ability !== null
                        && Gate::forUser($user)->allows(
                            $ability,
                            $incident
                        );
                }
            )
            
            ->values();

        return view(
            'incidents.show',
            [
                'user' => $user,
                'roleName' => $user->role?->role_name,
                'incident' => $incident,
                'currentStatusName' => $currentStatusName,
                'canManageStatus' => $canManageStatus,
                'availableStatuses' => $availableStatuses,
            ]
        );
    }
}