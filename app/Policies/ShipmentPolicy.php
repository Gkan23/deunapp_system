<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    /**
     * Bloquea todas las acciones si la cuenta
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

    public function viewAny(User $user): bool
    {
        return $this->hasAnyRole($user, [
            'CUSTOMER',
            'DELIVERY_PROVIDER',
            'COURIER',
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ]);
    }

    public function view(
        User $user,
        Shipment $shipment
    ): bool {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        if (
            $this->hasRole($user, 'CUSTOMER')
            && $this->ownsShipment($user, $shipment)
        ) {
            return true;
        }

        if (
            $this->hasRole(
                $user,
                'DELIVERY_PROVIDER'
            )
            && $this->belongsToProvider(
                $user,
                $shipment
            )
        ) {
            return true;
        }

        return $this->hasRole($user, 'COURIER')
            && $this->isAssignedCourier(
                $user,
                $shipment
            );
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user, 'CUSTOMER')
            && $user->customer()->exists();
    }

    /**
     * Las modificaciones deben pasar por
     * servicios específicos.
     */
    public function update(
        User $user,
        Shipment $shipment
    ): bool {
        return false;
    }

    public function cancel(
        User $user,
        Shipment $shipment
    ): bool {
        if (
            $this->hasRole(
                $user,
                'ADMINISTRATOR'
            )
        ) {
            return true;
        }

        return $this->hasRole($user, 'CUSTOMER')
            && $this->ownsShipment(
                $user,
                $shipment
            );
    }

    public function updateStatus(
        User $user,
        Shipment $shipment
    ): bool {
        if (
            $this->hasRole(
                $user,
                'ADMINISTRATOR'
            )
        ) {
            return true;
        }

        if (
            $this->hasRole(
                $user,
                'DELIVERY_PROVIDER'
            )
            && $this->belongsToProvider(
                $user,
                $shipment
            )
        ) {
            return true;
        }

        return $this->hasRole($user, 'COURIER')
            && $this->isAssignedCourier(
                $user,
                $shipment
            );
    }

    public function reportIncident(
        User $user,
        Shipment $shipment
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
            && $this->belongsToProvider(
                $user,
                $shipment
            )
        ) {
            return true;
        }

        return $this->hasRole($user, 'COURIER')
            && $this->isAssignedCourier(
                $user,
                $shipment
            );
    }

    /**
     * Solo el repartidor asignado puede reportar
     * un intento de entrega fallido.
     *
     * El servicio de dominio también comprueba esta
     * condición dentro de la transacción.
     */
    public function failDeliveryAttempt(
        User $user,
        Shipment $shipment
    ): bool {
        return $this->hasRole($user, 'COURIER')
            && $this->isAssignedCourier(
                $user,
                $shipment
            );
    }

    public function recordDeliveryProof(
        User $user,
        Shipment $shipment
    ): bool {
        if (
            $this->hasRole(
                $user,
                'ADMINISTRATOR'
            )
        ) {
            return true;
        }

        return $this->hasRole($user, 'COURIER')
            && $this->isAssignedCourier(
                $user,
                $shipment
            );
    }

    /**
     * Los envíos conservan su trazabilidad.
     */
    public function delete(
        User $user,
        Shipment $shipment
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        Shipment $shipment
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        Shipment $shipment
    ): bool {
        return false;
    }

    private function ownsShipment(
        User $user,
        Shipment $shipment
    ): bool {
        return $shipment->customer()
            ->where('user_id', $user->id)
            ->exists();
    }

    private function belongsToProvider(
        User $user,
        Shipment $shipment
    ): bool {
        return $shipment->deliveryService()
            ->whereHas(
                'trip.deliveryProvider',
                fn ($query) => $query->where(
                    'user_id',
                    $user->id
                )
            )
            ->exists();
    }

    private function isAssignedCourier(
        User $user,
        Shipment $shipment
    ): bool {
        return $shipment->routeShipments()
            ->whereHas(
                'route.courier',
                fn ($query) => $query->where(
                    'user_id',
                    $user->id
                )
            )
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
