<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CurrentUserPasswordController extends Controller
{
    /**
     * Actualiza la contraseña del usuario autenticado.
     */
    public function update(
        UpdatePasswordRequest $request
    ): JsonResponse {
        $validated = $request->validated();

        DB::transaction(function () use (
            $request,
            $validated
        ): void {
            $user = User::query()
                ->whereKey($request->user()->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $changedAt = now();

            /*
             * El modelo User tiene el cast "hashed", por lo que
             * Laravel cifra automáticamente la nueva contraseña.
             */
            $user->update([
                'password' => $validated['password'],
            ]);

            /*
             * Se registra el evento, pero nunca se almacena
             * la contraseña ni ningún dato confidencial.
             */
            AuditLog::query()->create([
                'performed_by_user_id' => $user->id,
                'table_name' => 'users',
                'record_id' => $user->id,
                'action_type' => 'PASSWORD_CHANGED',
                'details' => [
                    'user_id' => $user->id,
                ],
                'performed_at' => $changedAt,
            ]);
        }, attempts: 3);

        /*
         * Se renueva el identificador de sesión después
         * de modificar una credencial sensible.
         */
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }
}