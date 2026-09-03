<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShipmentCreatePageController extends Controller
{
    /**
     * Muestra el formulario Blade para crear un envío.
     */
    public function __invoke(
        Request $request
    ): View {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'create',
            Shipment::class
        );

        $municipalities =
            Municipality::query()
                ->where('is_active', true)
                ->orderBy('department_name')
                ->orderBy('municipality_name')
                ->get();

        return view('shipments.create', [
            'user' => $user,
            'municipalities' =>
                $municipalities,
        ]);
    }
}