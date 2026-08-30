<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegisterDeliveryProviderRequest extends FormRequest
{
    /**
     * El registro del proveedor es público.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
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
            'provider_type' => [
                'required',
                'string',
                Rule::in([
                    'INDEPENDENT',
                    'COMPANY',
                ]),
                Rule::exists(
                    'provider_types',
                    'type_name'
                ),
            ],
            'business_name' => [
                'nullable',
                'string',
                'max:150',
            ],
            'identity_number' => [
                'required',
                'string',
                'max:30',
                Rule::unique(
                    'delivery_providers',
                    'identity_number'
                ),
            ],
            'phone' => [
                'required',
                'string',
                'max:30',
            ],
        ];
    }

    /**
     * Las empresas necesitan un nombre comercial.
     */
    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(function (
            Validator $validator
        ): void {
            if (
                $this->input('provider_type')
                !== 'COMPANY'
            ) {
                return;
            }

            $businessName = $this->input(
                'business_name'
            );

            if (
                $businessName === null
                || trim(
                    (string) $businessName
                ) === ''
            ) {
                $validator->errors()->add(
                    'business_name',
                    'The business name is required for a company provider.'
                );
            }
        });
    }

    /**
     * Normaliza los datos antes de validarlos.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(
                (string) $this->input(
                    'name',
                    ''
                )
            ),
            'email' => strtolower(
                trim(
                    (string) $this->input(
                        'email',
                        ''
                    )
                )
            ),
            'provider_type' => strtoupper(
                trim(
                    (string) $this->input(
                        'provider_type',
                        ''
                    )
                )
            ),
            'business_name' =>
                $this->nullableString(
                    $this->input(
                        'business_name'
                    )
                ),
            'identity_number' => trim(
                (string) $this->input(
                    'identity_number',
                    ''
                )
            ),
            'phone' => trim(
                (string) $this->input(
                    'phone',
                    ''
                )
            ),
        ]);
    }

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
