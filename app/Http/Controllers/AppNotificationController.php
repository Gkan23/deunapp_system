<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Services\Notification\MarkAllAppNotificationsAsReadService;
use App\Services\Notification\MarkAppNotificationAsReadService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AppNotificationController extends Controller
{
    /**
     * Mostrar las notificaciones del usuario autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize(
            'viewAny',
            AppNotification::class
        );

        $notifications = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->with('notificationType')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $notifications,
        ]);
    }

    /**
     * Mostrar una notificación específica.
     */
    public function show(
        Request $request,
        AppNotification $appNotification
    ): JsonResponse {
        Gate::forUser($request->user())->authorize(
            'view',
            $appNotification
        );

        $appNotification->load('notificationType');

        return response()->json([
            'notification' => $appNotification,
        ]);
    }

    /**
     * Marcar una notificación como leída.
     */
    public function markAsRead(
        Request $request,
        AppNotification $appNotification,
        MarkAppNotificationAsReadService $service
    ): JsonResponse {
        Gate::forUser($request->user())->authorize(
            'markAsRead',
            $appNotification
        );

        try {
            $notification = $service->execute(
                $appNotification,
                $request->user()
            );

            return response()->json([
                'message' => 'Notification marked as read.',
                'notification' => $notification,
            ]);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Marcar todas las notificaciones del usuario como leídas.
     */
    public function markAllAsRead(
        Request $request,
        MarkAllAppNotificationsAsReadService $service
    ): JsonResponse {
        Gate::forUser($request->user())->authorize(
            'markAllAsRead',
            AppNotification::class
        );

        try {
            $updatedCount = $service->execute(
                $request->user()
            );

            return response()->json([
                'message' => 'Notifications marked as read.',
                'updated_count' => $updatedCount,
            ]);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
