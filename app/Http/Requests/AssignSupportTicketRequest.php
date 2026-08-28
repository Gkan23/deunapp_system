<?php

namespace App\Http\Requests;

use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssignSupportTicketRequest extends FormRequest
{
    /**
     * Autoriza la asignación mediante SupportTicketPolicy.
     *
     * La policy permite acceder a esta acción solamente
     * a agentes de soporte y administradores activos.
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
            'assign',
            $supportTicket
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_to_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
        ];
    }
}