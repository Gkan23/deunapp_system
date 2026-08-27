<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\AppNotification;
use App\Models\NotificationType;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppNotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $notificationSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_access_notification_endpoints(): void
    {
        $user = User::factory()->create();

        $notification = $this->createNotification(
            $user
        );

        $this->getJson(
            route('notifications.index')
        )->assertUnauthorized();

        $this->getJson(
            route('notifications.show', $notification)
        )->assertUnauthorized();

        $this->patchJson(
            route('notifications.read', $notification)
        )->assertUnauthorized();

        $this->patchJson(
            route('notifications.read-all')
        )->assertUnauthorized();
    }

    public function test_a_user_only_lists_their_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $firstNotification = $this->createNotification(
            $user,
            'First notification'
        );

        $secondNotification = $this->createNotification(
            $user,
            'Second notification'
        );

        $this->createNotification(
            $otherUser,
            'Another user notification'
        );

        $response = $this
            ->actingAs($user)
            ->getJson(route('notifications.index'));

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $notificationIds = collect(
            $response->json('data')
        )
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing(
            [
                $firstNotification->id,
                $secondNotification->id,
            ],
            $notificationIds
        );
    }

    public function test_a_user_can_view_their_notification(): void
    {
        $user = User::factory()->create();

        $notification = $this->createNotification(
            $user
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                route(
                    'notifications.show',
                    $notification
                )
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'notification.id',
                $notification->id
            )
            ->assertJsonPath(
                'notification.user_id',
                $user->id
            )
            ->assertJsonPath(
                'notification.is_read',
                false
            );
    }

    public function test_a_user_cannot_view_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $notification = $this->createNotification(
            $owner
        );

        $this
            ->actingAs($otherUser)
            ->getJson(
                route(
                    'notifications.show',
                    $notification
                )
            )
            ->assertForbidden();
    }

    public function test_a_user_can_mark_their_notification_as_read_idempotently(): void
    {
        $user = User::factory()->create();

        $notification = $this->createNotification(
            $user
        );

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    'notifications.read',
                    $notification
                )
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Notification marked as read.'
            )
            ->assertJsonPath(
                'notification.id',
                $notification->id
            )
            ->assertJsonPath(
                'notification.is_read',
                true
            );

        $freshNotification = $notification->fresh();

        $this->assertTrue(
            $freshNotification->is_read
        );

        $this->assertNotNull(
            $freshNotification->read_at
        );

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' => $user->id,
            'table_name' => 'app_notifications',
            'record_id' => $notification->id,
            'action_type' => 'NOTIFICATION_READ',
        ]);

        /*
         * Ejecutar la acción nuevamente no debe crear
         * otra auditoría ni cambiar la fecha.
         */
        $originalReadAt = $freshNotification->read_at;

        $this
            ->actingAs($user)
            ->patchJson(
                route(
                    'notifications.read',
                    $notification
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'notification.is_read',
                true
            );

        $this->assertTrue(
            $originalReadAt->equalTo(
                $notification->fresh()->read_at
            )
        );

        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_a_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $notification = $this->createNotification(
            $owner
        );

        $this
            ->actingAs($otherUser)
            ->patchJson(
                route(
                    'notifications.read',
                    $notification
                )
            )
            ->assertForbidden();

        $freshNotification = $notification->fresh();

        $this->assertFalse(
            $freshNotification->is_read
        );

        $this->assertNull(
            $freshNotification->read_at
        );

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_user_can_mark_all_their_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $firstUnread = $this->createNotification(
            $user,
            'First unread notification'
        );

        $secondUnread = $this->createNotification(
            $user,
            'Second unread notification'
        );

        $alreadyRead = $this->createNotification(
            $user,
            'Already read notification',
            true
        );

        $otherNotification = $this->createNotification(
            $otherUser,
            'Another user notification'
        );

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route('notifications.read-all')
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Notifications marked as read.'
            )
            ->assertJsonPath(
                'updated_count',
                2
            );

        $this->assertTrue(
            $firstUnread->fresh()->is_read
        );

        $this->assertTrue(
            $secondUnread->fresh()->is_read
        );

        $this->assertTrue(
            $alreadyRead->fresh()->is_read
        );

        $this->assertFalse(
            $otherNotification->fresh()->is_read
        );

        $this->assertNotNull(
            $firstUnread->fresh()->read_at
        );

        $this->assertNotNull(
            $secondUnread->fresh()->read_at
        );

        $this->assertNull(
            $otherNotification->fresh()->read_at
        );

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' => $user->id,
            'table_name' => 'app_notifications',
            'record_id' => $user->id,
            'action_type' => 'NOTIFICATIONS_READ',
        ]);

        /*
         * Ya no quedan notificaciones pendientes.
         */
        $this
            ->actingAs($user)
            ->patchJson(
                route('notifications.read-all')
            )
            ->assertOk()
            ->assertJsonPath(
                'updated_count',
                0
            );

        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_an_inactive_user_cannot_access_notifications(): void
    {
        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $user = User::factory()->create([
            'account_status_id' => $suspendedStatus->id,
        ]);

        $notification = $this->createNotification(
            $user
        );

        $this
            ->actingAs($user)
            ->getJson(route('notifications.index'))
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->getJson(
                route(
                    'notifications.show',
                    $notification
                )
            )
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->patchJson(
                route(
                    'notifications.read',
                    $notification
                )
            )
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->patchJson(
                route('notifications.read-all')
            )
            ->assertForbidden();

        $this->assertFalse(
            $notification->fresh()->is_read
        );
    }

    private function createNotification(
        User $user,
        string $title = 'Application notification',
        bool $isRead = false
    ): AppNotification {
        $notificationType = NotificationType::query()
            ->where('type_name', 'SUPPORT_UPDATE')
            ->firstOrFail();

        $this->notificationSequence++;

        return AppNotification::query()->create([
            'user_id' => $user->id,
            'notification_type_id' => $notificationType->id,
            'deduplication_key' => implode(':', [
                'http-test',
                $user->id,
                $this->notificationSequence,
            ]),
            'title' => $title,
            'message' => 'Notification message.',
            'is_read' => $isRead,
            'read_at' => $isRead
                ? now()->subMinutes(5)
                : null,
            'sent_at' => now()->subMinutes(10),
        ]);
    }
}