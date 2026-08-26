<?php

namespace Tests\Feature\Services\Support;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Role;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use App\Services\Support\AddSupportMessageService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddSupportMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_customer_can_add_a_message_to_an_open_ticket(): void
    {
        [$ticket, $customer] = $this->createTicketScenario();

        $message = app(
            AddSupportMessageService::class
        )->execute(
            $ticket,
            $customer->user,
            '  I want to provide additional information.  ',
            '   '
        );

        $this->assertSame($ticket->id, $message->ticket_id);
        $this->assertSame($customer->user_id, $message->user_id);

        $this->assertSame(
            'I want to provide additional information.',
            $message->message_text
        );

        $this->assertNull($message->attachment_url);
        $this->assertNotNull($message->sent_at);
        $this->assertFalse($message->is_read);

        $this->assertTicketStatus($ticket, 'OPEN');

        $this->assertDatabaseCount('support_messages', 1);
        $this->assertDatabaseCount('audit_logs', 1);

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            'SUPPORT_MESSAGE_SENT',
            $auditLog->action_type
        );

        $this->assertSame(
            'CUSTOMER',
            $auditLog->details['sender_type']
        );

        $this->assertSame(
            'OPEN',
            $auditLog->details['from_status']
        );

        $this->assertSame(
            'OPEN',
            $auditLog->details['to_status']
        );
    }

    public function test_assigned_support_agent_can_reply_and_wait_for_customer(): void
    {
        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        [$ticket] = $this->createTicketScenario(
            assignedTo: $supportAgent
        );

        $message = app(
            AddSupportMessageService::class
        )->execute(
            $ticket,
            $supportAgent,
            'Please verify the requested information.',
            '  /storage/support/instructions.pdf  '
        );

        $this->assertSame(
            '/storage/support/instructions.pdf',
            $message->attachment_url
        );

        $this->assertTicketStatus(
            $ticket,
            'WAITING_CUSTOMER'
        );

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            'SUPPORT',
            $auditLog->details['sender_type']
        );

        $this->assertSame(
            'OPEN',
            $auditLog->details['from_status']
        );

        $this->assertSame(
            'WAITING_CUSTOMER',
            $auditLog->details['to_status']
        );
    }

    public function test_assigned_administrator_can_reply(): void
    {
        $administrator = User::factory()
            ->administrator()
            ->create();

        [$ticket] = $this->createTicketScenario(
            assignedTo: $administrator
        );

        app(AddSupportMessageService::class)->execute(
            $ticket,
            $administrator,
            'The administrator is reviewing the ticket.'
        );

        $this->assertTicketStatus(
            $ticket,
            'WAITING_CUSTOMER'
        );

        $this->assertDatabaseCount('support_messages', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_customer_reply_moves_waiting_ticket_to_in_progress(): void
    {
        [$ticket, $customer] = $this->createTicketScenario(
            statusName: 'WAITING_CUSTOMER'
        );

        app(AddSupportMessageService::class)->execute(
            $ticket,
            $customer->user,
            'Here is the information you requested.'
        );

        $this->assertTicketStatus(
            $ticket,
            'IN_PROGRESS'
        );

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            'WAITING_CUSTOMER',
            $auditLog->details['from_status']
        );

        $this->assertSame(
            'IN_PROGRESS',
            $auditLog->details['to_status']
        );
    }

    public function test_new_message_reopens_a_resolved_ticket(): void
    {
        [$ticket, $customer] = $this->createTicketScenario(
            statusName: 'RESOLVED'
        );

        app(AddSupportMessageService::class)->execute(
            $ticket,
            $customer->user,
            'The problem occurred again.'
        );

        $this->assertTicketStatus(
            $ticket,
            'IN_PROGRESS'
        );

        $this->assertNull($ticket->fresh()->closed_at);
    }

    public function test_it_rejects_messages_in_a_closed_ticket(): void
    {
        [$ticket, $customer] = $this->createTicketScenario(
            statusName: 'CLOSED'
        );

        $this->assertDomainException(
            fn () => app(
                AddSupportMessageService::class
            )->execute(
                $ticket,
                $customer->user,
                'This message must not be created.'
            ),
            'Messages cannot be added to a closed support ticket.'
        );

        $this->assertMessageWasNotCreated();
    }

    public function test_it_rejects_an_unrelated_user(): void
    {
        [$ticket] = $this->createTicketScenario();

        $unrelatedUser = User::factory()->create();

        $this->assertDomainException(
            fn () => app(
                AddSupportMessageService::class
            )->execute(
                $ticket,
                $unrelatedUser,
                'Unauthorized message.'
            ),
            'The user is not allowed to participate in this support ticket.'
        );

        $this->assertMessageWasNotCreated();
    }

    public function test_it_rejects_an_unassigned_support_agent(): void
    {
        [$ticket] = $this->createTicketScenario();

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $this->assertDomainException(
            fn () => app(
                AddSupportMessageService::class
            )->execute(
                $ticket,
                $supportAgent,
                'An unassigned agent must not participate.'
            ),
            'The user is not allowed to participate in this support ticket.'
        );

        $this->assertMessageWasNotCreated();
    }

    public function test_it_rejects_an_assigned_user_without_a_support_role(): void
    {
        $invalidAssignedUser = User::factory()->create();

        [$ticket] = $this->createTicketScenario(
            assignedTo: $invalidAssignedUser
        );

        $this->assertDomainException(
            fn () => app(
                AddSupportMessageService::class
            )->execute(
                $ticket,
                $invalidAssignedUser,
                'This user does not have a support role.'
            ),
            'The user is not allowed to participate in this support ticket.'
        );

        $this->assertMessageWasNotCreated();
    }

    public function test_it_rejects_an_inactive_user(): void
    {
        [$ticket, $customer] = $this->createTicketScenario();

        $customer->user->update([
            'account_status_id' => AccountStatus::query()
                ->where('status_name', 'SUSPENDED')
                ->firstOrFail()
                ->id,
        ]);

        $this->assertDomainException(
            fn () => app(
                AddSupportMessageService::class
            )->execute(
                $ticket,
                $customer->user,
                'A suspended user must not send messages.'
            ),
            'Only an active user can send support messages.'
        );

        $this->assertMessageWasNotCreated();
    }

    public function test_it_rejects_an_empty_message(): void
    {
        [$ticket, $customer] = $this->createTicketScenario();

        $this->assertDomainException(
            fn () => app(
                AddSupportMessageService::class
            )->execute(
                $ticket,
                $customer->user,
                '   '
            ),
            'The support message is required.'
        );

        $this->assertMessageWasNotCreated();
    }

    public function test_it_rejects_an_attachment_url_longer_than_500_characters(): void
    {
        [$ticket, $customer] = $this->createTicketScenario();

        $this->assertDomainException(
            fn () => app(
                AddSupportMessageService::class
            )->execute(
                $ticket,
                $customer->user,
                'Valid message.',
                str_repeat('A', 501)
            ),
            'The support attachment URL may not exceed 500 characters.'
        );

        $this->assertMessageWasNotCreated();
    }

    /**
     * @return array{0: SupportTicket, 1: Customer}
     */
    private function createTicketScenario(
        string $statusName = 'OPEN',
        ?User $assignedTo = null
    ): array {
        $customer = Customer::factory()->create();

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
            'assigned_to_user_id' => $assignedTo?->id,
            'subject' => 'Application support request',
            'closed_at' => $statusName === 'CLOSED'
                ? now()->subMinutes(10)
                : null,
        ]);

        return [$ticket, $customer];
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

    private function assertTicketStatus(
        SupportTicket $ticket,
        string $expectedStatus
    ): void {
        $this->assertSame(
            $this->findTicketStatus($expectedStatus)->id,
            $ticket->fresh()->ticket_status_id
        );
    }

    private function assertMessageWasNotCreated(): void
    {
        $this->assertDatabaseCount('support_messages', 0);
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
