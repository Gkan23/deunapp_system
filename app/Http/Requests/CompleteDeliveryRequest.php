<?php

namespace App\Http\Requests;

use App\Models\DeliveryService;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class CompleteDeliveryRequest extends FormRequest
{
    /**
     * El usuario debe poder completar el servicio y
     * registrar la prueba de entrega del envío.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        $deliveryService = $this->route(
            'deliveryService'
        );

        if (
            ! $user instanceof User
            || ! $deliveryService instanceof DeliveryService
        ) {
            return false;
        }

        return Gate::forUser($user)->allows(
            'complete',
            $deliveryService
        ) && Gate::forUser($user)->allows(
            'recordDeliveryProof',
            $deliveryService->shipment
        );
    }

    /**
     * Valida los datos de la prueba de entrega.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'photo_url' => [
                'nullable',
                'string',
                'max:500',
            ],
            'signature_url' => [
                'nullable',
                'string',
                'max:500',
            ],
            'receiver_name' => [
                'required',
                'string',
                'max:150',
            ],
            'receiver_identity_number' => [
                'nullable',
                'string',
                'max:30',
            ],
            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
            'comment' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Además del nombre del receptor, debe existir al
     * menos una evidencia:
     *
     * - fotografía;
     * - firma;
     * - número de identificación.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $hasEvidence =
                    $this->filled('photo_url')
                    || $this->filled('signature_url')
                    || $this->filled(
                        'receiver_identity_number'
                    );

                if (! $hasEvidence) {
                    $validator->errors()->add(
                        'delivery_evidence',
                        'At least one form of delivery evidence is required.'
                    );
                }
            },
        ];
    }

    /**
     * Nombres legibles para los errores de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'photo_url' => 'delivery photograph',
            'signature_url' => 'receiver signature',
            'receiver_name' => 'receiver name',
            'receiver_identity_number' =>
                'receiver identity number',
            'latitude' => 'delivery latitude',
            'longitude' => 'delivery longitude',
            'comment' => 'completion comment',
        ];
    }
}
