<?php

namespace App\Http\Requests;

use App\Models\Courier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateCourierAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $courier = Courier::query()
            ->where('user_id', $user->id)
            ->first();

        return $courier !== null
            && Gate::forUser($user)->allows(
                'changeAvailability',
                $courier
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_available' => [
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

        if ($this->has('is_available')) {
            $value = $this->input(
                'is_available'
            );

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

            $preparedData['is_available'] =
                $value;
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
            'is_available.required' =>
                'The courier availability is required.',
            'is_available.boolean' =>
                'The courier availability must be true or false.',
            'comment.required' =>
                'A comment is required to change courier availability.',
            'comment.max' =>
                'The comment may not exceed 500 characters.',
        ];
    }
}
