<?php

namespace App\Http\Requests;

use App\Models\Route as DeliveryRoute;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CompleteRouteRequest extends FormRequest
{
    /**
     * Autoriza la finalización mediante RoutePolicy.
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
            'complete',
            $deliveryRoute
        );
    }

    /**
     * La finalización no recibe datos adicionales.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
