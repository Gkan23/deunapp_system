<?php

namespace App\Http\Requests;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ConfirmPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $payment = $this->route('payment');

        if (! $user instanceof User) {
            return false;
        }

        if (! $payment instanceof Payment) {
            return false;
        }

        return Gate::forUser($user)->allows(
            'confirm',
            $payment
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_reference' => [
                'nullable',
                'string',
                'max:150',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'payment_reference' => 'referencia del pago',
        ];
    }
}