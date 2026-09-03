<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\AppNotification;
use App\Models\NotificationType;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppNotificationIndexPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_view_the_notifications_page(): void
    {
        $this->get(
            route('portal.notifications.index')
        )->assertRedirect(
            route('login.page')
        );
    }

    public function test_an_unverified_user_is_redirected_to_verification(): void
    {
        $user = User::factory()
            ->unverified()
            ->create();

        $this->actingAs($user)
            ->get(
                route('portal.notifications.index')
            )
            ->assertRedirect(
                route('verification.notice')
            );
    }

    public function test_a_user_only_sees_their_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $firstNotification = $this->notificationFor(
            $user,
            [
                'title' => 'Actualización del envío',
            ]
        );

        $secondNotification = $this->notificationFor(
            $user,
            [
                'title' => 'Respuesta de soporte',
            ]
        );

        $otherNotification = $this->notificationFor(
            $otherUser,
            [
                'title' => 'Notificación privada externa',
            ]
        );

        $this->actingAs($user)
            ->get(
                route('portal.notifications.index')
            )
            ->assertOk()
            ->assertSee(
                $firstNotification->title
            )
            ->assertSee(
                $secondNotification->title
            )
            ->assertDontSee(
                $otherNotification->title
            );
    }

    public function test_notifications_can_be_filtered_as_unread(): void
    {
        $user = User::factory()->create();

        $unreadNotification = $this->notificationFor(
            $user,
            [
                'title' => 'Notificación pendiente',
            ]
        );

        $readNotification = $this->notificationFor(
            $user,
            [
                'title' => 'Notificación ya leída',
                'is_read' => true,
                'read_at' => now(),
            ]
        );

        $this->actingAs($user)
            ->get(
                route(
                    'portal.notifications.index',
                    [
                        'status' => 'unread',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $unreadNotification->title
            )
            ->assertDontSee(
                $readNotification->title
            );
    }

    public function test_notifications_can_be_filtered_as_read(): void
    {
        $user = User::factory()->create();

        $unreadNotification = $this->notificationFor(
            $user,
            [
                'title' => 'Aviso todavía pendiente',
            ]
        );

        $readNotification = $this->notificationFor(
            $user,
            [
                'title' => 'Aviso revisado',
                'is_read' => true,
                'read_at' => now(),
            ]
        );

        $this->actingAs($user)
            ->get(
                route(
                    'portal.notifications.index',
                    [
                        'status' => 'read',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $readNotification->title
            )
            ->assertDontSee(
                $unreadNotification->title
            );
    }

    public function test_an_invalid_status_filter_displays_all_notifications(): void
    {
        $user = User::factory()->create();

        $unreadNotification = $this->notificationFor(
            $user,
            [
                'title' => 'Aviso sin leer',
            ]
        );

        $readNotification = $this->notificationFor(
            $user,
            [
                'title' => 'Aviso leído',
                'is_read' => true,
                'read_at' => now(),
            ]
        );

        $this->actingAs($user)
            ->get(
                route(
                    'portal.notifications.index',
                    [
                        'status' => 'invalid',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $unreadNotification->title
            )
            ->assertSee(
                $readNotification->title
            );
    }

    public function test_a_user_can_mark_their_notification_as_read(): void
    {
        $user = User::factory()->create();

        $notification = $this->notificationFor(
            $user
        );

        $this->actingAs($user)
            ->from(
                route('portal.notifications.index')
            )
            ->patch(
                route(
                    'portal.notifications.read',
                    $notification
                )
            )
            ->assertRedirect(
                route('portal.notifications.index')
            )
            ->assertSessionHas('status');

        $notification->refresh();

        $this->assertTrue(
            $notification->is_read
        );

        $this->assertNotNull(
            $notification->read_at
        );

        $this->assertDatabaseHas(
            'app_notifications',
            [
                'id' => $notification->id,
                'user_id' => $user->id,
                'is_read' => true,
            ]
        );
    }

    public function test_a_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $notification = $this->notificationFor(
            $otherUser
        );

        $this->actingAs($user)
            ->patch(
                route(
                    'portal.notifications.read',
                    $notification
                )
            )
            ->assertForbidden();

        $notification->refresh();

        $this->assertFalse(
            $notification->is_read
        );

        $this->assertNull(
            $notification->read_at
        );
    }

    public function test_a_user_can_mark_all_their_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $firstNotification = $this->notificationFor(
            $user
        );

        $secondNotification = $this->notificationFor(
            $user
        );

        $otherNotification = $this->notificationFor(
            $otherUser
        );

        $this->actingAs($user)
            ->from(
                route('portal.notifications.index')
            )
            ->patch(
                route(
                    'portal.notifications.read-all'
                )
            )
            ->assertRedirect(
                route('portal.notifications.index')
            )
            ->assertSessionHas('status');

        $this->assertTrue(
            $firstNotification
                ->fresh()
                ->is_read
        );

        $this->assertTrue(
            $secondNotification
                ->fresh()
                ->is_read
        );

        $this->assertFalse(
            $otherNotification
                ->fresh()
                ->is_read
        );

        $this->assertNotNull(
            $firstNotification
                ->fresh()
                ->read_at
        );

        $this->assertNotNull(
            $secondNotification
                ->fresh()
                ->read_at
        );

        $this->assertNull(
            $otherNotification
                ->fresh()
                ->read_at
        );
    }

    public function test_an_inactive_account_cannot_view_notifications(): void
    {
        $inactiveStatus = AccountStatus::query()
            ->where(
                'status_name',
                '!=',
                'ACTIVE'
            )
            ->firstOrFail();

        $user = User::factory()->create([
            'account_status_id' =>
                $inactiveStatus->id,
        ]);

        $this->actingAs($user)
            ->get(
                route('portal.notifications.index')
            )
            ->assertForbidden();
    }

    /**
     * Crea una notificación para las pruebas.
     *
     * @param array<string, mixed> $attributes
     */
    private function notificationFor(
        User $user,
        array $attributes = []
    ): AppNotification {
        $notificationType = NotificationType::query()
            ->where(
                'type_name',
                'SUPPORT_UPDATE'
            )
            ->firstOrFail();

        $data = array_merge([
            'user_id' => $user->id,
            'notification_type_id' =>
                $notificationType->id,
            'deduplication_key' =>
                'test-notification-'.Str::uuid(),
            'title' => 'Notificación de prueba',
            'message' =>
                'Este es un mensaje de prueba.',
            'is_read' => false,
            'read_at' => null,
            'sent_at' => now(),
        ], $attributes);

        if (
            $data['is_read'] === true
            && $data['read_at'] === null
        ) {
            $data['read_at'] = now();
        }

        return AppNotification::query()
            ->create($data);
    }
}