<?php

namespace App\Http\Requests;

use App\Models\Courier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CreateCourierRequest extends FormRequest
{
    /**
     * Autoriza mediante CourierPolicy.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return Gate::forUser($user)->allows(
            'create',
            Courier::class
        );
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
            'license_number' =>
                $this->nullableString(
                    $this->input(
                        'license_number'
                    )
                ),
            'comment' => trim(
                (string) $this->input(
                    'comment',
                    ''
                )
            ),
        ]);
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
            'license_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique(
                    'couriers',
                    'license_number'
                ),
            ],
            'comment' => [
                'required',
                'string',
                'max:500',
            ],
        ];
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