<?php

namespace App\Services\Support;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class MarkSupportMessagesAsReadService
{
    private const SUPPORT_ROLES = [
        'SUPPORT_AGENT',
        'ADMINISTRATOR',
    ];

    /**
     * Mark unread messages sent by the other participant as read.
     *
     * @return int Number of messages marked as read.
     *
     * @throws DomainException
     */
    public function execute(
        SupportTicket $ticket,
        User $reader
    ): int {
        return DB::transaction(function () use (
            $ticket,
            $reader
        ): int {
            $lockedTicket = SupportTicket::query()
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedReader = User::query()
                ->with('role')
                ->whereKey($reader->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $activeAccountStatus = AccountStatus::query()
                ->where('status_name', 'ACTIVE')
                ->firstOrFail();

            if (
                (int) $lockedReader->account_status_id
                !== (int) $activeAccountStatus->id
            ) {
                throw new DomainException(
                    'Only an active user can read support messages.'
                );
            }

            $customer = Customer::query()
                ->whereKey($lockedTicket->customer_id)
                ->lockForUpdate()
                ->firstOrFail();

            $readerRole = $lockedReader->role
                ?->role_name;

            $isCustomer = (int) $customer->user_id
                === (int) $lockedReader->id;

            $isAssignedSupportUser = (
                (int) $lockedTicket->assigned_to_user_id
                === (int) $lockedReader->id
            ) && in_array(
                $readerRole,
                self::SUPPORT_ROLES,
                true
            );

            if (! $isCustomer && ! $isAssignedSupportUser) {
                throw new DomainException(
                    'The user is not allowed to read messages from this support ticket.'
                );
            }

            $unreadMessages = SupportMessage::query()
                ->where('ticket_id', $lockedTicket->id)
                ->where('is_read', false)
                ->where('user_id', '!=', $lockedReader->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($unreadMessages->isEmpty()) {
                return 0;
            }

            $messageIds = $unreadMessages
                ->pluck('id')
                ->all();

            SupportMessage::query()
                ->whereIn('id', $messageIds)
                ->update([
                    'is_read' => true,
                ]);

            $readAt = now();

            AuditLog::query()->create([
                'performed_by_user_id' => $lockedReader->id,
                'table_name' => 'support_messages',
                'record_id' => $lockedTicket->id,
                'action_type' => 'SUPPORT_MESSAGES_READ',
                'details' => [
                    'ticket_id' => $lockedTicket->id,
                    'reader_type' => $isCustomer
                        ? 'CUSTOMER'
                        : 'SUPPORT',
                    'message_ids' => $messageIds,
                    'message_count' => count($messageIds),
                ],
                'performed_at' => $readAt,
            ]);

            return count($messageIds);
        }, attempts: 3);
    }
}
