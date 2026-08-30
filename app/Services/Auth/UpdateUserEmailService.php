<?php

namespace App\Services\Auth;

use App\Models\AuditLog;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class UpdateUserEmailService
{
    /**
     * Cambia el correo y elimina su verificación anterior.
     *
     * @throws DomainException
     */
    public function execute(
        User $user,
        string $newEmail
    ): User {
        try {
            return DB::transaction(function () use (
                $user,
                $newEmail
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
                        'Only an active account can change its email address.'
                    );
                }

                $normalizedEmail = strtolower(
                    trim($newEmail)
                );

                if (
                    strtolower($lockedUser->email)
                    === $normalizedEmail
                ) {
                    throw new DomainException(
                        'The new email must be different from the current email.'
                    );
                }

                $previousEmail = $lockedUser->email;
                $changedAt = now();

                /*
                 * email_verified_at no está en $fillable,
                 * por eso se utiliza forceFill.
                 */
                $lockedUser->forceFill([
                    'email' => $normalizedEmail,
                    'email_verified_at' => null,
                    'remember_token' => Str::random(60),
                ])->save();

                AuditLog::query()->create([
                    'performed_by_user_id' =>
                        $lockedUser->id,
                    'table_name' => 'users',
                    'record_id' => $lockedUser->id,
                    'action_type' => 'EMAIL_CHANGED',
                    'details' => [
                        'previous_email' =>
                            $previousEmail,
                        'new_email' =>
                            $normalizedEmail,
                    ],
                    'performed_at' => $changedAt,
                ]);

                return $lockedUser->fresh([
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