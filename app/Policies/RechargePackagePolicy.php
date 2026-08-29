<?php

namespace App\Policies;

use App\Models\RechargePackage;
use App\Models\User;

class RechargePackagePolicy
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
     * Un proveedor activo, soporte o administración
     * pueden acceder al catálogo.
     */
    public function viewAny(User $user): bool
    {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        return $this->hasRole(
            $user,
            'DELIVERY_PROVIDER'
        ) && $user->deliveryProvider()
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Soporte y administración pueden consultar cualquier
     * paquete. El proveedor solamente puede consultar
     * paquetes disponibles actualmente.
     */
    public function view(
        User $user,
        RechargePackage $rechargePackage
    ): bool {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        if (
            ! $this->hasRole(
                $user,
                'DELIVERY_PROVIDER'
            )
            || ! $user->deliveryProvider()
                ->where('is_active', true)
                ->exists()
        ) {
            return false;
        }

        return $this->isCurrentlyAvailable(
            $rechargePackage
        );
    }

    /**
     * El catálogo se administra internamente.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(
        User $user,
        RechargePackage $rechargePackage
    ): bool {
        return false;
    }

    public function delete(
        User $user,
        RechargePackage $rechargePackage
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        RechargePackage $rechargePackage
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        RechargePackage $rechargePackage
    ): bool {
        return false;
    }

    private function isCurrentlyAvailable(
        RechargePackage $rechargePackage
    ): bool {
        if (! $rechargePackage->is_active) {
            return false;
        }

        return $rechargePackage->commissionRule()
            ->where('is_active', true)
            ->whereDate(
                'valid_from',
                '<=',
                today()
            )
            ->where(function ($query): void {
                $query
                    ->whereNull('valid_until')
                    ->orWhereDate(
                        'valid_until',
                        '>=',
                        today()
                    );
            })
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