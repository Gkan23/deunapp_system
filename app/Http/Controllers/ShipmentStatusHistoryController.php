<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShipmentStatusHistoryController extends Controller
{
    /**
     * Muestra cronológicamente los estados por los
     * que ha pasado un envío.
     */
    public function index(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'view',
            $shipment
        );

        $shipment->loadMissing(
            'shipmentStatus'
        );

        $history = $shipment
            ->statusHistory()
            ->with([
                'shipmentStatus',
                'changedBy.role',
            ])
            ->orderBy('changed_at')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => [
                'shipment' => [
                    'id' => $shipment->id,
                    'tracking_code' =>
                        $shipment->tracking_code,
                    'current_status' =>
                        $shipment
                            ->shipmentStatus
                            ?->status_name,
                ],
                'history' => $history
                    ->map(
                        fn (
                            ShipmentStatusHistory $entry
                        ): array => $this->historyData(
                            $entry
                        )
                    )
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function historyData(
        ShipmentStatusHistory $entry
    ): array {
        $changedBy = $entry->changedBy;

        return [
            'id' => $entry->id,
            'status' => [
                'id' =>
                    $entry->shipmentStatus->id,
                'name' =>
                    $entry
                        ->shipmentStatus
                        ->status_name,
                'description' =>
                    $entry
                        ->shipmentStatus
                        ->description,
            ],
            'comment' => $entry->comment,
            'changed_at' =>
                $entry
                    ->changed_at
                    ->toIso8601String(),
            'changed_by' =>
                $changedBy === null
                    ? null
                    : [
                        'id' => $changedBy->id,
                        'name' => $changedBy->name,
                        'role' =>
                            $changedBy
                                ->role
                                ?->role_name,
                    ],
        ];
    }
}