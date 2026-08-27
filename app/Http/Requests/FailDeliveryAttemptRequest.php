<?php

namespace App\Http\Requests;

use App\Models\RouteShipment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FailDeliveryAttemptRequest extends FormRequest
{
    private const ALLOWED_INCIDENT_TYPES = [
        'DELIVERY_FAILED',
        'RECIPIENT_ABSENT',
        'WRONG_ADDRESS',
        'CONTACT_FAILED',
        'DAMAGED_PACKAGE',
        'LOST_PACKAGE',
        'VEHICLE_PROBLEM',
    ];

    /**
     * Autoriza exclusivamente al repartidor asignado.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        $routeShipment = $this->route(
            'routeShipment'
        );

        if (
            ! $user instanceof User
            || ! $routeShipment instanceof RouteShipment
        ) {
            return false;
        }

        return Gate::forUser($user)->allows(
            'failDeliveryAttempt',
            $routeShipment->shipment
        );
    }

    /**
     * Normaliza el nombre del tipo de incidente.
     */
    protected function prepareForValidation(): void
    {
        $incidentType = $this->input(
            'incident_type'
        );

        if (is_string($incidentType)) {
            $this->merge([
                'incident_type' => strtoupper(
                    trim($incidentType)
                ),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'incident_type' => [
                'required',
                'string',
                Rule::in(
                    self::ALLOWED_INCIDENT_TYPES
                ),
            ],
            'description' => [
                'required',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'incident_type' => 'incident type',
            'description' => 'incident description',
        ];
    }
}
