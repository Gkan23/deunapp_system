<?php

namespace App\Services\Courier;

use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class UpdateCourierProfileService
{
    /**
     * Actualiza el usuario y su perfil de repartidor.
     *
     * @param array<string, mixed> $data
     *
     * @throws DomainException
     */
    public function execute(
        User $user,
        array $data
    ): User {
        try {
            return DB::transaction(function () use (
                $user,
                $data
            ): User {
                $lockedUser = User::query()
                    ->with([
                        'role',
                        'accountStatus',
                    ])
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedUser
                        ->accountStatus
                        ?->status_name !== 'ACTIVE'
                ) {
                    throw new DomainException(
                        'Only an active account can update a courier profile.'
                    );
                }

                if (
                    $lockedUser
                        ->role
                        ?->role_name !== 'COURIER'
                ) {
                    throw new DomainException(
                        'Only a courier can update this profile.'
                    );
                }

                $lockedCourier = Courier::query()
                    ->where(
                        'user_id',
                        $lockedUser->id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $lockedCourier->is_active) {
                    throw new DomainException(
                        'An inactive courier cannot update their profile.'
                    );
                }

                $lockedProvider =
                    DeliveryProvider::query()
                        ->whereKey(
                            $lockedCourier
                                ->delivery_provider_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (! $lockedProvider->is_active) {
                    throw new DomainException(
                        'A courier belonging to an inactive provider cannot update their profile.'
                    );
                }

                if (array_key_exists('name', $data)) {
                    $lockedUser->fill([
                        'name' => $data['name'],
                    ]);
                }

                if (
                    array_key_exists(
                        'license_number',
                        $data
                    )
                ) {
                    $lockedCourier->fill([
                        'license_number' =>
                            $data['license_number'],
                    ]);
                }

                $userChangedFields = array_keys(
                    $lockedUser->getDirty()
                );

                $courierChangedFields = array_keys(
                    $lockedCourier->getDirty()
                );

                $changedFields = array_values(
                    array_unique(
                        array_merge(
                            $userChangedFields,
                            $courierChangedFields
                        )
                    )
                );

                sort($changedFields);

                if ($lockedUser->isDirty()) {
                    $lockedUser->save();
                }

                if ($lockedCourier->isDirty()) {
                    $lockedCourier->save();
                }

                if ($changedFields !== []) {
                    $updatedAt = now();

                    AuditLog::query()->create([
                        'performed_by_user_id' =>
                            $lockedUser->id,
                        'table_name' => 'couriers',
                        'record_id' =>
                            $lockedCourier->id,
                        'action_type' =>
                            'COURIER_PROFILE_UPDATED',
                        'details' => [
                            'changed_fields' =>
                                $changedFields,
                            'delivery_provider_id' =>
                                $lockedProvider->id,
                        ],
                        'performed_at' => $updatedAt,
                    ]);
                }

                return $lockedUser->fresh([
                    'role',
                    'accountStatus',
                    'courier.deliveryProvider.providerType',
                ]);
            }, attempts: 3);
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            throw new DomainException(
                'The courier license number has already been registered.',
                0,
                $exception
            );
        }
    }
}