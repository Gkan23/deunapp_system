<?php

namespace App\Services\Notification;

use App\Models\AccountStatus;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\NotificationType;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateAppNotificationService
{
    /**
     * Create an internal application notification.
     *
     * When a deduplication key is supplied, repeated executions
     * with the same payload return the existing notification.
     *
     * @throws DomainException
     */
    public function execute(
        User $recipient,
        string $notificationTypeName,
        string $title,
        string $message,
        ?string $deduplicationKey = null
    ): AppNotification {
        $normalizedTypeName = strtoupper(
            trim($notificationTypeName)
        );

        $normalizedTitle = trim($title);
        $normalizedMessage = trim($message);

        $normalizedKey = $deduplicationKey === null
            ? null
            : trim($deduplicationKey);

        if ($normalizedKey === '') {
            $normalizedKey = null;
        }

        $this->validateContent(
            $normalizedTitle,
            $normalizedMessage,
            $normalizedKey
        );

        try {
            return DB::transaction(function () use (
                $recipient,
                $normalizedTypeName,
                $normalizedTitle,
                $normalizedMessage,
                $normalizedKey
            ): AppNotification {
                $lockedRecipient = User::query()
                    ->whereKey($recipient->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $activeAccountStatus = AccountStatus::query()
                    ->where('status_name', 'ACTIVE')
                    ->firstOrFail();

                if (
                    (int) $lockedRecipient->account_status_id
                    !== (int) $activeAccountStatus->id
                ) {
                    throw new DomainException(
                        'Notifications can only be sent to active users.'
                    );
                }

                $notificationType = NotificationType::query()
                    ->where('type_name', $normalizedTypeName)
                    ->first();

                if ($notificationType === null) {
                    throw new DomainException(
                        'The selected notification type does not exist.'
                    );
                }

                if ($normalizedKey !== null) {
                    $existingNotification = AppNotification::query()
                        ->where(
                            'deduplication_key',
                            $normalizedKey
                        )
                        ->lockForUpdate()
                        ->first();

                    if ($existingNotification !== null) {
                        return $this->resolveExistingNotification(
                            $existingNotification,
                            $lockedRecipient->id,
                            $notificationType->id,
                            $normalizedTitle,
                            $normalizedMessage
                        );
                    }
                }

                $sentAt = now();

                $notification = AppNotification::query()->create([
                    'user_id' => $lockedRecipient->id,
                    'notification_type_id' => $notificationType->id,
                    'deduplication_key' => $normalizedKey,
                    'title' => $normalizedTitle,
                    'message' => $normalizedMessage,
                    'is_read' => false,
                    'sent_at' => $sentAt,
                ]);

                AuditLog::query()->create([
                    'performed_by_user_id' => null,
                    'table_name' => 'app_notifications',
                    'record_id' => $notification->id,
                    'action_type' => 'NOTIFICATION_CREATED',
                    'details' => [
                        'recipient_user_id' => $lockedRecipient->id,
                        'notification_type' => $notificationType
                            ->type_name,
                        'deduplication_key' => $normalizedKey,
                        'title' => $normalizedTitle,
                    ],
                    'performed_at' => $sentAt,
                ]);

                return $notification->load([
                    'user',
                    'notificationType',
                ]);
            }, attempts: 3);
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            return $this->resolveConcurrentDuplicate(
                $recipient,
                $normalizedTypeName,
                $normalizedTitle,
                $normalizedMessage,
                $normalizedKey,
                $exception
            );
        }
    }

    private function validateContent(
        string $title,
        string $message,
        ?string $deduplicationKey
    ): void {
        if ($title === '') {
            throw new DomainException(
                'The notification title is required.'
            );
        }

        if (mb_strlen($title) > 150) {
            throw new DomainException(
                'The notification title may not exceed 150 characters.'
            );
        }

        if ($message === '') {
            throw new DomainException(
                'The notification message is required.'
            );
        }

        if (
            $deduplicationKey !== null
            && mb_strlen($deduplicationKey) > 150
        ) {
            throw new DomainException(
                'The notification deduplication key may not exceed 150 characters.'
            );
        }
    }

    private function resolveExistingNotification(
        AppNotification $notification,
        int $recipientId,
        int $notificationTypeId,
        string $title,
        string $message
    ): AppNotification {
        $samePayload = (
            (int) $notification->user_id === $recipientId
            && (int) $notification->notification_type_id
                === $notificationTypeId
            && $notification->title === $title
            && $notification->message === $message
        );

        if (! $samePayload) {
            throw new DomainException(
                'The notification deduplication key has already been used with different content.'
            );
        }

        return $notification->load([
            'user',
            'notificationType',
        ]);
    }

    private function resolveConcurrentDuplicate(
        User $recipient,
        string $notificationTypeName,
        string $title,
        string $message,
        ?string $deduplicationKey,
        Throwable $exception
    ): AppNotification {
        if ($deduplicationKey === null) {
            throw $exception;
        }

        $notificationType = NotificationType::query()
            ->where('type_name', $notificationTypeName)
            ->first();

        $existingNotification = AppNotification::query()
            ->where(
                'deduplication_key',
                $deduplicationKey
            )
            ->first();

        if (
            $notificationType !== null
            && $existingNotification !== null
        ) {
            return $this->resolveExistingNotification(
                $existingNotification,
                $recipient->getKey(),
                $notificationType->id,
                $title,
                $message
            );
        }

        throw new DomainException(
            'The notification could not be created because its deduplication key is already in use.',
            0,
            $exception
        );
    }
}
