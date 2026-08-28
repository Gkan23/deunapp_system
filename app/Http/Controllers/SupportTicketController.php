<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupportTicketRequest;
use App\Models\Shipment;
use App\Models\SupportTicket;
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
     * Muestra un ticket específico junto con sus relaciones.
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
            'assignedTo',
            'messages.user',
        ]);

        return response()->json([
            'data' => $supportTicket,
        ]);
    }
}