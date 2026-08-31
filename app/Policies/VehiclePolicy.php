<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    /**
     * Bloquea las operaciones si la cuenta
     * del usuario no está activa.
     */
    public function before(
        User $user,
        string $ability
    ): ?bool {
        $hasActiveAccount = $user
            ->accountStatus()
            ->where(
                'status_name',
                'ACTIVE'
            )
            ->exists();

        if (! $hasActiveAccount) {
            return false;
        }

        return null;
    }

    /**
     * Proveedores, repartidores, soporte y
     * administración pueden consultar vehículos.
     */
    public function viewAny(User $user): bool
    {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        if ($this->hasRole(
            $user,
            'DELIVERY_PROVIDER'
        )) {
            return $user
                ->deliveryProvider()
                ->where('is_active', true)
                ->exists();
        }

        if ($this->hasRole(
            $user,
            'COURIER'
        )) {
            return $user
                ->courier()
                ->where('is_active', true)
                ->whereHas(
                    'deliveryProvider',
                    fn ($query) => $query
                        ->where(
                            'is_active',
                            true
                        )
                )
                ->exists();
        }

        return false;
    }

    /**
     * Consulta de un vehículo específico.
     */
    public function view(
        User $user,
        Vehicle $vehicle
    ): bool {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        if (
            $this->hasRole(
                $user,
                'DELIVERY_PROVIDER'
            )
        ) {
            return $vehicle
                ->courier()
                ->whereHas(
                    'deliveryProvider',
                    fn ($query) => $query
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->where(
                            'is_active',
                            true
                        )
                )
                ->exists();
        }

        if ($this->hasRole(
            $user,
            'COURIER'
        )) {
            return $vehicle
                ->courier()
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'is_active',
                    true
                )
                ->whereHas(
                    'deliveryProvider',
                    fn ($query) => $query
                        ->where(
                            'is_active',
                            true
                        )
                )
                ->exists();
        }

        return false;
    }

    /**
     * Solo un proveedor activo y verificado
     * puede registrar vehículos.
     */
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $this->hasRole(
                $user,
                'DELIVERY_PROVIDER'
            )
            && $user
                ->deliveryProvider()
                ->where(
                    'is_active',
                    true
                )
                ->exists();
    }

    public function update(
        User $user,
        Vehicle $vehicle
    ): bool {
        return false;
    }

    public function delete(
        User $user,
        Vehicle $vehicle
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        Vehicle $vehicle
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        Vehicle $vehicle
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