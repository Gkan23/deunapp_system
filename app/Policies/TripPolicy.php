<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

class TripPolicy
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
        Trip $trip
    ): bool {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        return $this->isActiveProvider($user)
            && $this->ownsTrip($user, $trip);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(
        User $user,
        Trip $trip
    ): bool {
        return false;
    }

    public function delete(
        User $user,
        Trip $trip
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        Trip $trip
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        Trip $trip
    ): bool {
        return false;
    }

    private function ownsTrip(
        User $user,
        Trip $trip
    ): bool {
        return $trip->deliveryProvider()
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