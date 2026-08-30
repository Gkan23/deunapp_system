<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateEmailRequest;
use App\Services\Auth\UpdateUserEmailService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CurrentUserEmailController extends Controller
{
    /**
     * Cambia el correo del usuario autenticado.
     */
    public function update(
        UpdateEmailRequest $request,
        UpdateUserEmailService $service
    ): JsonResponse {
        try {
            $user = $service->execute(
                $request->user(),
                $request->validated('email')
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        /*
         * Actualiza el usuario del guard durante
         * la solicitud actual.
         */
        Auth::setUser($user);

        $request->session()->regenerate();

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' =>
                'Email updated successfully. A verification link has been sent.',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'email_verified' =>
                    $user->hasVerifiedEmail(),
                'email_verified_at' =>
                    $user->email_verified_at,
            ],
        ]);
    }
}