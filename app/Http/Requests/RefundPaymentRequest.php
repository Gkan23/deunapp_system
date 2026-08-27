<?php

namespace App\Http\Requests;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RefundPaymentRequest extends FormRequest
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
            'refund',
            $payment
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
            'refund_reference' => [
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
            'reason' => 'motivo del reembolso',
            'refund_reference' => 'referencia del reembolso',
        ];
    }
}
