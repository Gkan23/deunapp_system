<?php

namespace App\Http\Requests;

use App\Models\Courier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListCouriersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return Gate::forUser($user)->allows(
            'viewAny',
            Courier::class
        );
    }

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

        if (array_key_exists('is_active', $input)) {
            $normalized['is_active'] =
                $this->normalizeBoolean(
                    $this->input('is_active')
                );
        }

        if (
            array_key_exists(
                'is_available',
                $input
            )
        ) {
            $normalized['is_available'] =
                $this->normalizeBoolean(
                    $this->input(
                        'is_available'
                    )
                );
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
            'is_available' => [
                'nullable',
                'boolean',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    private function normalizeBoolean(
        mixed $value
    ): mixed {
        if (! is_string($value)) {
            return $value;
        }

        return match (strtolower(trim($value))) {
            'true' => true,
            'false' => false,
            default => $value,
        };
    }
}
