<?php

namespace App\Http\Controllers;

use App\Models\RechargePackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RechargePackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize(
            'viewAny',
            RechargePackage::class
        );

        $user = $request->user();

        $roleName = $user->role()
            ->value('role_name');

        $query = RechargePackage::query()
            ->with([
                'commissionRule.tripType',
            ])
            ->orderBy('price')
            ->orderBy('id');

        /*
         * El proveedor solamente recibe paquetes activos
         * y con una regla de comisión vigente.
         */
        if ($roleName === 'DELIVERY_PROVIDER') {
            $query
                ->where('is_active', true)
                ->whereHas(
                    'commissionRule',
                    function ($ruleQuery): void {
                        $ruleQuery
                            ->where('is_active', true)
                            ->whereDate(
                                'valid_from',
                                '<=',
                                today()
                            )
                            ->where(function (
                                $validityQuery
                            ): void {
                                $validityQuery
                                    ->whereNull('valid_until')
                                    ->orWhereDate(
                                        'valid_until',
                                        '>=',
                                        today()
                                    );
                            });
                    }
                );
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(
        RechargePackage $rechargePackage
    ): JsonResponse {
        Gate::authorize(
            'view',
            $rechargePackage
        );

        $rechargePackage->load([
            'commissionRule.tripType',
        ]);

        return response()->json([
            'data' => $rechargePackage,
        ]);
    }
}