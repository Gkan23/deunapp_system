<?php

namespace Tests\Feature\Services\Support;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Shipment;
use App\Services\Support\CreateSupportTicketService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateSupportTicketServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_creates_a_support_ticket_with_its_initial_message_atomically(): void
    {
        $customer = Customer::factory()->create();

        $ticket = app(
            CreateSupportTicketService::class
        )->execute(
            customer: $customer,
            categoryName: 'ACCOUNT',
            subject: '  I cannot update my account  ',
            message: '  The application does not save my changes.  '
        );

        $this->assertSame($customer->id, $ticket->customer_id);
        $this->assertNull($ticket->shipment_id);
        $this->assertNull($ticket->assigned_to_user_id);
        $this->assertNull($ticket->closed_at);

        $this->assertSame(
            'I cannot update my account',
            $ticket->subject
        );

        $this->assertSame(
            'ACCOUNT',
            $ticket->category->category_name
        );

        $this->assertSame(
            'OPEN',
            $ticket->status->status_name
        );

        $this->assertSame(
            'MEDIUM',
            $ticket->priority->priority_name
        );

        $this->assertCount(1, $ticket->messages);

        $initialMessage = $ticket->messages->first();

        $this->assertSame(
            $customer->user_id,
            $initialMessage->user_id
        );

        $this->assertSame(
            'The application does not save my changes.',
            $initialMessage->message_text
        );

        $this->assertNull($initialMessage->attachment_url);
        $this->assertNotNull($initialMessage->sent_at);
        $this->assertFalse($initialMessage->is_read);

        $this->assertDatabaseCount('support_tickets', 1);
        $this->assertDatabaseCount('support_messages', 1);
        $this->assertDatabaseCount('audit_logs', 1);

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            $customer->user_id,
            $auditLog->performed_by_user_id
        );

        $this->assertSame(
            'support_tickets',
            $auditLog->table_name
        );

        $this->assertSame($ticket->id, $auditLog->record_id);
        $this->assertSame('TICKET_CREATED', $auditLog->action_type);
        $this->assertSame('ACCOUNT', $auditLog->details['category']);
        $this->assertSame('OPEN', $auditLog->details['status']);
        $this->assertSame('MEDIUM', $auditLog->details['priority']);

        $this->assertSame(
            $initialMessage->id,
            $auditLog->details['initial_message_id']
        );
    }

    public function test_it_creates_a_ticket_for_a_shipment_owned_by_the_customer(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $ticket = app(
            CreateSupportTicketService::class
        )->execute(
            customer: $customer,
            categoryName: ' delivery ',
            subject: 'Delivery delay',
            message: 'My shipment has not arrived.',
            shipment: $shipment,
            attachmentUrl: '  /storage/support/evidence.jpg  '
        );

        $this->assertSame(
            $shipment->id,
            $ticket->shipment_id
        );

        $this->assertSame(
            'DELIVERY',
            $ticket->category->category_name
        );

        $initialMessage = $ticket->messages->first();

        $this->assertSame(
            '/storage/support/evidence.jpg',
            $initialMessage->attachment_url
        );

        $this->assertSame(
            $shipment->id,
            AuditLog::query()
                ->firstOrFail()
                ->details['shipment_id']
        );
    }

    public function test_it_rejects_a_shipment_owned_by_another_customer(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $otherCustomer->id,
        ]);

        $this->assertDomainException(
            fn () => app(
                CreateSupportTicketService::class
            )->execute(
                $customer,
                'DELIVERY',
                'Shipment problem',
                'I need help with this shipment.',
                $shipment
            ),
            'The selected shipment does not belong to the customer.'
        );

        $this->assertTicketWasNotCreated();
    }

    public function test_it_rejects_an_unknown_category(): void
    {
        $customer = Customer::factory()->create();

        $this->assertDomainException(
            fn () => app(
                CreateSupportTicketService::class
            )->execute(
                $customer,
                'UNKNOWN_CATEGORY',
                'Support request',
                'This is the support request description.'
            ),
            'The selected support ticket category does not exist.'
        );

        $this->assertTicketWasNotCreated();
    }

    public function test_it_rejects_an_empty_subject(): void
    {
        $customer = Customer::factory()->create();

        $this->assertDomainException(
            fn () => app(
                CreateSupportTicketService::class
            )->execute(
                $customer,
                'TECHNICAL',
                '   ',
                'The application displays an error.'
            ),
            'The support ticket subject is required.'
        );

        $this->assertTicketWasNotCreated();
    }

    public function test_it_rejects_a_subject_longer_than_200_characters(): void
    {
        $customer = Customer::factory()->create();

        $this->assertDomainException(
            fn () => app(
                CreateSupportTicketService::class
            )->execute(
                $customer,
                'TECHNICAL',
                str_repeat('S', 201),
                'The application displays an error.'
            ),
            'The support ticket subject may not exceed 200 characters.'
        );

        $this->assertTicketWasNotCreated();
    }

    public function test_it_rejects_an_empty_initial_message(): void
    {
        $customer = Customer::factory()->create();

        $this->assertDomainException(
            fn () => app(
                CreateSupportTicketService::class
            )->execute(
                $customer,
                'OTHER',
                'General support request',
                '   '
            ),
            'The initial support message is required.'
        );

        $this->assertTicketWasNotCreated();
    }

    public function test_it_rejects_an_attachment_url_longer_than_500_characters(): void
    {
        $customer = Customer::factory()->create();

        $this->assertDomainException(
            fn () => app(
                CreateSupportTicketService::class
            )->execute(
                $customer,
                'TECHNICAL',
                'Attachment validation',
                'The attachment URL is too long.',
                null,
                str_repeat('A', 501)
            ),
            'The support attachment URL may not exceed 500 characters.'
        );

        $this->assertTicketWasNotCreated();
    }

    public function test_it_normalizes_an_empty_attachment_to_null(): void
    {
        $customer = Customer::factory()->create();

        $ticket = app(
            CreateSupportTicketService::class
        )->execute(
            $customer,
            'OTHER',
            'General question',
            'I have a general question.',
            null,
            '   '
        );

        $this->assertNull(
            $ticket->messages->first()->attachment_url
        );

        $this->assertDatabaseCount('support_tickets', 1);
        $this->assertDatabaseCount('support_messages', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    private function assertTicketWasNotCreated(): void
    {
        $this->assertDatabaseCount('support_tickets', 0);
        $this->assertDatabaseCount('support_messages', 0);
        $this->assertDatabaseCount('audit_logs', 0);
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

