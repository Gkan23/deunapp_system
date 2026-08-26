<?php

namespace App\Services\Support;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

class CreateSupportTicketService
{
    /**
     * Create a support ticket with its initial message.
     *
     * @throws DomainException
     */
    public function execute(
        Customer $customer,
        string $categoryName,
        string $subject,
        string $message,
        ?Shipment $shipment = null,
        ?string $attachmentUrl = null
    ): SupportTicket {
        $normalizedSubject = trim($subject);
        $normalizedMessage = trim($message);

        if ($normalizedSubject === '') {
            throw new DomainException(
                'The support ticket subject is required.'
            );
        }

        if (mb_strlen($normalizedSubject) > 200) {
            throw new DomainException(
                'The support ticket subject may not exceed 200 characters.'
            );
        }

        if ($normalizedMessage === '') {
            throw new DomainException(
                'The initial support message is required.'
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

        $normalizedCategoryName = strtoupper(
            trim($categoryName)
        );

        return DB::transaction(function () use (
            $customer,
            $shipment,
            $normalizedCategoryName,
            $normalizedSubject,
            $normalizedMessage,
            $normalizedAttachmentUrl
        ): SupportTicket {
            $lockedCustomer = Customer::query()
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedShipment = null;

            if ($shipment !== null) {
                $lockedShipment = Shipment::query()
                    ->whereKey($shipment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    (int) $lockedShipment->customer_id
                    !== (int) $lockedCustomer->id
                ) {
                    throw new DomainException(
                        'The selected shipment does not belong to the customer.'
                    );
                }
            }

            $category = TicketCategory::query()
                ->where(
                    'category_name',
                    $normalizedCategoryName
                )
                ->first();

            if ($category === null) {
                throw new DomainException(
                    'The selected support ticket category does not exist.'
                );
            }

            $openStatus = TicketStatus::query()
                ->where('status_name', 'OPEN')
                ->firstOrFail();

            $mediumPriority = TicketPriority::query()
                ->where('priority_name', 'MEDIUM')
                ->firstOrFail();

            $ticket = SupportTicket::query()->create([
                'customer_id' => $lockedCustomer->id,
                'shipment_id' => $lockedShipment?->id,
                'ticket_category_id' => $category->id,
                'ticket_status_id' => $openStatus->id,
                'ticket_priority_id' => $mediumPriority->id,
                'assigned_to_user_id' => null,
                'subject' => $normalizedSubject,
                'closed_at' => null,
            ]);

            $sentAt = now();

            $initialMessage = SupportMessage::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $lockedCustomer->user_id,
                'message_text' => $normalizedMessage,
                'attachment_url' => $normalizedAttachmentUrl,
                'sent_at' => $sentAt,
                'is_read' => false,
            ]);

            AuditLog::query()->create([
                'performed_by_user_id' => $lockedCustomer->user_id,
                'table_name' => 'support_tickets',
                'record_id' => $ticket->id,
                'action_type' => 'TICKET_CREATED',
                'details' => [
                    'customer_id' => $lockedCustomer->id,
                    'shipment_id' => $lockedShipment?->id,
                    'category' => $category->category_name,
                    'status' => 'OPEN',
                    'priority' => 'MEDIUM',
                    'subject' => $normalizedSubject,
                    'initial_message_id' => $initialMessage->id,
                    'attachment_url' => $normalizedAttachmentUrl,
                ],
                'performed_at' => $sentAt,
            ]);

            return $ticket->load([
                'customer',
                'shipment',
                'category',
                'status',
                'priority',
                'assignedTo',
                'messages.user',
            ]);
        }, attempts: 3);
    }
}

