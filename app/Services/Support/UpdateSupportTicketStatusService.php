<?php

namespace App\Services\Support;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\SupportTicket;
use App\Models\TicketStatus;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpdateSupportTicketStatusService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'OPEN' => [
            'IN_PROGRESS',
        ],
        'IN_PROGRESS' => [
            'WAITING_CUSTOMER',
            'RESOLVED',
        ],
        'WAITING_CUSTOMER' => [
            'IN_PROGRESS',
            'RESOLVED',
        ],
        'RESOLVED' => [
            'IN_PROGRESS',
            'CLOSED',
        ],
        'CLOSED' => [],
    ];

    private const ACTIVE_WORKFLOW_STATUSES = [
        'IN_PROGRESS',
        'WAITING_CUSTOMER',
        'RESOLVED',
    ];

    /**
     * Update a support ticket status.
     *
     * @throws DomainException
     */
    public function execute(
        SupportTicket $ticket,
        string $targetStatusName,
        User $performedBy,
        ?string $comment = null
    ): SupportTicket {
        return DB::transaction(function () use (
            $ticket,
            $targetStatusName,
            $performedBy,
            $comment
        ): SupportTicket {
            $lockedTicket = SupportTicket::query()
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = TicketStatus::query()
                ->whereKey($lockedTicket->ticket_status_id)
                ->firstOrFail();

            $normalizedTargetStatus = strtoupper(
                trim($targetStatusName)
            );

            $targetStatus = TicketStatus::query()
                ->where(
                    'status_name',
                    $normalizedTargetStatus
                )
                ->first();

            if ($targetStatus === null) {
                throw new DomainException(
                    'The selected support ticket status does not exist.'
                );
            }

            if (
                $currentStatus->status_name
                === $targetStatus->status_name
            ) {
                throw new DomainException(
                    'The support ticket is already in the requested status.'
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
                        'The support ticket status transition from %s to %s is not allowed.',
                        $currentStatus->status_name,
                        $targetStatus->status_name
                    )
                );
            }

            $lockedPerformedBy = User::query()
                ->with('role')
                ->whereKey($performedBy->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $activeAccountStatus = AccountStatus::query()
                ->where('status_name', 'ACTIVE')
                ->firstOrFail();

            if (
                (int) $lockedPerformedBy->account_status_id
                !== (int) $activeAccountStatus->id
            ) {
                throw new DomainException(
                    'Only an active user can update support ticket statuses.'
                );
            }

            $performerRole = $lockedPerformedBy->role
                ?->role_name;

            $isAdministrator = $performerRole
                === 'ADMINISTRATOR';

            $isAssignedSupportAgent = (
                $performerRole === 'SUPPORT_AGENT'
                && (int) $lockedTicket->assigned_to_user_id
                    === (int) $lockedPerformedBy->id
            );

            if (
                ! $isAdministrator
                && ! $isAssignedSupportAgent
            ) {
                throw new DomainException(
                    'Only administrators or the assigned support agent can update this ticket.'
                );
            }

            if (
                in_array(
                    $targetStatus->status_name,
                    self::ACTIVE_WORKFLOW_STATUSES,
                    true
                )
                && $lockedTicket->assigned_to_user_id === null
            ) {
                throw new DomainException(
                    'The support ticket must be assigned before entering an active workflow status.'
                );
            }

            $normalizedComment = $comment === null
                ? null
                : trim($comment);

            if ($normalizedComment === '') {
                $normalizedComment = null;
            }

            if (
                $targetStatus->status_name === 'RESOLVED'
                && $normalizedComment === null
            ) {
                throw new DomainException(
                    'A comment is required to resolve a support ticket.'
                );
            }

            if (
                $targetStatus->status_name === 'CLOSED'
                && $normalizedComment === null
            ) {
                throw new DomainException(
                    'A comment is required to close a support ticket.'
                );
            }

            if (
                $currentStatus->status_name === 'RESOLVED'
                && $targetStatus->status_name === 'IN_PROGRESS'
                && $normalizedComment === null
            ) {
                throw new DomainException(
                    'A comment is required to reopen a resolved support ticket.'
                );
            }

            $changedAt = now();

            $lockedTicket->update([
                'ticket_status_id' => $targetStatus->id,
                'closed_at' => $targetStatus->status_name
                    === 'CLOSED'
                        ? $changedAt
                        : null,
            ]);

            AuditLog::query()->create([
                'performed_by_user_id' => $lockedPerformedBy->id,
                'table_name' => 'support_tickets',
                'record_id' => $lockedTicket->id,
                'action_type' => 'TICKET_STATUS_CHANGED',
                'details' => [
                    'from_status' => $currentStatus->status_name,
                    'to_status' => $targetStatus->status_name,
                    'comment' => $normalizedComment,
                    'performed_by_role' => $performerRole,
                    'assigned_to_user_id' => $lockedTicket
                        ->assigned_to_user_id,
                ],
                'performed_at' => $changedAt,
            ]);

            return $lockedTicket->fresh([
                'customer',
                'shipment',
                'category',
                'status',
                'priority',
                'assignedTo.role',
                'messages',
            ]);
        }, attempts: 3);
    }
}

