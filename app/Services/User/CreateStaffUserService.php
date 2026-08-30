<?php

namespace App\Services\User;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateStaffUserService
{
    private const ALLOWED_ROLES = [
        'SUPPORT_AGENT',
        'ADMINISTRATOR',
    ];

    /**
     * Crea una cuenta interna sin exponer
     * una contraseña temporal.
     *
     * @throws DomainException
     */
    public function execute(
        User $performedBy,
        string $name,
        string $email,
        string $roleName,
        string $comment
    ): User {
        try {
            return DB::transaction(function () use (
                $performedBy,
                $name,
                $email,
                $roleName,
                $comment
            ): User {
                $lockedPerformedBy = User::query()
                    ->with('role')
                    ->whereKey(
                        $performedBy->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $activeStatus = AccountStatus::query()
                    ->where(
                        'status_name',
                        'ACTIVE'
                    )
                    ->firstOrFail();

                if (
                    (int) $lockedPerformedBy
                        ->account_status_id
                    !== (int) $activeStatus->id
                ) {
                    throw new DomainException(
                        'Only an active administrator can create staff users.'
                    );
                }

                if (
                    $lockedPerformedBy
                        ->role
                        ?->role_name
                    !== 'ADMINISTRATOR'
                ) {
                    throw new DomainException(
                        'Only administrators can create staff users.'
                    );
                }

                $normalizedName = trim($name);

                if ($normalizedName === '') {
                    throw new DomainException(
                        'The staff user name is required.'
                    );
                }

                if (
                    mb_strlen(
                        $normalizedName
                    ) > 255
                ) {
                    throw new DomainException(
                        'The staff user name may not exceed 255 characters.'
                    );
                }

                $normalizedEmail = strtolower(
                    trim($email)
                );

                if (
                    $normalizedEmail === ''
                    || ! filter_var(
                        $normalizedEmail,
                        FILTER_VALIDATE_EMAIL
                    )
                ) {
                    throw new DomainException(
                        'The staff user email must be valid.'
                    );
                }

                $normalizedRoleName = strtoupper(
                    trim($roleName)
                );

                if (! in_array(
                    $normalizedRoleName,
                    self::ALLOWED_ROLES,
                    true
                )) {
                    throw new DomainException(
                        'Only support-agent and administrator accounts can be created through this endpoint.'
                    );
                }

                $role = Role::query()
                    ->where(
                        'role_name',
                        $normalizedRoleName
                    )
                    ->first();

                if ($role === null) {
                    throw new DomainException(
                        'The selected staff role does not exist.'
                    );
                }

                $normalizedComment = trim(
                    $comment
                );

                if ($normalizedComment === '') {
                    throw new DomainException(
                        'A comment is required to create a staff user.'
                    );
                }

                if (
                    mb_strlen(
                        $normalizedComment
                    ) > 500
                ) {
                    throw new DomainException(
                        'The staff creation comment may not exceed 500 characters.'
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

                /*
                 * La contraseña aleatoria nunca se devuelve.
                 * El usuario establecerá la suya utilizando
                 * el enlace de recuperación.
                 */
                $staffUser = User::query()->create([
                    'name' => $normalizedName,
                    'email' => $normalizedEmail,
                    'password' => Str::random(64),
                    'role_id' => $role->id,
                    'account_status_id' =>
                        $activeStatus->id,
                ]);

                $createdAt = now();

                AuditLog::query()->create([
                    'performed_by_user_id' =>
                        $lockedPerformedBy->id,
                    'table_name' => 'users',
                    'record_id' =>
                        $staffUser->id,
                    'action_type' =>
                        'STAFF_USER_CREATED',
                    'details' => [
                        'name' =>
                            $staffUser->name,
                        'email' =>
                            $staffUser->email,
                        'role' =>
                            $role->role_name,
                        'account_status' =>
                            $activeStatus
                                ->status_name,
                        'comment' =>
                            $normalizedComment,
                    ],
                    'performed_at' =>
                        $createdAt,
                ]);

                return $staffUser->load([
                    'role',
                    'accountStatus',
                ]);
            }, attempts: 3);
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            throw new DomainException(
                'The email has already been registered.',
                0,
                $exception
            );
        }
    }
}