<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
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

class SupportTicketStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_update_support_ticket_statuses(): void
    {
        [$ticket] = $this->createTicketScenario();

        $this->patchJson(
            route(
                'support-tickets.status.update',
                $ticket
            ),
            [
                'status' => 'IN_PROGRESS',
            ]
        )->assertUnauthorized();
    }

    public function test_the_assigned_agent_can_start_an_open_ticket(): void
    {
        [$ticket, $agent] = $this->createTicketScenario();

        $this->actingAs($agent)
            ->patchJson(
                route(
                    'support-tickets.status.update',
                    $ticket
                ),
                [
                    'status' => 'in_progress',
                    'comment' => 'The ticket is being reviewed.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Support ticket status updated successfully.'
            );

        $this->assertTicketStatus(
            $ticket,
            'IN_PROGRESS'
        );

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' => $agent->id,
            'table_name' => 'support_tickets',
            'record_id' => $ticket->id,
            'action_type' => 'TICKET_STATUS_CHANGED',
        ]);
    }

    public function test_an_administrator_can_update_a_ticket_assigned_to_another_user(): void
    {
        [$ticket] = $this->createTicketScenario(
            'IN_PROGRESS'
        );

        $administrator = $this->createUserWithRole(
            'ADMINISTRATOR'
        );

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'support-tickets.status.update',
                    $ticket
                ),
                [
                    'status' => 'WAITING_CUSTOMER',
                    'comment' => 'Waiting for customer information.',
                ]
            )
            ->assertOk();

        $this->assertTicketStatus(
            $ticket,
            'WAITING_CUSTOMER'
        );

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' => $administrator->id,
            'table_name' => 'support_tickets',
            'record_id' => $ticket->id,
            'action_type' => 'TICKET_STATUS_CHANGED',
        ]);
    }

    public function test_unrelated_users_cannot_update_the_ticket_status(): void
    {
        [$ticket, $assignedAgent, $customer] =
            $this->createTicketScenario(
                'IN_PROGRESS'
            );

        $unassignedAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        foreach ([
            $customer->user,
            $unassignedAgent,
        ] as $user) {
            $this->actingAs($user)
                ->patchJson(
                    route(
                        'support-tickets.status.update',
                        $ticket
                    ),
                    [
                        'status' => 'WAITING_CUSTOMER',
                        'comment' => 'Unauthorized update.',
                    ]
                )
                ->assertForbidden();
        }

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'assigned_to_user_id' => $assignedAgent->id,
            'ticket_status_id' => $this
                ->findTicketStatus('IN_PROGRESS')
                ->id,
        ]);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_an_inactive_agent_cannot_update_the_ticket_status(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            'IN_PROGRESS'
        );

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $agent->update([
            'account_status_id' => $suspendedStatus->id,
        ]);

        $this->actingAs($agent->fresh())
            ->patchJson(
                route(
                    'support-tickets.status.update',
                    $ticket
                ),
                [
                    'status' => 'WAITING_CUSTOMER',
                    'comment' => 'This update must be rejected.',
                ]
            )
            ->assertForbidden();

        $this->assertTicketStatus(
            $ticket,
            'IN_PROGRESS'
        );

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_the_status_field_is_required(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            'IN_PROGRESS'
        );

        $this->actingAs($agent)
            ->patchJson(
                route(
                    'support-tickets.status.update',
                    $ticket
                ),
                [
                    'comment' => 'The status was not supplied.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);

        $this->assertTicketStatus(
            $ticket,
            'IN_PROGRESS'
        );

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_the_selected_status_must_exist(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            'IN_PROGRESS'
        );

        $this->actingAs($agent)
            ->patchJson(
                route(
                    'support-tickets.status.update',
                    $ticket
                ),
                [
                    'status' => 'UNKNOWN_STATUS',
                    'comment' => 'Unknown status.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);

        $this->assertTicketStatus(
            $ticket,
            'IN_PROGRESS'
        );

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_invalid_transitions_are_returned_as_unprocessable(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            'OPEN'
        );

        $this->actingAs($agent)
            ->patchJson(
                route(
                    'support-tickets.status.update',
                    $ticket
                ),
                [
                    'status' => 'CLOSED',
                    'comment' => 'Invalid direct closure.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The support ticket status transition from OPEN to CLOSED is not allowed.'
            );

        $this->assertTicketStatus(
            $ticket,
            'OPEN'
        );

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_comment_is_required_to_resolve_a_ticket(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            'IN_PROGRESS'
        );

        $this->actingAs($agent)
            ->patchJson(
                route(
                    'support-tickets.status.update',
                    $ticket
                ),
                [
                    'status' => 'RESOLVED',
                    'comment' => '   ',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'A comment is required to resolve a support ticket.'
            );

        $this->assertTicketStatus(
            $ticket,
            'IN_PROGRESS'
        );

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_the_assigned_agent_can_resolve_a_ticket(): void
    {
        [$ticket, $agent] = $this->createTicketScenario(
            'IN_PROGRESS'
        );

        $this->actingAs($agent)
            ->patchJson(
                route(
                    'support-tickets.status.update',
                    $ticket
                ),
                [
                    'status' => 'RESOLVED',
                    'comment' => 'The reported problem was corrected.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Support ticket status updated successfully.'
            );

        $this->assertTicketStatus(
            $ticket,
            'RESOLVED'
        );

        $auditLog = AuditLog::query()
            ->where('table_name', 'support_tickets')
            ->where('record_id', $ticket->id)
            ->where(
                'action_type',
                'TICKET_STATUS_CHANGED'
            )
            ->firstOrFail();

        $this->assertSame(
            $agent->id,
            $auditLog->performed_by_user_id
        );

        $this->assertSame(
            'IN_PROGRESS',
            $auditLog->details['from_status']
        );

        $this->assertSame(
            'RESOLVED',
            $auditLog->details['to_status']
        );

        $this->assertSame(
            'The reported problem was corrected.',
            $auditLog->details['comment']
        );
    }

    /**
     * @return array{0: SupportTicket, 1: User, 2: Customer}
     */
    private function createTicketScenario(
        string $statusName = 'OPEN'
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
            'assigned_to_user_id' => $agent->id,
            'subject' => 'Application support request',
            'closed_at' => $statusName === 'CLOSED'
                ? now()->subMinutes(10)
                : null,
        ]);

        return [
            $ticket,
            $agent,
            $customer,
        ];
    }

    private function createUserWithRole(
        string $roleName
    ): User {
        $role = Role::query()
            ->where('role_name', $roleName)
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
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
}