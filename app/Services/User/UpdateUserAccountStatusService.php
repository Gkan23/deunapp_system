<?php

namespace App\Services\User;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateUserAccountStatusService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'PENDING' => [
            'ACTIVE',
            'BLOCKED',
        ],
        'ACTIVE' => [
            'SUSPENDED',
            'BLOCKED',
        ],
        'SUSPENDED' => [
            'ACTIVE',
            'BLOCKED',
        ],
        'BLOCKED' => [
            'ACTIVE',
        ],
    ];

    private const COMMENT_REQUIRED_STATUSES = [
        'SUSPENDED',
        'BLOCKED',
    ];

    private const SESSION_REVOCATION_STATUSES = [
        'SUSPENDED',
        'BLOCKED',
    ];

    /**
     * Cambia el estado de la cuenta de un usuario.
     *
     * @throws DomainException
     */
    public function execute(
        User $targetUser,
        User $performedBy,
        string $targetStatusName,
        ?string $comment = null
    ): User {
        return DB::transaction(function () use (
            $targetUser,
            $performedBy,
            $targetStatusName,
            $comment
        ): User {
            $lockedTargetUser = User::query()
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
                    'Only an active administrator can change account statuses.'
                );
            }

            if (
                $lockedPerformedBy->role?->role_name
                !== 'ADMINISTRATOR'
            ) {
                throw new DomainException(
                    'Only administrators can change account statuses.'
                );
            }

            if (
                (int) $lockedTargetUser->id
                === (int) $lockedPerformedBy->id
            ) {
                throw new DomainException(
                    'Administrators cannot change their own account status.'
                );
            }

            $currentStatus = AccountStatus::query()
                ->whereKey(
                    $lockedTargetUser
                        ->account_status_id
                )
                ->firstOrFail();

            $normalizedTargetStatus = strtoupper(
                trim($targetStatusName)
            );

            $targetStatus = AccountStatus::query()
                ->where(
                    'status_name',
                    $normalizedTargetStatus
                )
                ->first();

            if ($targetStatus === null) {
                throw new DomainException(
                    'The selected account status does not exist.'
                );
            }

            if (
                $currentStatus->status_name
                === $targetStatus->status_name
            ) {
                throw new DomainException(
                    'The user account is already in the requested status.'
                );
            }

            $allowedTargets = self::ALLOWED_TRANSITIONS[
                $currentStatus->status_name
            ] ?? [];

            if (! in_array(
                $targetStatus->status_name,
                $allowedTargets,
                true
            )) {
                throw new DomainException(
                    sprintf(
                        'The account status transition from %s to %s is not allowed.',
                        $currentStatus->status_name,
                        $targetStatus->status_name
                    )
                );
            }

            $normalizedComment = $comment === null
                ? null
                : trim($comment);

            if ($normalizedComment === '') {
                $normalizedComment = null;
            }

            if (
                in_array(
                    $targetStatus->status_name,
                    self::COMMENT_REQUIRED_STATUSES,
                    true
                )
                && $normalizedComment === null
            ) {
                throw new DomainException(
                    'A comment is required to suspend or block an account.'
                );
            }

            if (
                $normalizedComment !== null
                && mb_strlen(
                    $normalizedComment
                ) > 500
            ) {
                throw new DomainException(
                    'The account status comment may not exceed 500 characters.'
                );
            }

            $mustRevokeSessions = in_array(
                $targetStatus->status_name,
                self::SESSION_REVOCATION_STATUSES,
                true
            );

            $attributes = [
                'account_status_id' =>
                    $targetStatus->id,
            ];

            if ($mustRevokeSessions) {
                $attributes['remember_token'] =
                    Str::random(60);
            }

            /*
             * forceFill permite actualizar remember_token,
             * el cual no forma parte de $fillable.
             */
            $lockedTargetUser
                ->forceFill($attributes)
                ->save();

            $revokedSessionCount = 0;

            if ($mustRevokeSessions) {
                $revokedSessionCount = DB::table(
                    'sessions'
                )
                    ->where(
                        'user_id',
                        $lockedTargetUser->id
                    )
                    ->delete();
            }

            $changedAt = now();

            AuditLog::query()->create([
                'performed_by_user_id' =>
                    $lockedPerformedBy->id,
                'table_name' => 'users',
                'record_id' =>
                    $lockedTargetUser->id,
                'action_type' =>
                    'ACCOUNT_STATUS_CHANGED',
                'details' => [
                    'from_status' =>
                        $currentStatus->status_name,
                    'to_status' =>
                        $targetStatus->status_name,
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
}