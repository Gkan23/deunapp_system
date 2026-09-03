<?php

namespace Tests\Feature\Http\Controllers;

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
use Tests\TestCase;

class SupportTicketIndexPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_view_the_support_ticket_page(): void
    {
        $this->get(
            route('portal.support-tickets.index')
        )->assertRedirect(
            route('login.page')
        );
    }

    public function test_an_unverified_user_is_redirected_to_verification(): void
    {
        $user = User::factory()
            ->unverified()
            ->create();

        $this->actingAs($user)
            ->get(
                route('portal.support-tickets.index')
            )
            ->assertRedirect(
                route('verification.notice')
            );
    }

    public function test_a_customer_only_sees_their_tickets(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $ownTicket = $this->ticketFor(
            $customer,
            [
                'subject' =>
                    'Problema con mi entrega',
            ]
        );

        $otherTicket = $this->ticketFor(
            $otherCustomer,
            [
                'subject' =>
                    'Ticket privado de otro cliente',
            ]
        );

        $this->actingAs($customer->user)
            ->get(
                route('portal.support-tickets.index')
            )
            ->assertOk()
            ->assertSee(
                $ownTicket->subject
            )
            ->assertDontSee(
                $otherTicket->subject
            );
    }

    public function test_a_support_agent_can_view_all_tickets(): void
    {
        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $firstCustomer = Customer::factory()->create();
        $secondCustomer = Customer::factory()->create();

        $firstTicket = $this->ticketFor(
            $firstCustomer,
            [
                'subject' =>
                    'Consulta sobre un paquete',
            ]
        );

        $secondTicket = $this->ticketFor(
            $secondCustomer,
            [
                'subject' =>
                    'Problema con una dirección',
            ]
        );

        $this->actingAs($supportAgent)
            ->get(
                route('portal.support-tickets.index')
            )
            ->assertOk()
            ->assertSee(
                $firstTicket->subject
            )
            ->assertSee(
                $secondTicket->subject
            )
            ->assertSee(
                $firstCustomer->user->name
            )
            ->assertSee(
                $secondCustomer->user->name
            );
    }

    public function test_an_administrator_can_view_all_tickets(): void
    {
        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $customer = Customer::factory()->create();

        $ticket = $this->ticketFor(
            $customer,
            [
                'subject' =>
                    'Solicitud visible para administración',
            ]
        );

        $this->actingAs($administrator)
            ->get(
                route('portal.support-tickets.index')
            )
            ->assertOk()
            ->assertSee(
                $ticket->subject
            )
            ->assertSee(
                $customer->user->email
            );
    }

    public function test_unauthorized_roles_cannot_view_the_ticket_page(): void
    {
        $providerUser = User::factory()
            ->deliveryProvider()
            ->create();

        $courierUser = User::factory()
            ->courier()
            ->create();

        $this->actingAs($providerUser)
            ->get(
                route('portal.support-tickets.index')
            )
            ->assertForbidden();

        $this->actingAs($courierUser)
            ->get(
                route('portal.support-tickets.index')
            )
            ->assertForbidden();
    }

    public function test_tickets_can_be_searched_by_subject(): void
    {
        $customer = Customer::factory()->create();

        $matchingTicket = $this->ticketFor(
            $customer,
            [
                'subject' =>
                    'Paquete retenido en sucursal',
            ]
        );

        $otherTicket = $this->ticketFor(
            $customer,
            [
                'subject' =>
                    'Actualizar número telefónico',
            ]
        );

        $this->actingAs($customer->user)
            ->get(
                route(
                    'portal.support-tickets.index',
                    [
                        'search' => 'retenido',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $matchingTicket->subject
            )
            ->assertDontSee(
                $otherTicket->subject
            );
    }

    public function test_tickets_can_be_filtered_by_status_and_category(): void
    {
        $customer = Customer::factory()->create();

        $closedStatus = TicketStatus::query()
            ->where(
                'status_name',
                'CLOSED'
            )
            ->firstOrFail();

        $paymentCategory = TicketCategory::query()
            ->where(
                'category_name',
                'PAYMENT'
            )
            ->firstOrFail();

        $matchingTicket = $this->ticketFor(
            $customer,
            [
                'ticket_status_id' =>
                    $closedStatus->id,
                'ticket_category_id' =>
                    $paymentCategory->id,
                'subject' =>
                    'Pago cerrado correctamente',
                'closed_at' => now(),
            ]
        );

        $otherTicket = $this->ticketFor(
            $customer,
            [
                'subject' =>
                    'Consulta técnica abierta',
            ]
        );

        $this->actingAs($customer->user)
            ->get(
                route(
                    'portal.support-tickets.index',
                    [
                        'status' => 'CLOSED',
                        'category' => 'PAYMENT',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $matchingTicket->subject
            )
            ->assertDontSee(
                $otherTicket->subject
            );
    }

    public function test_the_page_displays_an_empty_state(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->get(
                route('portal.support-tickets.index')
            )
            ->assertOk()
            ->assertSee(
                'No se encontraron tickets'
            );
    }

    public function test_an_inactive_account_cannot_view_the_ticket_page(): void
    {
        $inactiveStatus = AccountStatus::query()
            ->where(
                'status_name',
                '!=',
                'ACTIVE'
            )
            ->firstOrFail();

        $customer = Customer::factory()->create();

        $customer->user->update([
            'account_status_id' =>
                $inactiveStatus->id,
        ]);

        $this->actingAs(
            $customer->user->fresh()
        )
            ->get(
                route('portal.support-tickets.index')
            )
            ->assertForbidden();
    }

    /**
     * Crea un ticket para las pruebas.
     *
     * @param array<string, mixed> $attributes
     */
    private function ticketFor(
        Customer $customer,
        array $attributes = []
    ): SupportTicket {
        $category = TicketCategory::query()
            ->where(
                'category_name',
                'TECHNICAL'
            )
            ->firstOrFail();

        $status = TicketStatus::query()
            ->where(
                'status_name',
                'OPEN'
            )
            ->firstOrFail();

        $priority = TicketPriority::query()
            ->where(
                'priority_name',
                'MEDIUM'
            )
            ->firstOrFail();

        return SupportTicket::query()->create(
            array_merge([
                'customer_id' => $customer->id,
                'shipment_id' => null,
                'ticket_category_id' =>
                    $category->id,
                'ticket_status_id' =>
                    $status->id,
                'ticket_priority_id' =>
                    $priority->id,
                'assigned_to_user_id' => null,
                'subject' =>
                    'Ticket de soporte de prueba',
                'closed_at' => null,
            ], $attributes)
        );
    }

    /**
     * Crea un usuario con el rol solicitado.
     */
    private function userWithRole(
        string $roleName
    ): User {
        $role = Role::query()
            ->where(
                'role_name',
                $roleName
            )
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}