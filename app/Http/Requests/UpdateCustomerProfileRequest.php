<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCustomerProfileRequest extends FormRequest
{
    /**
     * Solamente un cliente con una cuenta activa
     * puede modificar su perfil.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $hasActiveAccount = $user->accountStatus()
            ->where('status_name', 'ACTIVE')
            ->exists();

        $hasCustomerRole = $user->role()
            ->where('role_name', 'CUSTOMER')
            ->exists();

        $hasCustomerProfile = $user->customer()
            ->exists();

        return $hasActiveAccount
            && $hasCustomerRole
            && $hasCustomerProfile;
    }

    /**
     * Reglas para modificar el perfil.
     *
     * Todos los campos usan "sometimes" porque el endpoint
     * acepta actualizaciones parciales.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $customerId = $this->user()
            ?->customer()
            ->value('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'customer_type' => [
                'sometimes',
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
                'sometimes',
                'nullable',
                'string',
                'max:30',
                Rule::unique(
                    'customers',
                    'identity_number'
                )->ignore($customerId),
            ],
            'company_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:150',
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],
        ];
    }

    /**
     * Comprueba que un perfil empresarial tenga empresa.
     */
    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(function (
            Validator $validator
        ): void {
            $customer = $this->user()
                ?->customer()
                ->with('customerType')
                ->first();

            if ($customer === null) {
                return;
            }

            $effectiveCustomerType = $this->input(
                'customer_type',
                $customer->customerType->type_name
            );

            $input = $this->all();

            $effectiveCompanyName = array_key_exists(
                'company_name',
                $input
            )
                ? $this->input('company_name')
                : $customer->company_name;

            if (
                $effectiveCustomerType === 'BUSINESS'
                && (
                    $effectiveCompanyName === null
                    || trim(
                        (string) $effectiveCompanyName
                    ) === ''
                )
            ) {
                $validator->errors()->add(
                    'company_name',
                    'The company name is required for a business customer.'
                );
            }
        });
    }

    /**
     * Normaliza solamente los campos enviados.
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();
        $normalized = [];

        if (array_key_exists('name', $input)) {
            $normalized['name'] = trim(
                (string) $this->input('name')
            );
        }

        if (array_key_exists('customer_type', $input)) {
            $normalized['customer_type'] = strtoupper(
                trim(
                    (string) $this->input(
                        'customer_type'
                    )
                )
            );
        }

        foreach ([
            'identity_number',
            'company_name',
            'phone',
        ] as $field) {
            if (array_key_exists($field, $input)) {
                $normalized[$field] =
                    $this->nullableString(
                        $this->input($field)
                    );
            }
        }

        $this->merge($normalized);
    }

    /**
     * Convierte una cadena vacía en null.
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