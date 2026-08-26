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
use App\Services\Support\MarkSupportMessagesAsReadService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkSupportMessagesAsReadServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_customer_marks_only_support_messages_as_read(): void
    {
        [$ticket, $customer, $agent] =
            $this->createTicketScenario();

        $customerMessage = $this->createMessage(
            $ticket,
            $customer->user
        );

        $firstAgentMessage = $this->createMessage(
            $ticket,
            $agent
        );

        $secondAgentMessage = $this->createMessage(
            $ticket,
            $agent
        );

        $readCount = app(
            MarkSupportMessagesAsReadService::class
        )->execute(
            $ticket,
            $customer->user
        );

        $this->assertSame(2, $readCount);
        $this->assertFalse($customerMessage->fresh()->is_read);
        $this->assertTrue($firstAgentMessage->fresh()->is_read);
        $this->assertTrue($secondAgentMessage->fresh()->is_read);

        $this->assertDatabaseCount('audit_logs', 1);

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            $customer->user_id,
            $auditLog->performed_by_user_id
        );

        $this->assertSame(
            'SUPPORT_MESSAGES_READ',
            $auditLog->action_type
        );

        $this->assertSame(
            'CUSTOMER',
            $auditLog->details['reader_type']
        );

        $this->assertSame(
            2,
            $auditLog->details['message_count']
        );

        $this->assertSame(
            [
                $firstAgentMessage->id,
                $secondAgentMessage->id,
            ],
            $auditLog->details['message_ids']
        );
    }

    public function test_assigned_agent_marks_customer_messages_as_read(): void
    {
        [$ticket, $customer, $agent] =
            $this->createTicketScenario();

        $firstCustomerMessage = $this->createMessage(
            $ticket,
            $customer->user
        );

        $secondCustomerMessage = $this->createMessage(
            $ticket,
            $customer->user
        );

        $agentMessage = $this->createMessage(
            $ticket,
            $agent
        );

        $readCount = app(
            MarkSupportMessagesAsReadService::class
        )->execute(
            $ticket,
            $agent
        );

        $this->assertSame(2, $readCount);
        $this->assertTrue($firstCustomerMessage->fresh()->is_read);
        $this->assertTrue($secondCustomerMessage->fresh()->is_read);
        $this->assertFalse($agentMessage->fresh()->is_read);

        $this->assertSame(
            'SUPPORT',
            AuditLog::query()
                ->firstOrFail()
                ->details['reader_type']
        );
    }

    public function test_it_ignores_messages_that_are_already_read(): void
    {
        [$ticket, $customer, $agent] =
            $this->createTicketScenario();

        $alreadyReadMessage = $this->createMessage(
            $ticket,
            $agent,
            true
        );

        $unreadMessage = $this->createMessage(
            $ticket,
            $agent
        );

        $readCount = app(
            MarkSupportMessagesAsReadService::class
        )->execute(
            $ticket,
            $customer->user
        );

        $this->assertSame(1, $readCount);
        $this->assertTrue($alreadyReadMessage->fresh()->is_read);
        $this->assertTrue($unreadMessage->fresh()->is_read);

        $this->assertSame(
            [$unreadMessage->id],
            AuditLog::query()
                ->firstOrFail()
                ->details['message_ids']
        );
    }

    public function test_repeated_execution_is_idempotent(): void
    {
        [$ticket, $customer, $agent] =
            $this->createTicketScenario();

        $message = $this->createMessage(
            $ticket,
            $agent
        );

        $service = app(
            MarkSupportMessagesAsReadService::class
        );

        $firstResult = $service->execute(
            $ticket,
            $customer->user
        );

        $secondResult = $service->execute(
            $ticket,
            $customer->user
        );

        $this->assertSame(1, $firstResult);
        $this->assertSame(0, $secondResult);
        $this->assertTrue($message->fresh()->is_read);

        /*
         * The second execution does not create a duplicated audit.
         */
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_messages_from_a_closed_ticket_can_be_read(): void
    {
        [$ticket, $customer, $agent] =
            $this->createTicketScenario(
                statusName: 'CLOSED'
            );

        $message = $this->createMessage(
            $ticket,
            $agent
        );

        $readCount = app(
            MarkSupportMessagesAsReadService::class
        )->execute(
            $ticket,
            $customer->user
        );

        $this->assertSame(1, $readCount);
        $this->assertTrue($message->fresh()->is_read);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_unrelated_user_cannot_read_ticket_messages(): void
    {
        [$ticket, $customer, $agent] =
            $this->createTicketScenario();

        $message = $this->createMessage(
            $ticket,
            $customer->user
        );

        $unrelatedUser = User::factory()->create();

        $this->assertDomainException(
            fn () => app(
                MarkSupportMessagesAsReadService::class
            )->execute(
                $ticket,
                $unrelatedUser
            ),
            'The user is not allowed to read messages from this support ticket.'
        );

        $this->assertFalse($message->fresh()->is_read);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_unassigned_support_agent_cannot_read_messages(): void
    {
        [$ticket, $customer] =
            $this->createTicketScenario(
                assigned: false
            );

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $message = $this->createMessage(
            $ticket,
            $customer->user
        );

        $this->assertDomainException(
            fn () => app(
                MarkSupportMessagesAsReadService::class
            )->execute(
                $ticket,
                $supportAgent
            ),
            'The user is not allowed to read messages from this support ticket.'
        );

        $this->assertFalse($message->fresh()->is_read);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_assigned_user_without_support_role_cannot_read_messages(): void
    {
        $invalidAssignedUser = User::factory()->create();

        [$ticket, $customer] =
            $this->createTicketScenario(
                assignedUser: $invalidAssignedUser
            );

        $message = $this->createMessage(
            $ticket,
            $customer->user
        );

        $this->assertDomainException(
            fn () => app(
                MarkSupportMessagesAsReadService::class
            )->execute(
                $ticket,
                $invalidAssignedUser
            ),
            'The user is not allowed to read messages from this support ticket.'
        );

        $this->assertFalse($message->fresh()->is_read);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_inactive_user_cannot_read_support_messages(): void
    {
        [$ticket, $customer, $agent] =
            $this->createTicketScenario();

        $customer->user->update([
            'account_status_id' => $this
                ->findAccountStatus('SUSPENDED')
                ->id,
        ]);

        $message = $this->createMessage(
            $ticket,
            $agent
        );

        $this->assertDomainException(
            fn () => app(
                MarkSupportMessagesAsReadService::class
            )->execute(
                $ticket,
                $customer->user
            ),
            'Only an active user can read support messages.'
        );

        $this->assertFalse($message->fresh()->is_read);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_it_returns_zero_when_there_are_no_unread_messages(): void
    {
        [$ticket, $customer, $agent] =
            $this->createTicketScenario();

        $this->createMessage(
            $ticket,
            $agent,
            true
        );

        $readCount = app(
            MarkSupportMessagesAsReadService::class
        )->execute(
            $ticket,
            $customer->user
        );

        $this->assertSame(0, $readCount);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /**
     * @return array{0: SupportTicket, 1: Customer, 2: User}
     */
    private function createTicketScenario(
        string $statusName = 'IN_PROGRESS',
        bool $assigned = true,
        ?User $assignedUser = null
    ): array {
        $customer = Customer::factory()->create();

        $agent = $assignedUser
            ?? $this->createUserWithRole('SUPPORT_AGENT');

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

        return [$ticket, $customer, $agent];
    }

    private function createMessage(
        SupportTicket $ticket,
        User $sender,
        bool $isRead = false
    ): SupportMessage {
        return SupportMessage::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $sender->id,
            'message_text' => 'Support conversation message.',
            'attachment_url' => null,
            'sent_at' => now()->subMinutes(5),
            'is_read' => $isRead,
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

