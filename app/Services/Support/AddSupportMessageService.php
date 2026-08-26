<?php

namespace App\Services\Support;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\TicketStatus;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class AddSupportMessageService
{
    private const SUPPORT_ROLES = [
        'SUPPORT_AGENT',
        'ADMINISTRATOR',
    ];

    /**
     * Add a message to an existing support ticket.
     *
     * @throws DomainException
     */
    public function execute(
        SupportTicket $ticket,
        User $sender,
        string $message,
        ?string $attachmentUrl = null
    ): SupportMessage {
        $normalizedMessage = trim($message);

        if ($normalizedMessage === '') {
            throw new DomainException(
                'The support message is required.'
            );
        }

        $normalizedAttachmentUrl = $attachmentUrl === null
            ? null
            : trim($attachmentUrl);

        if ($normalizedAttachmentUrl === '') {
            $normalizedAttachmentUrl = null;
        }

        if (
            $normalizedAttachmentUrl !== null
            && mb_strlen($normalizedAttachmentUrl) > 500
        ) {
            throw new DomainException(
                'The support attachment URL may not exceed 500 characters.'
            );
        }

        return DB::transaction(function () use (
            $ticket,
            $sender,
            $normalizedMessage,
            $normalizedAttachmentUrl
        ): SupportMessage {
            $lockedTicket = SupportTicket::query()
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedSender = User::query()
                ->whereKey($sender->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $activeAccountStatus = AccountStatus::query()
                ->where('status_name', 'ACTIVE')
                ->firstOrFail();

            if (
                (int) $lockedSender->account_status_id
                !== (int) $activeAccountStatus->id
            ) {
                throw new DomainException(
                    'Only an active user can send support messages.'
                );
            }

            $currentTicketStatus = TicketStatus::query()
                ->whereKey($lockedTicket->ticket_status_id)
                ->firstOrFail();

            if ($currentTicketStatus->status_name === 'CLOSED') {
                throw new DomainException(
                    'Messages cannot be added to a closed support ticket.'
                );
            }

            $customer = Customer::query()
                ->whereKey($lockedTicket->customer_id)
                ->lockForUpdate()
                ->firstOrFail();

            $senderRoleName = $lockedSender->role
                ?->role_name;

            $isCustomer = (int) $customer->user_id
                === (int) $lockedSender->id;

            $isAssignedSupportUser = (
                (int) $lockedTicket->assigned_to_user_id
                === (int) $lockedSender->id
            ) && in_array(
                $senderRoleName,
                self::SUPPORT_ROLES,
                true
            );

            if (! $isCustomer && ! $isAssignedSupportUser) {
                throw new DomainException(
                    'The user is not allowed to participate in this support ticket.'
                );
            }

            $nextStatusName = $this->resolveNextStatus(
                currentStatus: $currentTicketStatus->status_name,
                sentByCustomer: $isCustomer
            );

            $nextStatus = TicketStatus::query()
                ->where('status_name', $nextStatusName)
                ->firstOrFail();

            $sentAt = now();

            $supportMessage = SupportMessage::query()->create([
                'ticket_id' => $lockedTicket->id,
                'user_id' => $lockedSender->id,
                'message_text' => $normalizedMessage,
                'attachment_url' => $normalizedAttachmentUrl,
                'sent_at' => $sentAt,
                'is_read' => false,
            ]);

            if (
                (int) $lockedTicket->ticket_status_id
                !== (int) $nextStatus->id
            ) {
                $lockedTicket->update([
                    'ticket_status_id' => $nextStatus->id,
                    'closed_at' => null,
                ]);
            }

            AuditLog::query()->create([
                'performed_by_user_id' => $lockedSender->id,
                'table_name' => 'support_messages',
                'record_id' => $supportMessage->id,
                'action_type' => 'SUPPORT_MESSAGE_SENT',
                'details' => [
                    'ticket_id' => $lockedTicket->id,
                    'sender_type' => $isCustomer
                        ? 'CUSTOMER'
                        : 'SUPPORT',
                    'from_status' => $currentTicketStatus->status_name,
                    'to_status' => $nextStatus->status_name,
                    'attachment_url' => $normalizedAttachmentUrl,
                ],
                'performed_at' => $sentAt,
            ]);

            return $supportMessage->load([
                'ticket.status',
                'user',
            ]);
        }, attempts: 3);
    }

    private function resolveNextStatus(
        string $currentStatus,
        bool $sentByCustomer
    ): string {
        if ($currentStatus === 'RESOLVED') {
            return 'IN_PROGRESS';
        }

        if (
            $sentByCustomer
            && $currentStatus === 'WAITING_CUSTOMER'
        ) {
            return 'IN_PROGRESS';
        }

        if (
            ! $sentByCustomer
            && in_array(
                $currentStatus,
                ['OPEN', 'IN_PROGRESS'],
                true
            )
        ) {
            return 'WAITING_CUSTOMER';
        }

        return $currentStatus;
    }
}

