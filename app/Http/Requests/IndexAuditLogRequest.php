<?php

namespace App\Http\Requests;

use App\Models\AuditLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can(
            'viewAny',
            AuditLog::class
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'table_name' => [
                'nullable',
                'string',
                'max:100',
            ],
            'action_type' => [
                'nullable',
                'string',
                'max:50',
            ],
            'performed_by_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'record_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'date_from' => [
                'nullable',
                'date',
            ],
            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if (
            $this->has('table_name')
            && is_string($this->input('table_name'))
        ) {
            $tableName = strtolower(
                trim($this->input('table_name'))
            );

            $data['table_name'] = $tableName === ''
                ? null
                : $tableName;
        }

        if (
            $this->has('action_type')
            && is_string($this->input('action_type'))
        ) {
            $actionType = strtoupper(
                trim($this->input('action_type'))
            );

            $data['action_type'] = $actionType === ''
                ? null
                : $actionType;
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }
}