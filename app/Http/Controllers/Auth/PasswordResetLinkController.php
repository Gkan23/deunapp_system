<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    /**
     * Envía un enlace únicamente si existe una
     * cuenta activa con el correo proporcionado.
     *
     * La respuesta siempre es la misma para evitar
     * revelar qué correos están registrados.
     */
    public function store(
        ForgotPasswordRequest $request
    ): JsonResponse {
        $email = $request->validated('email');

        $eligibleUser = User::query()
            ->where('email', $email)
            ->whereHas(
                'accountStatus',
                fn ($query) => $query->where(
                    'status_name',
                    'ACTIVE'
                )
            )
            ->exists();

        if ($eligibleUser) {
            Password::broker()->sendResetLink([
                'email' => $email,
            ]);
        }

        return response()->json([
            'message' =>
                'If an eligible account exists, a password reset link has been sent.',
        ]);
    }
}