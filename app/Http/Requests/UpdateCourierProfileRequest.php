<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourierProfileRequest extends FormRequest
{
    /**
     * Solamente un repartidor operativo puede
     * modificar su perfil.
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

        $hasCourierRole = $user->role()
            ->where('role_name', 'COURIER')
            ->exists();

        $hasActiveCourierProfile = $user
            ->courier()
            ->where('is_active', true)
            ->whereHas(
                'deliveryProvider',
                fn ($query) => $query->where(
                    'is_active',
                    true
                )
            )
            ->exists();

        return $hasActiveAccount
            && $hasCourierRole
            && $hasActiveCourierProfile;
    }

    /**
     * Reglas para actualizar parcialmente el perfil.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $courierId = $this->user()
            ?->courier()
            ->value('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'license_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique(
                    'couriers',
                    'license_number'
                )->ignore($courierId),
            ],
        ];
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

        if (
            array_key_exists(
                'license_number',
                $input
            )
        ) {
            $normalized['license_number'] =
                $this->nullableString(
                    $this->input('license_number')
                );
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
