<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * Solamente una cuenta activa puede cambiar su contraseña.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->accountStatus()
            ->where('status_name', 'ACTIVE')
            ->exists();
    }

    /**
     * Reglas de validación para el cambio de contraseña.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
                'current_password:web',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'confirmed',
                'different:current_password',
            ],
        ];
    }

    /**
     * Mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' =>
                'The current password is required.',
            'current_password.current_password' =>
                'The current password is incorrect.',
            'password.required' =>
                'The new password is required.',
            'password.min' =>
                'The new password must contain at least 8 characters.',
            'password.max' =>
                'The new password may not exceed 255 characters.',
            'password.confirmed' =>
                'The password confirmation does not match.',
            'password.different' =>
                'The new password must be different from the current password.',
        ];
    }
}
