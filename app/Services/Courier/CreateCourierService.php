<?php

namespace App\Services\Courier;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateCourierService
{
    /**
     * Crea un repartidor asociado al proveedor
     * autenticado.
     *
     * @throws DomainException
     */
    public function execute(
        User $performedBy,
        string $name,
        string $email,
        ?string $licenseNumber,
        string $comment
    ): User {
        try {
            return DB::transaction(function () use (
                $performedBy,
                $name,
                $email,
                $licenseNumber,
                $comment
            ): User {
                $lockedPerformedBy = User::query()
                    ->with([
                        'role',
                        'accountStatus',
                    ])
                    ->whereKey(
                        $performedBy->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedPerformedBy
                        ->accountStatus
                        ?->status_name !== 'ACTIVE'
                ) {
                    throw new DomainException(
                        'Only an active account can create couriers.'
                    );
                }

                if (
                    ! $lockedPerformedBy
                        ->hasVerifiedEmail()
                ) {
                    throw new DomainException(
                        'The delivery provider must verify their email before creating couriers.'
                    );
                }

                if (
                    $lockedPerformedBy
                        ->role
                        ?->role_name
                    !== 'DELIVERY_PROVIDER'
                ) {
                    throw new DomainException(
                        'Only delivery providers can create couriers.'
                    );
                }

                $lockedProvider =
                    DeliveryProvider::query()
                        ->where(
                            'user_id',
                            $lockedPerformedBy->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (! $lockedProvider->is_active) {
                    throw new DomainException(
                        'An inactive delivery provider cannot create couriers.'
                    );
                }

                $courierRole = Role::query()
                    ->where(
                        'role_name',
                        'COURIER'
                    )
                    ->firstOrFail();

                $activeStatus = AccountStatus::query()
                    ->where(
                        'status_name',
                        'ACTIVE'
                    )
                    ->firstOrFail();

                $normalizedName = trim($name);

                $normalizedEmail = strtolower(
                    trim($email)
                );

                $normalizedLicense =
                    $licenseNumber === null
                        ? null
                        : trim($licenseNumber);

                if ($normalizedLicense === '') {
                    $normalizedLicense = null;
                }

                $normalizedComment = trim($comment);

                if ($normalizedName === '') {
                    throw new DomainException(
                        'The courier name is required.'
                    );
                }

                if (
                    mb_strlen(
                        $normalizedName
                    ) > 255
                ) {
                    throw new DomainException(
                        'The courier name may not exceed 255 characters.'
                    );
                }

                if (
                    $normalizedEmail === ''
                    || ! filter_var(
                        $normalizedEmail,
                        FILTER_VALIDATE_EMAIL
                    )
                ) {
                    throw new DomainException(
                        'The courier email must be valid.'
                    );
                }

                if (
                    mb_strlen(
                        $normalizedEmail
                    ) > 255
                ) {
                    throw new DomainException(
                        'The courier email may not exceed 255 characters.'
                    );
                }

                if (
                    $normalizedLicense !== null
                    && mb_strlen(
                        $normalizedLicense
                    ) > 50
                ) {
                    throw new DomainException(
                        'The courier license number may not exceed 50 characters.'
                    );
                }

                if ($normalizedComment === '') {
                    throw new DomainException(
                        'A comment is required to create a courier.'
                    );
                }

                if (
                    mb_strlen(
                        $normalizedComment
                    ) > 500
                ) {
                    throw new DomainException(
                        'The courier creation comment may not exceed 500 characters.'
                    );
                }

                if (
                    User::query()
                        ->where(
                            'email',
                            $normalizedEmail
                        )
                        ->exists()
                ) {
                    throw new DomainException(
                        'The email has already been registered.'
                    );
                }

                if (
                    $normalizedLicense !== null
                    && Courier::query()
                        ->where(
                            'license_number',
                            $normalizedLicense
                        )
                        ->exists()
                ) {
                    throw new DomainException(
                        'The courier license number has already been registered.'
                    );
                }

                /*
                 * La contraseña aleatoria nunca se expone.
                 * El repartidor establecerá su contraseña
                 * usando el enlace de recuperación.
                 */
                $courierUser = User::query()->create([
                    'name' => $normalizedName,
                    'email' => $normalizedEmail,
                    'password' => Str::random(64),
                    'role_id' => $courierRole->id,
                    'account_status_id' =>
                        $activeStatus->id,
                ]);

                $courier = Courier::query()->create([
                    'delivery_provider_id' =>
                        $lockedProvider->id,
                    'user_id' => $courierUser->id,
                    'license_number' =>
                        $normalizedLicense,
                    'is_available' => false,
                    'is_active' => true,
                ]);

                $createdAt = now();

                AuditLog::query()->create([
                    'performed_by_user_id' =>
                        $lockedPerformedBy->id,
                    'table_name' => 'couriers',
                    'record_id' => $courier->id,
                    'action_type' =>
                        'COURIER_CREATED',
                    'details' => [
                        'courier_user_id' =>
                            $courierUser->id,
                        'delivery_provider_id' =>
                            $lockedProvider->id,
                        'email' =>
                            $courierUser->email,
                        'license_number' =>
                            $normalizedLicense,
                        'comment' =>
                            $normalizedComment,
                    ],
                    'performed_at' =>
                        $createdAt,
                ]);

                return $courierUser->load([
                    'role',
                    'accountStatus',
                    'courier.deliveryProvider.providerType',
                ]);
            }, attempts: 3);
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            throw new DomainException(
                'The email or courier license number has already been registered.',
                0,
                $exception
            );
        }
    }
}