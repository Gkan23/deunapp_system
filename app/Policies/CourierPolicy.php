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

        return $this->ownsCourier(
            $user,
            $courier
        );
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
     * Solamente el proveedor propietario puede
     * activar o desactivar al repartidor.
     */
    public function changeStatus(
        User $user,
        Courier $courier
    ): bool {
        return $user->hasVerifiedEmail()
            && $this->ownsCourier(
                $user,
                $courier
            );
    }

    /**
     * Solamente el repartidor propietario puede
     * cambiar su propia disponibilidad.
     */
    public function changeAvailability(
        User $user,
        Courier $courier
    ): bool {
        return $this->canOperateCourier(
            $user,
            $courier
        );
    }

    /**
     * Solamente el repartidor propietario puede
     * registrar su propia ubicación.
     */
    public function recordLocation(
        User $user,
        Courier $courier
    ): bool {
        return $this->canOperateCourier(
            $user,
            $courier
        );
    }

    /**
     * Las actualizaciones generales deben utilizar
     * acciones específicas del dominio.
     */
    public function update(
        User $user,
        Courier $courier
    ): bool {
        return false;
    }

    /**
     * Los repartidores conservan su trazabilidad.
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

    /**
     * Comprueba que el usuario pueda realizar
     * operaciones sobre su propio perfil de repartidor.
     */
    private function canOperateCourier(
        User $user,
        Courier $courier
    ): bool {
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        if (! $this->hasRole(
            $user,
            'COURIER'
        )) {
            return false;
        }

        if (
            (int) $courier->user_id
            !== (int) $user->id
        ) {
            return false;
        }

        if (! $courier->is_active) {
            return false;
        }

        return $courier->deliveryProvider()
            ->where('is_active', true)
            ->exists();
    }

    private function ownsCourier(
        User $user,
        Courier $courier
    ): bool {
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