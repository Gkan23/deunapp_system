<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Reenvía el enlace de verificación.
     */
    public function store(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        $activeAccount = $user->accountStatus()
            ->where('status_name', 'ACTIVE')
            ->exists();

        if (! $activeAccount) {
            return response()->json([
                'message' =>
                    'Only an active account can request an email verification link.',
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' =>
                    'The email address is already verified.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' =>
                'Email verification link sent successfully.',
        ]);
    }
}