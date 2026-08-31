<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListVehiclesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && Gate::forUser($user)->allows(
                'viewAny',
                Vehicle::class
            );
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'vehicle_type',
            'vehicle_status',
        ] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([
                    $field => strtoupper(
                        trim($value)
                    ),
                ]);
            }
        }

        $search = $this->input('search');

        if (is_string($search)) {
            $this->merge([
                'search' => trim($search),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'vehicle_type' => [
                'nullable',
                'string',
                Rule::exists(
                    'vehicle_types',
                    'type_name'
                ),
            ],
            'vehicle_status' => [
                'nullable',
                'string',
                Rule::exists(
                    'vehicle_statuses',
                    'status_name'
                ),
            ],
            'courier_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'couriers',
                    'id'
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
}
