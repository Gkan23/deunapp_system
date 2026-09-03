<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCourierProfileRequest;
use App\Services\Courier\UpdateCourierProfileService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CurrentCourierProfileController extends Controller
{
    /**
     * Actualiza el perfil del repartidor autenticado.
     */
    public function update(
        UpdateCourierProfileRequest $request,
        UpdateCourierProfileService $service
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
                'current-user.courier-profile.edit'
            )->with(
                'status',
                'Perfil de repartidor actualizado correctamente.'
            );
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