<?php

namespace App\Observers;

use App\Models\ShipmentStatusHistory;
use App\Services\Notification\CreateAppNotificationService;

class ShipmentStatusHistoryObserver
{
    public function __construct(
        private readonly CreateAppNotificationService $notificationService
    ) {
    }

    /**
     * Create a customer notification when a shipment status
     * history record is created.
     */
    public function created(
        ShipmentStatusHistory $statusHistory
    ): void {
        $statusHistory->loadMissing([
            'shipment.customer.user.accountStatus',
            'shipmentStatus',
        ]);

        $shipment = $statusHistory->shipment;
        $status = $statusHistory->shipmentStatus;
        $recipient = $shipment?->customer?->user;

        if (
            $shipment === null
            || $status === null
            || $recipient === null
        ) {
            return;
        }

        /*
         * REQUESTED is the initial shipment status.
         * It will be handled later through SERVICE_REQUESTED.
         */
        if ($status->status_name === 'REQUESTED') {
            return;
        }

        /*
         * Shipment operations must not fail only because the
         * recipient account is inactive.
         */
        if (
            $recipient->accountStatus?->status_name
            !== 'ACTIVE'
        ) {
            return;
        }

        $this->notificationService->execute(
            recipient: $recipient,
            notificationTypeName: 'SHIPMENT_STATUS_CHANGED',
            title: 'Shipment status updated',
            message: sprintf(
                'Shipment %s is now %s.',
                $shipment->tracking_code,
                $status->status_name
            ),
            deduplicationKey: sprintf(
                'shipment-status-history:%d',
                $statusHistory->id
            )
        );
    }
}
