<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRechargeRequest;
use App\Models\Recharge;
use App\Models\RechargePackage;
use App\Services\Recharge\ConfirmRechargeService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class RechargeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize(
            'viewAny',
            Recharge::class
        );

        $user = $request->user();

        $roleName = $user->role()
            ->value('role_name');

        $query = Recharge::query()
            ->with([
                'deliveryProvider.user',
                'rechargePackage',
                'tripType',
                'tripTransactions',
            ])
            ->orderByDesc('id');

        /*
         * El proveedor solamente puede consultar
         * sus propias recargas.
         */
        if ($roleName === 'DELIVERY_PROVIDER') {
            $query->whereHas(
                'deliveryProvider',
                fn ($providerQuery) => $providerQuery
                    ->where('user_id', $user->id)
            );
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(
        StoreRechargeRequest $request,
        ConfirmRechargeService $service
    ): JsonResponse {
        $provider = $request->user()
            ->deliveryProvider()
            ->firstOrFail();

        $package = RechargePackage::query()
            ->findOrFail(
                $request->integer(
                    'recharge_package_id'
                )
            );

        try {
            $recharge = $service->handle(
                deliveryProvider: $provider,
                rechargePackage: $package,
                paymentReference: $request->string(
                    'payment_reference'
                )->toString()
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Recharge confirmed successfully.',
            'data' => $recharge,
        ], Response::HTTP_CREATED);
    }

    public function show(
        Recharge $recharge
    ): JsonResponse {
        Gate::authorize(
            'view',
            $recharge
        );

        $recharge->load([
            'deliveryProvider.user',
            'rechargePackage',
            'tripType',
            'tripTransactions',
        ]);

        return response()->json([
            'data' => $recharge,
        ]);
    }
}