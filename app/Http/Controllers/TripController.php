<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TripController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize(
            'viewAny',
            Trip::class
        );

        $query = Trip::query();

        $this->applyUserScope(
            $query,
            $request
        );

        $summary = [
            'total' => (clone $query)->count(),
            'available' => (clone $query)
                ->where('status', 'AVAILABLE')
                ->count(),
            'used' => (clone $query)
                ->where('status', 'USED')
                ->count(),
        ];

        $trips = $query
            ->with([
                'deliveryProvider.user',
                'tripType',
                'deliveryService',
                'transactions',
            ])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $trips,
            'meta' => $summary,
        ]);
    }

    public function show(Trip $trip): JsonResponse
    {
        Gate::authorize(
            'view',
            $trip
        );

        $trip->load([
            'deliveryProvider.user',
            'tripType',
            'deliveryService',
            'transactions',
        ]);

        return response()->json([
            'data' => $trip,
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