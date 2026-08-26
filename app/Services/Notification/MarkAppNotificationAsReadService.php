<?php

namespace App\Services\Notification;

use App\Models\AccountStatus;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class MarkAppNotificationAsReadService
{
    /**
     * Mark one notification as read.
     *
     * @throws DomainException
     */
    public function execute(
        AppNotification $notification,
        User $reader
    ): AppNotification {
        return DB::transaction(function () use (
            $notification,
            $reader
        ): AppNotification {
            $lockedReader = User::query()
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
                    'Only an active user can read application notifications.'
                );
            }

            $lockedNotification = AppNotification::query()
                ->whereKey($notification->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (int) $lockedNotification->user_id
                !== (int) $lockedReader->id
            ) {
                throw new DomainException(
                    'The notification does not belong to the user.'
                );
            }

            if ($lockedNotification->is_read) {
                return $lockedNotification->load([
                    'user',
                    'notificationType',
                ]);
            }

            $readAt = now();

            $lockedNotification->update([
                'is_read' => true,
                'read_at' => $readAt,
            ]);

            AuditLog::query()->create([
                'performed_by_user_id' => $lockedReader->id,
                'table_name' => 'app_notifications',
                'record_id' => $lockedNotification->id,
                'action_type' => 'NOTIFICATION_READ',
                'details' => [
                    'notification_type_id' => $lockedNotification
                        ->notification_type_id,
                    'deduplication_key' => $lockedNotification
                        ->deduplication_key,
                ],
                'performed_at' => $readAt,
            ]);

            return $lockedNotification->fresh([
                'user',
                'notificationType',
            ]);
        }, attempts: 3);
    }
}

