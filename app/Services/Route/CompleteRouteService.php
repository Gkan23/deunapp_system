<?php

namespace App\Services\Route;

use App\Models\Courier;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

class CompleteRouteService
{
    private const TERMINAL_DELIVERY_STATUSES = [
        'DELIVERED',
        'FAILED',
    ];

    /**
     * Complete an active route.
     *
     * The route can only be completed when every route shipment
     * has reached a terminal delivery status.
     *
     * @throws DomainException
     */
    public function execute(Route $route): Route
    {
        return DB::transaction(function () use ($route): Route {
            $lockedRoute = Route::query()
                ->whereKey($route->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $activeStatus = RouteStatus::query()
                ->where('status_name', 'ACTIVE')
                ->firstOrFail();

            if ((int) $lockedRoute->route_status_id !== (int) $activeStatus->id) {
                throw new DomainException(
                    'Only an active route can be completed.'
                );
            }

            $courier = Courier::query()
                ->whereKey($lockedRoute->courier_id)
                ->lockForUpdate()
                ->firstOrFail();

            $routeShipments = RouteShipment::query()
                ->where('route_id', $lockedRoute->id)
                ->orderBy('delivery_order')
                ->lockForUpdate()
                ->get();

            if ($routeShipments->isEmpty()) {
                throw new DomainException(
                    'A route without shipments cannot be completed.'
                );
            }

            $hasNonTerminalShipment = $routeShipments->contains(
                fn (RouteShipment $routeShipment): bool => ! in_array(
                    $routeShipment->delivery_status,
                    self::TERMINAL_DELIVERY_STATUSES,
                    true
                )
            );

            if ($hasNonTerminalShipment) {
                throw new DomainException(
                    'Every route shipment must be delivered or failed before completing the route.'
                );
            }

            $completedStatus = RouteStatus::query()
                ->where('status_name', 'COMPLETED')
                ->firstOrFail();

            $lockedRoute->update([
                'route_status_id' => $completedStatus->id,
                'finished_at' => now(),
            ]);

            /*
             * An inactive courier must not appear as available,
             * even after finishing the route.
             */
            $courier->update([
                'is_available' => (bool) $courier->is_active,
            ]);

            return $lockedRoute->fresh([
                'routeStatus',
                'courier',
                'routeShipments',
            ]);
        }, attempts: 3);
    }
}
