<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationPromptController extends Controller
{
    /**
     * Devuelve el estado actual de verificación
     * del correo electrónico.
     */
    public function __invoke(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        $verified = $user->hasVerifiedEmail();

        return response()->json([
            'message' => $verified
                ? 'The email address is already verified.'
                : 'The email address must be verified.',
            'data' => [
                'email' => $user->email,
                'email_verified' => $verified,
                'email_verified_at' =>
                    $user->email_verified_at,
            ],
        ]);
    }
}