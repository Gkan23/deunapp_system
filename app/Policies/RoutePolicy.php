<?php

namespace App\Policies;

use App\Models\Route;
use App\Models\User;

class RoutePolicy
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
            'DELIVERY_PROVIDER',
            'COURIER',
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ]);
    }

    public function view(User $user, Route $route): bool
    {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        if (
            $this->hasRole($user, 'DELIVERY_PROVIDER')
            && $this->belongsToProvider($user, $route)
        ) {
            return true;
        }

        return $this->hasRole($user, 'COURIER')
            && $this->isAssignedCourier($user, $route);
    }

    public function create(User $user): bool
    {
        if ($this->hasRole($user, 'ADMINISTRATOR')) {
            return true;
        }

        return $this->hasRole($user, 'DELIVERY_PROVIDER')
            && $user->deliveryProvider()
                ->where('is_active', true)
                ->exists();
    }

    /**
     * Las modificaciones deben pasar por acciones específicas.
     */
    public function update(User $user, Route $route): bool
    {
        return false;
    }

    public function addShipment(User $user, Route $route): bool
    {
        if ($this->hasRole($user, 'ADMINISTRATOR')) {
            return true;
        }

        return $this->hasRole($user, 'DELIVERY_PROVIDER')
            && $this->belongsToProvider($user, $route);
    }

    public function activate(User $user, Route $route): bool
    {
        if ($this->hasRole($user, 'ADMINISTRATOR')) {
            return true;
        }

        if (
            $this->hasRole($user, 'DELIVERY_PROVIDER')
            && $this->belongsToProvider($user, $route)
        ) {
            return true;
        }

        return $this->hasRole($user, 'COURIER')
            && $this->isAssignedCourier($user, $route);
    }

    public function complete(User $user, Route $route): bool
    {
        if ($this->hasRole($user, 'ADMINISTRATOR')) {
            return true;
        }

        if (
            $this->hasRole($user, 'DELIVERY_PROVIDER')
            && $this->belongsToProvider($user, $route)
        ) {
            return true;
        }

        return $this->hasRole($user, 'COURIER')
            && $this->isAssignedCourier($user, $route);
    }

    public function cancel(User $user, Route $route): bool
    {
        if ($this->hasRole($user, 'ADMINISTRATOR')) {
            return true;
        }

        return $this->hasRole($user, 'DELIVERY_PROVIDER')
            && $this->belongsToProvider($user, $route);
    }

    /**
     * Las rutas mantienen trazabilidad y no se eliminan directamente.
     */
    public function delete(User $user, Route $route): bool
    {
        return false;
    }

    public function restore(User $user, Route $route): bool
    {
        return false;
    }

    public function forceDelete(User $user, Route $route): bool
    {
        return false;
    }

    private function belongsToProvider(
        User $user,
        Route $route
    ): bool {
        return $route->courier()
            ->whereHas(
                'deliveryProvider',
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->exists();
    }

    private function isAssignedCourier(
        User $user,
        Route $route
    ): bool {
        return $route->courier()
            ->where('user_id', $user->id)
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
