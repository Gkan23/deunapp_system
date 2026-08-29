<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCustomerProfileRequest;
use App\Services\Customer\UpdateCustomerProfileService;
use DomainException;
use Illuminate\Http\JsonResponse;

class CurrentCustomerProfileController extends Controller
{
    /**
     * Actualiza el perfil del cliente autenticado.
     */
    public function update(
        UpdateCustomerProfileRequest $request,
        UpdateCustomerProfileService $service
    ): JsonResponse {
        try {
            $user = $service->execute(
                $request->user(),
                $request->validated()
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' =>
                'Customer profile updated successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'account_status' =>
                    $user->accountStatus,
                'account_active' =>
                    $user
                        ->accountStatus
                        ?->status_name === 'ACTIVE',
                'profile_type' => 'CUSTOMER',
                'profile' => $user->customer,
            ],
        ]);
    }
}