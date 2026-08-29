<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function store(
        LoginRequest $request
    ): JsonResponse {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user()->load([
            'role',
            'accountStatus',
        ]);

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

    public function destroy(
        Request $request
    ): JsonResponse {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}