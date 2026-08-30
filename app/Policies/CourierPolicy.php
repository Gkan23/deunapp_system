<?php

namespace App\Policies;

use App\Models\Courier;
use App\Models\User;

class CourierPolicy
{
    /**
     * Impide cualquier operación cuando la cuenta
     * del usuario no se encuentra activa.
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
     * Pueden consultar el listado:
     *
     * - Proveedores activos.
     * - Soporte.
     * - Administradores.
     */
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

    /**
     * Autoriza la consulta de un repartidor.
     */
    public function view(
        User $user,
        Courier $courier
    ): bool {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        if (
            $this->hasRole($user, 'COURIER')
            && (int) $courier->user_id
                === (int) $user->id
        ) {
            return true;
        }

        if (! $this->isActiveProvider($user)) {
            return false;
        }

        $providerId = $user
            ->deliveryProvider()
            ->value('id');

        return $providerId !== null
            && (int) $courier
                ->delivery_provider_id
                === (int) $providerId;
    }

    /**
     * Solamente un proveedor activo y verificado
     * puede crear repartidores.
     */
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $this->isActiveProvider($user);
    }

    /**
     * Las actualizaciones generales están
     * deshabilitadas. Deben utilizarse acciones
     * específicas del dominio.
     */
    public function update(
        User $user,
        Courier $courier
    ): bool {
        return false;
    }

    /**
     * Los repartidores conservan su trazabilidad
     * y no deben eliminarse directamente.
     */
    public function delete(
        User $user,
        Courier $courier
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        Courier $courier
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        Courier $courier
    ): bool {
        return false;
    }

    private function isActiveProvider(
        User $user
    ): bool {
        if (! $this->hasRole(
            $user,
            'DELIVERY_PROVIDER'
        )) {
            return false;
        }

        return $user->deliveryProvider()
            ->where('is_active', true)
            ->exists();
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