<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    /**
     * Impide cualquier operación cuando la cuenta
     * del usuario no se encuentra activa.
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

    /**
     * Determina qué roles pueden acceder al listado
     * general de incidencias.
     *
     * El controlador deberá filtrar los resultados
     * según el rol y la propiedad de cada recurso.
     */
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

    /**
     * Determina si el usuario puede consultar
     * una incidencia específica.
     */
    public function view(User $user, Incident $incident): bool
    {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        if (
            $this->hasRole($user, 'CUSTOMER')
            && $this->ownsIncident($user, $incident)
        ) {
            return true;
        }

        if (
            $this->hasRole($user, 'DELIVERY_PROVIDER')
            && $this->belongsToProvider($user, $incident)
        ) {
            return true;
        }

        return $this->hasRole($user, 'COURIER')
            && $this->isAssignedCourier($user, $incident);
    }

    /**
     * Autoriza el acceso a la creación de incidencias.
     *
     * La comprobación de que el usuario está relacionado
     * con el envío debe realizarse al registrar la
     * incidencia específica.
     */
    public function create(User $user): bool
    {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        if ($this->hasRole($user, 'CUSTOMER')) {
            return $user->customer()->exists();
        }

        if ($this->hasRole($user, 'DELIVERY_PROVIDER')) {
            return $user->deliveryProvider()
                ->where('is_active', true)
                ->exists();
        }

        if ($this->hasRole($user, 'COURIER')) {
            return $user->courier()
                ->where('is_active', true)
                ->whereHas(
                    'deliveryProvider',
                    fn ($query) => $query->where(
                        'is_active',
                        true
                    )
                )
                ->exists();
        }

        return false;
    }

    /**
     * Solamente soporte y administración pueden
     * colocar una incidencia en revisión.
     */
    public function review(
        User $user,
        Incident $incident
    ): bool {
        return $this->canManageIncidents($user);
    }

    /**
     * Solamente soporte y administración pueden
     * marcar una incidencia como resuelta.
     */
    public function resolve(
        User $user,
        Incident $incident
    ): bool {
        return $this->canManageIncidents($user);
    }

    /**
     * Solamente soporte y administración pueden
     * cerrar definitivamente una incidencia.
     */
    public function close(
        User $user,
        Incident $incident
    ): bool {
        return $this->canManageIncidents($user);
    }

    /**
     * Las modificaciones deben pasar por las acciones
     * review, resolve o close.
     */
    public function update(
        User $user,
        Incident $incident
    ): bool {
        return false;
    }

    /**
     * Las incidencias forman parte de la trazabilidad
     * del envío y no deben eliminarse.
     */
    public function delete(
        User $user,
        Incident $incident
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        Incident $incident
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        Incident $incident
    ): bool {
        return false;
    }

    /**
     * Comprueba que el envío de la incidencia
     * pertenece al cliente autenticado.
     */
    private function ownsIncident(
        User $user,
        Incident $incident
    ): bool {
        return $incident->shipment()
            ->whereHas(
                'customer',
                fn ($query) => $query->where(
                    'user_id',
                    $user->id
                )
            )
            ->exists();
    }

    /**
     * Comprueba que el proveedor es propietario
     * del viaje utilizado por el servicio.
     */
    private function belongsToProvider(
        User $user,
        Incident $incident
    ): bool {
        return $incident->shipment()
            ->whereHas(
                'deliveryService.trip.deliveryProvider',
                fn ($query) => $query->where(
                    'user_id',
                    $user->id
                )
            )
            ->exists();
    }

    /**
     * Comprueba que el envío está incluido en una ruta
     * asignada al repartidor autenticado.
     */
    private function isAssignedCourier(
        User $user,
        Incident $incident
    ): bool {
        return $incident->shipment()
            ->whereHas(
                'routeShipments.route.courier',
                fn ($query) => $query->where(
                    'user_id',
                    $user->id
                )
            )
            ->exists();
    }

    private function canManageIncidents(User $user): bool
    {
        return $this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ]);
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

