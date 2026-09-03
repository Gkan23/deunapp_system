<?php

namespace App\Http\Controllers;

use App\Models\ProviderType;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CurrentDeliveryProviderProfilePageController extends Controller
{
    /**
     * Muestra la página para editar el perfil
     * del proveedor autenticado.
     */
    public function __invoke(
        Request $request
    ): View {
        $user = User::query()
            ->with([
                'role',
                'accountStatus',
                'deliveryProvider.providerType',
            ])
            ->findOrFail(
                $request->user()->getKey()
            );

        $provider = $user->deliveryProvider;

        $authorized =
            $user->role?->role_name
                === 'DELIVERY_PROVIDER'
            && $user->accountStatus?->status_name
                === 'ACTIVE'
            && $provider !== null
            && $provider->is_active;

        abort_unless(
            $authorized,
            403,
            'The delivery provider cannot access this page.'
        );

        $providerTypes = ProviderType::query()
            ->orderBy('id')
            ->get();

        return view(
            'settings.provider-profile',
            [
                'user' => $user,
                'provider' => $provider,
                'providerTypes' => $providerTypes,
            ]
        );
    }
}