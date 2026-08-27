<?php

namespace App\Http\Requests;

use App\Models\Route as DeliveryRoute;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CancelRouteRequest extends FormRequest
{
    /**
     * Solamente el proveedor relacionado o un
     * administrador pueden cancelar la ruta.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        $deliveryRoute = $this->route('route');

        if (
            ! $user instanceof User
            || ! $deliveryRoute instanceof DeliveryRoute
        ) {
            return false;
        }

        return Gate::forUser($user)->allows(
            'cancel',
            $deliveryRoute
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => [
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
            'reason' => 'route cancellation reason',
        ];
    }
}
