<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class CurrentUserPasswordController extends Controller
{
    /**
     * Update the authenticated user's password.
     */
    public function update(
        UpdatePasswordRequest $request
    ): JsonResponse|RedirectResponse {
        $validated = $request->validated();

        DB::transaction(function () use (
            $request,
            $validated
        ): void {
            $user = User::query()
                ->whereKey(
                    $request->user()->getKey()
                )
                ->lockForUpdate()
                ->firstOrFail();

            $changedAt = now();

            $user->update([
                'password' =>
                    $validated['password'],
            ]);

            AuditLog::query()->create([
                'performed_by_user_id' =>
                    $user->id,
                'table_name' => 'users',
                'record_id' => $user->id,
                'action_type' =>
                    'PASSWORD_CHANGED',
                'details' => [
                    'user_id' => $user->id,
                ],
                'performed_at' => $changedAt,
            ]);
        }, attempts: 3);

        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return response()->json([
                'message' =>
                    'Password updated successfully.',
            ]);
        }

        return redirect()
            ->route('current-user.settings')
            ->with(
                'status',
                'Contraseña actualizada correctamente.'
            );
    }
}