<?php

namespace App\Http\Controllers;

use App\Models\TripTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TripTransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize(
            'viewAny',
            TripTransaction::class
        );

        $query = TripTransaction::query();

        $this->applyUserScope(
            $query,
            $request
        );

        $creditQuantity = (int) (clone $query)
            ->where('transaction_type', 'CREDIT')
            ->sum('quantity');

        $debitQuantity = (int) (clone $query)
            ->where('transaction_type', 'DEBIT')
            ->sum('quantity');

        $transactions = $query
            ->with([
                'deliveryProvider.user',
                'recharge.rechargePackage',
                'trip.tripType',
            ])
            ->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $transactions,
            'meta' => [
                'total_transactions' => $transactions->count(),
                'credit_quantity' => $creditQuantity,
                'debit_quantity' => $debitQuantity,
                'net_quantity' => $creditQuantity
                    - $debitQuantity,
            ],
        ]);
    }

    public function show(
        TripTransaction $tripTransaction
    ): JsonResponse {
        Gate::authorize(
            'view',
            $tripTransaction
        );

        $tripTransaction->load([
            'deliveryProvider.user',
            'recharge.rechargePackage',
            'trip.tripType',
        ]);

        return response()->json([
            'data' => $tripTransaction,
        ]);
    }

    private function applyUserScope(
        Builder $query,
        Request $request
    ): void {
        $user = $request->user();

        $roleName = $user->role()
            ->value('role_name');

        if ($roleName === 'DELIVERY_PROVIDER') {
            $query->whereHas(
                'deliveryProvider',
                fn (Builder $providerQuery) => $providerQuery
                    ->where('user_id', $user->id)
            );
        }
    }
}