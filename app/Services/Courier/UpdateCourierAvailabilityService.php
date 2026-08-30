<?php

namespace App\Services\Courier;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\Route;
use App\Models\RouteStatus;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpdateCourierAvailabilityService
{
    /**
     * Cambia la disponibilidad del repartidor autenticado.
     *
     * @throws DomainException
     */
    public function execute(
        User $performedBy,
        bool $isAvailable,
        string $comment
    ): Courier {
        $normalizedComment = trim($comment);

        if ($normalizedComment === '') {
            throw new DomainException(
                'A comment is required to change courier availability.'
            );
        }

        if (mb_strlen($normalizedComment) > 500) {
            throw new DomainException(
                'The comment may not exceed 500 characters.'
            );
        }

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
            $isAvailable,
            $normalizedComment
        ): Courier {
            /*
             * Las rutas se bloquean primero para conservar
             * el orden utilizado por ActivateRouteService,
             * CancelRouteService y CompleteRouteService.
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
                    'Only an active account can change courier availability.'
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
                    'Only a courier can change courier availability.'
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
                    'An inactive courier cannot change their availability.'
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
                    'A courier belonging to an inactive provider cannot change their availability.'
                );
            }

            if (
                (bool) $lockedCourier
                    ->is_available
                === $isAvailable
            ) {
                throw new DomainException(
                    $isAvailable
                        ? 'The courier is already available.'
                        : 'The courier is already unavailable.'
                );
            }

            if ($isAvailable) {
                $activeRouteStatus =
                    RouteStatus::query()
                        ->where(
                            'status_name',
                            'ACTIVE'
                        )
                        ->firstOrFail();

                $hasActiveRoute = $lockedRoutes
                    ->contains(
                        fn (Route $route): bool =>
                            (int) $route
                                ->route_status_id
                            === (int) $activeRouteStatus
                                ->id
                    );

                if ($hasActiveRoute) {
                    throw new DomainException(
                        'A courier with an active route cannot become available.'
                    );
                }
            }

            $previousAvailability =
                (bool) $lockedCourier
                    ->is_available;

            $lockedCourier->update([
                'is_available' => $isAvailable,
            ]);

            $changedAt = now();

            AuditLog::query()->create([
                'performed_by_user_id' =>
                    $lockedUser->id,
                'table_name' => 'couriers',
                'record_id' => $lockedCourier->id,
                'action_type' =>
                    'COURIER_AVAILABILITY_CHANGED',
                'details' => [
                    'delivery_provider_id' =>
                        $lockedProvider->id,
                    'from_is_available' =>
                        $previousAvailability,
                    'to_is_available' =>
                        $isAvailable,
                    'comment' =>
                        $normalizedComment,
                ],
                'performed_at' => $changedAt,
            ]);

            return $lockedCourier->fresh([
                'user.role',
                'user.accountStatus',
                'deliveryProvider.providerType',
            ]);
        }, attempts: 3);
    }
}