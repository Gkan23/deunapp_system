<?php

namespace App\Services\Incident;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\IncidentStatus;
use App\Models\IncidentType;
use App\Models\Shipment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class CreateIncidentService
{
    /**
     * Registra una incidencia relacionada con un envío.
     *
     * @throws DomainException
     */
    public function execute(
        Shipment $shipment,
        User $reportedBy,
        string $incidentTypeName,
        string $description
    ): Incident {
        $normalizedType = strtoupper(
            trim($incidentTypeName)
        );

        $normalizedDescription = trim(
            $description
        );

        if ($normalizedDescription === '') {
            throw new DomainException(
                'The incident description is required.'
            );
        }

        return DB::transaction(function () use (
            $shipment,
            $reportedBy,
            $normalizedType,
            $normalizedDescription
        ): Incident {
            $lockedShipment = Shipment::query()
                ->whereKey($shipment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedReporter = User::query()
                ->with([
                    'role',
                    'customer',
                    'deliveryProvider',
                    'courier.deliveryProvider',
                ])
                ->whereKey($reportedBy->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $activeAccountStatus =
                AccountStatus::query()
                    ->where(
                        'status_name',
                        'ACTIVE'
                    )
                    ->firstOrFail();

            if (
                (int) $lockedReporter
                    ->account_status_id
                !== (int) $activeAccountStatus->id
            ) {
                throw new DomainException(
                    'Only an active user can report incidents.'
                );
            }

            if (! $this->canReportIncident(
                $lockedReporter,
                $lockedShipment
            )) {
                throw new DomainException(
                    'The user is not related to this shipment.'
                );
            }

            $incidentType = IncidentType::query()
                ->where(
                    'type_name',
                    $normalizedType
                )
                ->first();

            if ($incidentType === null) {
                throw new DomainException(
                    'The selected incident type does not exist.'
                );
            }

            $openStatus = IncidentStatus::query()
                ->where(
                    'status_name',
                    'OPEN'
                )
                ->firstOrFail();

            $reportedAt = now();

            $incident = Incident::query()->create([
                'shipment_id' =>
                    $lockedShipment->id,
                'reported_by_user_id' =>
                    $lockedReporter->id,
                'incident_type_id' =>
                    $incidentType->id,
                'incident_status_id' =>
                    $openStatus->id,
                'description' =>
                    $normalizedDescription,
                'reported_at' =>
                    $reportedAt,
            ]);

            AuditLog::query()->create([
                'performed_by_user_id' =>
                    $lockedReporter->id,
                'table_name' => 'incidents',
                'record_id' => $incident->id,
                'action_type' =>
                    'INCIDENT_CREATED',
                'details' => [
                    'shipment_id' =>
                        $lockedShipment->id,
                    'incident_type' =>
                        $incidentType->type_name,
                    'initial_status' =>
                        $openStatus->status_name,
                    'reported_by_role' =>
                        $lockedReporter
                            ->role
                            ?->role_name,
                ],
                'performed_at' =>
                    $reportedAt,
            ]);

            return $incident->load([
                'shipment',
                'reportedBy.role',
                'incidentType',
                'incidentStatus',
            ]);
        }, attempts: 3);
    }

    private function canReportIncident(
        User $user,
        Shipment $shipment
    ): bool {
        $roleName = $user
            ->role
            ?->role_name;

        if (in_array(
            $roleName,
            [
                'SUPPORT_AGENT',
                'ADMINISTRATOR',
            ],
            true
        )) {
            return true;
        }

        if ($roleName === 'CUSTOMER') {
            return $user->customer !== null
                && (int) $user->customer->id
                    === (int) $shipment->customer_id;
        }

        if ($roleName === 'DELIVERY_PROVIDER') {
            if (
                $user->deliveryProvider === null
                || ! $user
                    ->deliveryProvider
                    ->is_active
            ) {
                return false;
            }

            return $shipment
                ->deliveryService()
                ->whereHas(
                    'trip.deliveryProvider',
                    fn ($query) => $query
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->where(
                            'is_active',
                            true
                        )
                )
                ->exists();
        }

        if ($roleName === 'COURIER') {
            if (
                $user->courier === null
                || ! $user->courier->is_active
                || $user
                    ->courier
                    ->deliveryProvider === null
                || ! $user
                    ->courier
                    ->deliveryProvider
                    ->is_active
            ) {
                return false;
            }

            return $shipment
                ->routeShipments()
                ->whereHas(
                    'route.courier',
                    fn ($query) => $query
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->where(
                            'is_active',
                            true
                        )
                )
                ->exists();
        }

        return false;
    }
}