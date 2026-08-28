<?php

namespace App\Http\Requests;

use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreSupportMessageRequest extends FormRequest
{
    /**
     * Autoriza la respuesta mediante SupportTicketPolicy.
     *
     * Pueden intentar responder:
     *
     * - el cliente propietario;
     * - el agente de soporte asignado;
     * - un administrador.
     *
     * AddSupportMessageService aplicará las reglas
     * adicionales del dominio.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        $supportTicket = $this->route(
            'supportTicket'
        );

        if (
            $user === null
            || ! $supportTicket instanceof SupportTicket
        ) {
            return false;
        }

        return Gate::forUser($user)->allows(
            'reply',
            $supportTicket
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => [
                'required',
                'string',
            ],
            'attachment_url' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}