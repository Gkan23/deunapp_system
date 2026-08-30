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
use Illuminate\Support\Str;

class UpdateCourierStatusService
{
    /**
     * Activa o desactiva un repartidor.
     *
     * @throws DomainException
     */
    public function execute(
        Courier $courier,
        User $performedBy,
        bool $isActive,
        string $comment
    ): Courier {
        $normalizedComment = trim($comment);

        if ($normalizedComment === '') {
            throw new DomainException(
                'A comment is required to change the courier status.'
            );
        }

        if (mb_strlen($normalizedComment) > 500) {
            throw new DomainException(
                'The comment may not exceed 500 characters.'
            );
        }

        return DB::transaction(function () use (
            $courier,
            $performedBy,
            $isActive,
            $normalizedComment
        ): Courier {
            $lockedCourier = Courier::query()
                ->whereKey($courier->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPerformedBy = User::query()
                ->with('role')
                ->whereKey($performedBy->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $activeAccountStatus = AccountStatus::query()
                ->where('status_name', 'ACTIVE')
                ->firstOrFail();

            if (
                (int) $lockedPerformedBy
                    ->account_status_id
                !== (int) $activeAccountStatus->id
            ) {
                throw new DomainException(
                    'Only an active user can change courier statuses.'
                );
            }

            if (! $lockedPerformedBy->hasVerifiedEmail()) {
                throw new DomainException(
                    'The delivery provider email must be verified.'
                );
            }

            if (
                $lockedPerformedBy
                    ->role
                    ?->role_name
                !== 'DELIVERY_PROVIDER'
            ) {
                throw new DomainException(
                    'Only delivery providers can change courier statuses.'
                );
            }

            $lockedProvider = DeliveryProvider::query()
                ->where(
                    'user_id',
                    $lockedPerformedBy->id
                )
                ->lockForUpdate()
                ->first();

            if ($lockedProvider === null) {
                throw new DomainException(
                    'The user does not have a delivery provider profile.'
                );
            }

            if (! $lockedProvider->is_active) {
                throw new DomainException(
                    'The delivery provider is inactive.'
                );
            }

            if (
                (int) $lockedCourier
                    ->delivery_provider_id
                !== (int) $lockedProvider->id
            ) {
                throw new DomainException(
                    'The courier does not belong to the delivery provider.'
                );
            }

            if (
                (bool) $lockedCourier->is_active
                === $isActive
            ) {
                throw new DomainException(
                    $isActive
                        ? 'The courier is already active.'
                        : 'The courier is already inactive.'
                );
            }

            if (! $isActive) {
                $this->ensureCourierHasNoActiveRoute(
                    $lockedCourier
                );
            }

            $previousIsActive = (bool) $lockedCourier
                ->is_active;

            $previousIsAvailable = (bool) $lockedCourier
                ->is_available;

            $changes = [
                'is_active' => $isActive,
            ];

            if (! $isActive) {
                $changes['is_available'] = false;
            }

            $lockedCourier->update($changes);

            if (! $isActive) {
                $this->revokeCourierSessions(
                    $lockedCourier
                );
            }

            $changedAt = now();

            AuditLog::query()->create([
                'performed_by_user_id' =>
                    $lockedPerformedBy->id,
                'table_name' => 'couriers',
                'record_id' => $lockedCourier->id,
                'action_type' =>
                    'COURIER_STATUS_CHANGED',
                'details' => [
                    'delivery_provider_id' =>
                        $lockedProvider->id,
                    'courier_user_id' =>
                        $lockedCourier->user_id,
                    'from_is_active' =>
                        $previousIsActive,
                    'to_is_active' =>
                        $isActive,
                    'from_is_available' =>
                        $previousIsAvailable,
                    'to_is_available' =>
                        (bool) $lockedCourier
                            ->is_available,
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

    /**
     * @throws DomainException
     */
    private function ensureCourierHasNoActiveRoute(
        Courier $courier
    ): void {
        $activeStatus = RouteStatus::query()
            ->where('status_name', 'ACTIVE')
            ->firstOrFail();

        $activeRoute = Route::query()
            ->where(
                'courier_id',
                $courier->id
            )
            ->where(
                'route_status_id',
                $activeStatus->id
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($activeRoute !== null) {
            throw new DomainException(
                'The courier cannot be deactivated while having an active route.'
            );
        }
    }

    private function revokeCourierSessions(
        Courier $courier
    ): void {
        DB::table('sessions')
            ->where(
                'user_id',
                $courier->user_id
            )
            ->delete();

        User::query()
            ->whereKey($courier->user_id)
            ->update([
                'remember_token' =>
                    Str::random(60),
            ]);
    }
}