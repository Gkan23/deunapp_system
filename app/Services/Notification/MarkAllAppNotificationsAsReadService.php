<?php

namespace App\Services\Notification;

use App\Models\AccountStatus;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class MarkAllAppNotificationsAsReadService
{
    /**
     * Mark all unread notifications belonging to a user as read.
     *
     * @return int Number of notifications marked as read.
     *
     * @throws DomainException
     */
    public function execute(User $reader): int
    {
        return DB::transaction(function () use (
            $reader
        ): int {
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

            $notifications = AppNotification::query()
                ->where('user_id', $lockedReader->id)
                ->where('is_read', false)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($notifications->isEmpty()) {
                return 0;
            }

            $notificationIds = $notifications
                ->pluck('id')
                ->all();

            $readAt = now();

            AppNotification::query()
                ->whereIn('id', $notificationIds)
                ->update([
                    'is_read' => true,
                    'read_at' => $readAt,
                ]);

            AuditLog::query()->create([
                'performed_by_user_id' => $lockedReader->id,
                'table_name' => 'app_notifications',
                'record_id' => $lockedReader->id,
                'action_type' => 'NOTIFICATIONS_READ',
                'details' => [
                    'notification_ids' => $notificationIds,
                    'notification_count' => count(
                        $notificationIds
                    ),
                ],
                'performed_at' => $readAt,
            ]);

            return count($notificationIds);
        }, attempts: 3);
    }
}

