<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\RegisterCustomerService;
use DomainException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
    /**
     * Register a customer and start their session.
     */
    public function store(
        RegisterRequest $request,
        RegisterCustomerService $service
    ): JsonResponse|RedirectResponse {
        try {
            $user = $service->execute(
                $request->validated()
            );
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return back()
                ->withInput(
                    $request->safe()->except([
                        'password',
                        'password_confirmation',
                    ])
                )
                ->withErrors([
                    'registration' =>
                        $exception->getMessage(),
                ]);
        }

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        $customer = $user->customer;

        if ($request->expectsJson()) {
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

        return redirect()
            ->route('verification.notice')
            ->with(
                'status',
                'Cuenta creada correctamente. Revisa tu correo para verificarla.'
            );
    }
}