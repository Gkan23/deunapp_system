<?php

namespace App\Services\Vehicle;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleStatus;
use App\Models\VehicleType;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateVehicleService
{
    /**
     * Registra un vehículo para un repartidor
     * perteneciente al proveedor.
     *
     * @throws DomainException
     */
    public function execute(
        User $performedBy,
        Courier $courier,
        string $vehicleTypeName,
        string $plateNumber,
        ?string $brand = null,
        ?string $model = null,
        ?string $color = null
    ): Vehicle {
        $normalizedVehicleType = strtoupper(
            trim($vehicleTypeName)
        );

        $normalizedPlate = preg_replace(
            '/\s+/',
            ' ',
            strtoupper(
                trim($plateNumber)
            )
        ) ?? '';

        try {
            return DB::transaction(function () use (
                $performedBy,
                $courier,
                $normalizedVehicleType,
                $normalizedPlate,
                $brand,
                $model,
                $color
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
                        'Only an active user can create vehicles.'
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
                        'Only an active delivery provider can create vehicles.'
                    );
                }

                $lockedCourier = Courier::query()
                    ->with('deliveryProvider')
                    ->whereKey($courier->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    (int) $lockedCourier
                        ->delivery_provider_id
                    !== (int) $lockedUser
                        ->deliveryProvider
                        ->id
                ) {
                    throw new DomainException(
                        'The courier does not belong to the delivery provider.'
                    );
                }

                if (! $lockedCourier->is_active) {
                    throw new DomainException(
                        'Vehicles can only be assigned to active couriers.'
                    );
                }

                $vehicleType = VehicleType::query()
                    ->where(
                        'type_name',
                        $normalizedVehicleType
                    )
                    ->first();

                if ($vehicleType === null) {
                    throw new DomainException(
                        'The selected vehicle type does not exist.'
                    );
                }

                $availableStatus =
                    VehicleStatus::query()
                        ->where(
                            'status_name',
                            'AVAILABLE'
                        )
                        ->firstOrFail();

                if (
                    Vehicle::query()
                        ->where(
                            'plate_number',
                            $normalizedPlate
                        )
                        ->exists()
                ) {
                    throw new DomainException(
                        'The vehicle plate number has already been registered.'
                    );
                }

                $createdAt = now();

                $vehicle = Vehicle::query()->create([
                    'courier_id' =>
                        $lockedCourier->id,
                    'vehicle_type_id' =>
                        $vehicleType->id,
                    'vehicle_status_id' =>
                        $availableStatus->id,
                    'plate_number' =>
                        $normalizedPlate,
                    'brand' =>
                        $this->normalizeOptional(
                            $brand
                        ),
                    'model' =>
                        $this->normalizeOptional(
                            $model
                        ),
                    'color' =>
                        $this->normalizeOptional(
                            $color
                        ),
                ]);

                AuditLog::query()->create([
                    'performed_by_user_id' =>
                        $lockedUser->id,
                    'table_name' => 'vehicles',
                    'record_id' => $vehicle->id,
                    'action_type' =>
                        'VEHICLE_CREATED',
                    'details' => [
                        'courier_id' =>
                            $lockedCourier->id,
                        'delivery_provider_id' =>
                            $lockedUser
                                ->deliveryProvider
                                ->id,
                        'vehicle_type' =>
                            $vehicleType
                                ->type_name,
                        'initial_status' =>
                            $availableStatus
                                ->status_name,
                        'plate_number' =>
                            $normalizedPlate,
                    ],
                    'performed_at' =>
                        $createdAt,
                ]);

                return $vehicle->load([
                    'courier.user',
                    'courier.deliveryProvider',
                    'vehicleType',
                    'vehicleStatus',
                ]);
            }, attempts: 3);
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            throw new DomainException(
                'The vehicle plate number has already been registered.',
                0,
                $exception
            );
        }
    }

    private function normalizeOptional(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}