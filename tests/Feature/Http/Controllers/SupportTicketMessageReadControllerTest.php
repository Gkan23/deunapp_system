<?php

namespace Tests\Feature\Http\Controllers;

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
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketMessageReadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_mark_support_messages_as_read(): void
    {
        [$ticket] = $this->createTicketScenario();

        $this->patchJson(
            route(
                'support-tickets.messages.read',
                $ticket
            )
        )->assertUnauthorized();
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

        $this->actingAs($customer->user)
            ->patchJson(
                route(
                    'support-tickets.messages.read',
                    $ticket
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Support messages marked as read successfully.'
            )
            ->assertJsonPath(
                'data.read_count',
                2
            );

        $this->assertFalse(
            $customerMessage->fresh()->is_read
        );

        $this->assertTrue(
            $firstAgentMessage->fresh()->is_read
        );

        $this->assertTrue(
            $secondAgentMessage->fresh()->is_read
        );

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' => $customer->user_id,
            'table_name' => 'support_messages',
            'record_id' => $ticket->id,
            'action_type' => 'SUPPORT_MESSAGES_READ',
        ]);
    }

    public function test_assigned_agent_marks_customer_messages_as_read(): void
    {
        [$ticket, $customer, $agent] =
            $this->createTicketScenario();

        $customerMessage = $this->createMessage(
            $ticket,
            $customer->user
        );

        $agentMessage = $this->createMessage(
            $ticket,
            $agent
        );

        $this->actingAs($agent)
            ->patchJson(
                route(
                    'support-tickets.messages.read',
                    $ticket
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.read_count',
                1
            );

        $this->assertTrue(
            $customerMessage->fresh()->is_read
        );

        $this->assertFalse(
            $agentMessage->fresh()->is_read
        );
    }

    public function test_assigned_administrator_can_read_customer_messages(): void
    {
        $administrator = $this->createUserWithRole(
            'ADMINISTRATOR'
        );

        [$ticket, $customer] =
            $this->createTicketScenario(
                assignedUser: $administrator
            );

        $message = $this->createMessage(
            $ticket,
            $customer->user
        );

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'support-tickets.messages.read',
                    $ticket
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.read_count',
                1
            );

        $this->assertTrue(
            $message->fresh()->is_read
        );
    }

    public function test_already_read_messages_are_ignored(): void
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

        $this->actingAs($customer->user)
            ->patchJson(
                route(
                    'support-tickets.messages.read',
                    $ticket
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.read_count',
                1
            );

        $this->assertTrue(
            $alreadyReadMessage->fresh()->is_read
        );

        $this->assertTrue(
            $unreadMessage->fresh()->is_read
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

        $endpoint = route(
            'support-tickets.messages.read',
            $ticket
        );

        $this->actingAs($customer->user)
            ->patchJson($endpoint)
            ->assertOk()
            ->assertJsonPath(
                'data.read_count',
                1
            );

        $this->actingAs($customer->user)
            ->patchJson($endpoint)
            ->assertOk()
            ->assertJsonPath(
                'data.read_count',
                0
            );

        $this->assertTrue(
            $message->fresh()->is_read
        );

        $this->assertDatabaseCount(
            'audit_logs',
            1
        );
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

        $this->actingAs($customer->user)
            ->patchJson(
                route(
                    'support-tickets.messages.read',
                    $ticket
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.read_count',
                1
            );

        $this->assertTrue(
            $message->fresh()->is_read
        );
    }

    public function test_unrelated_user_cannot_read_ticket_messages(): void
    {
        [$ticket, $customer] =
            $this->createTicketScenario();

        $message = $this->createMessage(
            $ticket,
            $customer->user
        );

        $unrelatedUser = User::factory()->create();

        $this->actingAs($unrelatedUser)
            ->patchJson(
                route(
                    'support-tickets.messages.read',
                    $ticket
                )
            )
            ->assertForbidden();

        $this->assertFalse(
            $message->fresh()->is_read
        );

        $this->assertDatabaseCount(
            'audit_logs',
            0
        );
    }

    public function test_unassigned_support_agent_cannot_read_messages(): void
    {
        [$ticket, $customer] =
            $this->createTicketScenario();

        $unassignedAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $message = $this->createMessage(
            $ticket,
            $customer->user
        );

        $this->actingAs($unassignedAgent)
            ->patchJson(
                route(
                    'support-tickets.messages.read',
                    $ticket
                )
            )
            ->assertForbidden();

        $this->assertFalse(
            $message->fresh()->is_read
        );

        $this->assertDatabaseCount(
            'audit_logs',
            0
        );
    }

    public function test_inactive_user_cannot_read_support_messages(): void
    {
        [$ticket, $customer, $agent] =
            $this->createTicketScenario();

        $customer->user->update([
            'account_status_id' => AccountStatus::query()
                ->where('status_name', 'SUSPENDED')
                ->firstOrFail()
                ->id,
        ]);

        $message = $this->createMessage(
            $ticket,
            $agent
        );

        $this->actingAs($customer->user->fresh())
            ->patchJson(
                route(
                    'support-tickets.messages.read',
                    $ticket
                )
            )
            ->assertForbidden();

        $this->assertFalse(
            $message->fresh()->is_read
        );

        $this->assertDatabaseCount(
            'audit_logs',
            0
        );
    }

    /**
     * @return array{0: SupportTicket, 1: Customer, 2: User}
     */
    private function createTicketScenario(
        string $statusName = 'IN_PROGRESS',
        ?User $assignedUser = null
    ): array {
        $customer = Customer::factory()->create();

        $agent = $assignedUser
            ?? $this->createUserWithRole(
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
            'assigned_to_user_id' => $agent->id,
            'subject' => 'Application support request',
            'closed_at' => $statusName === 'CLOSED'
                ? now()->subMinutes(10)
                : null,
        ]);

        return [
            $ticket,
            $customer,
            $agent,
        ];
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
}