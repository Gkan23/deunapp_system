<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && Gate::forUser($user)->allows(
                'create',
                Vehicle::class
            );
    }

    protected function prepareForValidation(): void
    {
        $vehicleType = $this->input(
            'vehicle_type'
        );

        $plateNumber = $this->input(
            'plate_number'
        );

        $this->merge([
            'vehicle_type' =>
                is_string($vehicleType)
                    ? strtoupper(
                        trim($vehicleType)
                    )
                    : $vehicleType,
            'plate_number' =>
                is_string($plateNumber)
                    ? $this->normalizePlate(
                        $plateNumber
                    )
                    : $plateNumber,
        ]);

        foreach ([
            'brand',
            'model',
            'color',
        ] as $field) {
            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);

            $this->merge([
                $field => $value === ''
                    ? null
                    : $value,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'courier_id' => [
                'required',
                'integer',
                Rule::exists(
                    'couriers',
                    'id'
                ),
            ],
            'vehicle_type' => [
                'required',
                'string',
                'max:50',
                Rule::exists(
                    'vehicle_types',
                    'type_name'
                ),
            ],
            'plate_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique(
                    'vehicles',
                    'plate_number'
                ),
            ],
            'brand' => [
                'nullable',
                'string',
                'max:80',
            ],
            'model' => [
                'nullable',
                'string',
                'max:80',
            ],
            'color' => [
                'nullable',
                'string',
                'max:50',
            ],
        ];
    }

    private function normalizePlate(
        string $plateNumber
    ): string {
        $normalized = preg_replace(
            '/\s+/',
            ' ',
            strtoupper(
                trim($plateNumber)
            )
        );

        return $normalized ?? '';
    }
}
