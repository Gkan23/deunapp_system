<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SupportTicketShowPageController extends Controller
{
    /**
     * Transiciones de estado disponibles para mostrar
     * en el formulario de la página.
     *
     * El servicio UpdateSupportTicketStatusService
     * seguirá aplicando la validación definitiva.
     *
     * @var array<string, array<int, string>>
     */
    private const STATUS_TRANSITIONS = [
        'OPEN' => [
            'IN_PROGRESS',
        ],
        'IN_PROGRESS' => [
            'WAITING_CUSTOMER',
            'RESOLVED',
        ],
        'WAITING_CUSTOMER' => [
            'IN_PROGRESS',
            'RESOLVED',
        ],
        'RESOLVED' => [
            'IN_PROGRESS',
            'CLOSED',
        ],
        'CLOSED' => [],
    ];

    /**
     * Muestra el detalle y la conversación
     * de un ticket de soporte.
     */
    public function __invoke(
        Request $request,
        SupportTicket $supportTicket
    ): View {
        Gate::authorize(
            'view',
            $supportTicket
        );

        $user = $request->user()
            ->loadMissing([
                'role',
                'accountStatus',
                'customer',
            ]);

        $supportTicket->load([
            'customer.user',
            'shipment',
            'category',
            'status',
            'priority',
            'assignedTo.role',
            'messages' => function (
                $query
            ): void {
                $query
                    ->with([
                        'user.role',
                    ])
                    ->orderBy('sent_at')
                    ->orderBy('id');
            },
        ]);

        $roleName = $user->role?->role_name;

        $statusName = $supportTicket
            ->status
            ?->status_name;

        $isCustomerOwner = (
            $roleName === 'CUSTOMER'
            && (int) $supportTicket
                ->customer
                ?->user_id
                === (int) $user->id
        );

        $isAssignedSupportUser = (
            in_array(
                $roleName,
                [
                    'SUPPORT_AGENT',
                    'ADMINISTRATOR',
                ],
                true
            )
            && (int) $supportTicket
                ->assigned_to_user_id
                === (int) $user->id
        );

        /*
         * El servicio permite responder al cliente
         * propietario o al usuario de soporte que
         * está asignado al ticket.
         */
        $canReply = $user->can(
            'reply',
            $supportTicket
        ) && (
            $isCustomerOwner
            || $isAssignedSupportUser
        ) && $statusName !== 'CLOSED';

        /*
         * Solamente estos estados permiten asignación
         * o reasignación.
         */
        $assignableStatus = in_array(
            $statusName,
            [
                'OPEN',
                'IN_PROGRESS',
                'WAITING_CUSTOMER',
            ],
            true
        );

        $canAssign = $user->can(
            'assign',
            $supportTicket
        ) && $assignableStatus;

        /*
         * Por defecto no se entregan opciones
         * para el formulario de asignación.
         */
        $supportUsers = collect();

        if ($canAssign) {
            $supportUsersQuery = User::query()
                ->with([
                    'role',
                    'accountStatus',
                ])
                ->whereHas(
                    'role',
                    fn (Builder $query) =>
                        $query->whereIn(
                            'role_name',
                            [
                                'SUPPORT_AGENT',
                                'ADMINISTRATOR',
                            ]
                        )
                )
                ->whereHas(
                    'accountStatus',
                    fn (Builder $query) =>
                        $query->where(
                            'status_name',
                            'ACTIVE'
                        )
                )
                ->orderBy('name');

            /*
             * Un agente de soporte solamente puede
             * reclamar para sí mismo un ticket que
             * todavía no tenga usuario asignado.
             *
             * Si el ticket ya está asignado, se genera
             * una consulta vacía para no mostrar el
             * formulario de asignación.
             */
            if ($roleName === 'SUPPORT_AGENT') {
                if (
                    $supportTicket
                        ->assigned_to_user_id
                    !== null
                ) {
                    $supportUsersQuery
                        ->whereRaw('1 = 0');
                } else {
                    $supportUsersQuery
                        ->whereKey($user->id);
                }
            }

            $supportUsers = $supportUsersQuery
                ->get();
        }

        /*
         * Obtiene únicamente los estados a los que
         * puede avanzar el ticket desde su estado actual.
         */
        $allowedStatusNames =
            self::STATUS_TRANSITIONS[
                $statusName
            ] ?? [];

        $availableStatuses = TicketStatus::query()
            ->whereIn(
                'status_name',
                $allowedStatusNames
            )
            ->orderBy('id')
            ->get();

        /*
         * La policy permite que administración cambie
         * cualquier ticket y que el agente cambie
         * solamente el ticket que tiene asignado.
         */
        $canChangeStatus = $user->can(
            'changeStatus',
            $supportTicket
        ) && $availableStatuses->isNotEmpty();

        /*
         * Cuenta exclusivamente los mensajes enviados
         * por otro participante que aún no fueron leídos.
         */
        $unreadMessageCount = $supportTicket
            ->messages
            ->where(
                'user_id',
                '!=',
                $user->id
            )
            ->where(
                'is_read',
                false
            )
            ->count();

        $canMarkMessagesAsRead = (
            $unreadMessageCount > 0
            && $user->can(
                'readMessages',
                $supportTicket
            )
        );

        return view(
            'support-tickets.show',
            [
                'user' => $user,
                'roleName' => $roleName,
                'supportTicket' =>
                    $supportTicket,
                'canReply' => $canReply,
                'canAssign' => $canAssign,
                'canChangeStatus' =>
                    $canChangeStatus,
                'canMarkMessagesAsRead' =>
                    $canMarkMessagesAsRead,
                'unreadMessageCount' =>
                    $unreadMessageCount,
                'supportUsers' =>
                    $supportUsers,
                'availableStatuses' =>
                    $availableStatuses,
            ]
        );
    }
}