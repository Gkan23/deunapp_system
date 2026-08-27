<?php

namespace App\Http\Requests;

use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;

class CancelShipmentRequest extends FormRequest
{
    /**
     * ShipmentPolicy permite cancelar al cliente
     * propietario y al administrador.
     */
    public function authorize(): bool
    {
        $shipment = $this->route('shipment');
        $user = $this->user();

        return $shipment instanceof Shipment
            && $user !== null
            && $user->can('cancel', $shipment);
    }

    /**
     * El estado CANCELLED no se recibe desde la petición.
     * El controlador lo seleccionará internamente.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'comment' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comment.max' =>
                'El motivo de cancelación no puede superar los 1000 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'comment' => 'motivo de cancelación',
        ];
    }
}

