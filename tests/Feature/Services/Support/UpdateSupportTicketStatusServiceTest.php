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
use App\Services\Support\UpdateSupportTicketStatusService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateSupportTicketStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_assigned_agent_can_start_an_open_ticket(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            statusName: 'OPEN'
        );

        $updatedTicket = app(
            UpdateSupportTicketStatusService::class
        )->execute(
            $ticket,
            'IN_PROGRESS',
            $agent
        );

        $this->assertSame(
            'IN_PROGRESS',
            $updatedTicket->status->status_name
        );

        $this->assertNull($updatedTicket->closed_at);
        $this->assertDatabaseCount('audit_logs', 1);

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            $agent->id,
            $auditLog->performed_by_user_id
        );

        $this->assertSame(
            'TICKET_STATUS_CHANGED',
            $auditLog->action_type
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

    public function test_assigned_agent_can_wait_for_the_customer(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            statusName: 'IN_PROGRESS'
        );

        app(UpdateSupportTicketStatusService::class)->execute(
            $ticket,
            'WAITING_CUSTOMER',
            $agent,
            'Additional information was requested.'
        );

        $this->assertTicketStatus(
            $ticket,
            'WAITING_CUSTOMER'
        );
    }

    public function test_assigned_agent_can_resolve_a_ticket_with_a_comment(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            statusName: 'IN_PROGRESS'
        );

        $updatedTicket = app(
            UpdateSupportTicketStatusService::class
        )->execute(
            $ticket,
            'RESOLVED',
            $agent,
            'The technical issue was corrected.'
        );

        $this->assertSame(
            'RESOLVED',
            $updatedTicket->status->status_name
        );

        $this->assertNull($updatedTicket->closed_at);

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            'The technical issue was corrected.',
            $auditLog->details['comment']
        );
    }

    public function test_resolved_ticket_can_be_closed(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            statusName: 'RESOLVED'
        );

        $updatedTicket = app(
            UpdateSupportTicketStatusService::class
        )->execute(
            $ticket,
            'CLOSED',
            $agent,
            'The resolution was confirmed.'
        );

        $this->assertSame(
            'CLOSED',
            $updatedTicket->status->status_name
        );

        $this->assertNotNull($updatedTicket->closed_at);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'ticket_status_id' => $this
                ->findTicketStatus('CLOSED')
                ->id,
        ]);
    }

    public function test_resolved_ticket_can_be_reopened_with_a_comment(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            statusName: 'RESOLVED'
        );

        $updatedTicket = app(
            UpdateSupportTicketStatusService::class
        )->execute(
            $ticket,
            'IN_PROGRESS',
            $agent,
            'The reported problem happened again.'
        );

        $this->assertSame(
            'IN_PROGRESS',
            $updatedTicket->status->status_name
        );

        $this->assertNull($updatedTicket->closed_at);

        $this->assertSame(
            'The reported problem happened again.',
            AuditLog::query()
                ->firstOrFail()
                ->details['comment']
        );
    }

    public function test_administrator_can_update_a_ticket_assigned_to_another_user(): void
    {
        [$ticket] = $this->createTicketScenario(
            statusName: 'IN_PROGRESS'
        );

        $administrator = User::factory()
            ->administrator()
            ->create();

        app(UpdateSupportTicketStatusService::class)->execute(
            $ticket,
            'WAITING_CUSTOMER',
            $administrator,
            'Administrative update.'
        );

        $this->assertTicketStatus(
            $ticket,
            'WAITING_CUSTOMER'
        );

        $this->assertSame(
            $administrator->id,
            AuditLog::query()
                ->firstOrFail()
                ->performed_by_user_id
        );
    }

    public function test_unassigned_support_agent_cannot_update_the_ticket(): void
    {
        [$ticket] = $this->createTicketScenario(
            statusName: 'IN_PROGRESS'
        );

        $otherAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $this->assertDomainException(
            fn () => app(
                UpdateSupportTicketStatusService::class
            )->execute(
                $ticket,
                'WAITING_CUSTOMER',
                $otherAgent
            ),
            'Only administrators or the assigned support agent can update this ticket.'
        );

        $this->assertTicketWasNotModified(
            $ticket,
            'IN_PROGRESS'
        );
    }

    public function test_customer_cannot_update_ticket_status_directly(): void
    {
        [$ticket] = $this->createTicketScenario(
            statusName: 'IN_PROGRESS'
        );

        $customerUser = User::factory()->create();

        $this->assertDomainException(
            fn () => app(
                UpdateSupportTicketStatusService::class
            )->execute(
                $ticket,
                'WAITING_CUSTOMER',
                $customerUser
            ),
            'Only administrators or the assigned support agent can update this ticket.'
        );

        $this->assertTicketWasNotModified(
            $ticket,
            'IN_PROGRESS'
        );
    }

    public function test_inactive_user_cannot_update_ticket_status(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            statusName: 'IN_PROGRESS'
        );

        $agent->update([
            'account_status_id' => $this
                ->findAccountStatus('SUSPENDED')
                ->id,
        ]);

        $this->assertDomainException(
            fn () => app(
                UpdateSupportTicketStatusService::class
            )->execute(
                $ticket,
                'WAITING_CUSTOMER',
                $agent
            ),
            'Only an active user can update support ticket statuses.'
        );

        $this->assertTicketWasNotModified(
            $ticket,
            'IN_PROGRESS'
        );
    }

    public function test_unassigned_ticket_cannot_enter_an_active_status(): void
    {
        [$ticket] = $this->createTicketScenario(
            statusName: 'OPEN',
            assigned: false
        );

        $administrator = User::factory()
            ->administrator()
            ->create();

        $this->assertDomainException(
            fn () => app(
                UpdateSupportTicketStatusService::class
            )->execute(
                $ticket,
                'IN_PROGRESS',
                $administrator
            ),
            'The support ticket must be assigned before entering an active workflow status.'
        );

        $this->assertTicketWasNotModified(
            $ticket,
            'OPEN'
        );
    }

    public function test_it_rejects_an_invalid_direct_transition(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            statusName: 'OPEN'
        );

        $this->assertDomainException(
            fn () => app(
                UpdateSupportTicketStatusService::class
            )->execute(
                $ticket,
                'CLOSED',
                $agent,
                'Invalid direct closure.'
            ),
            'The support ticket status transition from OPEN to CLOSED is not allowed.'
        );

        $this->assertTicketWasNotModified(
            $ticket,
            'OPEN'
        );
    }

    public function test_it_rejects_the_same_status(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            statusName: 'IN_PROGRESS'
        );

        $this->assertDomainException(
            fn () => app(
                UpdateSupportTicketStatusService::class
            )->execute(
                $ticket,
                'IN_PROGRESS',
                $agent
            ),
            'The support ticket is already in the requested status.'
        );

        $this->assertTicketWasNotModified(
            $ticket,
            'IN_PROGRESS'
        );
    }

    public function test_it_rejects_an_unknown_status(): void
    {
        [$ticket, $agent] = $this->createTicketScenario();

        $this->assertDomainException(
            fn () => app(
                UpdateSupportTicketStatusService::class
            )->execute(
                $ticket,
                'UNKNOWN_STATUS',
                $agent
            ),
            'The selected support ticket status does not exist.'
        );

        $this->assertTicketWasNotModified(
            $ticket,
            'OPEN'
        );
    }

    public function test_it_requires_a_comment_to_resolve_a_ticket(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            statusName: 'IN_PROGRESS'
        );

        $this->assertDomainException(
            fn () => app(
                UpdateSupportTicketStatusService::class
            )->execute(
                $ticket,
                'RESOLVED',
                $agent,
                '   '
            ),
            'A comment is required to resolve a support ticket.'
        );

        $this->assertTicketWasNotModified(
            $ticket,
            'IN_PROGRESS'
        );
    }

    public function test_it_requires_a_comment_to_close_a_ticket(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            statusName: 'RESOLVED'
        );

        $this->assertDomainException(
            fn () => app(
                UpdateSupportTicketStatusService::class
            )->execute(
                $ticket,
                'CLOSED',
                $agent
            ),
            'A comment is required to close a support ticket.'
        );

        $this->assertTicketWasNotModified(
            $ticket,
            'RESOLVED'
        );
    }

    public function test_it_requires_a_comment_to_reopen_a_resolved_ticket(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            statusName: 'RESOLVED'
        );

        $this->assertDomainException(
            fn () => app(
                UpdateSupportTicketStatusService::class
            )->execute(
                $ticket,
                'IN_PROGRESS',
                $agent,
                null
            ),
            'A comment is required to reopen a resolved support ticket.'
        );

        $this->assertTicketWasNotModified(
            $ticket,
            'RESOLVED'
        );
    }

    /**
     * @return array{0: SupportTicket, 1: User, 2: Customer}
     */
    private function createTicketScenario(
        string $statusName = 'OPEN',
        bool $assigned = true
    ): array {
        $customer = Customer::factory()->create();

        $agent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = SupportTicket::query()->create([
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
            'assigned_to_user_id' => $assigned
                ? $agent->id
                : null,
            'subject' => 'Application support request',
            'closed_at' => $statusName === 'CLOSED'
                ? now()->subMinutes(10)
                : null,
        ]);

        return [$ticket, $agent, $customer];
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

    private function assertTicketStatus(
        SupportTicket $ticket,
        string $expectedStatus
    ): void {
        $this->assertSame(
            $this->findTicketStatus($expectedStatus)->id,
            $ticket->fresh()->ticket_status_id
        );
    }

    private function assertTicketWasNotModified(
        SupportTicket $ticket,
        string $expectedStatus
    ): void {
        $this->assertTicketStatus(
            $ticket,
            $expectedStatus
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

