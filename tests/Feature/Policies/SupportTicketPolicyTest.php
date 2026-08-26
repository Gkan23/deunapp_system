<?php

namespace Tests\Feature\Policies;

use App\Models\AccountStatus;
use App\Models\Customer;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SupportTicketPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_customer_can_view_the_list_and_create_tickets(): void
    {
        $customer = Customer::factory()->create();
        $user = $customer->user;

        $this->assertTrue(
            Gate::forUser($user)->allows(
                'viewAny',
                SupportTicket::class
            )
        );

        $this->assertTrue(
            Gate::forUser($user)->allows(
                'create',
                SupportTicket::class
            )
        );
    }

    public function test_a_customer_can_only_view_their_own_tickets(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $ownTicket = $this->ticketFor($customer);
        $otherTicket = $this->ticketFor($otherCustomer);

        $this->assertTrue(
            Gate::forUser($customer->user)->allows(
                'view',
                $ownTicket
            )
        );

        $this->assertFalse(
            Gate::forUser($customer->user)->allows(
                'view',
                $otherTicket
            )
        );
    }

    public function test_a_customer_can_reply_to_their_own_ticket(): void
    {
        $customer = Customer::factory()->create();
        $ticket = $this->ticketFor($customer);

        $this->assertTrue(
            Gate::forUser($customer->user)->allows(
                'reply',
                $ticket
            )
        );
    }

    public function test_a_customer_cannot_assign_or_change_ticket_status(): void
    {
        $customer = Customer::factory()->create();
        $ticket = $this->ticketFor($customer);

        $this->assertFalse(
            Gate::forUser($customer->user)->allows(
                'assign',
                $ticket
            )
        );

        $this->assertFalse(
            Gate::forUser($customer->user)->allows(
                'changeStatus',
                $ticket
            )
        );
    }

    public function test_a_support_agent_can_view_any_ticket(): void
    {
        $agent = $this->userWithRole('SUPPORT_AGENT');
        $ticket = $this->ticketFor(Customer::factory()->create());

        $this->assertTrue(
            Gate::forUser($agent)->allows(
                'viewAny',
                SupportTicket::class
            )
        );

        $this->assertTrue(
            Gate::forUser($agent)->allows(
                'view',
                $ticket
            )
        );
    }

    public function test_an_assigned_agent_can_reply_and_change_status(): void
    {
        $agent = $this->userWithRole('SUPPORT_AGENT');

        $ticket = $this->ticketFor(
            Customer::factory()->create(),
            $agent
        );

        $this->assertTrue(
            Gate::forUser($agent)->allows(
                'reply',
                $ticket
            )
        );

        $this->assertTrue(
            Gate::forUser($agent)->allows(
                'changeStatus',
                $ticket
            )
        );
    }

    public function test_an_unassigned_agent_can_assign_but_cannot_reply_or_change_status(): void
    {
        $agent = $this->userWithRole('SUPPORT_AGENT');
        $ticket = $this->ticketFor(Customer::factory()->create());

        $this->assertTrue(
            Gate::forUser($agent)->allows(
                'assign',
                $ticket
            )
        );

        $this->assertFalse(
            Gate::forUser($agent)->allows(
                'reply',
                $ticket
            )
        );

        $this->assertFalse(
            Gate::forUser($agent)->allows(
                'changeStatus',
                $ticket
            )
        );
    }

    public function test_an_administrator_can_perform_domain_actions(): void
    {
        $administrator = $this->userWithRole('ADMINISTRATOR');
        $ticket = $this->ticketFor(Customer::factory()->create());

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'viewAny',
                SupportTicket::class
            )
        );

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'view',
                $ticket
            )
        );

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'assign',
                $ticket
            )
        );

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'reply',
                $ticket
            )
        );

        $this->assertTrue(
            Gate::forUser($administrator)->allows(
                'changeStatus',
                $ticket
            )
        );
    }

    public function test_an_inactive_user_cannot_access_support_tickets(): void
    {
        $customer = Customer::factory()->create();
        $ticket = $this->ticketFor($customer);

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $customer->user->update([
            'account_status_id' => $suspendedStatus->id,
        ]);

        $user = $customer->user->fresh();

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'viewAny',
                SupportTicket::class
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'view',
                $ticket
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'reply',
                $ticket
            )
        );
    }

    public function test_direct_update_and_deletion_are_denied(): void
    {
        $customer = Customer::factory()->create();
        $ticket = $this->ticketFor($customer);
        $user = $customer->user;

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'update',
                $ticket
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'delete',
                $ticket
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'restore',
                $ticket
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'forceDelete',
                $ticket
            )
        );
    }

    private function ticketFor(
        Customer $customer,
        ?User $assignedAgent = null
    ): SupportTicket {
        return SupportTicket::query()->create([
            'customer_id' => $customer->id,
            'shipment_id' => null,
            'ticket_category_id' => TicketCategory::query()
                ->where('category_name', 'OTHER')
                ->firstOrFail()
                ->id,
            'ticket_status_id' => TicketStatus::query()
                ->where('status_name', 'OPEN')
                ->firstOrFail()
                ->id,
            'ticket_priority_id' => TicketPriority::query()
                ->where('priority_name', 'MEDIUM')
                ->firstOrFail()
                ->id,
            'assigned_to_user_id' => $assignedAgent?->id,
            'subject' => 'Policy test ticket',
            'closed_at' => null,
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::query()
            ->where('role_name', $roleName)
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}

