<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketCreatePageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);

        /*
         * Estas pruebas verifican el comportamiento HTTP
         * y el HTML, sin depender del manifiesto de Vite.
         */
        $this->withoutVite();
    }

    public function test_a_guest_cannot_open_or_submit_the_form(): void
    {
        $this->get(
            route('portal.support-tickets.create')
        )->assertRedirect(
            route('login.page')
        );

        $this->post(
            route('portal.support-tickets.store'),
            $this->validPayload()
        )->assertRedirect(
            route('login.page')
        );

        $this->assertDatabaseCount('support_tickets', 0);
        $this->assertDatabaseCount('support_messages', 0);
    }

    public function test_an_unverified_customer_cannot_open_or_submit_the_form(): void
    {
        $customer = Customer::factory()->create();

        $user = $customer->user;

        $user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this->actingAs($user)
            ->get(
                route('portal.support-tickets.create')
            )
            ->assertRedirect(
                route('verification.notice')
            );

        $this->post(
            route('portal.support-tickets.store'),
            $this->validPayload()
        )->assertRedirect(
            route('verification.notice')
        );

        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_an_active_customer_can_view_the_form(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->get(
                route('portal.support-tickets.create')
            )
            ->assertOk()
            ->assertViewIs('support-tickets.create')
            ->assertSee('Nuevo ticket de soporte')
            ->assertSee('TECHNICAL')
            ->assertSee('Sin envío relacionado')
            ->assertSee(
                route('portal.support-tickets.store'),
                escape: false
            );
    }

    public function test_the_form_only_lists_the_customers_shipments(): void
    {
        $customer = Customer::factory()->create();

        $otherCustomer = Customer::factory()->create();

        $ownShipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
            'tracking_code' => 'DUNA-SUPPORT-OWN-001',
        ]);

        $otherShipment = Shipment::factory()->create([
            'customer_id' => $otherCustomer->id,
            'tracking_code' => 'DUNA-SUPPORT-OTHER-001',
        ]);

        $this->actingAs($customer->user)
            ->get(
                route('portal.support-tickets.create')
            )
            ->assertOk()
            ->assertSee($ownShipment->tracking_code)
            ->assertDontSee($otherShipment->tracking_code)
            ->assertViewHas(
                'shipments',
                fn ($shipments) =>
                    $shipments->count() === 1
                    && $shipments->first()->id
                        === $ownShipment->id
            );
    }

    public function test_a_customer_can_create_a_general_ticket(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer->user)
            ->from(
                route('portal.support-tickets.create')
            )
            ->post(
                route('portal.support-tickets.store'),
                $this->validPayload()
            );

        $response->assertSessionHasNoErrors();

        $ticket = SupportTicket::query()->firstOrFail();

        $response
            ->assertRedirect(
                route(
                    'portal.support-tickets.show',
                    $ticket
                )
            )
            ->assertSessionHas('status');

        $this->assertDatabaseCount('support_tickets', 1);
        $this->assertDatabaseCount('support_messages', 1);

        $this->assertSame(
            $customer->id,
            $ticket->customer_id
        );

        $this->assertNull($ticket->shipment_id);
        $this->assertNull($ticket->assigned_to_user_id);
        $this->assertNull($ticket->closed_at);

        $this->assertSame(
            'OPEN',
            $ticket->status->status_name
        );

        $this->assertSame(
            'MEDIUM',
            $ticket->priority->priority_name
        );

        $this->assertSame(
            'TECHNICAL',
            $ticket->category->category_name
        );

        $this->assertDatabaseHas(
            'support_messages',
            [
                'ticket_id' => $ticket->id,
                'user_id' => $customer->user->id,
                'message_text' =>
                    'The application is not displaying my information.',
                'is_read' => false,
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'performed_by_user_id' =>
                    $customer->user->id,
                'table_name' => 'support_tickets',
                'record_id' => $ticket->id,
                'action_type' => 'TICKET_CREATED',
            ]
        );
    }

    public function test_a_customer_can_create_a_ticket_for_their_shipment(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $response = $this->actingAs($customer->user)
            ->post(
                route('portal.support-tickets.store'),
                $this->validPayload([
                    'shipment_id' => $shipment->id,
                ])
            );

        $response->assertSessionHasNoErrors();

        $ticket = SupportTicket::query()->firstOrFail();

        $response->assertRedirect(
            route(
                'portal.support-tickets.show',
                $ticket
            )
        );

        $this->assertDatabaseHas(
            'support_tickets',
            [
                'id' => $ticket->id,
                'customer_id' => $customer->id,
                'shipment_id' => $shipment->id,
            ]
        );
    }

    public function test_a_customer_cannot_use_another_customers_shipment(): void
    {
        $customer = Customer::factory()->create();

        $otherCustomer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $otherCustomer->id,
        ]);

        $this->actingAs($customer->user)
            ->from(
                route('portal.support-tickets.create')
            )
            ->post(
                route('portal.support-tickets.store'),
                $this->validPayload([
                    'shipment_id' => $shipment->id,
                ])
            )
            ->assertRedirect(
                route('portal.support-tickets.create')
            )
            ->assertSessionHasErrors('shipment_id');

        $this->assertDatabaseCount('support_tickets', 0);
        $this->assertDatabaseCount('support_messages', 0);

        $this->assertDatabaseMissing(
            'audit_logs',
            [
                'action_type' => 'TICKET_CREATED',
            ]
        );
    }

    public function test_category_subject_and_message_are_required(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->from(
                route('portal.support-tickets.create')
            )
            ->post(
                route('portal.support-tickets.store'),
                []
            )
            ->assertRedirect(
                route('portal.support-tickets.create')
            )
            ->assertSessionHasErrors([
                'category',
                'subject',
                'message',
            ]);

        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_the_category_and_shipment_must_exist(): void
    {
        $customer = Customer::factory()->create();

        $missingShipmentId = (int) Shipment::query()
            ->max('id') + 1;

        $this->actingAs($customer->user)
            ->from(
                route('portal.support-tickets.create')
            )
            ->post(
                route('portal.support-tickets.store'),
                $this->validPayload([
                    'category' => 'UNKNOWN_CATEGORY',
                    'shipment_id' => $missingShipmentId,
                ])
            )
            ->assertRedirect(
                route('portal.support-tickets.create')
            )
            ->assertSessionHasErrors([
                'category',
                'shipment_id',
            ]);

        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_the_subject_cannot_exceed_two_hundred_characters(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->from(
                route('portal.support-tickets.create')
            )
            ->post(
                route('portal.support-tickets.store'),
                $this->validPayload([
                    'subject' => str_repeat('A', 201),
                ])
            )
            ->assertRedirect(
                route('portal.support-tickets.create')
            )
            ->assertSessionHasErrors('subject');

        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_other_roles_cannot_open_or_submit_the_form(): void
    {
        foreach (
            [
                'DELIVERY_PROVIDER',
                'COURIER',
                'SUPPORT_AGENT',
                'ADMINISTRATOR',
            ] as $roleName
        ) {
            $user = $this->userWithRole($roleName);

            $this->actingAs($user)
                ->get(
                    route('portal.support-tickets.create')
                )
                ->assertForbidden();

            $this->post(
                route('portal.support-tickets.store'),
                $this->validPayload()
            )->assertForbidden();
        }

        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_an_inactive_customer_cannot_open_or_submit_the_form(): void
    {
        $customer = Customer::factory()->create();

        $user = $customer->user;

        $user->update([
            'account_status_id' => AccountStatus::query()
                ->where('status_name', 'SUSPENDED')
                ->firstOrFail()
                ->id,
        ]);

        $this->actingAs($user)
            ->get(
                route('portal.support-tickets.create')
            )
            ->assertForbidden();

        $this->post(
            route('portal.support-tickets.store'),
            $this->validPayload()
        )->assertForbidden();

        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_the_create_link_is_only_shown_to_eligible_customers(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->get(
                route('portal.support-tickets.index')
            )
            ->assertOk()
            ->assertSee('Nuevo ticket')
            ->assertSee(
                route('portal.support-tickets.create'),
                escape: false
            );

        foreach (
            [
                'SUPPORT_AGENT',
                'ADMINISTRATOR',
            ] as $roleName
        ) {
            $user = $this->userWithRole($roleName);

            $this->actingAs($user)
                ->get(
                    route('portal.support-tickets.index')
                )
                ->assertOk()
                ->assertDontSee(
                    route('portal.support-tickets.create'),
                    escape: false
                );
        }
    }

    public function test_a_customer_user_without_a_profile_cannot_create_tickets(): void
    {
        $user = $this->userWithRole('CUSTOMER');

        $this->assertFalse(
            $user->customer()->exists()
        );

        $this->actingAs($user)
            ->get(
                route('portal.support-tickets.create')
            )
            ->assertForbidden();

        $this->post(
            route('portal.support-tickets.store'),
            $this->validPayload()
        )->assertForbidden();

        $this->assertDatabaseCount('support_tickets', 0);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function validPayload(
        array $attributes = []
    ): array {
        return array_merge(
            [
                'category' => 'TECHNICAL',
                'subject' => 'Application support request',
                'message' =>
                    'The application is not displaying my information.',
                'shipment_id' => null,
            ],
            $attributes
        );
    }

    private function userWithRole(
        string $roleName
    ): User {
        return User::factory()->create([
            'role_id' => Role::query()
                ->where('role_name', $roleName)
                ->firstOrFail()
                ->id,
        ]);
    }
}