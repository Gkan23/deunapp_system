<?php

namespace App\Policies;

use App\Models\AppNotification;
use App\Models\User;

class AppNotificationPolicy
{
    /**
     * Bloquea cualquier operación si la cuenta no está activa.
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
     * Permite consultar la lista personal de notificaciones.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Solamente el destinatario puede consultar la notificación.
     */
    public function view(
        User $user,
        AppNotification $appNotification
    ): bool {
        return $appNotification->user_id === $user->id;
    }

    /**
     * Las notificaciones se crean internamente mediante servicios.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * No se permite modificar directamente una notificación.
     */
    public function update(
        User $user,
        AppNotification $appNotification
    ): bool {
        return false;
    }

    /**
     * No se permite eliminar directamente una notificación.
     */
    public function delete(
        User $user,
        AppNotification $appNotification
    ): bool {
        return false;
    }

    /**
     * Solamente el destinatario puede marcarla como leída.
     */
    public function markAsRead(
        User $user,
        AppNotification $appNotification
    ): bool {
        return $appNotification->user_id === $user->id;
    }

    /**
     * Un usuario activo puede marcar todas sus notificaciones como leídas.
     */
    public function markAllAsRead(User $user): bool
    {
        return true;
    }

    public function restore(
        User $user,
        AppNotification $appNotification
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        AppNotification $appNotification
    ): bool {
        return false;
    }
}
