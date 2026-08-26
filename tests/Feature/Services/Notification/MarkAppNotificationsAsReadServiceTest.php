<?php

namespace Tests\Feature\Services\Notification;

use App\Models\AccountStatus;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\NotificationType;
use App\Models\User;
use App\Services\Notification\MarkAllAppNotificationsAsReadService;
use App\Services\Notification\MarkAppNotificationAsReadService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkAppNotificationsAsReadServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user);

        $readNotification = app(
            MarkAppNotificationAsReadService::class
        )->execute(
            $notification,
            $user
        );

        $this->assertTrue($readNotification->is_read);
        $this->assertNotNull($readNotification->read_at);

        $this->assertDatabaseHas('app_notifications', [
            'id' => $notification->id,
            'user_id' => $user->id,
            'is_read' => true,
        ]);

        $this->assertDatabaseCount('audit_logs', 1);

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            $user->id,
            $auditLog->performed_by_user_id
        );

        $this->assertSame(
            'app_notifications',
            $auditLog->table_name
        );

        $this->assertSame(
            $notification->id,
            $auditLog->record_id
        );

        $this->assertSame(
            'NOTIFICATION_READ',
            $auditLog->action_type
        );
    }

    public function test_user_cannot_read_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $notification = $this->createNotification($owner);

        $this->assertDomainException(
            fn () => app(
                MarkAppNotificationAsReadService::class
            )->execute(
                $notification,
                $otherUser
            ),
            'The notification does not belong to the user.'
        );

        $this->assertFalse($notification->fresh()->is_read);
        $this->assertNull($notification->fresh()->read_at);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_inactive_user_cannot_read_a_notification(): void
    {
        $user = User::factory()->create([
            'account_status_id' => $this
                ->findAccountStatus('SUSPENDED')
                ->id,
        ]);

        $notification = $this->createNotification($user);

        $this->assertDomainException(
            fn () => app(
                MarkAppNotificationAsReadService::class
            )->execute(
                $notification,
                $user
            ),
            'Only an active user can read application notifications.'
        );

        $this->assertFalse($notification->fresh()->is_read);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_individual_read_is_idempotent(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user);

        $service = app(
            MarkAppNotificationAsReadService::class
        );

        $firstResult = $service->execute(
            $notification,
            $user
        );

        $originalReadAt = $firstResult->read_at;

        $secondResult = $service->execute(
            $notification,
            $user
        );

        $this->assertTrue($secondResult->is_read);

        $this->assertTrue(
            $originalReadAt->equalTo(
                $secondResult->read_at
            )
        );

        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_bulk_read_marks_all_unread_notifications(): void
    {
        $user = User::factory()->create();

        $firstNotification = $this->createNotification($user);
        $secondNotification = $this->createNotification($user);
        $thirdNotification = $this->createNotification($user);

        $readCount = app(
            MarkAllAppNotificationsAsReadService::class
        )->execute($user);

        $this->assertSame(3, $readCount);

        foreach ([
            $firstNotification,
            $secondNotification,
            $thirdNotification,
        ] as $notification) {
            $this->assertTrue(
                $notification->fresh()->is_read
            );

            $this->assertNotNull(
                $notification->fresh()->read_at
            );
        }

        $this->assertDatabaseCount('audit_logs', 1);

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            'NOTIFICATIONS_READ',
            $auditLog->action_type
        );

        $this->assertSame(
            3,
            $auditLog->details['notification_count']
        );

        $this->assertSame(
            [
                $firstNotification->id,
                $secondNotification->id,
                $thirdNotification->id,
            ],
            $auditLog->details['notification_ids']
        );
    }

    public function test_bulk_read_ignores_notifications_already_read(): void
    {
        $user = User::factory()->create();

        $alreadyRead = $this->createNotification(
            $user,
            true
        );

        $unread = $this->createNotification($user);

        $originalReadAt = $alreadyRead->read_at;

        $readCount = app(
            MarkAllAppNotificationsAsReadService::class
        )->execute($user);

        $this->assertSame(1, $readCount);

        $this->assertTrue(
            $originalReadAt->equalTo(
                $alreadyRead->fresh()->read_at
            )
        );

        $this->assertTrue($unread->fresh()->is_read);
        $this->assertNotNull($unread->fresh()->read_at);
    }

    public function test_bulk_read_does_not_modify_another_users_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownNotification = $this->createNotification($user);

        $otherNotification = $this->createNotification(
            $otherUser
        );

        $readCount = app(
            MarkAllAppNotificationsAsReadService::class
        )->execute($user);

        $this->assertSame(1, $readCount);
        $this->assertTrue($ownNotification->fresh()->is_read);
        $this->assertFalse($otherNotification->fresh()->is_read);
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_bulk_read_returns_zero_when_nothing_is_unread(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user, true);

        $readCount = app(
            MarkAllAppNotificationsAsReadService::class
        )->execute($user);

        $this->assertSame(0, $readCount);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_inactive_user_cannot_use_bulk_read(): void
    {
        $user = User::factory()->create([
            'account_status_id' => $this
                ->findAccountStatus('SUSPENDED')
                ->id,
        ]);

        $notification = $this->createNotification($user);

        $this->assertDomainException(
            fn () => app(
                MarkAllAppNotificationsAsReadService::class
            )->execute($user),
            'Only an active user can read application notifications.'
        );

        $this->assertFalse($notification->fresh()->is_read);
        $this->assertNull($notification->fresh()->read_at);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function createNotification(
        User $user,
        bool $isRead = false
    ): AppNotification {
        return AppNotification::query()->create([
            'user_id' => $user->id,
            'notification_type_id' => NotificationType::query()
                ->where('type_name', 'SUPPORT_UPDATE')
                ->firstOrFail()
                ->id,
            'deduplication_key' => null,
            'title' => 'Support ticket updated',
            'message' => 'Your support ticket has been updated.',
            'is_read' => $isRead,
            'read_at' => $isRead
                ? now()->subMinutes(5)
                : null,
            'sent_at' => now()->subMinutes(10),
        ]);
    }

    private function findAccountStatus(
        string $statusName
    ): AccountStatus {
        return AccountStatus::query()
            ->where('status_name', $statusName)
            ->firstOrFail();
    }

    private function assertDomainException(
        Closure $callback,
        string $expectedMessage
    ): void {
        try {
            $callback();

            $this->fail('A DomainException was expected.');
        } catch (DomainException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );
        }
    }
}

