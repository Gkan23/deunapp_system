<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDeliveryProviderProfileRequest;
use App\Services\Provider\UpdateDeliveryProviderProfileService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CurrentDeliveryProviderProfileController extends Controller
{
    /**
     * Actualiza el perfil del proveedor autenticado.
     */
    public function update(
        UpdateDeliveryProviderProfileRequest $request,
        UpdateDeliveryProviderProfileService $service
    ): JsonResponse|RedirectResponse {
        try {
            $user = $service->execute(
                $request->user(),
                $request->validated()
            );
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' =>
                        $exception->getMessage(),
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
                'current-user.provider-profile.edit'
            )->with(
                'status',
                'Perfil de proveedor actualizado correctamente.'
            );
        }

        return response()->json([
            'message' =>
                'Delivery provider profile updated successfully.',
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
                'profile_type' =>
                    'DELIVERY_PROVIDER',
                'profile' =>
                    $user->deliveryProvider,
            ],
        ]);
    }
}