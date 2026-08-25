<?php

namespace Tests\Feature\Services\Incident;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\IncidentStatus;
use App\Models\IncidentType;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Incident\UpdateIncidentStatusService;
use Closure;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateIncidentStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_moves_an_open_incident_to_in_review(): void
    {
        $incident = $this->createIncident('OPEN');
        $performedBy = User::factory()->create();

        $updatedIncident = app(
            UpdateIncidentStatusService::class
        )->execute(
            $incident,
            'IN_REVIEW',
            $performedBy,
            'The incident is now being reviewed.'
        );

        $this->assertSame(
            'IN_REVIEW',
            $updatedIncident->incidentStatus->status_name
        );

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'incident_status_id' => $this
                ->findIncidentStatus('IN_REVIEW')
                ->id,
        ]);

        $this->assertDatabaseCount('audit_logs', 1);

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            $performedBy->id,
            $auditLog->performed_by_user_id
        );

        $this->assertSame('incidents', $auditLog->table_name);
        $this->assertSame($incident->id, $auditLog->record_id);
        $this->assertSame('STATUS_CHANGED', $auditLog->action_type);

        $this->assertSame(
            'OPEN',
            $auditLog->details['from_status']
        );

        $this->assertSame(
            'IN_REVIEW',
            $auditLog->details['to_status']
        );

        $this->assertSame(
            'The incident is now being reviewed.',
            $auditLog->details['comment']
        );

        $this->assertNotNull($auditLog->performed_at);
    }

    public function test_it_resolves_an_incident_with_a_required_comment(): void
    {
        $incident = $this->createIncident('IN_REVIEW');
        $performedBy = User::factory()->create();

        $updatedIncident = app(
            UpdateIncidentStatusService::class
        )->execute(
            $incident,
            'RESOLVED',
            $performedBy,
            'The recipient was contacted and the delivery was rescheduled.'
        );

        $this->assertSame(
            'RESOLVED',
            $updatedIncident->incidentStatus->status_name
        );

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            'IN_REVIEW',
            $auditLog->details['from_status']
        );

        $this->assertSame(
            'RESOLVED',
            $auditLog->details['to_status']
        );

        $this->assertSame(
            'The recipient was contacted and the delivery was rescheduled.',
            $auditLog->details['comment']
        );
    }

    public function test_it_closes_a_resolved_incident(): void
    {
        $incident = $this->createIncident('RESOLVED');
        $performedBy = User::factory()->create();

        $updatedIncident = app(
            UpdateIncidentStatusService::class
        )->execute(
            $incident,
            'CLOSED',
            $performedBy,
            'The resolution was verified.'
        );

        $this->assertSame(
            'CLOSED',
            $updatedIncident->incidentStatus->status_name
        );

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            'RESOLVED',
            $auditLog->details['from_status']
        );

        $this->assertSame(
            'CLOSED',
            $auditLog->details['to_status']
        );
    }

    public function test_it_reopens_a_resolved_incident_with_a_comment(): void
    {
        $incident = $this->createIncident('RESOLVED');
        $performedBy = User::factory()->create();

        $updatedIncident = app(
            UpdateIncidentStatusService::class
        )->execute(
            $incident,
            'IN_REVIEW',
            $performedBy,
            'The reported problem occurred again.'
        );

        $this->assertSame(
            'IN_REVIEW',
            $updatedIncident->incidentStatus->status_name
        );

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(
            'RESOLVED',
            $auditLog->details['from_status']
        );

        $this->assertSame(
            'IN_REVIEW',
            $auditLog->details['to_status']
        );

        $this->assertSame(
            'The reported problem occurred again.',
            $auditLog->details['comment']
        );
    }

    public function test_it_rejects_an_invalid_direct_transition(): void
    {
        $incident = $this->createIncident('OPEN');
        $performedBy = User::factory()->create();

        $this->assertDomainException(
            fn () => app(
                UpdateIncidentStatusService::class
            )->execute(
                $incident,
                'CLOSED',
                $performedBy,
                'Invalid direct closure.'
            ),
            'The incident status transition from OPEN to CLOSED is not allowed.'
        );

        $this->assertIncidentWasNotModified(
            $incident,
            'OPEN'
        );
    }

    public function test_it_rejects_the_same_status(): void
    {
        $incident = $this->createIncident('OPEN');
        $performedBy = User::factory()->create();

        $this->assertDomainException(
            fn () => app(
                UpdateIncidentStatusService::class
            )->execute(
                $incident,
                'OPEN',
                $performedBy
            ),
            'The incident is already in the requested status.'
        );

        $this->assertIncidentWasNotModified(
            $incident,
            'OPEN'
        );
    }

    public function test_it_requires_a_comment_to_resolve_an_incident(): void
    {
        $incident = $this->createIncident('IN_REVIEW');
        $performedBy = User::factory()->create();

        $this->assertDomainException(
            fn () => app(
                UpdateIncidentStatusService::class
            )->execute(
                $incident,
                'RESOLVED',
                $performedBy,
                '   '
            ),
            'A comment is required to resolve an incident.'
        );

        $this->assertIncidentWasNotModified(
            $incident,
            'IN_REVIEW'
        );
    }

    public function test_it_requires_a_comment_to_reopen_a_resolved_incident(): void
    {
        $incident = $this->createIncident('RESOLVED');
        $performedBy = User::factory()->create();

        $this->assertDomainException(
            fn () => app(
                UpdateIncidentStatusService::class
            )->execute(
                $incident,
                'IN_REVIEW',
                $performedBy,
                null
            ),
            'A comment is required to reopen a resolved incident.'
        );

        $this->assertIncidentWasNotModified(
            $incident,
            'RESOLVED'
        );
    }

    public function test_it_rejects_an_unknown_incident_status(): void
    {
        $incident = $this->createIncident('OPEN');
        $performedBy = User::factory()->create();

        $this->assertDomainException(
            fn () => app(
                UpdateIncidentStatusService::class
            )->execute(
                $incident,
                'UNKNOWN_STATUS',
                $performedBy
            ),
            'The selected incident status does not exist.'
        );

        $this->assertIncidentWasNotModified(
            $incident,
            'OPEN'
        );
    }

    private function createIncident(string $statusName): Incident
    {
        $shipment = Shipment::factory()->create();
        $reportedBy = User::factory()->create();

        return Incident::query()->create([
            'shipment_id' => $shipment->id,
            'reported_by_user_id' => $reportedBy->id,
            'incident_type_id' => IncidentType::query()
                ->where('type_name', 'DELIVERY_FAILED')
                ->firstOrFail()
                ->id,
            'incident_status_id' => $this
                ->findIncidentStatus($statusName)
                ->id,
            'description' => 'The delivery attempt could not be completed.',
            'reported_at' => now()->subHour(),
        ]);
    }

    private function findIncidentStatus(
        string $statusName
    ): IncidentStatus {
        return IncidentStatus::query()
            ->where('status_name', $statusName)
            ->firstOrFail();
    }

    private function assertIncidentWasNotModified(
        Incident $incident,
        string $expectedStatus
    ): void {
        $this->assertSame(
            $this->findIncidentStatus($expectedStatus)->id,
            $incident->fresh()->incident_status_id
        );

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

