<?php

namespace App\Http\Requests;

use App\Models\Courier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateCourierStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $courier = $this->route('courier');

        return $courier instanceof Courier
            && Gate::forUser($this->user())
                ->allows(
                    'changeStatus',
                    $courier
                );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_active' => [
                'required',
                'boolean',
            ],
            'comment' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $preparedData = [];

        if ($this->has('is_active')) {
            $value = $this->input('is_active');

            if (is_string($value)) {
                $normalizedValue = strtolower(
                    trim($value)
                );

                if (in_array(
                    $normalizedValue,
                    ['1', 'true'],
                    true
                )) {
                    $value = true;
                } elseif (in_array(
                    $normalizedValue,
                    ['0', 'false'],
                    true
                )) {
                    $value = false;
                }
            }

            $preparedData['is_active'] = $value;
        }

        if ($this->has('comment')) {
            $preparedData['comment'] = trim(
                (string) $this->input('comment')
            );
        }

        if ($preparedData !== []) {
            $this->merge($preparedData);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_active.required' =>
                'The courier status is required.',
            'is_active.boolean' =>
                'The courier status must be true or false.',
            'comment.required' =>
                'A comment is required to change the courier status.',
            'comment.max' =>
                'The comment may not exceed 500 characters.',
        ];
    }
}