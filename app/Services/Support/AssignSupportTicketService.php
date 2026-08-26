<?php

namespace App\Services\Support;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\SupportTicket;
use App\Models\TicketStatus;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class AssignSupportTicketService
{
    private const ASSIGNABLE_TICKET_STATUSES = [
        'OPEN',
        'IN_PROGRESS',
        'WAITING_CUSTOMER',
    ];

    private const ASSIGNEE_ROLES = [
        'SUPPORT_AGENT',
        'ADMINISTRATOR',
    ];

    /**
     * Assign or reassign a support ticket.
     *
     * @throws DomainException
     */
    public function execute(
        SupportTicket $ticket,
        User $assignee,
        User $performedBy
    ): SupportTicket {
        return DB::transaction(function () use (
            $ticket,
            $assignee,
            $performedBy
        ): SupportTicket {
            $lockedTicket = SupportTicket::query()
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = TicketStatus::query()
                ->whereKey($lockedTicket->ticket_status_id)
                ->firstOrFail();

            if (! in_array(
                $currentStatus->status_name,
                self::ASSIGNABLE_TICKET_STATUSES,
                true
            )) {
                throw new DomainException(
                    'Only open, in-progress, or waiting-customer tickets can be assigned.'
                );
            }

            $lockedPerformedBy = User::query()
                ->with('role')
                ->whereKey($performedBy->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAssignee = User::query()
                ->with('role')
                ->whereKey($assignee->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $activeStatus = AccountStatus::query()
                ->where('status_name', 'ACTIVE')
                ->firstOrFail();

            if (
                (int) $lockedPerformedBy->account_status_id
                !== (int) $activeStatus->id
            ) {
                throw new DomainException(
                    'Only an active user can assign support tickets.'
                );
            }

            $performerRole = $lockedPerformedBy->role
                ?->role_name;

            if (! in_array(
                $performerRole,
                self::ASSIGNEE_ROLES,
                true
            )) {
                throw new DomainException(
                    'Only administrators and support agents can assign support tickets.'
                );
            }

            if (
                (int) $lockedAssignee->account_status_id
                !== (int) $activeStatus->id
            ) {
                throw new DomainException(
                    'Only an active user can be assigned to a support ticket.'
                );
            }

            $assigneeRole = $lockedAssignee->role
                ?->role_name;

            if (! in_array(
                $assigneeRole,
                self::ASSIGNEE_ROLES,
                true
            )) {
                throw new DomainException(
                    'The assigned user must have a support role.'
                );
            }

            if (
                $lockedTicket->assigned_to_user_id !== null
                && (int) $lockedTicket->assigned_to_user_id
                    === (int) $lockedAssignee->id
            ) {
                throw new DomainException(
                    'The support ticket is already assigned to this user.'
                );
            }

            /*
             * Administrators can assign or reassign any eligible ticket.
             *
             * Support agents can only claim an unassigned ticket
             * for themselves.
             */
            if ($performerRole === 'SUPPORT_AGENT') {
                $isSelfAssignment = (int) $lockedPerformedBy->id
                    === (int) $lockedAssignee->id;

                $isUnassigned = $lockedTicket
                    ->assigned_to_user_id === null;

                if (! $isSelfAssignment || ! $isUnassigned) {
                    throw new DomainException(
                        'Support agents can only claim unassigned tickets for themselves.'
                    );
                }
            }

            $previousAssigneeId = $lockedTicket
                ->assigned_to_user_id;

            $nextStatusName = $currentStatus->status_name === 'OPEN'
                ? 'IN_PROGRESS'
                : $currentStatus->status_name;

            $nextStatus = TicketStatus::query()
                ->where('status_name', $nextStatusName)
                ->firstOrFail();

            $lockedTicket->update([
                'assigned_to_user_id' => $lockedAssignee->id,
                'ticket_status_id' => $nextStatus->id,
                'closed_at' => null,
            ]);

            $assignedAt = now();

            AuditLog::query()->create([
                'performed_by_user_id' => $lockedPerformedBy->id,
                'table_name' => 'support_tickets',
                'record_id' => $lockedTicket->id,
                'action_type' => $previousAssigneeId === null
                    ? 'TICKET_ASSIGNED'
                    : 'TICKET_REASSIGNED',
                'details' => [
                    'previous_assignee_id' => $previousAssigneeId,
                    'new_assignee_id' => $lockedAssignee->id,
                    'performed_by_role' => $performerRole,
                    'assignee_role' => $assigneeRole,
                    'from_status' => $currentStatus->status_name,
                    'to_status' => $nextStatus->status_name,
                ],
                'performed_at' => $assignedAt,
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

