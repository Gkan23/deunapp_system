<?php

namespace Tests\Feature\Observers;

use App\Models\AccountStatus;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\ShipmentStatusHistory;
use App\Observers\ShipmentStatusHistoryObserver;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ShipmentStatusHistoryObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_creates_a_notification_for_a_shipment_status_change(): void
    {
        [$shipment, $customer] = $this->createShipmentScenario();

        $pickedUpStatus = $this->findShipmentStatus(
            'PICKED_UP'
        );

        $shipment->update([
            'shipment_status_id' => $pickedUpStatus->id,
        ]);

        $statusHistory = ShipmentStatusHistory::query()->create([
            'shipment_id' => $shipment->id,
            'shipment_status_id' => $pickedUpStatus->id,
            'changed_by_user_id' => $customer->user_id,
            'comment' => 'The package was picked up.',
            'changed_at' => now(),
        ]);

        $this->assertDatabaseCount('app_notifications', 1);
        $this->assertDatabaseCount('audit_logs', 1);

        $notification = AppNotification::query()
            ->firstOrFail();

        $this->assertSame(
            $customer->user_id,
            $notification->user_id
        );

        $this->assertSame(
            'SHIPMENT_STATUS_CHANGED',
            $notification->notificationType->type_name
        );

        $this->assertSame(
            'Shipment status updated',
            $notification->title
        );

        $this->assertSame(
            sprintf(
                'Shipment %s is now PICKED_UP.',
                $shipment->tracking_code
            ),
            $notification->message
        );

        $this->assertSame(
            sprintf(
                'shipment-status-history:%d',
                $statusHistory->id
            ),
            $notification->deduplication_key
        );

        $this->assertFalse($notification->is_read);
        $this->assertNull($notification->read_at);
        $this->assertNotNull($notification->sent_at);

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            'NOTIFICATION_CREATED',
            $auditLog->action_type
        );

        $this->assertSame(
            $notification->id,
            $auditLog->record_id
        );
    }

    public function test_it_uses_the_current_status_from_the_history(): void
    {
        [$shipment, $customer] = $this->createShipmentScenario();

        $inTransitStatus = $this->findShipmentStatus(
            'IN_TRANSIT'
        );

        $shipment->update([
            'shipment_status_id' => $inTransitStatus->id,
        ]);

        ShipmentStatusHistory::query()->create([
            'shipment_id' => $shipment->id,
            'shipment_status_id' => $inTransitStatus->id,
            'changed_by_user_id' => $customer->user_id,
            'comment' => 'Shipment in transit.',
            'changed_at' => now(),
        ]);

        $notification = AppNotification::query()
            ->firstOrFail();

        $this->assertStringContainsString(
            'IN_TRANSIT',
            $notification->message
        );

        $this->assertSame(
            $customer->user_id,
            $notification->user_id
        );
    }

    public function test_it_does_not_notify_the_initial_requested_status(): void
    {
        [$shipment, $customer] = $this->createShipmentScenario();

        $requestedStatus = $this->findShipmentStatus(
            'REQUESTED'
        );

        ShipmentStatusHistory::query()->create([
            'shipment_id' => $shipment->id,
            'shipment_status_id' => $requestedStatus->id,
            'changed_by_user_id' => $customer->user_id,
            'comment' => 'Initial shipment status.',
            'changed_at' => now(),
        ]);

        $this->assertDatabaseCount('app_notifications', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_it_does_not_notify_an_inactive_customer(): void
    {
        [$shipment, $customer] = $this->createShipmentScenario();

        $customer->user->update([
            'account_status_id' => AccountStatus::query()
                ->where('status_name', 'SUSPENDED')
                ->firstOrFail()
                ->id,
        ]);

        $status = $this->findShipmentStatus(
            'IN_TRANSIT'
        );

        $shipment->update([
            'shipment_status_id' => $status->id,
        ]);

        ShipmentStatusHistory::query()->create([
            'shipment_id' => $shipment->id,
            'shipment_status_id' => $status->id,
            'changed_by_user_id' => $customer->user_id,
            'comment' => 'Shipment in transit.',
            'changed_at' => now(),
        ]);

        $this->assertDatabaseCount('shipment_status_history', 1);
        $this->assertDatabaseCount('app_notifications', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_observer_execution_is_idempotent(): void
    {
        [$shipment, $customer] = $this->createShipmentScenario();

        $status = $this->findShipmentStatus(
            'OUT_FOR_DELIVERY'
        );

        $shipment->update([
            'shipment_status_id' => $status->id,
        ]);

        $statusHistory = ShipmentStatusHistory::query()->create([
            'shipment_id' => $shipment->id,
            'shipment_status_id' => $status->id,
            'changed_by_user_id' => $customer->user_id,
            'comment' => 'Shipment is out for delivery.',
            'changed_at' => now(),
        ]);

        /*
         * Simulate a repeated observer execution.
         */
        app(ShipmentStatusHistoryObserver::class)
            ->created($statusHistory);

        $this->assertDatabaseCount('app_notifications', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_notification_is_rolled_back_with_the_status_history(): void
    {
        [$shipment, $customer] = $this->createShipmentScenario();

        $status = $this->findShipmentStatus(
            'IN_TRANSIT'
        );

        try {
            DB::transaction(function () use (
                $shipment,
                $customer,
                $status
            ): void {
                $shipment->update([
                    'shipment_status_id' => $status->id,
                ]);

                ShipmentStatusHistory::query()->create([
                    'shipment_id' => $shipment->id,
                    'shipment_status_id' => $status->id,
                    'changed_by_user_id' => $customer->user_id,
                    'comment' => 'This transaction will fail.',
                    'changed_at' => now(),
                ]);

                throw new RuntimeException(
                    'Forced transaction failure.'
                );
            });

            $this->fail(
                'A RuntimeException was expected.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Forced transaction failure.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount(
            'shipment_status_history',
            0
        );

        $this->assertDatabaseCount(
            'app_notifications',
            0
        );

        $this->assertDatabaseCount(
            'audit_logs',
            0
        );

        $this->assertSame(
            $this->findShipmentStatus('REQUESTED')->id,
            $shipment->fresh()->shipment_status_id
        );
    }

    /**
     * @return array{0: Shipment, 1: Customer}
     */
    private function createShipmentScenario(): array
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
            'shipment_status_id' => $this
                ->findShipmentStatus('REQUESTED')
                ->id,
            'delivered_at' => null,
        ]);

        return [$shipment, $customer];
    }

    private function findShipmentStatus(
        string $statusName
    ): ShipmentStatus {
        return ShipmentStatus::query()
            ->where('status_name', $statusName)
            ->firstOrFail();
    }
}

