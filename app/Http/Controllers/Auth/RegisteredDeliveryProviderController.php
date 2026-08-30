<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterDeliveryProviderRequest;
use App\Services\Auth\RegisterDeliveryProviderService;
use DomainException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;

class RegisteredDeliveryProviderController extends Controller
{
    /**
     * Registra un proveedor pendiente de aprobación.
     */
    public function store(
        RegisterDeliveryProviderRequest $request,
        RegisterDeliveryProviderService $service
    ): JsonResponse {
        try {
            $user = $service->execute(
                $request->validated()
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' =>
                    $exception->getMessage(),
            ], 422);
        }

        /*
         * Envía la notificación de verificación.
         * Si el enlace vence antes de la aprobación,
         * el proveedor podrá solicitar otro después.
         */
        event(new Registered($user));

        $provider = $user->deliveryProvider;

        return response()->json([
            'message' =>
                'Delivery provider registration submitted successfully.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' =>
                        $user->email_verified_at,
                    'email_verified' =>
                        $user->hasVerifiedEmail(),
                    'role' =>
                        $user->role->role_name,
                    'account_status' =>
                        $user
                            ->accountStatus
                            ->status_name,
                ],
                'provider' => [
                    'id' => $provider->id,
                    'provider_type' =>
                        $provider
                            ->providerType
                            ->type_name,
                    'business_name' =>
                        $provider->business_name,
                    'identity_number' =>
                        $provider->identity_number,
                    'phone' =>
                        $provider->phone,
                    'is_active' =>
                        $provider->is_active,
                ],
            ],
        ], 201);
    }
}