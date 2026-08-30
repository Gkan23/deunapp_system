<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmailRequest extends FormRequest
{
    /**
     * Solamente una cuenta activa puede cambiar
     * su dirección de correo.
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'current_password' => [
                'required',
                'string',
                'current_password:web',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::notIn([
                    strtolower(
                        (string) $user?->email
                    ),
                ]),
                Rule::unique(
                    'users',
                    'email'
                )->ignore($user?->getKey()),
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' =>
                'The current password is incorrect.',
            'email.not_in' =>
                'The new email must be different from the current email.',
            'email.unique' =>
                'The email has already been registered.',
        ];
    }
}
