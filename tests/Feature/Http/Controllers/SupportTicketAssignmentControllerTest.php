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

class SupportTicketAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_assign_support_tickets(): void
    {
        $ticket = $this->createTicket();

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $this->patchJson(
            route('support-tickets.assign', $ticket),
            [
                'assigned_to_user_id' => $supportAgent->id,
            ]
        )->assertUnauthorized();

        $this->assertNull(
            $ticket->fresh()->assigned_to_user_id
        );

        $this->assertDatabaseCount(
            'audit_logs',
            0
        );
    }

    public function test_an_administrator_can_assign_an_open_ticket(): void
    {
        $administrator = User::factory()
            ->administrator()
            ->create();

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->createTicket();

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'support-tickets.assign',
                    $ticket
                ),
                [
                    'assigned_to_user_id' => $supportAgent->id,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Support ticket assigned successfully.'
            )
            ->assertJsonPath(
                'data.assigned_to_user_id',
                $supportAgent->id
            )
            ->assertJsonPath(
                'data.status.status_name',
                'IN_PROGRESS'
            );

        $ticket->refresh();

        $this->assertSame(
            $supportAgent->id,
            $ticket->assigned_to_user_id
        );

        $this->assertSame(
            'IN_PROGRESS',
            $ticket->status->status_name
        );

        $this->assertDatabaseCount(
            'audit_logs',
            1
        );

        $auditLog = AuditLog::query()
            ->firstOrFail();

        $this->assertSame(
            'TICKET_ASSIGNED',
            $auditLog->action_type
        );

        $this->assertSame(
            $administrator->id,
            $auditLog->performed_by_user_id
        );

        $this->assertSame(
            $supportAgent->id,
            $auditLog->details[
                'new_assignee_id'
            ]
        );
    }

    public function test_a_support_agent_can_claim_an_unassigned_ticket(): void
    {
        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->createTicket();

        $this->actingAs($supportAgent)
            ->patchJson(
                route(
                    'support-tickets.assign',
                    $ticket
                ),
                [
                    'assigned_to_user_id' => $supportAgent->id,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.assigned_to_user_id',
                $supportAgent->id
            )
            ->assertJsonPath(
                'data.status.status_name',
                'IN_PROGRESS'
            );

        $this->assertSame(
            $supportAgent->id,
            $ticket->fresh()->assigned_to_user_id
        );

        $this->assertSame(
            'TICKET_ASSIGNED',
            AuditLog::query()
                ->firstOrFail()
                ->action_type
        );
    }

    public function test_an_administrator_can_reassign_a_ticket(): void
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

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'support-tickets.assign',
                    $ticket
                ),
                [
                    'assigned_to_user_id' => $secondAgent->id,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.assigned_to_user_id',
                $secondAgent->id
            );

        $auditLog = AuditLog::query()
            ->firstOrFail();

        $this->assertSame(
            'TICKET_REASSIGNED',
            $auditLog->action_type
        );

        $this->assertSame(
            $firstAgent->id,
            $auditLog->details[
                'previous_assignee_id'
            ]
        );

        $this->assertSame(
            $secondAgent->id,
            $auditLog->details[
                'new_assignee_id'
            ]
        );
    }

    public function test_a_support_agent_cannot_assign_a_ticket_to_another_agent(): void
    {
        $performedBy = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $assignee = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->createTicket();

        $this->actingAs($performedBy)
            ->patchJson(
                route(
                    'support-tickets.assign',
                    $ticket
                ),
                [
                    'assigned_to_user_id' => $assignee->id,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Support agents can only claim unassigned tickets for themselves.'
            );

        $this->assertNull(
            $ticket->fresh()->assigned_to_user_id
        );

        $this->assertDatabaseCount(
            'audit_logs',
            0
        );
    }

    public function test_customers_cannot_assign_support_tickets(): void
    {
        $customer = Customer::factory()->create();

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->createTicket();

        $this->actingAs($customer->user)
            ->patchJson(
                route(
                    'support-tickets.assign',
                    $ticket
                ),
                [
                    'assigned_to_user_id' => $supportAgent->id,
                ]
            )
            ->assertForbidden();

        $this->assertNull(
            $ticket->fresh()->assigned_to_user_id
        );

        $this->assertDatabaseCount(
            'audit_logs',
            0
        );
    }

    public function test_assignment_data_is_validated(): void
    {
        $administrator = User::factory()
            ->administrator()
            ->create();

        $ticket = $this->createTicket();

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'support-tickets.assign',
                    $ticket
                ),
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'assigned_to_user_id',
            ]);

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'support-tickets.assign',
                    $ticket
                ),
                [
                    'assigned_to_user_id' => 999999,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'assigned_to_user_id',
            ]);

        $this->assertNull(
            $ticket->fresh()->assigned_to_user_id
        );

        $this->assertDatabaseCount(
            'audit_logs',
            0
        );
    }

    public function test_assignment_domain_errors_are_returned_as_unprocessable(): void
    {
        $administrator = User::factory()
            ->administrator()
            ->create();

        /*
         * Un cliente no puede ser asignado como agente.
         */
        $invalidAssignee = Customer::factory()
            ->create()
            ->user;

        $firstTicket = $this->createTicket();

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'support-tickets.assign',
                    $firstTicket
                ),
                [
                    'assigned_to_user_id' => $invalidAssignee->id,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The assigned user must have a support role.'
            );

        /*
         * Un agente suspendido tampoco puede ser asignado.
         */
        $inactiveAgent = $this->createUserWithRole(
            'SUPPORT_AGENT',
            'SUSPENDED'
        );

        $secondTicket = $this->createTicket();

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'support-tickets.assign',
                    $secondTicket
                ),
                [
                    'assigned_to_user_id' => $inactiveAgent->id,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Only an active user can be assigned to a support ticket.'
            );

        /*
         * Un ticket resuelto ya no puede asignarse.
         */
        $activeAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $resolvedTicket = $this->createTicket(
            statusName: 'RESOLVED'
        );

        $this->actingAs($administrator)
            ->patchJson(
                route(
                    'support-tickets.assign',
                    $resolvedTicket
                ),
                [
                    'assigned_to_user_id' => $activeAgent->id,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Only open, in-progress, or waiting-customer tickets can be assigned.'
            );

        $this->assertNull(
            $firstTicket->fresh()->assigned_to_user_id
        );

        $this->assertNull(
            $secondTicket->fresh()->assigned_to_user_id
        );

        $this->assertNull(
            $resolvedTicket->fresh()->assigned_to_user_id
        );

        $this->assertDatabaseCount(
            'audit_logs',
            0
        );
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
}