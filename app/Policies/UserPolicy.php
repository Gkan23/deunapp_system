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

    /**
     * Administración y soporte pueden acceder
     * al listado de cuentas.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ]);
    }

    /**
     * Administración y soporte pueden consultar
     * los datos no confidenciales de una cuenta.
     */
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
     * Las cuentas públicas se crean mediante
     * el flujo específico de registro.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * No se permiten modificaciones genéricas.
     */
    public function update(
        User $user,
        User $targetUser
    ): bool {
        return false;
    }

    /**
     * Solamente administración puede cambiar
     * el estado de otra cuenta.
     */
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

    /**
     * Solamente administración puede cambiar
     * el rol de otra cuenta.
     */
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

    /**
     * Las cuentas conservan su trazabilidad
     * y no se eliminan directamente.
     */
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