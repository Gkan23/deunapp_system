<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Shipment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IncidentCreatePageController extends Controller
{
    /**
     * Mostrar el formulario para reportar un incidente.
     */
    public function __invoke(
        Request $request,
        Shipment $shipment
    ): View {
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'create',
            Incident::class
        );

        Gate::forUser($user)->authorize(
            'view',
            $shipment
        );

        $user->loadMissing([
            'role',
            'accountStatus',
        ]);

        $shipment->loadMissing('shipmentStatus');

        $incidentTypes = IncidentType::query()
            ->orderBy('type_name')
            ->get([
                'id',
                'type_name',
                'description',
            ]);

        return view('incidents.create', [
            'user' => $user,
            'roleName' => $user->role?->role_name,
            'shipment' => $shipment,
            'incidentTypes' => $incidentTypes,
        ]);
    }
}