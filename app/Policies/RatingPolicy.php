<?php

namespace App\Policies;

use App\Models\Rating;
use App\Models\User;

class RatingPolicy
{
    /**
     * Bloquea todas las acciones si la cuenta no está activa.
     */
    public function before(User $user, string $ability): ?bool
    {
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
        return $this->hasAnyRole($user, [
            'CUSTOMER',
            'DELIVERY_PROVIDER',
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ]);
    }

    public function view(User $user, Rating $rating): bool
    {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        if (
            $this->hasRole($user, 'CUSTOMER')
            && $this->ownsRating($user, $rating)
        ) {
            return true;
        }

        return $this->hasRole($user, 'DELIVERY_PROVIDER')
            && $this->belongsToProvider($user, $rating);
    }

    /**
     * La validación del servicio específico se realiza
     * dentro de CreateRatingService.
     */
    public function create(User $user): bool
    {
        return $this->hasRole($user, 'CUSTOMER')
            && $user->customer()->exists();
    }

    /**
     * Las evaluaciones son inmutables.
     */
    public function update(User $user, Rating $rating): bool
    {
        return false;
    }

    public function delete(User $user, Rating $rating): bool
    {
        return false;
    }

    public function restore(User $user, Rating $rating): bool
    {
        return false;
    }

    public function forceDelete(User $user, Rating $rating): bool
    {
        return false;
    }

    private function ownsRating(User $user, Rating $rating): bool
    {
        return $rating->customer()
            ->where('user_id', $user->id)
            ->exists();
    }

    private function belongsToProvider(
        User $user,
        Rating $rating
    ): bool {
        return $rating->deliveryService()
            ->whereHas(
                'trip.deliveryProvider',
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->exists();
    }

    private function hasRole(User $user, string $role): bool
    {
        return $user->role()
            ->where('role_name', $role)
            ->exists();
    }

    /**
     * @param array<int, string> $roles
     */
    private function hasAnyRole(User $user, array $roles): bool
    {
        return $user->role()
            ->whereIn('role_name', $roles)
            ->exists();
    }
}

