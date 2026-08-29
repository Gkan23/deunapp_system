<?php

namespace Tests\Feature\Policies;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuditLogPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_administrator_can_view_audit_logs(): void
    {
        $administrator = $this->createUserWithRole(
            'ADMINISTRATOR'
        );

        $auditLog = $this->auditLogFor(
            $administrator
        );

        $this->assertTrue(
            $this->allows(
                $administrator,
                'viewAny',
                AuditLog::class
            )
        );

        $this->assertTrue(
            $this->allows(
                $administrator,
                'view',
                $auditLog
            )
        );
    }

    public function test_support_agent_cannot_view_audit_logs(): void
    {
        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $auditLog = $this->auditLogFor(
            $supportAgent
        );

        $this->assertFalse(
            $this->allows(
                $supportAgent,
                'viewAny',
                AuditLog::class
            )
        );

        $this->assertFalse(
            $this->allows(
                $supportAgent,
                'view',
                $auditLog
            )
        );
    }

    public function test_operational_roles_cannot_view_audit_logs(): void
    {
        $auditLog = $this->auditLogFor(null);

        $users = [
            Customer::factory()->create()->user,
            DeliveryProvider::factory()->create()->user,
            Courier::factory()->create()->user,
        ];

        foreach ($users as $user) {
            $this->assertFalse(
                $this->allows(
                    $user,
                    'viewAny',
                    AuditLog::class
                )
            );

            $this->assertFalse(
                $this->allows(
                    $user,
                    'view',
                    $auditLog
                )
            );
        }
    }

    public function test_inactive_administrator_cannot_view_audit_logs(): void
    {
        $administrator = $this->createUserWithRole(
            'ADMINISTRATOR'
        );

        $auditLog = $this->auditLogFor(
            $administrator
        );

        $administrator->update([
            'account_status_id' => AccountStatus::query()
                ->where('status_name', 'SUSPENDED')
                ->firstOrFail()
                ->id,
        ]);

        $administrator = $administrator->fresh();

        $this->assertFalse(
            $this->allows(
                $administrator,
                'viewAny',
                AuditLog::class
            )
        );

        $this->assertFalse(
            $this->allows(
                $administrator,
                'view',
                $auditLog
            )
        );
    }

    public function test_audit_logs_cannot_be_modified_directly(): void
    {
        $administrator = $this->createUserWithRole(
            'ADMINISTRATOR'
        );

        $auditLog = $this->auditLogFor(
            $administrator
        );

        $this->assertFalse(
            $this->allows(
                $administrator,
                'create',
                AuditLog::class
            )
        );

        foreach ([
            'update',
            'delete',
            'restore',
            'forceDelete',
        ] as $ability) {
            $this->assertFalse(
                $this->allows(
                    $administrator,
                    $ability,
                    $auditLog
                )
            );
        }
    }

    private function auditLogFor(
        ?User $user
    ): AuditLog {
        return AuditLog::query()->create([
            'performed_by_user_id' => $user?->id,
            'table_name' => 'shipments',
            'record_id' => 1,
            'action_type' => 'STATUS_CHANGED',
            'details' => [
                'from_status' => 'PENDING',
                'to_status' => 'IN_TRANSIT',
            ],
            'performed_at' => now(),
        ]);
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

    private function allows(
        User $user,
        string $ability,
        mixed $arguments
    ): bool {
        return Gate::forUser($user)->allows(
            $ability,
            $arguments
        );
    }
}