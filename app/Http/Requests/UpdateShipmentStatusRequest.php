<?php

namespace App\Http\Requests;

use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShipmentStatusRequest extends FormRequest
{
    /**
     * Autoriza la operación mediante ShipmentPolicy.
     *
     * Normalmente podrán realizarla:
     * - Proveedor relacionado.
     * - Repartidor asignado.
     * - Administrador.
     */
    public function authorize(): bool
    {
        $shipment = $this->route('shipment');
        $user = $this->user();

        return $shipment instanceof Shipment
            && $user !== null
            && $user->can('updateStatus', $shipment);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shipment_status_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('shipment_statuses', 'id'),
            ],

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
            'shipment_status_id.required' =>
                'Debes seleccionar el nuevo estado del envío.',

            'shipment_status_id.exists' =>
                'El estado seleccionado no existe.',

            'comment.max' =>
                'El comentario no puede superar los 1000 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'shipment_status_id' => 'estado del envío',
            'comment' => 'comentario',
        ];
    }
}

