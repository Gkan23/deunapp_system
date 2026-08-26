<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
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
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ]);
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return true;
        }

        if (
            $this->hasRole($user, 'CUSTOMER')
            && $this->ownsPayment($user, $payment)
        ) {
            return true;
        }

        return $this->hasRole($user, 'DELIVERY_PROVIDER')
            && $this->belongsToProvider($user, $payment);
    }

    /**
     * Los pagos se crean mediante ConfirmPaymentService.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * El cliente propietario o un administrador puede confirmar el pago.
     */
    public function confirm(User $user, Payment $payment): bool
    {
        if ($this->hasRole($user, 'ADMINISTRATOR')) {
            return true;
        }

        return $this->hasRole($user, 'CUSTOMER')
            && $this->ownsPayment($user, $payment);
    }

    /**
     * Solamente administración puede autorizar reembolsos.
     */
    public function refund(User $user, Payment $payment): bool
    {
        return $this->hasRole($user, 'ADMINISTRATOR');
    }

    /**
     * No se permite modificar directamente un pago.
     */
    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    /**
     * Los registros financieros no se eliminan.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function restore(User $user, Payment $payment): bool
    {
        return false;
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return false;
    }

    private function ownsPayment(
        User $user,
        Payment $payment
    ): bool {
        return $payment->deliveryService()
            ->whereHas(
                'customer',
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->exists();
    }

    private function belongsToProvider(
        User $user,
        Payment $payment
    ): bool {
        return $payment->deliveryService()
            ->whereHas(
                'trip.deliveryProvider',
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
