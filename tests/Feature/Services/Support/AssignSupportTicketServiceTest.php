<?php

namespace Tests\Feature\Services\Support;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use App\Services\Support\AssignSupportTicketService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignSupportTicketServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_administrator_can_assign_an_open_ticket_to_a_support_agent(): void
    {
        $administrator = User::factory()
            ->administrator()
            ->create();

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->createTicket();

        $assignedTicket = app(
            AssignSupportTicketService::class
        )->execute(
            $ticket,
            $supportAgent,
            $administrator
        );

        $this->assertSame(
            $supportAgent->id,
            $assignedTicket->assigned_to_user_id
        );

        $this->assertSame(
            'IN_PROGRESS',
            $assignedTicket->status->status_name
        );

        $this->assertNull($assignedTicket->closed_at);
        $this->assertDatabaseCount('audit_logs', 1);

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            $administrator->id,
            $auditLog->performed_by_user_id
        );

        $this->assertSame(
            'support_tickets',
            $auditLog->table_name
        );

        $this->assertSame($ticket->id, $auditLog->record_id);

        $this->assertSame(
            'TICKET_ASSIGNED',
            $auditLog->action_type
        );

        $this->assertNull(
            $auditLog->details['previous_assignee_id']
        );

        $this->assertSame(
            $supportAgent->id,
            $auditLog->details['new_assignee_id']
        );

        $this->assertSame(
            'OPEN',
            $auditLog->details['from_status']
        );

        $this->assertSame(
            'IN_PROGRESS',
            $auditLog->details['to_status']
        );
    }

    public function test_support_agent_can_claim_an_unassigned_ticket(): void
    {
        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->createTicket();

        $assignedTicket = app(
            AssignSupportTicketService::class
        )->execute(
            $ticket,
            $supportAgent,
            $supportAgent
        );

        $this->assertSame(
            $supportAgent->id,
            $assignedTicket->assigned_to_user_id
        );

        $this->assertSame(
            'IN_PROGRESS',
            $assignedTicket->status->status_name
        );

        $this->assertSame(
            'TICKET_ASSIGNED',
            AuditLog::query()->firstOrFail()->action_type
        );
    }

    public function test_administrator_can_assign_a_ticket_to_another_administrator(): void
    {
        $performedBy = User::factory()
            ->administrator()
            ->create();

        $assignee = User::factory()
            ->administrator()
            ->create();

        $ticket = $this->createTicket();

        $assignedTicket = app(
            AssignSupportTicketService::class
        )->execute(
            $ticket,
            $assignee,
            $performedBy
        );

        $this->assertSame(
            $assignee->id,
            $assignedTicket->assigned_to_user_id
        );

        $this->assertSame(
            'ADMINISTRATOR',
            $assignedTicket->assignedTo->role->role_name
        );
    }

    public function test_administrator_can_reassign_a_ticket(): void
    {
        $administrator = User::factory()
            ->administrator()
            ->create();

        $firstAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $secondAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->createTicket(
            statusName: 'IN_PROGRESS',
            assignedTo: $firstAgent
        );

        $assignedTicket = app(
            AssignSupportTicketService::class
        )->execute(
            $ticket,
            $secondAgent,
            $administrator
        );

        $this->assertSame(
            $secondAgent->id,
            $assignedTicket->assigned_to_user_id
        );

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            'TICKET_REASSIGNED',
            $auditLog->action_type
        );

        $this->assertSame(
            $firstAgent->id,
            $auditLog->details['previous_assignee_id']
        );

        $this->assertSame(
            $secondAgent->id,
            $auditLog->details['new_assignee_id']
        );
    }

    public function test_support_agent_cannot_assign_a_ticket_to_another_agent(): void
    {
        $performedBy = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $assignee = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->createTicket();

        $this->assertDomainException(
            fn () => app(
                AssignSupportTicketService::class
            )->execute(
                $ticket,
                $assignee,
                $performedBy
            ),
            'Support agents can only claim unassigned tickets for themselves.'
        );

        $this->assertTicketWasNotAssigned($ticket);
    }

    public function test_support_agent_cannot_claim_an_already_assigned_ticket(): void
    {
        $currentAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $otherAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->createTicket(
            statusName: 'IN_PROGRESS',
            assignedTo: $currentAgent
        );

        $this->assertDomainException(
            fn () => app(
                AssignSupportTicketService::class
            )->execute(
                $ticket,
                $otherAgent,
                $otherAgent
            ),
            'Support agents can only claim unassigned tickets for themselves.'
        );

        $this->assertSame(
            $currentAgent->id,
            $ticket->fresh()->assigned_to_user_id
        );

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_customer_cannot_assign_support_tickets(): void
    {
        $customerUser = User::factory()->create();

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->createTicket();

        $this->assertDomainException(
            fn () => app(
                AssignSupportTicketService::class
            )->execute(
                $ticket,
                $supportAgent,
                $customerUser
            ),
            'Only administrators and support agents can assign support tickets.'
        );

        $this->assertTicketWasNotAssigned($ticket);
    }

    public function test_inactive_user_cannot_assign_support_tickets(): void
    {
        $administrator = User::factory()
            ->administrator()
            ->create([
                'account_status_id' => $this
                    ->findAccountStatus('SUSPENDED')
                    ->id,
            ]);

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->createTicket();

        $this->assertDomainException(
            fn () => app(
                AssignSupportTicketService::class
            )->execute(
                $ticket,
                $supportAgent,
                $administrator
            ),
            'Only an active user can assign support tickets.'
        );

        $this->assertTicketWasNotAssigned($ticket);
    }

    public function test_inactive_user_cannot_be_assigned(): void
    {
        $administrator = User::factory()
            ->administrator()
            ->create();

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT',
            'SUSPENDED'
        );

        $ticket = $this->createTicket();

        $this->assertDomainException(
            fn () => app(
                AssignSupportTicketService::class
            )->execute(
                $ticket,
                $supportAgent,
                $administrator
            ),
            'Only an active user can be assigned to a support ticket.'
        );

        $this->assertTicketWasNotAssigned($ticket);
    }

    public function test_customer_cannot_be_assigned_as_support_user(): void
    {
        $administrator = User::factory()
            ->administrator()
            ->create();

        $invalidAssignee = User::factory()->create();

        $ticket = $this->createTicket();

        $this->assertDomainException(
            fn () => app(
                AssignSupportTicketService::class
            )->execute(
                $ticket,
                $invalidAssignee,
                $administrator
            ),
            'The assigned user must have a support role.'
        );

        $this->assertTicketWasNotAssigned($ticket);
    }

    public function test_it_rejects_a_resolved_ticket(): void
    {
        $administrator = User::factory()
            ->administrator()
            ->create();

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->createTicket(
            statusName: 'RESOLVED'
        );

        $this->assertDomainException(
            fn () => app(
                AssignSupportTicketService::class
            )->execute(
                $ticket,
                $supportAgent,
                $administrator
            ),
            'Only open, in-progress, or waiting-customer tickets can be assigned.'
        );

        $this->assertTicketWasNotAssigned($ticket);
    }

    public function test_it_rejects_a_closed_ticket(): void
    {
        $administrator = User::factory()
            ->administrator()
            ->create();

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->createTicket(
            statusName: 'CLOSED'
        );

        $this->assertDomainException(
            fn () => app(
                AssignSupportTicketService::class
            )->execute(
                $ticket,
                $supportAgent,
                $administrator
            ),
            'Only open, in-progress, or waiting-customer tickets can be assigned.'
        );

        $this->assertTicketWasNotAssigned($ticket);
    }

    public function test_it_rejects_assigning_the_same_user_twice(): void
    {
        $administrator = User::factory()
            ->administrator()
            ->create();

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->createTicket(
            statusName: 'IN_PROGRESS',
            assignedTo: $supportAgent
        );

        $this->assertDomainException(
            fn () => app(
                AssignSupportTicketService::class
            )->execute(
                $ticket,
                $supportAgent,
                $administrator
            ),
            'The support ticket is already assigned to this user.'
        );

        $this->assertSame(
            $supportAgent->id,
            $ticket->fresh()->assigned_to_user_id
        );

        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function createTicket(
        string $statusName = 'OPEN',
        ?User $assignedTo = null
    ): SupportTicket {
        $customer = Customer::factory()->create();

        return SupportTicket::query()->create([
            'customer_id' => $customer->id,
            'shipment_id' => null,
            'ticket_category_id' => TicketCategory::query()
                ->where('category_name', 'TECHNICAL')
                ->firstOrFail()
                ->id,
            'ticket_status_id' => $this
                ->findTicketStatus($statusName)
                ->id,
            'ticket_priority_id' => TicketPriority::query()
                ->where('priority_name', 'MEDIUM')
                ->firstOrFail()
                ->id,
            'assigned_to_user_id' => $assignedTo?->id,
            'subject' => 'Application support request',
            'closed_at' => $statusName === 'CLOSED'
                ? now()->subMinutes(10)
                : null,
        ]);
    }

    private function createUserWithRole(
        string $roleName,
        string $accountStatus = 'ACTIVE'
    ): User {
        return User::factory()->create([
            'role_id' => Role::query()
                ->where('role_name', $roleName)
                ->firstOrFail()
                ->id,
            'account_status_id' => $this
                ->findAccountStatus($accountStatus)
                ->id,
        ]);
    }

    private function findTicketStatus(
        string $statusName
    ): TicketStatus {
        return TicketStatus::query()
            ->where('status_name', $statusName)
            ->firstOrFail();
    }

    private function findAccountStatus(
        string $statusName
    ): AccountStatus {
        return AccountStatus::query()
            ->where('status_name', $statusName)
            ->firstOrFail();
    }

    private function assertTicketWasNotAssigned(
        SupportTicket $ticket
    ): void {
        $this->assertNull(
            $ticket->fresh()->assigned_to_user_id
        );

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
