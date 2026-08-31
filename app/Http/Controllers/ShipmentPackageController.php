<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShipmentPackageController extends Controller
{
    /**
     * Lista los paquetes pertenecientes a un envío.
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

        $packages = $shipment
            ->packages()
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => [
                'shipment' => [
                    'id' => $shipment->id,
                    'tracking_code' =>
                        $shipment->tracking_code,
                ],
                'summary' => [
                    'package_count' =>
                        $packages->count(),
                    'fragile_package_count' =>
                        $packages
                            ->where('is_fragile', true)
                            ->count(),
                    'total_weight' => round(
                        $packages->sum(
                            fn (Package $package): float =>
                                $package->weight === null
                                    ? 0.0
                                    : (float) $package->weight
                        ),
                        2
                    ),
                    'total_declared_value' => round(
                        $packages->sum(
                            fn (Package $package): float =>
                                $package->declared_value === null
                                    ? 0.0
                                    : (float) $package
                                        ->declared_value
                        ),
                        2
                    ),
                ],
                'packages' => $packages
                    ->map(
                        fn (Package $package): array =>
                            $this->packageData(
                                $package
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
    private function packageData(
        Package $package
    ): array {
        return [
            'id' => $package->id,
            'shipment_id' =>
                $package->shipment_id,
            'weight' => $this->nullableFloat(
                $package->weight
            ),
            'dimensions' => [
                'height' => $this->nullableFloat(
                    $package->height
                ),
                'width' => $this->nullableFloat(
                    $package->width
                ),
                'length' => $this->nullableFloat(
                    $package->length
                ),
            ],
            'content_description' =>
                $package->content_description,
            'is_fragile' =>
                (bool) $package->is_fragile,
            'declared_value' =>
                $this->nullableFloat(
                    $package->declared_value
                ),
        ];
    }

    private function nullableFloat(
        mixed $value
    ): ?float {
        return $value === null
            ? null
            : (float) $value;
    }
}