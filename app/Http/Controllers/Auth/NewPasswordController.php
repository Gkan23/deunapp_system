<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class NewPasswordController extends Controller
{
    /**
     * Entrega al cliente los datos incluidos en
     * el enlace de restablecimiento.
     */
    public function create(
        Request $request,
        string $token
    ): JsonResponse {
        return response()->json([
            'data' => [
                'token' => $token,
                'email' => strtolower(
                    trim(
                        (string) $request->query(
                            'email',
                            ''
                        )
                    )
                ),
            ],
        ]);
    }

    /**
     * Restablece la contraseña utilizando el token.
     */
    public function store(
        ResetPasswordRequest $request
    ): JsonResponse {
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
            return $this->invalidTokenResponse();
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

                    /*
                     * User utiliza el cast "hashed", por lo que
                     * la contraseña se cifra automáticamente.
                     */
                    $lockedUser->forceFill([
                        'password' => $password,
                        'remember_token' =>
                            Str::random(60),
                    ])->save();

                    /*
                     * Elimina las sesiones anteriores del usuario.
                     */
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
            return $this->invalidTokenResponse();
        }

        return response()->json([
            'message' =>
                'Password reset successfully.',
        ]);
    }

    /**
     * Respuesta genérica para tokens inválidos,
     * vencidos o correspondientes a cuentas inactivas.
     */
    private function invalidTokenResponse(): JsonResponse
    {
        return response()->json([
            'message' =>
                'The password reset token is invalid or has expired.',
            'errors' => [
                'token' => [
                    'The password reset token is invalid or has expired.',
                ],
            ],
        ], 422);
    }
}