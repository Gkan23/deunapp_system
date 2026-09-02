<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Authenticate a user.
     */
    public function store(
        LoginRequest $request
    ): JsonResponse|RedirectResponse {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user()->load([
            'role',
            'accountStatus',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Logged in successfully.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'account_status' => $user
                        ->accountStatus,
                ],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()
                ->route('verification.notice')
                ->with(
                    'status',
                    'Debes verificar tu correo electrónico.'
                );
        }

        return redirect()
            ->intended(
                route('dashboard')
            )
            ->with(
                'status',
                'Sesión iniciada correctamente.'
            );
    }

    /**
     * Destroy the current session.
     */
    public function destroy(
        Request $request
    ): JsonResponse|RedirectResponse {
        $expectsJson = $request->expectsJson();

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if ($expectsJson) {
            return response()->json([
                'message' => 'Logged out successfully.',
            ]);
        }

        return redirect()
            ->route('login.page')
            ->with(
                'status',
                'Sesión cerrada correctamente.'
            );
    }
}