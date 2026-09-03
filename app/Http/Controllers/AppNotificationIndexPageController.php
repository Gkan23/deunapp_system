<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppNotificationIndexPageController extends Controller
{
    /**
     * Muestra las notificaciones del usuario.
     */
    public function __invoke(
        Request $request
    ): View {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize(
            'viewAny',
            AppNotification::class
        );

        $selectedStatus = strtolower(
            trim(
                (string) $request->query(
                    'status',
                    'all'
                )
            )
        );

        if (! in_array(
            $selectedStatus,
            [
                'all',
                'unread',
                'read',
            ],
            true
        )) {
            $selectedStatus = 'all';
        }

        $baseQuery = AppNotification::query()
            ->where('user_id', $user->id);

        $totalCount = (clone $baseQuery)
            ->count();

        $unreadCount = (clone $baseQuery)
            ->where('is_read', false)
            ->count();

        $readCount = (clone $baseQuery)
            ->where('is_read', true)
            ->count();

        $notifications = (clone $baseQuery)
            ->with('notificationType')
            ->when(
                $selectedStatus === 'unread',
                fn ($query) => $query->where(
                    'is_read',
                    false
                )
            )
            ->when(
                $selectedStatus === 'read',
                fn ($query) => $query->where(
                    'is_read',
                    true
                )
            )
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('notifications.index', [
            'user' => $user,
            'notifications' => $notifications,
            'selectedStatus' => $selectedStatus,
            'totalCount' => $totalCount,
            'unreadCount' => $unreadCount,
            'readCount' => $readCount,
        ]);
    }
}