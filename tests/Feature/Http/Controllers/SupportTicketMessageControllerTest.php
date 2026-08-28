<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_send_support_messages(): void
    {
        [$ticket] = $this->createTicketScenario();

        $this->postJson(
            route(
                'support-tickets.messages.store',
                $ticket
            ),
            [
                'message' => 'Unauthorized message.',
            ]
        )->assertUnauthorized();

        $this->assertDatabaseCount(
            'support_messages',
            0
        );

        $this->assertDatabaseCount(
            'audit_logs',
            0
        );
    }

    public function test_customer_can_add_a_message_to_their_open_ticket(): void
    {
        [$ticket, $customer] = $this
            ->createTicketScenario();

        $this->actingAs($customer->user)
            ->postJson(
                route(
                    'support-tickets.messages.store',
                    $ticket
                ),
                [
                    'message' => '  Additional information.  ',
                    'attachment_url' => '   ',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Support message sent successfully.'
            )
            ->assertJsonPath(
                'data.message_text',
                'Additional information.'
            )
            ->assertJsonPath(
                'data.attachment_url',
                null
            )
            ->assertJsonPath(
                'data.ticket.status.status_name',
                'OPEN'
            );

        $this->assertDatabaseHas(
            'support_messages',
            [
                'ticket_id' => $ticket->id,
                'user_id' => $customer->user_id,
                'message_text' => 'Additional information.',
                'attachment_url' => null,
                'is_read' => false,
            ]
        );

        $this->assertDatabaseCount(
            'audit_logs',
            1
        );

        $auditLog = AuditLog::query()
            ->firstOrFail();

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

        $this->actingAs($supportAgent)
            ->postJson(
                route(
                    'support-tickets.messages.store',
                    $ticket
                ),
                [
                    'message' => 'Please verify the information.',
                    'attachment_url' => ' /storage/support/guide.pdf ',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.attachment_url',
                '/storage/support/guide.pdf'
            )
            ->assertJsonPath(
                'data.ticket.status.status_name',
                'WAITING_CUSTOMER'
            );

        $this->assertTicketStatus(
            $ticket,
            'WAITING_CUSTOMER'
        );

        $auditLog = AuditLog::query()
            ->firstOrFail();

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

    public function test_customer_reply_moves_waiting_ticket_to_in_progress(): void
    {
        [$ticket, $customer] = $this
            ->createTicketScenario(
                statusName: 'WAITING_CUSTOMER'
            );

        $this->actingAs($customer->user)
            ->postJson(
                route(
                    'support-tickets.messages.store',
                    $ticket
                ),
                [
                    'message' => 'Here is the requested information.',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.ticket.status.status_name',
                'IN_PROGRESS'
            );

        $this->assertTicketStatus(
            $ticket,
            'IN_PROGRESS'
        );
    }

    public function test_new_message_reopens_a_resolved_ticket(): void
    {
        [$ticket, $customer] = $this
            ->createTicketScenario(
                statusName: 'RESOLVED'
            );

        $this->actingAs($customer->user)
            ->postJson(
                route(
                    'support-tickets.messages.store',
                    $ticket
                ),
                [
                    'message' => 'The problem occurred again.',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.ticket.status.status_name',
                'IN_PROGRESS'
            );

        $this->assertTicketStatus(
            $ticket,
            'IN_PROGRESS'
        );

        $this->assertNull(
            $ticket->fresh()->closed_at
        );
    }

    public function test_unrelated_users_cannot_reply_to_a_ticket(): void
    {
        [$ticket] = $this->createTicketScenario();

        $otherCustomer = Customer::factory()->create();

        $this->actingAs($otherCustomer->user)
            ->postJson(
                route(
                    'support-tickets.messages.store',
                    $ticket
                ),
                [
                    'message' => 'Unauthorized customer message.',
                ]
            )
            ->assertForbidden();

        $unassignedAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $this->actingAs($unassignedAgent)
            ->postJson(
                route(
                    'support-tickets.messages.store',
                    $ticket
                ),
                [
                    'message' => 'Unauthorized support message.',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'support_messages',
            0
        );

        $this->assertDatabaseCount(
            'audit_logs',
            0
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

        $this->actingAs($administrator)
            ->postJson(
                route(
                    'support-tickets.messages.store',
                    $ticket
                ),
                [
                    'message' => 'Administration is reviewing the ticket.',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.user_id',
                $administrator->id
            )
            ->assertJsonPath(
                'data.ticket.status.status_name',
                'WAITING_CUSTOMER'
            );

        $this->assertTicketStatus(
            $ticket,
            'WAITING_CUSTOMER'
        );
    }

    public function test_support_message_data_is_validated(): void
    {
        [$ticket, $customer] = $this
            ->createTicketScenario();

        $this->actingAs($customer->user)
            ->postJson(
                route(
                    'support-tickets.messages.store',
                    $ticket
                ),
                [
                    'message' => '',
                    'attachment_url' => str_repeat(
                        'A',
                        501
                    ),
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'message',
                'attachment_url',
            ]);

        $this->assertDatabaseCount(
            'support_messages',
            0
        );

        $this->assertDatabaseCount(
            'audit_logs',
            0
        );
    }

    public function test_messages_cannot_be_added_to_a_closed_ticket(): void
    {
        [$ticket, $customer] = $this
            ->createTicketScenario(
                statusName: 'CLOSED'
            );

        $this->actingAs($customer->user)
            ->postJson(
                route(
                    'support-tickets.messages.store',
                    $ticket
                ),
                [
                    'message' => 'This message must not be created.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Messages cannot be added to a closed support ticket.'
            );

        $this->assertDatabaseCount(
            'support_messages',
            0
        );

        $this->assertDatabaseCount(
            'audit_logs',
            0
        );
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
                ->where(
                    'category_name',
                    'TECHNICAL'
                )
                ->firstOrFail()
                ->id,
            'ticket_status_id' => $this
                ->findTicketStatus($statusName)
                ->id,
            'ticket_priority_id' => TicketPriority::query()
                ->where(
                    'priority_name',
                    'MEDIUM'
                )
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
            $this->findTicketStatus(
                $expectedStatus
            )->id,
            $ticket->fresh()->ticket_status_id
        );
    }
}