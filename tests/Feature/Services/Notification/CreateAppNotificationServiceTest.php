<?php

namespace Tests\Feature\Services\Notification;

use App\Models\AccountStatus;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Notification\CreateAppNotificationService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAppNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_creates_an_application_notification_atomically(): void
    {
        $recipient = User::factory()->create();

        $notification = app(
            CreateAppNotificationService::class
        )->execute(
            recipient: $recipient,
            notificationTypeName: 'SHIPMENT_STATUS_CHANGED',
            title: 'Shipment delivered',
            message: 'Your shipment was delivered successfully.',
            deduplicationKey: 'shipment:100:delivered'
        );

        $this->assertSame(
            $recipient->id,
            $notification->user_id
        );

        $this->assertSame(
            'SHIPMENT_STATUS_CHANGED',
            $notification->notificationType->type_name
        );

        $this->assertSame(
            'shipment:100:delivered',
            $notification->deduplication_key
        );

        $this->assertSame(
            'Shipment delivered',
            $notification->title
        );

        $this->assertSame(
            'Your shipment was delivered successfully.',
            $notification->message
        );

        $this->assertFalse($notification->is_read);
        $this->assertNotNull($notification->sent_at);

        $this->assertDatabaseCount('app_notifications', 1);
        $this->assertDatabaseCount('audit_logs', 1);

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertNull(
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
            'NOTIFICATION_CREATED',
            $auditLog->action_type
        );

        $this->assertSame(
            $recipient->id,
            $auditLog->details['recipient_user_id']
        );
    }

    public function test_it_normalizes_notification_content(): void
    {
        $recipient = User::factory()->create();

        $notification = app(
            CreateAppNotificationService::class
        )->execute(
            $recipient,
            ' support_update ',
            '  Ticket updated  ',
            '  Your support ticket has a new response.  ',
            '  ticket:50:response:10  '
        );

        $this->assertSame(
            'SUPPORT_UPDATE',
            $notification->notificationType->type_name
        );

        $this->assertSame(
            'Ticket updated',
            $notification->title
        );

        $this->assertSame(
            'Your support ticket has a new response.',
            $notification->message
        );

        $this->assertSame(
            'ticket:50:response:10',
            $notification->deduplication_key
        );
    }

    public function test_same_key_and_content_returns_the_existing_notification(): void
    {
        $recipient = User::factory()->create();

        $service = app(
            CreateAppNotificationService::class
        );

        $firstNotification = $service->execute(
            $recipient,
            'PAYMENT_CONFIRMED',
            'Payment confirmed',
            'Your payment was confirmed.',
            'payment:25:confirmed'
        );

        $secondNotification = $service->execute(
            $recipient,
            'PAYMENT_CONFIRMED',
            'Payment confirmed',
            'Your payment was confirmed.',
            'payment:25:confirmed'
        );

        $this->assertSame(
            $firstNotification->id,
            $secondNotification->id
        );

        $this->assertDatabaseCount('app_notifications', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_same_key_cannot_be_used_for_another_recipient(): void
    {
        $firstRecipient = User::factory()->create();
        $secondRecipient = User::factory()->create();

        $service = app(
            CreateAppNotificationService::class
        );

        $service->execute(
            $firstRecipient,
            'SUPPORT_UPDATE',
            'Ticket updated',
            'Your ticket was updated.',
            'support:event:100'
        );

        $this->assertDomainException(
            fn () => $service->execute(
                $secondRecipient,
                'SUPPORT_UPDATE',
                'Ticket updated',
                'Your ticket was updated.',
                'support:event:100'
            ),
            'The notification deduplication key has already been used with different content.'
        );

        $this->assertDatabaseCount('app_notifications', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_same_key_cannot_be_used_with_different_content(): void
    {
        $recipient = User::factory()->create();

        $service = app(
            CreateAppNotificationService::class
        );

        $service->execute(
            $recipient,
            'SERVICE_ASSIGNED',
            'Service assigned',
            'A provider was assigned.',
            'service:80:assigned'
        );

        $this->assertDomainException(
            fn () => $service->execute(
                $recipient,
                'SERVICE_ASSIGNED',
                'Different title',
                'Different notification content.',
                'service:80:assigned'
            ),
            'The notification deduplication key has already been used with different content.'
        );

        $this->assertDatabaseCount('app_notifications', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_it_rejects_an_unknown_notification_type(): void
    {
        $recipient = User::factory()->create();

        $this->assertDomainException(
            fn () => app(
                CreateAppNotificationService::class
            )->execute(
                $recipient,
                'UNKNOWN_TYPE',
                'Unknown notification',
                'This type does not exist.'
            ),
            'The selected notification type does not exist.'
        );

        $this->assertNothingWasCreated();
    }

    public function test_it_rejects_an_inactive_recipient(): void
    {
        $recipient = User::factory()->create([
            'account_status_id' => AccountStatus::query()
                ->where('status_name', 'SUSPENDED')
                ->firstOrFail()
                ->id,
        ]);

        $this->assertDomainException(
            fn () => app(
                CreateAppNotificationService::class
            )->execute(
                $recipient,
                'SUPPORT_UPDATE',
                'Ticket updated',
                'Your ticket was updated.'
            ),
            'Notifications can only be sent to active users.'
        );

        $this->assertNothingWasCreated();
    }

    public function test_it_rejects_an_empty_title(): void
    {
        $recipient = User::factory()->create();

        $this->assertDomainException(
            fn () => app(
                CreateAppNotificationService::class
            )->execute(
                $recipient,
                'SUPPORT_UPDATE',
                '   ',
                'Valid message.'
            ),
            'The notification title is required.'
        );

        $this->assertNothingWasCreated();
    }

    public function test_it_rejects_a_title_longer_than_150_characters(): void
    {
        $recipient = User::factory()->create();

        $this->assertDomainException(
            fn () => app(
                CreateAppNotificationService::class
            )->execute(
                $recipient,
                'SUPPORT_UPDATE',
                str_repeat('T', 151),
                'Valid message.'
            ),
            'The notification title may not exceed 150 characters.'
        );

        $this->assertNothingWasCreated();
    }

    public function test_it_rejects_an_empty_message(): void
    {
        $recipient = User::factory()->create();

        $this->assertDomainException(
            fn () => app(
                CreateAppNotificationService::class
            )->execute(
                $recipient,
                'SUPPORT_UPDATE',
                'Valid title',
                '   '
            ),
            'The notification message is required.'
        );

        $this->assertNothingWasCreated();
    }

    public function test_it_rejects_a_deduplication_key_longer_than_150_characters(): void
    {
        $recipient = User::factory()->create();

        $this->assertDomainException(
            fn () => app(
                CreateAppNotificationService::class
            )->execute(
                $recipient,
                'SUPPORT_UPDATE',
                'Valid title',
                'Valid message.',
                str_repeat('K', 151)
            ),
            'The notification deduplication key may not exceed 150 characters.'
        );

        $this->assertNothingWasCreated();
    }

    private function assertNothingWasCreated(): void
    {
        $this->assertDatabaseCount('app_notifications', 0);
        $this->assertDatabaseCount('audit_logs', 0);
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
