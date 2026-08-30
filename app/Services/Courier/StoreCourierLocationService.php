<?php

namespace App\Services\Courier;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\CourierLocation;
use App\Models\DeliveryProvider;
use App\Models\Route;
use App\Models\RouteStatus;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class StoreCourierLocationService
{
    /**
     * Registra la ubicación del repartidor autenticado.
     *
     * @throws DomainException
     */
    public function execute(
        User $performedBy,
        float $latitude,
        float $longitude,
        ?float $gpsAccuracy = null
    ): CourierLocation {
        $this->validateCoordinates(
            $latitude,
            $longitude,
            $gpsAccuracy
        );

        $courierId = Courier::query()
            ->where(
                'user_id',
                $performedBy->getKey()
            )
            ->value('id');

        if ($courierId === null) {
            throw new DomainException(
                'The user does not have a courier profile.'
            );
        }

        return DB::transaction(function () use (
            $performedBy,
            $courierId,
            $latitude,
            $longitude,
            $gpsAccuracy
        ): CourierLocation {
            /*
             * Las rutas se bloquean primero para conservar
             * el orden usado por los servicios de rutas.
             */
            $lockedRoutes = Route::query()
                ->where('courier_id', $courierId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get([
                    'id',
                    'route_status_id',
                ]);

            $lockedUser = User::query()
                ->with('role')
                ->whereKey($performedBy->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $activeAccountStatus = AccountStatus::query()
                ->where('status_name', 'ACTIVE')
                ->firstOrFail();

            if (
                (int) $lockedUser
                    ->account_status_id
                !== (int) $activeAccountStatus->id
            ) {
                throw new DomainException(
                    'Only an active account can record courier locations.'
                );
            }

            if (! $lockedUser->hasVerifiedEmail()) {
                throw new DomainException(
                    'The courier email must be verified.'
                );
            }

            if (
                $lockedUser
                    ->role
                    ?->role_name
                !== 'COURIER'
            ) {
                throw new DomainException(
                    'Only a courier can record courier locations.'
                );
            }

            $lockedCourier = Courier::query()
                ->whereKey($courierId)
                ->where(
                    'user_id',
                    $lockedUser->id
                )
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedCourier->is_active) {
                throw new DomainException(
                    'An inactive courier cannot record locations.'
                );
            }

            $lockedProvider = DeliveryProvider::query()
                ->whereKey(
                    $lockedCourier
                        ->delivery_provider_id
                )
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedProvider->is_active) {
                throw new DomainException(
                    'A courier belonging to an inactive provider cannot record locations.'
                );
            }

            $activeRouteStatus = RouteStatus::query()
                ->where('status_name', 'ACTIVE')
                ->firstOrFail();

            $hasActiveRoute = $lockedRoutes
                ->contains(
                    fn (Route $route): bool =>
                        (int) $route
                            ->route_status_id
                        === (int) $activeRouteStatus
                            ->id
                );

            if (! $hasActiveRoute) {
                throw new DomainException(
                    'The courier must have an active route before recording a location.'
                );
            }

            $recordedAt = now();

            $location = CourierLocation::query()
                ->create([
                    'courier_id' =>
                        $lockedCourier->id,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'gps_accuracy' =>
                        $gpsAccuracy,
                    'recorded_at' =>
                        $recordedAt,
                ]);

            return $location->load([
                'courier.user',
                'courier.deliveryProvider',
            ]);
        }, attempts: 3);
    }

    /**
     * @throws DomainException
     */
    private function validateCoordinates(
        float $latitude,
        float $longitude,
        ?float $gpsAccuracy
    ): void {
        if (
            ! is_finite($latitude)
            || $latitude < -90
            || $latitude > 90
        ) {
            throw new DomainException(
                'The latitude must be between -90 and 90.'
            );
        }

        if (
            ! is_finite($longitude)
            || $longitude < -180
            || $longitude > 180
        ) {
            throw new DomainException(
                'The longitude must be between -180 and 180.'
            );
        }

        if (
            $gpsAccuracy !== null
            && (
                ! is_finite($gpsAccuracy)
                || $gpsAccuracy < 0
                || $gpsAccuracy > 10000
            )
        ) {
            throw new DomainException(
                'The GPS accuracy must be between 0 and 10000 meters.'
            );
        }
    }
}