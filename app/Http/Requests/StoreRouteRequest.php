<?php

namespace App\Http\Requests;

use App\Models\Route as DeliveryRoute;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreRouteRequest extends FormRequest
{
    /**
     * Solamente los roles autorizados por RoutePolicy
     * pueden intentar crear rutas.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        return Gate::forUser($user)->allows(
            'create',
            DeliveryRoute::class
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'courier_id' => [
                'required',
                'integer',
                'exists:couriers,id',
            ],
            'shipment_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'shipment_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:shipments,id',
            ],
            'route_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'estimated_distance_km' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'courier_id' => 'courier',
            'shipment_ids' => 'shipments',
            'shipment_ids.*' => 'shipment',
            'route_date' => 'route date',
            'estimated_distance_km' =>
                'estimated distance',
        ];
    }
}