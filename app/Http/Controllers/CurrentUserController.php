<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    public function __invoke(
        Request $request
    ): JsonResponse {
        $user = User::query()
            ->with([
                'role',
                'accountStatus',
                'customer.customerType',
                'deliveryProvider.providerType',
                'courier.deliveryProvider.providerType',
            ])
            ->findOrFail(
                $request->user()->getKey()
            );

        $roleName = $user->role?->role_name;

        $profileType = match ($roleName) {
            'CUSTOMER' => 'CUSTOMER',
            'DELIVERY_PROVIDER' => 'DELIVERY_PROVIDER',
            'COURIER' => 'COURIER',
            default => null,
        };

        $profile = match ($roleName) {
            'CUSTOMER' => $user->customer,
            'DELIVERY_PROVIDER' => $user
                ->deliveryProvider,
            'COURIER' => $user->courier,
            default => null,
        };

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user
                    ->email_verified_at,
                'role' => $user->role,
                'account_status' => $user
                    ->accountStatus,
                'account_active' => $user
                    ->accountStatus
                    ?->status_name === 'ACTIVE',
                'profile_type' => $profileType,
                'profile' => $profile,
            ],
        ]);
    }
}