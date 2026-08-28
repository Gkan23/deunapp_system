<?php

namespace App\Http\Requests;

use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
{
    /**
     * Solamente un cliente activo y con perfil asociado
     * puede crear tickets de soporte.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return Gate::forUser($user)->allows(
            'create',
            SupportTicket::class
        );
    }

    /**
     * Normaliza la categoría antes de validarla.
     *
     * Por ejemplo, " delivery " se transforma en "DELIVERY".
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('category')) {
            $this->merge([
                'category' => strtoupper(
                    trim(
                        (string) $this->input('category')
                    )
                ),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => [
                'required',
                'string',
                'max:100',
                Rule::exists(
                    'ticket_categories',
                    'category_name'
                ),
            ],
            'subject' => [
                'required',
                'string',
                'max:200',
            ],
            'message' => [
                'required',
                'string',
            ],
            'shipment_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'shipments',
                    'id'
                ),
            ],
            'attachment_url' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}