<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_access_audit_log_endpoints(): void
    {
        $auditLog = $this->auditLog();

        $this->getJson(
            route('audit-logs.index')
        )->assertUnauthorized();

        $this->getJson(
            route('audit-logs.show', $auditLog)
        )->assertUnauthorized();
    }

    public function test_administrator_can_list_audit_logs(): void
    {
        $administrator = $this->administrator();

        $this->auditLog(
            tableName: 'shipments',
            actionType: 'STATUS_CHANGED'
        );

        $this->auditLog(
            tableName: 'payments',
            actionType: 'PAYMENT_CONFIRMED'
        );

        $this->actingAs($administrator)
            ->getJson(route('audit-logs.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_administrator_can_view_audit_log_details(): void
    {
        $administrator = $this->administrator();

        $auditLog = $this->auditLog(
            performedBy: $administrator,
            tableName: 'payments',
            actionType: 'PAYMENT_CONFIRMED'
        );

        $this->actingAs($administrator)
            ->getJson(
                route('audit-logs.show', $auditLog)
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $auditLog->id
            )
            ->assertJsonPath(
                'data.table_name',
                'payments'
            )
            ->assertJsonPath(
                'data.action_type',
                'PAYMENT_CONFIRMED'
            )
            ->assertJsonPath(
                'data.details.result',
                'success'
            );
    }

    public function test_non_administrators_cannot_access_audit_logs(): void
    {
        $auditLog = $this->auditLog();

        $users = [
            $this->createUserWithRole('SUPPORT_AGENT'),
            Customer::factory()->create()->user,
            DeliveryProvider::factory()->create()->user,
            Courier::factory()->create()->user,
        ];

        foreach ($users as $user) {
            $this->actingAs($user)
                ->getJson(route('audit-logs.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->getJson(
                    route('audit-logs.show', $auditLog)
                )
                ->assertForbidden();
        }
    }

    public function test_audit_logs_can_be_filtered_by_table(): void
    {
        $administrator = $this->administrator();

        $shipmentLog = $this->auditLog(
            tableName: 'shipments'
        );

        $this->auditLog(
            tableName: 'payments'
        );

        $this->actingAs($administrator)
            ->getJson(
                route('audit-logs.index', [
                    'table_name' => ' SHIPMENTS ',
                ])
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $shipmentLog->id
            );
    }

    public function test_audit_logs_can_be_filtered_by_action(): void
    {
        $administrator = $this->administrator();

        $confirmedLog = $this->auditLog(
            actionType: 'PAYMENT_CONFIRMED'
        );

        $this->auditLog(
            actionType: 'PAYMENT_REFUNDED'
        );

        $this->actingAs($administrator)
            ->getJson(
                route('audit-logs.index', [
                    'action_type' => ' payment_confirmed ',
                ])
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $confirmedLog->id
            );
    }

    public function test_audit_logs_can_be_filtered_by_user(): void
    {
        $administrator = $this->administrator();

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $firstLog = $this->auditLog(
            performedBy: $firstUser
        );

        $this->auditLog(
            performedBy: $secondUser
        );

        $this->actingAs($administrator)
            ->getJson(
                route('audit-logs.index', [
                    'performed_by_user_id' => $firstUser->id,
                ])
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $firstLog->id
            );
    }

    public function test_audit_logs_can_be_filtered_by_record(): void
    {
        $administrator = $this->administrator();

        $matchingLog = $this->auditLog(
            recordId: 25
        );

        $this->auditLog(
            recordId: 50
        );

        $this->actingAs($administrator)
            ->getJson(
                route('audit-logs.index', [
                    'record_id' => 25,
                ])
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $matchingLog->id
            );
    }

    public function test_audit_logs_can_be_filtered_by_date_range(): void
    {
        $administrator = $this->administrator();

        $this->auditLog(
            performedAt: now()->subDays(10)
        );

        $matchingLog = $this->auditLog(
            performedAt: now()->subDays(3)
        );

        $this->auditLog(
            performedAt: now()
        );

        $this->actingAs($administrator)
            ->getJson(
                route('audit-logs.index', [
                    'date_from' => today()
                        ->subDays(5)
                        ->toDateString(),
                    'date_to' => today()
                        ->subDay()
                        ->toDateString(),
                ])
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $matchingLog->id
            );
    }

    public function test_audit_log_filters_are_validated(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->getJson(
                route('audit-logs.index', [
                    'table_name' => str_repeat('T', 101),
                    'action_type' => str_repeat('A', 51),
                    'performed_by_user_id' => 999999,
                    'record_id' => 0,
                    'date_from' => today()
                        ->addDay()
                        ->toDateString(),
                    'date_to' => today()
                        ->toDateString(),
                ])
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'table_name',
                'action_type',
                'performed_by_user_id',
                'record_id',
                'date_to',
            ]);
    }

    private function auditLog(
        ?User $performedBy = null,
        string $tableName = 'shipments',
        string $actionType = 'STATUS_CHANGED',
        int $recordId = 1,
        mixed $performedAt = null
    ): AuditLog {
        return AuditLog::query()->create([
            'performed_by_user_id' => $performedBy?->id,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'action_type' => $actionType,
            'details' => [
                'result' => 'success',
            ],
            'performed_at' => $performedAt ?? now(),
        ]);
    }

    private function administrator(): User
    {
        return $this->createUserWithRole(
            'ADMINISTRATOR'
        );
    }

    private function createUserWithRole(
        string $roleName
    ): User {
        return User::factory()->create([
            'role_id' => Role::query()
                ->where('role_name', $roleName)
                ->firstOrFail()
                ->id,
        ]);
    }
}