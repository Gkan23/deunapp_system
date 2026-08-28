<?php

namespace App\Http\Requests;

use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupportTicketStatusRequest extends FormRequest
{
    /**
     * Determina si el usuario puede modificar el estado
     * del ticket recibido mediante la ruta.
     */
    public function authorize(): bool
    {
        $supportTicket = $this->route(
            'supportTicket'
        );

        if (! $supportTicket instanceof SupportTicket) {
            return false;
        }

        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can(
            'changeStatus',
            $supportTicket
        );
    }

    /**
     * Reglas para actualizar el estado del ticket.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                'max:50',
                Rule::exists(
                    'ticket_statuses',
                    'status_name'
                ),
            ],
            'comment' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * Normaliza los datos antes de validarlos.
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        if (
            $this->has('status')
            && is_string($this->input('status'))
        ) {
            $data['status'] = strtoupper(
                trim($this->input('status'))
            );
        }

        if (
            $this->has('comment')
            && is_string($this->input('comment'))
        ) {
            $comment = trim(
                $this->input('comment')
            );

            $data['comment'] = $comment === ''
                ? null
                : $comment;
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }
}