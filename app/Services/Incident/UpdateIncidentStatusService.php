<?php

namespace App\Services\Incident;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\IncidentStatus;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpdateIncidentStatusService
{
    /**
     * Allowed incident status transitions.
     *
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_TRANSITIONS = [
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
     * Update an incident status and create its audit record.
     *
     * @throws DomainException
     */
    public function execute(
        Incident $incident,
        string $targetStatusName,
        User $performedBy,
        ?string $comment = null
    ): Incident {
        return DB::transaction(function () use (
            $incident,
            $targetStatusName,
            $performedBy,
            $comment
        ): Incident {
            $lockedIncident = Incident::query()
                ->whereKey($incident->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = IncidentStatus::query()
                ->whereKey($lockedIncident->incident_status_id)
                ->firstOrFail();

            $normalizedTargetStatus = strtoupper(
                trim($targetStatusName)
            );

            $targetStatus = IncidentStatus::query()
                ->where('status_name', $normalizedTargetStatus)
                ->first();

            if ($targetStatus === null) {
                throw new DomainException(
                    'The selected incident status does not exist.'
                );
            }

            if ($currentStatus->status_name === $targetStatus->status_name) {
                throw new DomainException(
                    'The incident is already in the requested status.'
                );
            }

            $allowedTargets = self::ALLOWED_TRANSITIONS[
                $currentStatus->status_name
            ] ?? [];

            if (! in_array(
                $targetStatus->status_name,
                $allowedTargets,
                true
            )) {
                throw new DomainException(
                    sprintf(
                        'The incident status transition from %s to %s is not allowed.',
                        $currentStatus->status_name,
                        $targetStatus->status_name
                    )
                );
            }

            $normalizedComment = $comment === null
                ? null
                : trim($comment);

            if (
                $targetStatus->status_name === 'RESOLVED'
                && ($normalizedComment === null || $normalizedComment === '')
            ) {
                throw new DomainException(
                    'A comment is required to resolve an incident.'
                );
            }

            if (
                $currentStatus->status_name === 'RESOLVED'
                && $targetStatus->status_name === 'IN_REVIEW'
                && ($normalizedComment === null || $normalizedComment === '')
            ) {
                throw new DomainException(
                    'A comment is required to reopen a resolved incident.'
                );
            }

            $lockedIncident->update([
                'incident_status_id' => $targetStatus->id,
            ]);

            AuditLog::query()->create([
                'performed_by_user_id' => $performedBy->getKey(),
                'table_name' => 'incidents',
                'record_id' => $lockedIncident->id,
                'action_type' => 'STATUS_CHANGED',
                'details' => [
                    'from_status' => $currentStatus->status_name,
                    'to_status' => $targetStatus->status_name,
                    'comment' => $normalizedComment !== ''
                        ? $normalizedComment
                        : null,
                ],
                'performed_at' => now(),
            ]);

            return $lockedIncident->fresh([
                'shipment',
                'reportedBy',
                'incidentType',
                'incidentStatus',
            ]);
        }, attempts: 3);
    }
}

