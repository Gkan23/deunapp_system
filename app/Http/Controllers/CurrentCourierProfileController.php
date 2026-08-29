<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCourierProfileRequest;
use App\Services\Courier\UpdateCourierProfileService;
use DomainException;
use Illuminate\Http\JsonResponse;

class CurrentCourierProfileController extends Controller
{
    /**
     * Actualiza el perfil del repartidor autenticado.
     */
    public function update(
        UpdateCourierProfileRequest $request,
        UpdateCourierProfileService $service
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
                'Courier profile updated successfully.',
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
                'profile_type' => 'COURIER',
                'profile' => $user->courier,
            ],
        ]);
    }
}