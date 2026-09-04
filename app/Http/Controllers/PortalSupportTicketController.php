<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignSupportTicketRequest;
use App\Http\Requests\StoreSupportMessageRequest;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Http\Requests\UpdateSupportTicketStatusRequest;
use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\AddSupportMessageService;
use App\Services\Support\AssignSupportTicketService;
use App\Services\Support\CreateSupportTicketService;
use App\Services\Support\MarkSupportMessagesAsReadService;
use App\Services\Support\UpdateSupportTicketStatusService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PortalSupportTicketController extends Controller
{
    /**
     * Crea un ticket desde el formulario Blade.
     */
    public function store(
        StoreSupportTicketRequest $request,
        CreateSupportTicketService $service
    ): RedirectResponse {
        $validated = $request->validated();

        /*
         * El cliente siempre se obtiene de la sesión.
         * No se acepta customer_id desde el formulario.
         */
        $customer = $request->user()
            ->customer()
            ->firstOrFail();

        $shipment = null;

        if (
            ($validated['shipment_id'] ?? null)
            !== null
        ) {
            $shipment = Shipment::query()
                ->where(
                    'customer_id',
                    $customer->id
                )
                ->whereKey(
                    $validated['shipment_id']
                )
                ->first();

            if ($shipment === null) {
                throw ValidationException::withMessages([
                    'shipment_id' =>
                        'El envío seleccionado no está disponible para tu cuenta.',
                ]);
            }
        }

        try {
            $ticket = $service->execute(
                customer: $customer,
                categoryName: $validated['category'],
                subject: $validated['subject'],
                message: $validated['message'],
                shipment: $shipment,
                attachmentUrl:
                    $validated['attachment_url'] ?? null
            );
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'ticket' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'portal.support-tickets.show',
                $ticket
            )
            ->with(
                'status',
                'El ticket de soporte fue creado correctamente.'
            );
    }

    /**
     * Agrega un mensaje desde el formulario Blade.
     */
    public function addMessage(
        StoreSupportMessageRequest $request,
        SupportTicket $supportTicket,
        AddSupportMessageService $service
    ): RedirectResponse {
        try {
            $service->execute(
                ticket: $supportTicket,
                sender: $request->user(),
                message: $request->string(
                    'message'
                )->toString(),
                attachmentUrl: $request->validated(
                    'attachment_url'
                )
            );
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'message' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'portal.support-tickets.show',
                $supportTicket
            )
            ->with(
                'status',
                'El mensaje fue enviado correctamente.'
            );
    }

    /**
     * Marca como leídos los mensajes del otro participante.
     */
    public function markMessagesAsRead(
        Request $request,
        SupportTicket $supportTicket,
        MarkSupportMessagesAsReadService $service
    ): RedirectResponse {
        Gate::authorize(
            'readMessages',
            $supportTicket
        );

        try {
            $readCount = $service->execute(
                ticket: $supportTicket,
                reader: $request->user()
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'messages' => $exception->getMessage(),
            ]);
        }

        $message = $readCount === 1
            ? 'Se marcó un mensaje como leído.'
            : sprintf(
                'Se marcaron %d mensajes como leídos.',
                $readCount
            );

        return redirect()
            ->route(
                'portal.support-tickets.show',
                $supportTicket
            )
            ->with('status', $message);
    }

    /**
     * Asigna o reclama un ticket.
     */
    public function assign(
        AssignSupportTicketRequest $request,
        SupportTicket $supportTicket,
        AssignSupportTicketService $service
    ): RedirectResponse {
        $assignee = User::query()->findOrFail(
            $request->integer(
                'assigned_to_user_id'
            )
        );

        try {
            $service->execute(
                ticket: $supportTicket,
                assignee: $assignee,
                performedBy: $request->user()
            );
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'assigned_to_user_id' =>
                        $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'portal.support-tickets.show',
                $supportTicket
            )
            ->with(
                'status',
                'El ticket fue asignado correctamente.'
            );
    }

    /**
     * Cambia el estado del ticket.
     */
    public function updateStatus(
        UpdateSupportTicketStatusRequest $request,
        SupportTicket $supportTicket,
        UpdateSupportTicketStatusService $service
    ): RedirectResponse {
        try {
            $service->execute(
                ticket: $supportTicket,
                targetStatusName: $request->string(
                    'status'
                )->toString(),
                performedBy: $request->user(),
                comment: $request->validated(
                    'comment'
                )
            );
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'status' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'portal.support-tickets.show',
                $supportTicket
            )
            ->with(
                'status',
                'El estado del ticket fue actualizado.'
            );
    }
}