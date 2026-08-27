<?php

namespace App\Http\Requests;

use App\Models\DeliveryService;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssignDeliveryServiceRequest extends FormRequest
{
    /**
     * Autoriza a un proveedor activo o administrador.
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
            'assign',
            $deliveryService
        );
    }

    /**
     * Un proveedor utiliza automáticamente su propio
     * perfil y no envía delivery_provider_id.
     *
     * Un administrador debe indicar para qué proveedor
     * realizará la asignación.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isAdministrator = $this->user()
            ?->role()
            ->where(
                'role_name',
                'ADMINISTRATOR'
            )
            ->exists() ?? false;

        return [
            'delivery_provider_id' => [
                Rule::requiredIf(
                    $isAdministrator
                ),
                Rule::prohibitedIf(
                    ! $isAdministrator
                ),
                'nullable',
                'integer',
                'exists:delivery_providers,id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'delivery_provider_id' =>
                'delivery provider',
        ];
    }
}