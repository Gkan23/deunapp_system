<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display or return the email verification status.
     */
    public function __invoke(
        Request $request
    ): JsonResponse|RedirectResponse|View {
        $user = $request->user();

        $verified = $user->hasVerifiedEmail();

        if ($request->expectsJson()) {
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

        if ($verified) {
            return redirect()
                ->route('dashboard');
        }

        return view('auth.verify-email', [
            'user' => $user,
        ]);
    }
}