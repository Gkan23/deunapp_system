<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    /**
     * El restablecimiento se autoriza mediante el token.
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
            'token' => [
                'required',
                'string',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'confirmed',
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
            'token' => trim(
                (string) $this->input('token', '')
            ),
        ]);
    }
}