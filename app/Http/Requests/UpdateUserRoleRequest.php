<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $performedBy = $this->user();
        $targetUser = $this->route('user');

        if (
            $performedBy === null
            || ! $targetUser instanceof User
        ) {
            return false;
        }

        return Gate::forUser($performedBy)->allows(
            'changeRole',
            $targetUser
        );
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();
        $normalized = [];

        if (array_key_exists('role', $input)) {
            $role = strtoupper(
                trim(
                    (string) $this->input('role')
                )
            );

            $normalized['role'] = $role === ''
                ? null
                : $role;
        }

        if (array_key_exists('comment', $input)) {
            $comment = trim(
                (string) $this->input('comment')
            );

            $normalized['comment'] = $comment === ''
                ? null
                : $comment;
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => [
                'required',
                'string',
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