<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\RegisterCustomerService;
use DomainException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
    /**
     * Registra un cliente, envía la verificación
     * e inicia su sesión.
     */
    public function store(
        RegisterRequest $request,
        RegisterCustomerService $service
    ): JsonResponse {
        try {
            $user = $service->execute(
                $request->validated()
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        /*
         * El evento Registered envía automáticamente
         * la notificación cuando User implementa
         * MustVerifyEmail.
         */
        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        $customer = $user->customer;

        return response()->json([
            'message' =>
                'Customer registered successfully.',
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
                'customer' => [
                    'id' => $customer->id,
                    'customer_type' =>
                        $customer
                            ->customerType
                            ->type_name,
                    'identity_number' =>
                        $customer->identity_number,
                    'company_name' =>
                        $customer->company_name,
                    'phone' => $customer->phone,
                ],
            ],
        ], 201);
    }
}