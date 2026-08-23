<?php

namespace App\Services\Delivery;

use App\Models\Courier;
use App\Models\DeliveryService;
use App\Models\Incident;
use App\Models\IncidentStatus;
use App\Models\IncidentType;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class FailDeliveryAttemptService
{
    /**
     * Incident types that may finish a delivery attempt unsuccessfully.
     */
    private const ALLOWED_INCIDENT_TYPES = [
        'DELIVERY_FAILED',
        'RECIPIENT_ABSENT',
        'WRONG_ADDRESS',
        'CONTACT_FAILED',
        'DAMAGED_PACKAGE',
        'LOST_PACKAGE',
        'VEHICLE_PROBLEM',
    ];

    /**
     * Register a failed delivery attempt.
     *
     * @throws DomainException
     */
    public function execute(
        RouteShipment $routeShipment,
        User $reportedBy,
        string $incidentTypeName,
        string $description
    ): Incident {
        return DB::transaction(function () use (
            $routeShipment,
            $reportedBy,
            $incidentTypeName,
            $description
        ): Incident {
            /*
             * Lock order:
             * 1. Route
             * 2. Courier
             * 3. Route shipment
             * 4. Delivery service
             *
             * This order is consistent with the route services and helps
             * reduce the possibility of database deadlocks.
             */
            $lockedRoute = Route::query()
                ->whereKey($routeShipment->route_id)
                ->lockForUpdate()
                ->firstOrFail();

            $activeStatus = RouteStatus::query()
                ->where('status_name', 'ACTIVE')
                ->firstOrFail();

            if ((int) $lockedRoute->route_status_id !== (int) $activeStatus->id) {
                throw new DomainException(
                    'Only shipments from an active route can be marked as failed.'
                );
            }

            $courier = Courier::query()
                ->whereKey($lockedRoute->courier_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $courier->user_id !== (int) $reportedBy->getKey()) {
                throw new DomainException(
                    'Only the courier assigned to the route can report the failed attempt.'
                );
            }

            $lockedRouteShipment = RouteShipment::query()
                ->whereKey($routeShipment->getKey())
                ->where('route_id', $lockedRoute->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRouteShipment->delivery_status !== 'IN_PROGRESS') {
                throw new DomainException(
                    'Only an in-progress route shipment can be marked as failed.'
                );
            }

            $normalizedDescription = trim($description);

            if ($normalizedDescription === '') {
                throw new DomainException(
                    'The incident description is required.'
                );
            }

            $normalizedIncidentType = strtoupper(
                trim($incidentTypeName)
            );

            if (! in_array(
                $normalizedIncidentType,
                self::ALLOWED_INCIDENT_TYPES,
                true
            )) {
                throw new DomainException(
                    'The selected incident type cannot be used for a failed delivery attempt.'
                );
            }

            $deliveryService = DeliveryService::query()
                ->where('shipment_id', $lockedRouteShipment->shipment_id)
                ->lockForUpdate()
                ->first();

            if ($deliveryService === null) {
                throw new DomainException(
                    'The shipment does not have a delivery service.'
                );
            }

            if ($deliveryService->status !== 'IN_PROGRESS') {
                throw new DomainException(
                    'Only an in-progress delivery service can report a failed attempt.'
                );
            }

            $incidentType = IncidentType::query()
                ->where('type_name', $normalizedIncidentType)
                ->firstOrFail();

            $openIncidentStatus = IncidentStatus::query()
                ->where('status_name', 'OPEN')
                ->firstOrFail();

            $lockedRouteShipment->update([
                'delivery_status' => 'FAILED',
            ]);

            /*
             * The paid trip remains USED. A failed attempt does not return
             * the trip to the provider or create another debit.
             *
             * The same delivery service returns to ASSIGNED so it can be
             * included in a future route.
             */
            $deliveryService->update([
                'status' => 'ASSIGNED',
                'started_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
            ]);

            $incident = Incident::query()->create([
                'shipment_id' => $lockedRouteShipment->shipment_id,
                'reported_by_user_id' => $reportedBy->getKey(),
                'incident_type_id' => $incidentType->id,
                'incident_status_id' => $openIncidentStatus->id,
                'description' => $normalizedDescription,
                'reported_at' => now(),
            ]);

            return $incident->load([
                'shipment',
                'reportedBy',
                'incidentType',
                'incidentStatus',
            ]);
        }, attempts: 3);
    }
}

