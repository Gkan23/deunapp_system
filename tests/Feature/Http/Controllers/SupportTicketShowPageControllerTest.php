<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
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

class SupportTicketShowPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_view_a_support_ticket(): void
    {
        $customer = Customer::factory()->create();

        $ticket = $this->ticketFor($customer);

        $this->get(
            route(
                'portal.support-tickets.show',
                $ticket
            )
        )->assertRedirect(
            route('login.page')
        );
    }

    public function test_an_unverified_user_is_redirected_to_verification(): void
    {
        $customer = Customer::factory()->create();

        $customer->user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $ticket = $this->ticketFor($customer);

        $this->actingAs($customer->user)
            ->get(
                route(
                    'portal.support-tickets.show',
                    $ticket
                )
            )
            ->assertRedirect(
                route('verification.notice')
            );
    }

    public function test_a_customer_can_view_their_support_ticket(): void
    {
        $customer = Customer::factory()->create();

        $ticket = $this->ticketFor(
            $customer,
            [
                'subject' =>
                    'Problem with shipment tracking',
            ]
        );

        $message = $this->messageFor(
            ticket: $ticket,
            user: $customer->user,
            attributes: [
                'message_text' =>
                    'The tracking information is not updating.',
            ]
        );

        $this->actingAs($customer->user)
            ->get(
                route(
                    'portal.support-tickets.show',
                    $ticket
                )
            )
            ->assertOk()
            ->assertSee(
                'Problem with shipment tracking'
            )
            ->assertSee(
                $message->message_text
            )
            ->assertSee(
                $customer->user->name
            )
            ->assertSee('TECHNICAL')
            ->assertSee('OPEN');
    }

    public function test_an_unrelated_customer_cannot_view_the_ticket(): void
    {
        $owner = Customer::factory()->create();

        $otherCustomer = Customer::factory()->create();

        $ticket = $this->ticketFor($owner);

        $this->actingAs($otherCustomer->user)
            ->get(
                route(
                    'portal.support-tickets.show',
                    $ticket
                )
            )
            ->assertForbidden();
    }

    public function test_support_and_administration_can_view_the_ticket(): void
    {
        $customer = Customer::factory()->create();

        $ticket = $this->ticketFor(
            $customer,
            [
                'subject' =>
                    'Ticket visible to support staff',
            ]
        );

        foreach (
            [
                'SUPPORT_AGENT',
                'ADMINISTRATOR',
            ] as $roleName
        ) {
            $user = $this->userWithRole(
                $roleName
            );

            $this->actingAs($user)
                ->get(
                    route(
                        'portal.support-tickets.show',
                        $ticket
                    )
                )
                ->assertOk()
                ->assertSee(
                    'Ticket visible to support staff'
                );
        }
    }

    public function test_provider_and_courier_cannot_view_the_ticket(): void
    {
        $customer = Customer::factory()->create();

        $ticket = $this->ticketFor($customer);

        foreach (
            [
                'DELIVERY_PROVIDER',
                'COURIER',
            ] as $roleName
        ) {
            $user = $this->userWithRole(
                $roleName
            );

            $this->actingAs($user)
                ->get(
                    route(
                        'portal.support-tickets.show',
                        $ticket
                    )
                )
                ->assertForbidden();
        }
    }

    public function test_a_customer_can_reply_to_their_ticket(): void
    {
        $customer = Customer::factory()->create();

        $agent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->ticketFor(
            $customer,
            [
                'ticket_status_id' => $this
                    ->ticketStatus(
                        'WAITING_CUSTOMER'
                    )
                    ->id,
                'assigned_to_user_id' =>
                    $agent->id,
            ]
        );

        $this->actingAs($customer->user)
            ->post(
                route(
                    'portal.support-tickets.messages.store',
                    $ticket
                ),
                [
                    'message' =>
                        'Here is the requested information.',
                    'attachment_url' =>
                        'https://example.com/customer-file.pdf',
                ]
            )
            ->assertRedirect(
                route(
                    'portal.support-tickets.show',
                    $ticket
                )
            )
            ->assertSessionHas('status');

        $this->assertDatabaseHas(
            'support_messages',
            [
                'ticket_id' => $ticket->id,
                'user_id' => $customer->user->id,
                'message_text' =>
                    'Here is the requested information.',
                'attachment_url' =>
                    'https://example.com/customer-file.pdf',
                'is_read' => false,
            ]
        );

        $this->assertSame(
            $this->ticketStatus(
                'IN_PROGRESS'
            )->id,
            $ticket->fresh()->ticket_status_id
        );
    }

    public function test_the_assigned_support_agent_can_reply(): void
    {
        $customer = Customer::factory()->create();

        $agent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->ticketFor(
            $customer,
            [
                'ticket_status_id' => $this
                    ->ticketStatus(
                        'IN_PROGRESS'
                    )
                    ->id,
                'assigned_to_user_id' =>
                    $agent->id,
            ]
        );

        $this->actingAs($agent)
            ->post(
                route(
                    'portal.support-tickets.messages.store',
                    $ticket
                ),
                [
                    'message' =>
                        'Please confirm the shipment address.',
                    'attachment_url' => null,
                ]
            )
            ->assertRedirect(
                route(
                    'portal.support-tickets.show',
                    $ticket
                )
            )
            ->assertSessionHas('status');

        $this->assertDatabaseHas(
            'support_messages',
            [
                'ticket_id' => $ticket->id,
                'user_id' => $agent->id,
                'message_text' =>
                    'Please confirm the shipment address.',
                'is_read' => false,
            ]
        );

        $this->assertSame(
            $this->ticketStatus(
                'WAITING_CUSTOMER'
            )->id,
            $ticket->fresh()->ticket_status_id
        );
    }

    public function test_a_support_agent_can_claim_an_unassigned_ticket(): void
    {
        $customer = Customer::factory()->create();

        $agent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->ticketFor(
            $customer,
            [
                'ticket_status_id' => $this
                    ->ticketStatus('OPEN')
                    ->id,
                'assigned_to_user_id' => null,
            ]
        );

        $this->actingAs($agent)
            ->patch(
                route(
                    'portal.support-tickets.assign',
                    $ticket
                ),
                [
                    'assigned_to_user_id' =>
                        $agent->id,
                ]
            )
            ->assertRedirect(
                route(
                    'portal.support-tickets.show',
                    $ticket
                )
            )
            ->assertSessionHas('status');

        $ticket->refresh();

        $this->assertSame(
            $agent->id,
            $ticket->assigned_to_user_id
        );

        $this->assertSame(
            $this->ticketStatus(
                'IN_PROGRESS'
            )->id,
            $ticket->ticket_status_id
        );
    }

    public function test_an_administrator_can_assign_a_support_agent(): void
    {
        $customer = Customer::factory()->create();

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $agent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->ticketFor(
            $customer,
            [
                'ticket_status_id' => $this
                    ->ticketStatus('OPEN')
                    ->id,
                'assigned_to_user_id' => null,
            ]
        );

        $this->actingAs($administrator)
            ->patch(
                route(
                    'portal.support-tickets.assign',
                    $ticket
                ),
                [
                    'assigned_to_user_id' =>
                        $agent->id,
                ]
            )
            ->assertRedirect(
                route(
                    'portal.support-tickets.show',
                    $ticket
                )
            )
            ->assertSessionHas('status');

        $ticket->refresh();

        $this->assertSame(
            $agent->id,
            $ticket->assigned_to_user_id
        );

        $this->assertSame(
            $this->ticketStatus(
                'IN_PROGRESS'
            )->id,
            $ticket->ticket_status_id
        );
    }

    public function test_the_assigned_agent_can_update_the_ticket_status(): void
    {
        $customer = Customer::factory()->create();

        $agent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->ticketFor(
            $customer,
            [
                'ticket_status_id' => $this
                    ->ticketStatus(
                        'IN_PROGRESS'
                    )
                    ->id,
                'assigned_to_user_id' =>
                    $agent->id,
            ]
        );

        $this->actingAs($agent)
            ->patch(
                route(
                    'portal.support-tickets.status.update',
                    $ticket
                ),
                [
                    'status' => 'RESOLVED',
                    'comment' =>
                        'The reported problem was corrected.',
                ]
            )
            ->assertRedirect(
                route(
                    'portal.support-tickets.show',
                    $ticket
                )
            )
            ->assertSessionHas('status');

        $ticket->refresh();

        $this->assertSame(
            $this->ticketStatus(
                'RESOLVED'
            )->id,
            $ticket->ticket_status_id
        );

        $this->assertNull(
            $ticket->closed_at
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'performed_by_user_id' =>
                    $agent->id,
                'table_name' =>
                    'support_tickets',
                'record_id' => $ticket->id,
                'action_type' =>
                    'TICKET_STATUS_CHANGED',
            ]
        );
    }

    public function test_a_customer_can_mark_support_messages_as_read(): void
    {
        $customer = Customer::factory()->create();

        $agent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $ticket = $this->ticketFor(
            $customer,
            [
                'ticket_status_id' => $this
                    ->ticketStatus(
                        'WAITING_CUSTOMER'
                    )
                    ->id,
                'assigned_to_user_id' =>
                    $agent->id,
            ]
        );

        $supportMessage = $this->messageFor(
            ticket: $ticket,
            user: $agent,
            attributes: [
                'message_text' =>
                    'Please review this information.',
                'is_read' => false,
            ]
        );

        $customerMessage = $this->messageFor(
            ticket: $ticket,
            user: $customer->user,
            attributes: [
                'message_text' =>
                    'This message belongs to the customer.',
                'is_read' => false,
            ]
        );

        $this->actingAs($customer->user)
            ->patch(
                route(
                    'portal.support-tickets.messages.read',
                    $ticket
                )
            )
            ->assertRedirect(
                route(
                    'portal.support-tickets.show',
                    $ticket
                )
            )
            ->assertSessionHas('status');

        $this->assertDatabaseHas(
            'support_messages',
            [
                'id' => $supportMessage->id,
                'is_read' => true,
            ]
        );

        $this->assertDatabaseHas(
            'support_messages',
            [
                'id' => $customerMessage->id,
                'is_read' => false,
            ]
        );
    }

    public function test_messages_cannot_be_added_to_a_closed_ticket(): void
    {
        $customer = Customer::factory()->create();

        $ticket = $this->ticketFor(
            $customer,
            [
                'ticket_status_id' => $this
                    ->ticketStatus('CLOSED')
                    ->id,
                'closed_at' => now(),
            ]
        );

        $this->actingAs($customer->user)
            ->from(
                route(
                    'portal.support-tickets.show',
                    $ticket
                )
            )
            ->post(
                route(
                    'portal.support-tickets.messages.store',
                    $ticket
                ),
                [
                    'message' =>
                        'Trying to reply to a closed ticket.',
                ]
            )
            ->assertRedirect(
                route(
                    'portal.support-tickets.show',
                    $ticket
                )
            )
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing(
            'support_messages',
            [
                'ticket_id' => $ticket->id,
                'message_text' =>
                    'Trying to reply to a closed ticket.',
            ]
        );

        $this->assertSame(
            $this->ticketStatus('CLOSED')->id,
            $ticket->fresh()->ticket_status_id
        );
    }

    public function test_an_inactive_account_cannot_view_the_ticket(): void
    {
        $customer = Customer::factory()->create();

        $ticket = $this->ticketFor($customer);

        $customer->user->update([
            'account_status_id' =>
                AccountStatus::query()
                    ->where(
                        'status_name',
                        'SUSPENDED'
                    )
                    ->firstOrFail()
                    ->id,
        ]);

        $this->actingAs($customer->user)
            ->get(
                route(
                    'portal.support-tickets.show',
                    $ticket
                )
            )
            ->assertForbidden();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function ticketFor(
        Customer $customer,
        array $attributes = []
    ): SupportTicket {
        return SupportTicket::query()->create(
            array_merge(
                [
                    'customer_id' =>
                        $customer->id,
                    'shipment_id' => null,
                    'ticket_category_id' =>
                        TicketCategory::query()
                            ->where(
                                'category_name',
                                'TECHNICAL'
                            )
                            ->firstOrFail()
                            ->id,
                    'ticket_status_id' =>
                        $this->ticketStatus(
                            'OPEN'
                        )->id,
                    'ticket_priority_id' =>
                        TicketPriority::query()
                            ->where(
                                'priority_name',
                                'MEDIUM'
                            )
                            ->firstOrFail()
                            ->id,
                    'assigned_to_user_id' =>
                        null,
                    'subject' =>
                        'Application support request',
                    'closed_at' => null,
                ],
                $attributes
            )
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function messageFor(
        SupportTicket $ticket,
        User $user,
        array $attributes = []
    ): SupportMessage {
        return SupportMessage::query()->create(
            array_merge(
                [
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'message_text' =>
                        'Support ticket message.',
                    'attachment_url' => null,
                    'sent_at' => now(),
                    'is_read' => false,
                ],
                $attributes
            )
        );
    }

    private function ticketStatus(
        string $statusName
    ): TicketStatus {
        return TicketStatus::query()
            ->where(
                'status_name',
                $statusName
            )
            ->firstOrFail();
    }

    private function userWithRole(
        string $roleName
    ): User {
        return User::factory()->create([
            'role_id' => Role::query()
                ->where(
                    'role_name',
                    $roleName
                )
                ->firstOrFail()
                ->id,
        ]);
    }
}