<?php

namespace App\Http\Requests;

use App\Models\Recharge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRechargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can(
            'create',
            Recharge::class
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recharge_package_id' => [
                'required',
                'integer',
                Rule::exists(
                    'recharge_packages',
                    'id'
                ),
            ],
            'payment_reference' => [
                'required',
                'string',
                'max:100',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (
            $this->has('payment_reference')
            && is_string(
                $this->input('payment_reference')
            )
        ) {
            $this->merge([
                'payment_reference' => trim(
                    $this->input('payment_reference')
                ),
            ]);
        }
    }
}