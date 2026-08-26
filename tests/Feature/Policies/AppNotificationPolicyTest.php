<?php

namespace Tests\Feature\Policies;

use App\Models\AccountStatus;
use App\Models\AppNotification;
use App\Models\NotificationType;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppNotificationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_an_active_user_can_view_their_notification_list(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(
            Gate::forUser($user)->allows(
                'viewAny',
                AppNotification::class
            )
        );
    }

    public function test_an_inactive_user_cannot_access_notifications(): void
    {
        $user = $this->inactiveUser();
        $notification = $this->notificationFor($user);

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'viewAny',
                AppNotification::class
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'view',
                $notification
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'markAsRead',
                $notification
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'markAllAsRead',
                AppNotification::class
            )
        );
    }

    public function test_a_user_can_view_their_own_notification(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationFor($user);

        $this->assertTrue(
            Gate::forUser($user)->allows(
                'view',
                $notification
            )
        );
    }

    public function test_a_user_cannot_view_another_users_notification(): void
    {
        $recipient = User::factory()->create();
        $otherUser = User::factory()->create();

        $notification = $this->notificationFor($recipient);

        $this->assertFalse(
            Gate::forUser($otherUser)->allows(
                'view',
                $notification
            )
        );
    }

    public function test_a_user_can_mark_their_own_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationFor($user);

        $this->assertTrue(
            Gate::forUser($user)->allows(
                'markAsRead',
                $notification
            )
        );
    }

    public function test_a_user_cannot_mark_another_users_notification_as_read(): void
    {
        $recipient = User::factory()->create();
        $otherUser = User::factory()->create();

        $notification = $this->notificationFor($recipient);

        $this->assertFalse(
            Gate::forUser($otherUser)->allows(
                'markAsRead',
                $notification
            )
        );
    }

    public function test_an_active_user_can_mark_all_their_notifications_as_read(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(
            Gate::forUser($user)->allows(
                'markAllAsRead',
                AppNotification::class
            )
        );
    }

    public function test_direct_creation_update_and_deletion_are_denied(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationFor($user);

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'create',
                AppNotification::class
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'update',
                $notification
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'delete',
                $notification
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'restore',
                $notification
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'forceDelete',
                $notification
            )
        );
    }

    private function inactiveUser(): User
    {
        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        return User::factory()->create([
            'account_status_id' => $suspendedStatus->id,
        ]);
    }

    private function notificationFor(User $user): AppNotification
    {
        $notificationType = NotificationType::query()
            ->where('type_name', 'SHIPMENT_STATUS_CHANGED')
            ->firstOrFail();

        return AppNotification::query()->create([
            'user_id' => $user->id,
            'notification_type_id' => $notificationType->id,
            'title' => 'Shipment status updated',
            'message' => 'Your shipment status has changed.',
            'deduplication_key' => 'policy-test-'.Str::uuid(),
            'is_read' => false,
            'read_at' => null,
            'sent_at' => now(),
        ]);
    }
}

