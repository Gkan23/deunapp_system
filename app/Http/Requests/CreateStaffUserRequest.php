<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CreateStaffUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $performedBy = $this->user();

        if ($performedBy === null) {
            return false;
        }

        return Gate::forUser($performedBy)->allows(
            'createStaff',
            User::class
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(
                (string) $this->input('name', '')
            ),
            'email' => strtolower(
                trim(
                    (string) $this->input(
                        'email',
                        ''
                    )
                )
            ),
            'role' => strtoupper(
                trim(
                    (string) $this->input(
                        'role',
                        ''
                    )
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
            'role' => [
                'required',
                'string',
                Rule::in([
                    'SUPPORT_AGENT',
                    'ADMINISTRATOR',
                ]),
                Rule::exists(
                    'roles',
                    'role_name'
                ),
            ],
            'comment' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }
}
