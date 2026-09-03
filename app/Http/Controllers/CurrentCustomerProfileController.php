<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCustomerProfileRequest;
use App\Services\Customer\UpdateCustomerProfileService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CurrentCustomerProfileController extends Controller
{
    /**
     * Actualiza el perfil del cliente autenticado.
     */
    public function update(
        UpdateCustomerProfileRequest $request,
        UpdateCustomerProfileService $service
    ): JsonResponse|RedirectResponse {
        try {
            $user = $service->execute(
                $request->user(),
                $request->validated()
            );
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors([
                    'profile' =>
                        $exception->getMessage(),
                ]);
        }

        if (! $request->expectsJson()) {
            return to_route(
                'current-user.profile.edit'
            )->with(
                'status',
                'Perfil actualizado correctamente.'
            );
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