<?php

namespace App\Services\Provider;

use App\Models\AuditLog;
use App\Models\DeliveryProvider;
use App\Models\ProviderType;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class UpdateDeliveryProviderProfileService
{
    /**
     * Actualiza el usuario y el perfil del proveedor.
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
                        'Only an active account can update a provider profile.'
                    );
                }

                if (
                    $lockedUser
                        ->role
                        ?->role_name !== 'DELIVERY_PROVIDER'
                ) {
                    throw new DomainException(
                        'Only a delivery provider can update this profile.'
                    );
                }

                $lockedProvider =
                    DeliveryProvider::query()
                        ->where(
                            'user_id',
                            $lockedUser->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (! $lockedProvider->is_active) {
                    throw new DomainException(
                        'An inactive delivery provider cannot update their profile.'
                    );
                }

                $currentProviderType =
                    ProviderType::query()
                        ->whereKey(
                            $lockedProvider
                                ->provider_type_id
                        )
                        ->firstOrFail();

                $nextProviderType = array_key_exists(
                    'provider_type',
                    $data
                )
                    ? ProviderType::query()
                        ->where(
                            'type_name',
                            $data['provider_type']
                        )
                        ->firstOrFail()
                    : $currentProviderType;

                if (array_key_exists('name', $data)) {
                    $lockedUser->fill([
                        'name' => $data['name'],
                    ]);
                }

                $providerAttributes = [
                    'provider_type_id' =>
                        $nextProviderType->id,
                ];

                if (
                    array_key_exists(
                        'identity_number',
                        $data
                    )
                ) {
                    $providerAttributes[
                        'identity_number'
                    ] = $data['identity_number'];
                }

                if (array_key_exists('phone', $data)) {
                    $providerAttributes['phone'] =
                        $data['phone'];
                }

                /*
                 * Los proveedores independientes no deben
                 * conservar un nombre empresarial.
                 */
                if (
                    $nextProviderType->type_name
                    === 'INDEPENDENT'
                ) {
                    $providerAttributes[
                        'business_name'
                    ] = null;
                } elseif (
                    array_key_exists(
                        'business_name',
                        $data
                    )
                ) {
                    $providerAttributes[
                        'business_name'
                    ] = $data['business_name'];
                }

                $lockedProvider->fill(
                    $providerAttributes
                );

                $userChangedFields = array_keys(
                    $lockedUser->getDirty()
                );

                $providerChangedFields = array_keys(
                    $lockedProvider->getDirty()
                );

                $changedFields = array_values(
                    array_unique(
                        array_merge(
                            $userChangedFields,
                            $providerChangedFields
                        )
                    )
                );

                sort($changedFields);

                if ($lockedUser->isDirty()) {
                    $lockedUser->save();
                }

                if ($lockedProvider->isDirty()) {
                    $lockedProvider->save();
                }

                if ($changedFields !== []) {
                    $updatedAt = now();

                    AuditLog::query()->create([
                        'performed_by_user_id' =>
                            $lockedUser->id,
                        'table_name' =>
                            'delivery_providers',
                        'record_id' =>
                            $lockedProvider->id,
                        'action_type' =>
                            'PROVIDER_PROFILE_UPDATED',
                        'details' => [
                            'changed_fields' =>
                                $changedFields,
                            'provider_type' =>
                                $nextProviderType
                                    ->type_name,
                        ],
                        'performed_at' => $updatedAt,
                    ]);
                }

                return $lockedUser->fresh([
                    'role',
                    'accountStatus',
                    'deliveryProvider.providerType',
                ]);
            }, attempts: 3);
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            throw new DomainException(
                'The identity number has already been registered.',
                0,
                $exception
            );
        }
    }
}