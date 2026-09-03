<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Services\Notification\MarkAllAppNotificationsAsReadService;
use App\Services\Notification\MarkAppNotificationAsReadService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PortalAppNotificationController extends Controller
{
    public function markAsRead(
        Request $request,
        AppNotification $appNotification,
        MarkAppNotificationAsReadService $service
    ): RedirectResponse {
        Gate::forUser($request->user())->authorize(
            'markAsRead',
            $appNotification
        );

        try {
            $service->execute(
                $appNotification,
                $request->user()
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'notification' =>
                    $exception->getMessage(),
            ]);
        }

        return back()->with(
            'status',
            'La notificación fue marcada como leída.'
        );
    }

    public function markAllAsRead(
        Request $request,
        MarkAllAppNotificationsAsReadService $service
    ): RedirectResponse {
        Gate::forUser($request->user())->authorize(
            'markAllAsRead',
            AppNotification::class
        );

        try {
            $updatedCount = $service->execute(
                $request->user()
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'notification' =>
                    $exception->getMessage(),
            ]);
        }

        return back()->with(
            'status',
            $updatedCount === 1
                ? 'Se marcó una notificación como leída.'
                : sprintf(
                    'Se marcaron %d notificaciones como leídas.',
                    $updatedCount
                )
        );
    }
}