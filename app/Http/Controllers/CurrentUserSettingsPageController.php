<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CurrentUserSettingsPageController extends Controller
{
    /**
     * Display the account settings page.
     */
    public function __invoke(
        Request $request
    ): View {
        $user = User::query()
            ->with([
                'role',
                'accountStatus',
            ])
            ->findOrFail(
                $request->user()->getKey()
            );

        abort_unless(
            $user->accountStatus?->status_name === 'ACTIVE',
            403,
            'The account is not active.'
        );

        return view('settings.account', [
            'user' => $user,
            'roleLabel' => $this->roleLabel(
                $user->role?->role_name
            ),
        ]);
    }

    private function roleLabel(
        ?string $roleName
    ): string {
        return match ($roleName) {
            'CUSTOMER' => 'Cliente',
            'DELIVERY_PROVIDER' =>
                'Proveedor de entrega',
            'COURIER' => 'Repartidor',
            'SUPPORT_AGENT' =>
                'Agente de soporte',
            'ADMINISTRATOR' =>
                'Administrador',
            default => 'Usuario',
        };
    }
}