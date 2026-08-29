<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    /**
     * El registro es un endpoint público.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas para registrar un cliente.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'confirmed',
            ],
            'customer_type' => [
                'required',
                'string',
                Rule::in([
                    'INDIVIDUAL',
                    'BUSINESS',
                ]),
                Rule::exists(
                    'customer_types',
                    'type_name'
                ),
            ],
            'identity_number' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique(
                    'customers',
                    'identity_number'
                ),
            ],
            'company_name' => [
                'exclude_unless:customer_type,BUSINESS',
                'required_if:customer_type,BUSINESS',
                'nullable',
                'string',
                'max:150',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],
        ];
    }

    /**
     * Normaliza los datos antes de validarlos.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(
                (string) $this->input('name', '')
            ),
            'email' => strtolower(
                trim(
                    (string) $this->input('email', '')
                )
            ),
            'customer_type' => strtoupper(
                trim(
                    (string) $this->input(
                        'customer_type',
                        ''
                    )
                )
            ),
            'identity_number' => $this->nullableString(
                $this->input('identity_number')
            ),
            'company_name' => $this->nullableString(
                $this->input('company_name')
            ),
            'phone' => $this->nullableString(
                $this->input('phone')
            ),
        ]);
    }

    /**
     * Convierte cadenas vacías en null.
     */
    private function nullableString(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $normalizedValue = trim(
            (string) $value
        );

        return $normalizedValue === ''
            ? null
            : $normalizedValue;
    }
}