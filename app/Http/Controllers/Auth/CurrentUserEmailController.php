<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateEmailRequest;
use App\Services\Auth\UpdateUserEmailService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CurrentUserEmailController extends Controller
{
    /**
     * Update the authenticated user's email.
     */
    public function update(
        UpdateEmailRequest $request,
        UpdateUserEmailService $service
    ): JsonResponse|RedirectResponse {
        try {
            $user = $service->execute(
                $request->user(),
                $request->validated('email')
            );
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' =>
                        $exception->getMessage(),
                ], 422);
            }

            return back()
                ->withInput([
                    'email' =>
                        $request->input('email'),
                ])
                ->withErrors([
                    'email' =>
                        $exception->getMessage(),
                ]);
        }

        Auth::setUser($user);

        $request->session()->regenerate();

        $user->sendEmailVerificationNotification();

        if ($request->expectsJson()) {
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

        return redirect()
            ->route('verification.notice')
            ->with(
                'status',
                'Correo actualizado correctamente. Revisa el nuevo correo para verificarlo.'
            );
    }
}