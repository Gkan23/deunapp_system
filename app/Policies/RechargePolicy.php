<?php

namespace App\Policies;

use App\Models\Recharge;
use App\Models\User;

class RechargePolicy
{
    /**
     * Bloquea todas las acciones cuando la cuenta
     * del usuario no está activa.
     */
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

    /**
     * Proveedores, soporte y administración pueden
     * acceder al listado correspondiente a su contexto.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasAnyRole($user, [
            'DELIVERY_PROVIDER',
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ]);
    }

    /**
     * Soporte y administración pueden consultar cualquier
     * recarga. Un proveedor solamente puede consultar
     * sus propias recargas.
     */
    public function view(
        User $user,
        Recharge $recharge
    ): bool {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        return $this->hasRole(
            $user,
            'DELIVERY_PROVIDER'
        ) && $this->ownsRecharge(
            $user,
            $recharge
        );
    }

    /**
     * Solamente un proveedor activo con un perfil
     * asociado puede confirmar una recarga.
     */
    public function create(User $user): bool
    {
        return $this->hasRole(
            $user,
            'DELIVERY_PROVIDER'
        ) && $user->deliveryProvider()
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Las recargas no se modifican directamente.
     */
    public function update(
        User $user,
        Recharge $recharge
    ): bool {
        return false;
    }

    /**
     * Las recargas forman parte del historial financiero
     * y no deben eliminarse.
     */
    public function delete(
        User $user,
        Recharge $recharge
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        Recharge $recharge
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        Recharge $recharge
    ): bool {
        return false;
    }

    private function ownsRecharge(
        User $user,
        Recharge $recharge
    ): bool {
        return $recharge->deliveryProvider()
            ->where('user_id', $user->id)
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