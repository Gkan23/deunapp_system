<?php

namespace App\Http\Requests;

use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePortalShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            Shipment::class
        ) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sender' => [
                'required',
                'array',
            ],
            'sender.first_name' => [
                'required',
                'string',
                'max:100',
            ],
            'sender.last_name' => [
                'required',
                'string',
                'max:100',
            ],
            'sender.phone' => [
                'required',
                'string',
                'max:30',
            ],
            'sender.identity_number' => [
                'nullable',
                'string',
                'max:30',
            ],
            'sender.email' => [
                'nullable',
                'email',
                'max:150',
            ],

            'recipient' => [
                'required',
                'array',
            ],
            'recipient.first_name' => [
                'required',
                'string',
                'max:100',
            ],
            'recipient.last_name' => [
                'required',
                'string',
                'max:100',
            ],
            'recipient.phone' => [
                'required',
                'string',
                'max:30',
            ],
            'recipient.identity_number' => [
                'nullable',
                'string',
                'max:30',
            ],
            'recipient.email' => [
                'nullable',
                'email',
                'max:150',
            ],

            'origin_address' => [
                'required',
                'array',
            ],
            'origin_address.municipality_id' => [
                'required',
                'integer',
                Rule::exists(
                    'municipalities',
                    'id'
                )->where(
                    fn ($query) => $query->where(
                        'is_active',
                        true
                    )
                ),
            ],
            'origin_address.address_line' => [
                'required',
                'string',
                'max:255',
            ],
            'origin_address.reference_note' => [
                'nullable',
                'string',
                'max:500',
            ],
            'origin_address.latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'origin_address.longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'destination_address' => [
                'required',
                'array',
            ],
            'destination_address.municipality_id' => [
                'required',
                'integer',
                Rule::exists(
                    'municipalities',
                    'id'
                )->where(
                    fn ($query) => $query->where(
                        'is_active',
                        true
                    )
                ),
            ],
            'destination_address.address_line' => [
                'required',
                'string',
                'max:255',
            ],
            'destination_address.reference_note' => [
                'nullable',
                'string',
                'max:500',
            ],
            'destination_address.latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'destination_address.longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'origin_address.municipality_id.exists' =>
                'El municipio de origen no está disponible.',

            'destination_address.municipality_id.exists' =>
                'El municipio de destino no está disponible.',

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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sender.first_name' =>
                'nombre del remitente',
            'sender.last_name' =>
                'apellido del remitente',
            'sender.phone' =>
                'teléfono del remitente',
            'sender.email' =>
                'correo del remitente',

            'recipient.first_name' =>
                'nombre del destinatario',
            'recipient.last_name' =>
                'apellido del destinatario',
            'recipient.phone' =>
                'teléfono del destinatario',
            'recipient.email' =>
                'correo del destinatario',

            'origin_address.municipality_id' =>
                'municipio de origen',
            'origin_address.address_line' =>
                'dirección de origen',

            'destination_address.municipality_id' =>
                'municipio de destino',
            'destination_address.address_line' =>
                'dirección de destino',

            'scheduled_at' =>
                'fecha programada',
            'declared_value' =>
                'valor declarado',
            'packages' =>
                'paquetes',
            'packages.*.content_description' =>
                'descripción del paquete',
        ];
    }
}