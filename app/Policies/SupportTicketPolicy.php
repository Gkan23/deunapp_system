<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    /**
     * Bloquea cualquier acción cuando la cuenta
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
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ]);
    }

    public function view(
        User $user,
        SupportTicket $supportTicket
    ): bool {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        return $this->hasRole($user, 'CUSTOMER')
            && $this->ownsTicket(
                $user,
                $supportTicket
            );
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user, 'CUSTOMER')
            && $user->customer()->exists();
    }

    /**
     * Las modificaciones generales están deshabilitadas.
     */
    public function update(
        User $user,
        SupportTicket $supportTicket
    ): bool {
        return false;
    }

    /**
     * Soporte y administración pueden solicitar
     * la asignación de tickets.
     *
     * El servicio aplica las reglas específicas
     * de asignación y reasignación.
     */
    public function assign(
        User $user,
        SupportTicket $supportTicket
    ): bool {
        return $this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ]);
    }

    /**
     * El cliente propietario puede responder.
     * El agente debe estar asignado al ticket.
     * Administración puede responder cualquier ticket.
     */
    public function reply(
        User $user,
        SupportTicket $supportTicket
    ): bool {
        if ($this->hasRole(
            $user,
            'ADMINISTRATOR'
        )) {
            return true;
        }

        if (
            $this->hasRole($user, 'CUSTOMER')
            && $this->ownsTicket(
                $user,
                $supportTicket
            )
        ) {
            return true;
        }

        return $this->hasRole(
            $user,
            'SUPPORT_AGENT'
        ) && (int) $supportTicket->assigned_to_user_id
            === (int) $user->id;
    }

    /**
     * El cliente propietario puede marcar como leídos
     * los mensajes enviados por soporte.
     *
     * Un agente o administrador solamente puede hacerlo
     * cuando está asignado directamente al ticket.
     */
    public function readMessages(
        User $user,
        SupportTicket $supportTicket
    ): bool {
        if (
            $this->hasRole($user, 'CUSTOMER')
            && $this->ownsTicket(
                $user,
                $supportTicket
            )
        ) {
            return true;
        }

        return $this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ]) && (int) $supportTicket->assigned_to_user_id
            === (int) $user->id;
    }

    /**
     * Administración puede cambiar el estado de cualquier
     * ticket. Un agente solamente puede cambiar el estado
     * del ticket que tiene asignado.
     */
    public function changeStatus(
        User $user,
        SupportTicket $supportTicket
    ): bool {
        if ($this->hasRole(
            $user,
            'ADMINISTRATOR'
        )) {
            return true;
        }

        return $this->hasRole(
            $user,
            'SUPPORT_AGENT'
        ) && (int) $supportTicket->assigned_to_user_id
            === (int) $user->id;
    }

    /**
     * Los tickets deben conservarse para mantener
     * la trazabilidad del soporte.
     */
    public function delete(
        User $user,
        SupportTicket $supportTicket
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        SupportTicket $supportTicket
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        SupportTicket $supportTicket
    ): bool {
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