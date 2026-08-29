<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDeliveryProviderProfileRequest extends FormRequest
{
    /**
     * Solamente un proveedor activo, con una cuenta activa,
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

        $hasProviderRole = $user->role()
            ->where('role_name', 'DELIVERY_PROVIDER')
            ->exists();

        $hasActiveProviderProfile = $user
            ->deliveryProvider()
            ->where('is_active', true)
            ->exists();

        return $hasActiveAccount
            && $hasProviderRole
            && $hasActiveProviderProfile;
    }

    /**
     * Reglas para actualizar parcialmente el perfil.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $providerId = $this->user()
            ?->deliveryProvider()
            ->value('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'provider_type' => [
                'sometimes',
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
                'sometimes',
                'nullable',
                'string',
                'max:150',
            ],
            'identity_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                Rule::unique(
                    'delivery_providers',
                    'identity_number'
                )->ignore($providerId),
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
     * Comprueba que un proveedor empresarial
     * tenga un nombre comercial.
     */
    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(function (
            Validator $validator
        ): void {
            $provider = $this->user()
                ?->deliveryProvider()
                ->with('providerType')
                ->first();

            if ($provider === null) {
                return;
            }

            $effectiveProviderType = $this->input(
                'provider_type',
                $provider->providerType->type_name
            );

            $input = $this->all();

            $effectiveBusinessName = array_key_exists(
                'business_name',
                $input
            )
                ? $this->input('business_name')
                : $provider->business_name;

            if (
                $effectiveProviderType === 'COMPANY'
                && (
                    $effectiveBusinessName === null
                    || trim(
                        (string) $effectiveBusinessName
                    ) === ''
                )
            ) {
                $validator->errors()->add(
                    'business_name',
                    'The business name is required for a company provider.'
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

        if (array_key_exists('provider_type', $input)) {
            $normalized['provider_type'] = strtoupper(
                trim(
                    (string) $this->input(
                        'provider_type'
                    )
                )
            );
        }

        foreach ([
            'business_name',
            'identity_number',
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
