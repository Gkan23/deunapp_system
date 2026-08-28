<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentStatusRequest extends FormRequest
{
    /**
     * Normalizar el estado antes de ejecutar la validación.
     *
     * Por ejemplo:
     *
     * in_review  -> IN_REVIEW
     * resolved   -> RESOLVED
     * closed     -> CLOSED
     */
    protected function prepareForValidation(): void
    {
        $status = $this->input('status');

        if (is_string($status)) {
            $this->merge([
                'status' => strtoupper(trim($status)),
            ]);
        }
    }

    /**
     * La ruta ya se encuentra protegida por el middleware auth.
     *
     * La autorización específica sobre la incidencia se ejecuta
     * en IncidentController después de validar el estado.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validar el nuevo estado y el comentario.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::exists(
                    'incident_statuses',
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
     * Nombres legibles de los campos.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => 'estado de la incidencia',
            'comment' => 'comentario',
        ];
    }
}