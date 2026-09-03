<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CurrentCourierProfilePageController extends Controller
{
    /**
     * Muestra la página del perfil del repartidor.
     */
    public function __invoke(
        Request $request
    ): View {
        $user = User::query()
            ->with([
                'role',
                'accountStatus',
                'courier.deliveryProvider.providerType',
            ])
            ->findOrFail(
                $request->user()->getKey()
            );

        $courier = $user->courier;
        $provider = $courier?->deliveryProvider;

        $authorized =
            $user->role?->role_name === 'COURIER'
            && $user->accountStatus?->status_name
                === 'ACTIVE'
            && $courier !== null
            && $courier->is_active
            && $provider !== null
            && $provider->is_active;

        abort_unless(
            $authorized,
            403,
            'The courier cannot access this page.'
        );

        return view(
            'settings.courier-profile',
            [
                'user' => $user,
                'courier' => $courier,
                'provider' => $provider,
            ]
        );
    }
}