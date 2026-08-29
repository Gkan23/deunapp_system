<?php

namespace App\Policies;

use App\Models\TripTransaction;
use App\Models\User;

class TripTransactionPolicy
{
    public function before(
        User $user,
        string $ability
    ): ?bool {
        $isActive = $user->accountStatus()
            ->where('status_name', 'ACTIVE')
            ->exists();

        if (! $isActive) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        return $this->isActiveProvider($user);
    }

    public function view(
        User $user,
        TripTransaction $tripTransaction
    ): bool {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        return $this->isActiveProvider($user)
            && $this->ownsTransaction(
                $user,
                $tripTransaction
            );
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(
        User $user,
        TripTransaction $tripTransaction
    ): bool {
        return false;
    }

    public function delete(
        User $user,
        TripTransaction $tripTransaction
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        TripTransaction $tripTransaction
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        TripTransaction $tripTransaction
    ): bool {
        return false;
    }

    private function ownsTransaction(
        User $user,
        TripTransaction $tripTransaction
    ): bool {
        return $tripTransaction->deliveryProvider()
            ->where('user_id', $user->id)
            ->exists();
    }

    private function isActiveProvider(
        User $user
    ): bool {
        return $this->hasRole(
            $user,
            'DELIVERY_PROVIDER'
        ) && $user->deliveryProvider()
            ->where('is_active', true)
            ->exists();
    }

    private function hasRole(
        User $user,
        string $role
    ): bool {
        return $user->role()
            ->where('role_name', $role)
            ->exists();
    }

    /**
     * @param array<int, string> $roles
     */
    private function hasAnyRole(
        User $user,
        array $roles
    ): bool {
        return $user->role()
            ->whereIn('role_name', $roles)
            ->exists();
    }
}
