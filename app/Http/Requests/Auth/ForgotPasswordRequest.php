<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    /**
     * La recuperación de contraseña es pública.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
        ];
    }

    /**
     * Normaliza el correo antes de validarlo.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(
                trim(
                    (string) $this->input('email', '')
                )
            ),
        ]);
    }
}
