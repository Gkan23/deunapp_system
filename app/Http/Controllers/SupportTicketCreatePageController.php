<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Models\TicketCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SupportTicketCreatePageController extends Controller
{
    /**
     * Muestra el formulario de creación de tickets.
     */
    public function __invoke(
        Request $request
    ): View {
        Gate::authorize(
            'create',
            SupportTicket::class
        );

        $user = $request->user()->loadMissing([
            'role',
            'accountStatus',
            'customer',
        ]);

        $customer = $user->customer;

        abort_if($customer === null, 403);

        $categories = TicketCategory::query()
            ->orderBy('category_name')
            ->get();

        /*
         * Solamente se muestran los envíos
         * pertenecientes al cliente autenticado.
         */
        $shipments = Shipment::query()
            ->where(
                'customer_id',
                $customer->id
            )
            ->latest('id')
            ->get([
                'id',
                'tracking_code',
            ]);

        return view(
            'support-tickets.create',
            [
                'user' => $user,
                'roleName' => $user->role?->role_name,
                'categories' => $categories,
                'shipments' => $shipments,
            ]
        );
    }
}