<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class NewPasswordController extends Controller
{
    /**
     * Display the reset form or return its JSON data.
     */
    public function create(
        Request $request,
        string $token
    ): JsonResponse|View {
        $email = strtolower(
            trim(
                (string) $request->query(
                    'email',
                    ''
                )
            )
        );

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'token' => $token,
                    'email' => $email,
                ],
            ]);
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Reset the password using a valid token.
     */
    public function store(
        ResetPasswordRequest $request
    ): JsonResponse|RedirectResponse {
        $validated = $request->validated();

        $eligibleUser = User::query()
            ->where('email', $validated['email'])
            ->whereHas(
                'accountStatus',
                fn ($query) => $query->where(
                    'status_name',
                    'ACTIVE'
                )
            )
            ->exists();

        if (! $eligibleUser) {
            return $this->invalidTokenResponse(
                $request
            );
        }

        $status = Password::broker()->reset(
            $validated,
            function (
                User $user,
                string $password
            ): void {
                DB::transaction(function () use (
                    $user,
                    $password
                ): void {
                    $lockedUser = User::query()
                        ->whereKey($user->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    $resetAt = now();

                    $lockedUser->forceFill([
                        'password' => $password,
                        'remember_token' =>
                            Str::random(60),
                    ])->save();

                    $invalidatedSessions = DB::table(
                        'sessions'
                    )
                        ->where(
                            'user_id',
                            $lockedUser->id
                        )
                        ->delete();

                    AuditLog::query()->create([
                        'performed_by_user_id' =>
                            $lockedUser->id,
                        'table_name' => 'users',
                        'record_id' =>
                            $lockedUser->id,
                        'action_type' =>
                            'PASSWORD_RESET',
                        'details' => [
                            'invalidated_sessions' =>
                                $invalidatedSessions,
                        ],
                        'performed_at' => $resetAt,
                    ]);
                }, attempts: 3);

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->invalidTokenResponse(
                $request
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' =>
                    'Password reset successfully.',
            ]);
        }

        return redirect()
            ->route('login.page')
            ->with(
                'status',
                'Contraseña restablecida correctamente. Ya puedes iniciar sesión.'
            );
    }

    /**
     * Return the generic invalid-token response.
     */
    private function invalidTokenResponse(
        Request $request
    ): JsonResponse|RedirectResponse {
        $message =
            'The password reset token is invalid or has expired.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'errors' => [
                    'token' => [
                        $message,
                    ],
                ],
            ], 422);
        }

        return back()
            ->withInput([
                'email' => $request->input('email'),
            ])
            ->withErrors([
                'token' =>
                    'El enlace para restablecer la contraseña es inválido o ha vencido.',
            ]);
    }
}