<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateVehicleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        $vehicle = $this->route(
            'vehicle'
        );

        return $user !== null
            && $vehicle instanceof Vehicle
            && Gate::forUser($user)->allows(
                'changeStatus',
                $vehicle
            );
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('status');

        $comment = $this->input('comment');

        $this->merge([
            'status' => is_string($status)
                ? strtoupper(
                    trim($status)
                )
                : $status,
            'comment' => is_string($comment)
                ? trim($comment)
                : $comment,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                'max:50',
                Rule::exists(
                    'vehicle_statuses',
                    'status_name'
                ),
            ],
            'comment' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' =>
                'The vehicle status is required.',
            'status.exists' =>
                'The selected vehicle status does not exist.',
            'comment.required' =>
                'A comment is required to change the vehicle status.',
        ];
    }
}
