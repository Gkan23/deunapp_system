<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignSupportTicketRequest;
use App\Http\Requests\StoreSupportMessageRequest;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\AddSupportMessageService;
use App\Services\Support\AssignSupportTicketService;
use App\Services\Support\CreateSupportTicketService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SupportTicketController extends Controller
{
    /**
     * Lista los tickets visibles para el usuario autenticado.
     *
     * Los clientes solamente reciben sus propios tickets.
     * Soporte y administración reciben todos los tickets.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize(
            'viewAny',
            SupportTicket::class
        );

        $user = $request->user();

        $roleName = $user->role()
            ->value('role_name');

        $query = SupportTicket::query()
            ->with([
                'customer.user',
                'shipment',
                'category',
                'status',
                'priority',
                'assignedTo',
                'messages.user',
            ])
            ->orderByDesc('id');

        if ($roleName === 'CUSTOMER') {
            $query->whereHas(
                'customer',
                fn ($query) => $query->where(
                    'user_id',
                    $user->id
                )
            );
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    /**
     * Crea un ticket con su mensaje inicial.
     */
    public function store(
        StoreSupportTicketRequest $request,
        CreateSupportTicketService $service
    ): JsonResponse {
        $validated = $request->validated();

        $customer = $request->user()
            ->customer()
            ->firstOrFail();

        $shipment = null;

        if (isset($validated['shipment_id'])) {
            $shipment = Shipment::query()
                ->findOrFail(
                    $validated['shipment_id']
                );
        }

        try {
            $ticket = $service->execute(
                customer: $customer,
                categoryName: $validated['category'],
                subject: $validated['subject'],
                message: $validated['message'],
                shipment: $shipment,
                attachmentUrl: $validated[
                    'attachment_url'
                ] ?? null
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Support ticket created successfully.',
            'data' => $ticket,
        ], 201);
    }

    /**
     * Asigna o reasigna un ticket.
     */
    public function assign(
        AssignSupportTicketRequest $request,
        SupportTicket $supportTicket,
        AssignSupportTicketService $service
    ): JsonResponse {
        $validated = $request->validated();

        $assignee = User::query()
            ->findOrFail(
                $validated['assigned_to_user_id']
            );

        try {
            $assignedTicket = $service->execute(
                ticket: $supportTicket,
                assignee: $assignee,
                performedBy: $request->user()
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Support ticket assigned successfully.',
            'data' => $assignedTicket,
        ]);
    }

    /**
     * Agrega una respuesta a un ticket.
     *
     * AddSupportMessageService comprueba que el usuario
     * pueda participar y actualiza automáticamente el estado.
     */
    public function reply(
        StoreSupportMessageRequest $request,
        SupportTicket $supportTicket,
        AddSupportMessageService $service
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $supportMessage = $service->execute(
                ticket: $supportTicket,
                sender: $request->user(),
                message: $validated['message'],
                attachmentUrl: $validated[
                    'attachment_url'
                ] ?? null
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Support message sent successfully.',
            'data' => $supportMessage,
        ], 201);
    }

    /**
     * Muestra un ticket específico y sus relaciones.
     */
    public function show(
        SupportTicket $supportTicket
    ): JsonResponse {
        Gate::authorize(
            'view',
            $supportTicket
        );

        $supportTicket->load([
            'customer.user',
            'shipment',
            'category',
            'status',
            'priority',
            'assignedTo.role',
            'messages.user',
        ]);

        return response()->json([
            'data' => $supportTicket,
        ]);
    }
}