<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\CreateSupportTicketService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_access_support_ticket_endpoints(): void
    {
        $ticket = $this->ticketFor(
            Customer::factory()->create()
        );

        $this->getJson(
            route('support-tickets.index')
        )->assertUnauthorized();

        $this->postJson(
            route('support-tickets.store'),
            []
        )->assertUnauthorized();

        $this->getJson(
            route('support-tickets.show', $ticket)
        )->assertUnauthorized();
    }

    public function test_a_customer_only_lists_their_own_tickets(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $ownTicket = $this->ticketFor(
            $customer,
            'My account problem'
        );

        $this->ticketFor(
            $otherCustomer,
            'Another customer problem'
        );

        $this->actingAs($customer->user)
            ->getJson(route('support-tickets.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $ownTicket->id
            )
            ->assertJsonPath(
                'data.0.subject',
                'My account problem'
            );
    }

    public function test_support_and_administration_list_all_tickets(): void
    {
        $this->ticketFor(
            Customer::factory()->create(),
            'First ticket'
        );

        $this->ticketFor(
            Customer::factory()->create(),
            'Second ticket'
        );

        $users = [
            $this->userWithRole('SUPPORT_AGENT'),
            $this->userWithRole('ADMINISTRATOR'),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)
                ->getJson(route('support-tickets.index'))
                ->assertOk()
                ->assertJsonCount(2, 'data');
        }
    }

    public function test_unsupported_roles_cannot_list_tickets(): void
    {
        $courier = Courier::factory()->create();

        $this->actingAs($courier->user)
            ->getJson(route('support-tickets.index'))
            ->assertForbidden();
    }

    public function test_ticket_details_respect_the_policy_scope(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $ticket = $this->ticketFor($customer);

        $this->actingAs($customer->user)
            ->getJson(
                route('support-tickets.show', $ticket)
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $ticket->id
            )
            ->assertJsonCount(
                1,
                'data.messages'
            );

        $this->actingAs($otherCustomer->user)
            ->getJson(
                route('support-tickets.show', $ticket)
            )
            ->assertForbidden();

        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $this->actingAs($supportAgent)
            ->getJson(
                route('support-tickets.show', $ticket)
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $ticket->id
            );
    }

    public function test_a_customer_can_create_a_support_ticket(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->postJson(
                route('support-tickets.store'),
                [
                    'category' => ' account ',
                    'subject' => '  Account problem  ',
                    'message' => '  I cannot update my profile.  ',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Support ticket created successfully.'
            )
            ->assertJsonPath(
                'data.subject',
                'Account problem'
            )
            ->assertJsonPath(
                'data.category.category_name',
                'ACCOUNT'
            )
            ->assertJsonPath(
                'data.status.status_name',
                'OPEN'
            )
            ->assertJsonPath(
                'data.priority.priority_name',
                'MEDIUM'
            )
            ->assertJsonPath(
                'data.messages.0.message_text',
                'I cannot update my profile.'
            );

        $this->assertDatabaseCount(
            'support_tickets',
            1
        );

        $this->assertDatabaseCount(
            'support_messages',
            1
        );

        $this->assertDatabaseCount(
            'audit_logs',
            1
        );
    }

    public function test_a_customer_can_create_a_ticket_for_their_shipment(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($customer->user)
            ->postJson(
                route('support-tickets.store'),
                [
                    'category' => 'delivery',
                    'subject' => 'Delivery delay',
                    'message' => 'My shipment has not arrived.',
                    'shipment_id' => $shipment->id,
                    'attachment_url' => ' /storage/support/delay.jpg ',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.shipment_id',
                $shipment->id
            )
            ->assertJsonPath(
                'data.category.category_name',
                'DELIVERY'
            )
            ->assertJsonPath(
                'data.messages.0.attachment_url',
                '/storage/support/delay.jpg'
            );

        $this->assertDatabaseHas(
            'support_tickets',
            [
                'customer_id' => $customer->id,
                'shipment_id' => $shipment->id,
                'subject' => 'Delivery delay',
            ]
        );
    }

    public function test_non_customer_roles_cannot_create_tickets(): void
    {
        $users = [
            $this->userWithRole('SUPPORT_AGENT'),
            $this->userWithRole('ADMINISTRATOR'),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)
                ->postJson(
                    route('support-tickets.store'),
                    [
                        'category' => 'ACCOUNT',
                        'subject' => 'Support request',
                        'message' => 'This request is not allowed.',
                    ]
                )
                ->assertForbidden();
        }

        $this->assertDatabaseCount(
            'support_tickets',
            0
        );
    }

    public function test_support_ticket_data_is_validated(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->postJson(
                route('support-tickets.store'),
                [
                    'category' => 'UNKNOWN_CATEGORY',
                    'subject' => '',
                    'message' => '',
                    'shipment_id' => 999999,
                    'attachment_url' => str_repeat(
                        'A',
                        501
                    ),
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category',
                'subject',
                'message',
                'shipment_id',
                'attachment_url',
            ]);

        $this->assertDatabaseCount(
            'support_tickets',
            0
        );
    }

    public function test_a_customer_cannot_create_a_ticket_for_another_customers_shipment(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $otherCustomer->id,
        ]);

        $this->actingAs($customer->user)
            ->postJson(
                route('support-tickets.store'),
                [
                    'category' => 'DELIVERY',
                    'subject' => 'Shipment problem',
                    'message' => 'I need help with this shipment.',
                    'shipment_id' => $shipment->id,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The selected shipment does not belong to the customer.'
            );

        $this->assertDatabaseCount(
            'support_tickets',
            0
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

    private function ticketFor(
        Customer $customer,
        string $subject = 'Support request'
    ): SupportTicket {
        return app(
            CreateSupportTicketService::class
        )->execute(
            customer: $customer,
            categoryName: 'ACCOUNT',
            subject: $subject,
            message: 'This is the initial support message.'
        );
    }

    private function userWithRole(
        string $roleName
    ): User {
        $role = Role::query()
            ->where('role_name', $roleName)
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}