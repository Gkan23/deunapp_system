<?php

namespace App\Services\Vehicle;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpdateVehicleStatusService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'AVAILABLE' => [
            'MAINTENANCE',
            'INACTIVE',
        ],
        'IN_USE' => [
            'AVAILABLE',
            'MAINTENANCE',
        ],
        'MAINTENANCE' => [
            'AVAILABLE',
            'INACTIVE',
        ],
        'INACTIVE' => [
            'AVAILABLE',
        ],
    ];

    /**
     * Cambia el estado de un vehículo.
     *
     * IN_USE queda reservado para operaciones internas.
     *
     * @throws DomainException
     */
    public function execute(
        Vehicle $vehicle,
        User $performedBy,
        string $targetStatusName,
        string $comment
    ): Vehicle {
        $normalizedStatus = strtoupper(
            trim($targetStatusName)
        );

        $normalizedComment = trim(
            $comment
        );

        if ($normalizedComment === '') {
            throw new DomainException(
                'A comment is required to change the vehicle status.'
            );
        }

        return DB::transaction(function () use (
            $vehicle,
            $performedBy,
            $normalizedStatus,
            $normalizedComment
        ): Vehicle {
            $lockedUser = User::query()
                ->with([
                    'role',
                    'deliveryProvider',
                ])
                ->whereKey(
                    $performedBy->getKey()
                )
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
                (int) $lockedUser
                    ->account_status_id
                !== (int) $activeAccountStatus
                    ->id
            ) {
                throw new DomainException(
                    'Only an active user can change vehicle statuses.'
                );
            }

            if (
                $lockedUser
                    ->role
                    ?->role_name
                !== 'DELIVERY_PROVIDER'
                || $lockedUser
                    ->deliveryProvider === null
                || ! $lockedUser
                    ->deliveryProvider
                    ->is_active
            ) {
                throw new DomainException(
                    'Only an active delivery provider can change vehicle statuses.'
                );
            }

            $lockedVehicle = Vehicle::query()
                ->with([
                    'courier.deliveryProvider',
                    'vehicleStatus',
                ])
                ->whereKey($vehicle->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (int) $lockedVehicle
                    ->courier
                    ->delivery_provider_id
                !== (int) $lockedUser
                    ->deliveryProvider
                    ->id
            ) {
                throw new DomainException(
                    'The vehicle does not belong to the delivery provider.'
                );
            }

            $currentStatus = $lockedVehicle
                ->vehicleStatus;

            $targetStatus = VehicleStatus::query()
                ->where(
                    'status_name',
                    $normalizedStatus
                )
                ->first();

            if ($targetStatus === null) {
                throw new DomainException(
                    'The selected vehicle status does not exist.'
                );
            }

            if (
                $targetStatus->status_name
                === 'IN_USE'
            ) {
                throw new DomainException(
                    'The IN_USE status can only be assigned by an internal route operation.'
                );
            }

            if (
                $currentStatus->status_name
                === $targetStatus->status_name
            ) {
                throw new DomainException(
                    'The vehicle is already in the requested status.'
                );
            }

            $allowedTargets =
                self::ALLOWED_TRANSITIONS[
                    $currentStatus->status_name
                ] ?? [];

            if (! in_array(
                $targetStatus->status_name,
                $allowedTargets,
                true
            )) {
                throw new DomainException(
                    sprintf(
                        'The vehicle status transition from %s to %s is not allowed.',
                        $currentStatus->status_name,
                        $targetStatus->status_name
                    )
                );
            }

            $changedAt = now();

            $lockedVehicle->update([
                'vehicle_status_id' =>
                    $targetStatus->id,
            ]);

            AuditLog::query()->create([
                'performed_by_user_id' =>
                    $lockedUser->id,
                'table_name' => 'vehicles',
                'record_id' =>
                    $lockedVehicle->id,
                'action_type' =>
                    'VEHICLE_STATUS_CHANGED',
                'details' => [
                    'from_status' =>
                        $currentStatus
                            ->status_name,
                    'to_status' =>
                        $targetStatus
                            ->status_name,
                    'comment' =>
                        $normalizedComment,
                    'delivery_provider_id' =>
                        $lockedUser
                            ->deliveryProvider
                            ->id,
                    'courier_id' =>
                        $lockedVehicle
                            ->courier_id,
                ],
                'performed_at' =>
                    $changedAt,
            ]);

            return $lockedVehicle->fresh([
                'courier.user',
                'courier.deliveryProvider',
                'vehicleType',
                'vehicleStatus',
            ]);
        }, attempts: 3);
    }
}