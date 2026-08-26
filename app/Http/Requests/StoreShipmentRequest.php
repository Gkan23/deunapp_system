<?php

namespace App\Http\Requests;

use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShipmentRequest extends FormRequest
{
    /**
     * La autorización se delega a ShipmentPolicy.
     *
     * Solamente un cliente activo con perfil asociado
     * debería recibir permiso para crear envíos.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->can('create', Shipment::class);
    }

    /**
     * Reglas de validación para registrar un envío.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sender_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('shipment_people', 'id'),
            ],

            'recipient_id' => [
                'bail',
                'required',
                'integer',
                'different:sender_id',
                Rule::exists('shipment_people', 'id'),
            ],

            'origin_address_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('addresses', 'id'),
            ],

            'destination_address_id' => [
                'bail',
                'required',
                'integer',
                'different:origin_address_id',
                Rule::exists('addresses', 'id'),
            ],

            'origin_branch_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::exists('branches', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'is_active',
                            true
                        )
                    ),
            ],

            'destination_branch_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::exists('branches', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'is_active',
                            true
                        )
                    ),
            ],

            'scheduled_at' => [
                'nullable',
                'date',
                'after_or_equal:now',
            ],

            'declared_value' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],

            'delivery_instructions' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],

            /*
             * Todo envío debe contener al menos un paquete.
             */
            'packages' => [
                'required',
                'array',
                'min:1',
                'max:20',
            ],

            'packages.*.weight' => [
                'nullable',
                'numeric',
                'gt:0',
                'max:99999999.99',
            ],

            'packages.*.height' => [
                'nullable',
                'numeric',
                'gt:0',
                'max:99999999.99',
            ],

            'packages.*.width' => [
                'nullable',
                'numeric',
                'gt:0',
                'max:99999999.99',
            ],

            'packages.*.length' => [
                'nullable',
                'numeric',
                'gt:0',
                'max:99999999.99',
            ],

            'packages.*.content_description' => [
                'required',
                'string',
                'max:1000',
            ],

            'packages.*.is_fragile' => [
                'required',
                'boolean',
            ],

            'packages.*.declared_value' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
        ];
    }

    /**
     * Mensajes específicos para las reglas más importantes.
     *
     * Los demás mensajes utilizarán las traducciones
     * generales configuradas por Laravel.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipient_id.different' =>
                'El remitente y el destinatario deben ser diferentes.',

            'destination_address_id.different' =>
                'La dirección de destino debe ser diferente de la dirección de origen.',

            'origin_branch_id.exists' =>
                'La sucursal de origen no existe o se encuentra inactiva.',

            'destination_branch_id.exists' =>
                'La sucursal de destino no existe o se encuentra inactiva.',

            'scheduled_at.after_or_equal' =>
                'La fecha programada no puede estar en el pasado.',

            'packages.required' =>
                'Debes registrar al menos un paquete.',

            'packages.min' =>
                'Debes registrar al menos un paquete.',

            'packages.max' =>
                'Un envío no puede contener más de 20 paquetes.',

            'packages.*.content_description.required' =>
                'Debes describir el contenido de cada paquete.',

            'packages.*.is_fragile.required' =>
                'Debes indicar si cada paquete es frágil.',

            'packages.*.is_fragile.boolean' =>
                'El indicador de paquete frágil debe ser verdadero o falso.',
        ];
    }

    /**
     * Nombres legibles que aparecerán en los errores.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sender_id' => 'remitente',
            'recipient_id' => 'destinatario',
            'origin_address_id' => 'dirección de origen',
            'destination_address_id' => 'dirección de destino',
            'origin_branch_id' => 'sucursal de origen',
            'destination_branch_id' => 'sucursal de destino',
            'scheduled_at' => 'fecha programada',
            'declared_value' => 'valor declarado',
            'delivery_instructions' => 'instrucciones de entrega',
            'notes' => 'notas',
            'packages' => 'paquetes',
            'packages.*.weight' => 'peso del paquete',
            'packages.*.height' => 'altura del paquete',
            'packages.*.width' => 'ancho del paquete',
            'packages.*.length' => 'largo del paquete',
            'packages.*.content_description' =>
                'descripción del contenido',
            'packages.*.is_fragile' =>
                'indicador de paquete frágil',
            'packages.*.declared_value' =>
                'valor declarado del paquete',
        ];
    }
}

