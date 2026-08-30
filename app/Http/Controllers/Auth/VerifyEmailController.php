<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class VerifyEmailController extends Controller
{
    /**
     * Confirma el correo mediante una URL firmada.
     */
    public function __invoke(
        EmailVerificationRequest $request
    ): JsonResponse {
        $verifiedNow = false;

        $user = DB::transaction(function () use (
            $request,
            &$verifiedNow
        ): User {
            $lockedUser = User::query()
                ->whereKey(
                    $request->user()->getKey()
                )
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUser->hasVerifiedEmail()) {
                return $lockedUser;
            }

            $verifiedNow = $lockedUser
                ->markEmailAsVerified();

            if ($verifiedNow) {
                $verifiedAt = now();

                AuditLog::query()->create([
                    'performed_by_user_id' =>
                        $lockedUser->id,
                    'table_name' => 'users',
                    'record_id' =>
                        $lockedUser->id,
                    'action_type' =>
                        'EMAIL_VERIFIED',
                    'details' => [
                        'email' =>
                            $lockedUser->email,
                    ],
                    'performed_at' => $verifiedAt,
                ]);
            }

            return $lockedUser->fresh();
        }, attempts: 3);

        if ($verifiedNow) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => $verifiedNow
                ? 'Email verified successfully.'
                : 'The email address is already verified.',
            'data' => [
                'email' => $user->email,
                'email_verified' =>
                    $user->hasVerifiedEmail(),
                'email_verified_at' =>
                    $user->email_verified_at,
            ],
        ]);
    }
}