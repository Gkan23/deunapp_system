<?php

namespace App\Policies;

use App\Models\DeliveryService;
use App\Models\User;

class DeliveryServicePolicy
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
            'COURIER',
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ]);
    }

    public function view(
        User $user,
        DeliveryService $deliveryService
    ): bool {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        if (
            $this->hasRole($user, 'CUSTOMER')
            && $this->ownsService($user, $deliveryService)
        ) {
            return true;
        }

        if (
            $this->hasRole($user, 'DELIVERY_PROVIDER')
            && $this->belongsToProvider($user, $deliveryService)
        ) {
            return true;
        }

        return $this->hasRole($user, 'COURIER')
            && $this->isAssignedCourier($user, $deliveryService);
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user, 'CUSTOMER')
            && $user->customer()->exists();
    }

    /**
     * Las modificaciones deben pasar por acciones específicas.
     */
    public function update(
        User $user,
        DeliveryService $deliveryService
    ): bool {
        return false;
    }

    /**
     * Un proveedor activo puede intentar aceptar y asignar
     * un viaje disponible al servicio.
     */
    public function assign(
        User $user,
        DeliveryService $deliveryService
    ): bool {
        if ($this->hasRole($user, 'ADMINISTRATOR')) {
            return true;
        }

        return $this->hasRole($user, 'DELIVERY_PROVIDER')
            && $user->deliveryProvider()
                ->where('is_active', true)
                ->exists();
    }

    public function start(
        User $user,
        DeliveryService $deliveryService
    ): bool {
        if ($this->hasRole($user, 'ADMINISTRATOR')) {
            return true;
        }

        if (
            $this->hasRole($user, 'DELIVERY_PROVIDER')
            && $this->belongsToProvider($user, $deliveryService)
        ) {
            return true;
        }

        return $this->hasRole($user, 'COURIER')
            && $this->isAssignedCourier($user, $deliveryService);
    }

    public function complete(
        User $user,
        DeliveryService $deliveryService
    ): bool {
        if ($this->hasRole($user, 'ADMINISTRATOR')) {
            return true;
        }

        if (
            $this->hasRole($user, 'DELIVERY_PROVIDER')
            && $this->belongsToProvider($user, $deliveryService)
        ) {
            return true;
        }

        return $this->hasRole($user, 'COURIER')
            && $this->isAssignedCourier($user, $deliveryService);
    }

    public function cancel(
        User $user,
        DeliveryService $deliveryService
    ): bool {
        if ($this->hasRole($user, 'ADMINISTRATOR')) {
            return true;
        }

        if (
            $this->hasRole($user, 'CUSTOMER')
            && $this->ownsService($user, $deliveryService)
        ) {
            return true;
        }

        return $this->hasRole($user, 'DELIVERY_PROVIDER')
            && $this->belongsToProvider($user, $deliveryService);
    }

    public function confirmPayment(
        User $user,
        DeliveryService $deliveryService
    ): bool {
        if ($this->hasRole($user, 'ADMINISTRATOR')) {
            return true;
        }

        return $this->hasRole($user, 'CUSTOMER')
            && $this->ownsService($user, $deliveryService);
    }

    public function rate(
        User $user,
        DeliveryService $deliveryService
    ): bool {
        return $this->hasRole($user, 'CUSTOMER')
            && $this->ownsService($user, $deliveryService);
    }

    /**
     * Los servicios conservan trazabilidad y no se eliminan.
     */
    public function delete(
        User $user,
        DeliveryService $deliveryService
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        DeliveryService $deliveryService
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        DeliveryService $deliveryService
    ): bool {
        return false;
    }

    private function ownsService(
        User $user,
        DeliveryService $deliveryService
    ): bool {
        return $deliveryService->customer()
            ->where('user_id', $user->id)
            ->exists();
    }

    private function belongsToProvider(
        User $user,
        DeliveryService $deliveryService
    ): bool {
        return $deliveryService->trip()
            ->whereHas(
                'deliveryProvider',
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->exists();
    }

    private function isAssignedCourier(
        User $user,
        DeliveryService $deliveryService
    ): bool {
        return $deliveryService->shipment()
            ->whereHas(
                'routeShipments.route.courier',
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
