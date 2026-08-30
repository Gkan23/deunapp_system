<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCourierRequest;
use App\Services\Courier\CreateCourierService;
use DomainException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class CourierController extends Controller
{
    /**
     * Crea un repartidor asociado al proveedor
     * autenticado.
     */
    public function store(
        CreateCourierRequest $request,
        CreateCourierService $service
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $courierUser = $service->execute(
                performedBy: $request->user(),
                name: $validated['name'],
                email: $validated['email'],
                licenseNumber:
                    $validated['license_number']
                    ?? null,
                comment: $validated['comment']
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' =>
                    $exception->getMessage(),
            ], 422);
        }

        /*
         * Envía la verificación de correo.
         */
        event(new Registered($courierUser));

        /*
         * Envía el enlace para que el repartidor
         * establezca su contraseña.
         */
        $passwordResetStatus = Password::broker()
            ->sendResetLink([
                'email' => $courierUser->email,
            ]);

        $courier = $courierUser->courier;

        return response()->json([
            'message' =>
                'Courier created successfully.',
            'data' => [
                'user' => [
                    'id' => $courierUser->id,
                    'name' => $courierUser->name,
                    'email' =>
                        $courierUser->email,
                    'email_verified_at' =>
                        $courierUser
                            ->email_verified_at,
                    'email_verified' =>
                        $courierUser
                            ->hasVerifiedEmail(),
                    'role' =>
                        $courierUser
                            ->role
                            ->role_name,
                    'account_status' =>
                        $courierUser
                            ->accountStatus
                            ->status_name,
                ],
                'courier' => [
                    'id' => $courier->id,
                    'delivery_provider_id' =>
                        $courier
                            ->delivery_provider_id,
                    'license_number' =>
                        $courier->license_number,
                    'is_available' =>
                        $courier->is_available,
                    'is_active' =>
                        $courier->is_active,
                ],
            ],
            'invitation' => [
                'verification_email_sent' => true,
                'password_setup_email_sent' =>
                    $passwordResetStatus
                    === Password::RESET_LINK_SENT,
            ],
        ], 201);
    }
}