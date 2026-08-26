<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
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

    /**
     * Clientes, agentes de soporte y administradores pueden
     * acceder al listado correspondiente a su contexto.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasAnyRole($user, [
            'CUSTOMER',
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ]);
    }

    /**
     * El cliente puede consultar sus propios tickets.
     * Soporte y administración pueden consultar cualquier ticket.
     */
    public function view(User $user, SupportTicket $supportTicket): bool
    {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        return $this->ownsTicket($user, $supportTicket);
    }

    /**
     * Solo un cliente con perfil asociado puede crear tickets.
     */
    public function create(User $user): bool
    {
        return $this->hasRole($user, 'CUSTOMER')
            && $user->customer()->exists();
    }

    /**
     * La modificación genérica queda deshabilitada.
     * Deben utilizarse las acciones específicas.
     */
    public function update(User $user, SupportTicket $supportTicket): bool
    {
        return false;
    }

    /**
     * Un agente de soporte o administrador puede asignar tickets.
     */
    public function assign(User $user, SupportTicket $supportTicket): bool
    {
        return $this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ]);
    }

    /**
     * El cliente propietario puede responder.
     *
     * Un agente de soporte solo puede responder si tiene
     * asignado el ticket. El administrador siempre puede hacerlo.
     */
    public function reply(User $user, SupportTicket $supportTicket): bool
    {
        if ($this->hasRole($user, 'ADMINISTRATOR')) {
            return true;
        }

        if ($this->ownsTicket($user, $supportTicket)) {
            return true;
        }

        return $this->hasRole($user, 'SUPPORT_AGENT')
            && $supportTicket->assigned_to_user_id === $user->id;
    }

    /**
     * El agente asignado o un administrador puede cambiar el estado.
     */
    public function changeStatus(
        User $user,
        SupportTicket $supportTicket
    ): bool {
        if ($this->hasRole($user, 'ADMINISTRATOR')) {
            return true;
        }

        return $this->hasRole($user, 'SUPPORT_AGENT')
            && $supportTicket->assigned_to_user_id === $user->id;
    }

    /**
     * Los tickets conservan su trazabilidad y no se eliminan directamente.
     */
    public function delete(User $user, SupportTicket $supportTicket): bool
    {
        return false;
    }

    public function restore(User $user, SupportTicket $supportTicket): bool
    {
        return false;
    }

    public function forceDelete(User $user, SupportTicket $supportTicket): bool
    {
        return false;
    }

    private function ownsTicket(
        User $user,
        SupportTicket $supportTicket
    ): bool {
        return $supportTicket->customer()
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
