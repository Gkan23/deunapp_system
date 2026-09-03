<?php

namespace App\Services\Shipment;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class VisibleShipmentsQuery
{
    /**
     * Construye la consulta de envíos visibles
     * para el usuario autenticado.
     */
    public function for(
        User $user
    ): Builder {
        $query = Shipment::query();

        $roleName = $user->role()
            ->value('role_name');

        return match ($roleName) {
            'ADMINISTRATOR',
            'SUPPORT_AGENT' => $query,

            'CUSTOMER' => $query->whereHas(
                'customer',
                fn (Builder $customerQuery): Builder =>
                    $customerQuery->where(
                        'user_id',
                        $user->id
                    )
            ),

            'DELIVERY_PROVIDER' => $query->whereHas(
                'deliveryService.trip.deliveryProvider',
                fn (Builder $providerQuery): Builder =>
                    $providerQuery->where(
                        'user_id',
                        $user->id
                    )
            ),

            'COURIER' => $query->whereHas(
                'routeShipments.route.courier',
                fn (Builder $courierQuery): Builder =>
                    $courierQuery->where(
                        'user_id',
                        $user->id
                    )
            ),

            default => $query->whereRaw('1 = 0'),
        };
    }
}