<?php

namespace App\Http\Requests;

use App\Models\DeliveryService;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $deliveryService = $this->route(
            'deliveryService'
        );

        if (! $user instanceof User) {
            return false;
        }

        if (! $deliveryService instanceof DeliveryService) {
            return false;
        }

        return Gate::forUser($user)->allows(
            'create',
            Rating::class
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'punctuality_score' => [
                'required',
                'integer',
                'between:1,5',
            ],
            'customer_service_score' => [
                'required',
                'integer',
                'between:1,5',
            ],
            'package_condition_score' => [
                'required',
                'integer',
                'between:1,5',
            ],
            'comment' => [
                'nullable',
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
            'punctuality_score' => 'puntuación de puntualidad',
            'customer_service_score' => 'puntuación de atención',
            'package_condition_score' => 'puntuación del paquete',
            'comment' => 'comentario',
        ];
    }
}