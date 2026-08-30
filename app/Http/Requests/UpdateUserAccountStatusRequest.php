<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateUserAccountStatusRequest extends FormRequest
{
    /**
     * Autoriza el cambio mediante UserPolicy.
     */
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
            'changeAccountStatus',
            $targetUser
        );
    }

    /**
     * Normaliza el estado y el comentario.
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();
        $normalized = [];

        if (array_key_exists('status', $input)) {
            $status = strtoupper(
                trim(
                    (string) $this->input('status')
                )
            );

            $normalized['status'] = $status === ''
                ? null
                : $status;
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
            'status' => [
                'required',
                'string',
                Rule::exists(
                    'account_statuses',
                    'status_name'
                ),
            ],
            'comment' => [
                Rule::requiredIf(
                    fn (): bool => in_array(
                        $this->input('status'),
                        [
                            'SUSPENDED',
                            'BLOCKED',
                        ],
                        true
                    )
                ),
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}
