<?php

namespace App\Services\Auth;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\DeliveryProvider;
use App\Models\ProviderType;
use App\Models\Role;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class RegisterDeliveryProviderService
{
    /**
     * Registra una cuenta y su perfil de proveedor.
     *
     * @param array<string, mixed> $data
     *
     * @throws DomainException
     */
    public function execute(array $data): User
    {
        try {
            return DB::transaction(function () use (
                $data
            ): User {
                $providerRole = Role::query()
                    ->where(
                        'role_name',
                        'DELIVERY_PROVIDER'
                    )
                    ->firstOrFail();

                $pendingStatus = AccountStatus::query()
                    ->where(
                        'status_name',
                        'PENDING'
                    )
                    ->firstOrFail();

                $providerType = ProviderType::query()
                    ->where(
                        'type_name',
                        $data['provider_type']
                    )
                    ->first();

                if ($providerType === null) {
                    throw new DomainException(
                        'The selected provider type does not exist.'
                    );
                }

                $name = trim(
                    (string) $data['name']
                );

                $email = strtolower(
                    trim(
                        (string) $data['email']
                    )
                );

                $identityNumber = trim(
                    (string) $data[
                        'identity_number'
                    ]
                );

                $phone = trim(
                    (string) $data['phone']
                );

                if ($name === '') {
                    throw new DomainException(
                        'The provider name is required.'
                    );
                }

                if (
                    $email === ''
                    || ! filter_var(
                        $email,
                        FILTER_VALIDATE_EMAIL
                    )
                ) {
                    throw new DomainException(
                        'The provider email must be valid.'
                    );
                }

                if ($identityNumber === '') {
                    throw new DomainException(
                        'The provider identity number is required.'
                    );
                }

                if ($phone === '') {
                    throw new DomainException(
                        'The provider phone is required.'
                    );
                }

                $businessName = $data[
                    'business_name'
                ] ?? null;

                $businessName = $businessName === null
                    ? null
                    : trim(
                        (string) $businessName
                    );

                if ($businessName === '') {
                    $businessName = null;
                }

                if (
                    $providerType->type_name
                    === 'COMPANY'
                    && $businessName === null
                ) {
                    throw new DomainException(
                        'The business name is required for a company provider.'
                    );
                }

                /*
                 * Los proveedores independientes no
                 * conservan un nombre empresarial.
                 */
                if (
                    $providerType->type_name
                    === 'INDEPENDENT'
                ) {
                    $businessName = null;
                }

                if (
                    User::query()
                        ->where(
                            'email',
                            $email
                        )
                        ->exists()
                ) {
                    throw new DomainException(
                        'The email has already been registered.'
                    );
                }

                if (
                    DeliveryProvider::query()
                        ->where(
                            'identity_number',
                            $identityNumber
                        )
                        ->exists()
                ) {
                    throw new DomainException(
                        'The identity number has already been registered.'
                    );
                }

                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $data['password'],
                    'role_id' => $providerRole->id,
                    'account_status_id' =>
                        $pendingStatus->id,
                ]);

                /*
                 * El perfil está preparado para operar,
                 * pero el estado PENDING de la cuenta
                 * impide el acceso hasta su aprobación.
                 */
                $provider =
                    DeliveryProvider::query()->create([
                        'user_id' => $user->id,
                        'provider_type_id' =>
                            $providerType->id,
                        'business_name' =>
                            $businessName,
                        'identity_number' =>
                            $identityNumber,
                        'phone' => $phone,
                        'is_active' => true,
                    ]);

                $registeredAt = now();

                AuditLog::query()->create([
                    'performed_by_user_id' =>
                        $user->id,
                    'table_name' =>
                        'delivery_providers',
                    'record_id' =>
                        $provider->id,
                    'action_type' =>
                        'PROVIDER_REGISTERED',
                    'details' => [
                        'user_id' => $user->id,
                        'provider_type' =>
                            $providerType->type_name,
                        'account_status' =>
                            $pendingStatus
                                ->status_name,
                    ],
                    'performed_at' =>
                        $registeredAt,
                ]);

                return $user->load([
                    'role',
                    'accountStatus',
                    'deliveryProvider.providerType',
                ]);
            }, attempts: 3);
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            throw new DomainException(
                'The email or identity number has already been registered.',
                0,
                $exception
            );
        }
    }
}