<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Impide operaciones administrativas cuando
     * la cuenta del actor no está activa.
     */
    public function before(
        User $user,
        string $ability
    ): ?bool {
        $activeAccount = $user->accountStatus()
            ->where('status_name', 'ACTIVE')
            ->exists();

        if (! $activeAccount) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ]);
    }

    public function view(
        User $user,
        User $targetUser
    ): bool {
        return $this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ]);
    }

    /**
     * El registro público utiliza su propio flujo.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Solamente un administrador activo puede
     * crear cuentas internas.
     */
    public function createStaff(User $user): bool
    {
        return $this->hasRole(
            $user,
            'ADMINISTRATOR'
        );
    }

    public function update(
        User $user,
        User $targetUser
    ): bool {
        return false;
    }

    public function changeAccountStatus(
        User $user,
        User $targetUser
    ): bool {
        return $this->hasRole(
            $user,
            'ADMINISTRATOR'
        ) && (int) $user->getKey()
            !== (int) $targetUser->getKey();
    }

    public function changeRole(
        User $user,
        User $targetUser
    ): bool {
        return $this->hasRole(
            $user,
            'ADMINISTRATOR'
        ) && (int) $user->getKey()
            !== (int) $targetUser->getKey();
    }

    public function delete(
        User $user,
        User $targetUser
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        User $targetUser
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        User $targetUser
    ): bool {
        return false;
    }

    private function hasRole(
        User $user,
        string $roleName
    ): bool {
        return $user->role()
            ->where(
                'role_name',
                $roleName
            )
            ->exists();
    }

    /**
     * @param array<int, string> $roleNames
     */
    private function hasAnyRole(
        User $user,
        array $roleNames
    ): bool {
        return $user->role()
            ->whereIn(
                'role_name',
                $roleNames
            )
            ->exists();
    }
}