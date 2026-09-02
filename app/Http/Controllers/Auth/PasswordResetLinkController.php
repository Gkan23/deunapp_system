<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    /**
     * Send a reset link only when an eligible account exists.
     */
    public function store(
        ForgotPasswordRequest $request
    ): JsonResponse|RedirectResponse {
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

        $message =
            'If an eligible account exists, a password reset link has been sent.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ]);
        }

        return back()->with(
            'status',
            'Si existe una cuenta activa con ese correo, recibirás un enlace para restablecer la contraseña.'
        );
    }
}