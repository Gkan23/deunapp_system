<?php

namespace App\Services\User;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateUserRoleService
{
    private const STAFF_ROLES = [
        'SUPPORT_AGENT',
        'ADMINISTRATOR',
    ];

    private const OPERATIONAL_ROLES = [
        'CUSTOMER',
        'DELIVERY_PROVIDER',
        'COURIER',
    ];

    /**
     * Cambia el rol de una cuenta.
     *
     * @throws DomainException
     */
    public function execute(
        User $targetUser,
        User $performedBy,
        string $targetRoleName,
        string $comment
    ): User {
        return DB::transaction(function () use (
            $targetUser,
            $performedBy,
            $targetRoleName,
            $comment
        ): User {
            $lockedTargetUser = User::query()
                ->with([
                    'role',
                    'customer',
                    'deliveryProvider',
                    'courier',
                ])
                ->whereKey($targetUser->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPerformedBy = User::query()
                ->with('role')
                ->whereKey($performedBy->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $activeStatus = AccountStatus::query()
                ->where('status_name', 'ACTIVE')
                ->firstOrFail();

            if (
                (int) $lockedPerformedBy
                    ->account_status_id
                !== (int) $activeStatus->id
            ) {
                throw new DomainException(
                    'Only an active administrator can change user roles.'
                );
            }

            if (
                $lockedPerformedBy->role?->role_name
                !== 'ADMINISTRATOR'
            ) {
                throw new DomainException(
                    'Only administrators can change user roles.'
                );
            }

            if (
                (int) $lockedTargetUser->id
                === (int) $lockedPerformedBy->id
            ) {
                throw new DomainException(
                    'Administrators cannot change their own role.'
                );
            }

            $normalizedRoleName = strtoupper(
                trim($targetRoleName)
            );

            $targetRole = Role::query()
                ->where(
                    'role_name',
                    $normalizedRoleName
                )
                ->first();

            if ($targetRole === null) {
                throw new DomainException(
                    'The selected user role does not exist.'
                );
            }

            $currentRole = $lockedTargetUser->role;

            if ($currentRole === null) {
                throw new DomainException(
                    'The user does not have a valid current role.'
                );
            }

            if (
                $currentRole->role_name
                === $targetRole->role_name
            ) {
                throw new DomainException(
                    'The user already has the requested role.'
                );
            }

            $normalizedComment = trim($comment);

            if ($normalizedComment === '') {
                throw new DomainException(
                    'A comment is required to change a user role.'
                );
            }

            if (mb_strlen($normalizedComment) > 500) {
                throw new DomainException(
                    'The user role comment may not exceed 500 characters.'
                );
            }

            $this->validateProfileCompatibility(
                $lockedTargetUser,
                $targetRole->role_name
            );

            $lockedTargetUser->forceFill([
                'role_id' => $targetRole->id,
                'remember_token' => Str::random(60),
            ])->save();

            $revokedSessionCount = DB::table(
                'sessions'
            )
                ->where(
                    'user_id',
                    $lockedTargetUser->id
                )
                ->delete();

            $changedAt = now();

            AuditLog::query()->create([
                'performed_by_user_id' =>
                    $lockedPerformedBy->id,
                'table_name' => 'users',
                'record_id' =>
                    $lockedTargetUser->id,
                'action_type' =>
                    'USER_ROLE_CHANGED',
                'details' => [
                    'from_role' =>
                        $currentRole->role_name,
                    'to_role' =>
                        $targetRole->role_name,
                    'comment' =>
                        $normalizedComment,
                    'revoked_session_count' =>
                        $revokedSessionCount,
                ],
                'performed_at' => $changedAt,
            ]);

            return $lockedTargetUser->fresh([
                'role',
                'accountStatus',
                'customer.customerType',
                'deliveryProvider.providerType',
                'courier.deliveryProvider.providerType',
            ]);
        }, attempts: 3);
    }

    /**
     * @throws DomainException
     */
    private function validateProfileCompatibility(
        User $user,
        string $targetRoleName
    ): void {
        $profiles = [
            'CUSTOMER' =>
                $user->customer !== null,
            'DELIVERY_PROVIDER' =>
                $user->deliveryProvider !== null,
            'COURIER' =>
                $user->courier !== null,
        ];

        if (
            in_array(
                $targetRoleName,
                self::STAFF_ROLES,
                true
            )
        ) {
            if (in_array(true, $profiles, true)) {
                throw new DomainException(
                    'A user with an operational profile cannot be assigned a staff role.'
                );
            }

            return;
        }

        if (! in_array(
            $targetRoleName,
            self::OPERATIONAL_ROLES,
            true
        )) {
            throw new DomainException(
                'The selected user role is not supported.'
            );
        }

        if (! $profiles[$targetRoleName]) {
            throw new DomainException(
                sprintf(
                    'The user must have a %s profile before receiving this role.',
                    $targetRoleName
                )
            );
        }

        foreach (
            $profiles as $profileRole => $exists
        ) {
            if (
                $profileRole !== $targetRoleName
                && $exists
            ) {
                throw new DomainException(
                    'The user has a profile that is incompatible with the selected role.'
                );
            }
        }
    }
}