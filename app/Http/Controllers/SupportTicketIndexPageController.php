<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\TicketCategory;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class SupportTicketIndexPageController extends Controller
{
    /**
     * Muestra los tickets visibles para el usuario.
     */
    public function __invoke(
        Request $request
    ): View {
        Gate::authorize(
            'viewAny',
            SupportTicket::class
        );

        $user = $request->user()
            ->loadMissing('role');

        $roleName = $user->role?->role_name;

        abort_unless(
            in_array(
                $roleName,
                [
                    'CUSTOMER',
                    'SUPPORT_AGENT',
                    'ADMINISTRATOR',
                ],
                true
            ),
            Response::HTTP_FORBIDDEN
        );

        $search = trim(
            (string) $request->query(
                'search',
                ''
            )
        );

        $selectedStatus = strtoupper(
            trim(
                (string) $request->query(
                    'status',
                    ''
                )
            )
        );

        $selectedCategory = strtoupper(
            trim(
                (string) $request->query(
                    'category',
                    ''
                )
            )
        );

        $visibleQuery = $this->visibleQuery(
            $user,
            $roleName
        );

        $totalTickets = (
            clone $visibleQuery
        )->count();

        $openTickets = (
            clone $visibleQuery
        )
            ->whereHas(
                'status',
                fn (Builder $query) =>
                    $query->whereNotIn(
                        'status_name',
                        [
                            'RESOLVED',
                            'CLOSED',
                        ]
                    )
            )
            ->count();

        $closedTickets = (
            clone $visibleQuery
        )
            ->whereHas(
                'status',
                fn (Builder $query) =>
                    $query->whereIn(
                        'status_name',
                        [
                            'RESOLVED',
                            'CLOSED',
                        ]
                    )
            )
            ->count();

        $ticketsQuery = $this->visibleQuery(
            $user,
            $roleName
        )
            ->with([
                'customer.user',
                'shipment',
                'category',
                'status',
                'priority',
                'assignedTo.role',
            ])
            ->withCount('messages')
            ->latest('id');

        if ($search !== '') {
            $ticketsQuery->where(
                function (
                    Builder $query
                ) use ($search): void {
                    $query
                        ->where(
                            'subject',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhereHas(
                            'shipment',
                            fn (Builder $shipmentQuery) =>
                                $shipmentQuery->where(
                                    'tracking_code',
                                    'like',
                                    '%'.$search.'%'
                                )
                        )
                        ->orWhereHas(
                            'customer.user',
                            fn (Builder $userQuery) =>
                                $userQuery
                                    ->where(
                                        'name',
                                        'like',
                                        '%'.$search.'%'
                                    )
                                    ->orWhere(
                                        'email',
                                        'like',
                                        '%'.$search.'%'
                                    )
                        );
                }
            );
        }

        if ($selectedStatus !== '') {
            $ticketsQuery->whereHas(
                'status',
                fn (Builder $query) =>
                    $query->where(
                        'status_name',
                        $selectedStatus
                    )
            );
        }

        if ($selectedCategory !== '') {
            $ticketsQuery->whereHas(
                'category',
                fn (Builder $query) =>
                    $query->where(
                        'category_name',
                        $selectedCategory
                    )
            );
        }

        $tickets = $ticketsQuery
            ->paginate(15)
            ->withQueryString();

        return view(
            'support-tickets.index',
            [
                'user' => $user,
                'roleName' => $roleName,
                'tickets' => $tickets,
                'categories' =>
                    TicketCategory::query()
                        ->orderBy('category_name')
                        ->get(),
                'statuses' =>
                    TicketStatus::query()
                        ->orderBy('status_name')
                        ->get(),
                'search' => $search,
                'selectedStatus' =>
                    $selectedStatus,
                'selectedCategory' =>
                    $selectedCategory,
                'totalTickets' =>
                    $totalTickets,
                'openTickets' =>
                    $openTickets,
                'closedTickets' =>
                    $closedTickets,
            ]
        );
    }

    /**
     * Aplica el alcance de visibilidad según el rol.
     */
    private function visibleQuery(
        User $user,
        string $roleName
    ): Builder {
        $query = SupportTicket::query();

        if ($roleName === 'CUSTOMER') {
            $query->whereHas(
                'customer',
                fn (Builder $customerQuery) =>
                    $customerQuery->where(
                        'user_id',
                        $user->id
                    )
            );
        }

        return $query;
    }
}