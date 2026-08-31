<?php

namespace App\Http\Requests;

use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return Gate::forUser($user)->allows(
            'create',
            Incident::class
        );
    }

    /**
     * Normaliza los valores antes de validarlos.
     */
    protected function prepareForValidation(): void
    {
        $incidentType = $this->input(
            'incident_type'
        );

        $description = $this->input(
            'description'
        );

        $this->merge([
            'incident_type' =>
                is_string($incidentType)
                    ? strtoupper(
                        trim($incidentType)
                    )
                    : $incidentType,
            'description' =>
                is_string($description)
                    ? trim($description)
                    : $description,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'incident_type' => [
                'required',
                'string',
                'max:100',
                Rule::exists(
                    'incident_types',
                    'type_name'
                ),
            ],
            'description' => [
                'required',
                'string',
                'max:5000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'incident_type.required' =>
                'The incident type is required.',
            'incident_type.exists' =>
                'The selected incident type does not exist.',
            'description.required' =>
                'The incident description is required.',
            'description.max' =>
                'The incident description may not exceed 5000 characters.',
        ];
    }
}