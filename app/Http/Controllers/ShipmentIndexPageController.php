<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\User;
use App\Services\Shipment\VisibleShipmentsQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShipmentIndexPageController extends Controller
{
    /**
     * Muestra el listado Blade de envíos.
     */
    public function __invoke(
        Request $request,
        VisibleShipmentsQuery $visibleShipments
    ): View {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'viewAny',
            Shipment::class
        );

        $search = trim(
            (string) $request->query(
                'search',
                ''
            )
        );

        $status = strtoupper(
            trim(
                (string) $request->query(
                    'status',
                    ''
                )
            )
        );

        $query = $visibleShipments
            ->for($user)
            ->with([
                'customer.user',
                'shipmentStatus',
                'sender',
                'recipient',
                'originAddress',
                'destinationAddress',
            ]);

        if ($search !== '') {
            $query->where(function (
                Builder $shipmentQuery
            ) use ($search): void {
                $shipmentQuery
                    ->where(
                        'tracking_code',
                        'like',
                        '%'.$search.'%'
                    )
                    ->orWhereHas(
                        'sender',
                        function (
                            Builder $personQuery
                        ) use ($search): void {
                            $personQuery
                                ->where(
                                    'first_name',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhere(
                                    'last_name',
                                    'like',
                                    '%'.$search.'%'
                                );
                        }
                    )
                    ->orWhereHas(
                        'recipient',
                        function (
                            Builder $personQuery
                        ) use ($search): void {
                            $personQuery
                                ->where(
                                    'first_name',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhere(
                                    'last_name',
                                    'like',
                                    '%'.$search.'%'
                                );
                        }
                    )
                    ->orWhereHas(
                        'originAddress',
                        fn (
                            Builder $addressQuery
                        ): Builder =>
                            $addressQuery->where(
                                'address_line',
                                'like',
                                '%'.$search.'%'
                            )
                    )
                    ->orWhereHas(
                        'destinationAddress',
                        fn (
                            Builder $addressQuery
                        ): Builder =>
                            $addressQuery->where(
                                'address_line',
                                'like',
                                '%'.$search.'%'
                            )
                    );
            });
        }

        if ($status !== '') {
            $query->whereHas(
                'shipmentStatus',
                fn (
                    Builder $statusQuery
                ): Builder =>
                    $statusQuery->where(
                        'status_name',
                        $status
                    )
            );
        }

        $shipments = $query
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $statuses = ShipmentStatus::query()
            ->orderBy('id')
            ->get();

        return view('shipments.index', [
            'user' => $user,
            'shipments' => $shipments,
            'statuses' => $statuses,
            'search' => $search,
            'selectedStatus' => $status,
        ]);
    }
}