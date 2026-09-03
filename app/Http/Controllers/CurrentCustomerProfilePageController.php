<?php

namespace App\Http\Controllers;

use App\Models\CustomerType;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CurrentCustomerProfilePageController extends Controller
{
    /**
     * Muestra el formulario del perfil del cliente.
     */
    public function __invoke(
        Request $request
    ): View {
        $user = User::query()
            ->with([
                'role',
                'accountStatus',
                'customer.customerType',
            ])
            ->findOrFail(
                $request->user()->getKey()
            );

        $authorized = $user->role?->role_name
                === 'CUSTOMER'
            && $user->accountStatus?->status_name
                === 'ACTIVE'
            && $user->customer !== null;

        abort_unless($authorized, 403);

        $customerTypes = CustomerType::query()
            ->orderBy('id')
            ->get();

        return view(
            'settings.customer-profile',
            [
                'user' => $user,
                'customer' => $user->customer,
                'customerTypes' => $customerTypes,
            ]
        );
    }
}