<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
{
    /**
     * Utiliza UserPolicy para autorizar el listado.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return Gate::forUser($user)->allows(
            'viewAny',
            User::class
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
            'role' => [
                'nullable',
                'string',
                Rule::exists(
                    'roles',
                    'role_name'
                ),
            ],
            'account_status' => [
                'nullable',
                'string',
                Rule::exists(
                    'account_statuses',
                    'status_name'
                ),
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    /**
     * Normaliza filtros textuales.
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();
        $normalized = [];

        if (array_key_exists('search', $input)) {
            $search = trim(
                (string) $this->input('search')
            );

            $normalized['search'] = $search === ''
                ? null
                : $search;
        }

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

        if (
            array_key_exists(
                'account_status',
                $input
            )
        ) {
            $status = strtoupper(
                trim(
                    (string) $this->input(
                        'account_status'
                    )
                )
            );

            $normalized['account_status'] =
                $status === ''
                    ? null
                    : $status;
        }

        $this->merge($normalized);
    }
}