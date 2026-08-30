<?php

namespace App\Http\Requests;

use App\Models\Courier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCourierLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $courier = Courier::query()
            ->where('user_id', $user->id)
            ->first();

        return $courier !== null
            && Gate::forUser($user)->allows(
                'recordLocation',
                $courier
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],
            'gps_accuracy' => [
                'nullable',
                'numeric',
                'min:0',
                'max:10000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.required' =>
                'The latitude is required.',
            'latitude.numeric' =>
                'The latitude must be numeric.',
            'latitude.between' =>
                'The latitude must be between -90 and 90.',
            'longitude.required' =>
                'The longitude is required.',
            'longitude.numeric' =>
                'The longitude must be numeric.',
            'longitude.between' =>
                'The longitude must be between -180 and 180.',
            'gps_accuracy.numeric' =>
                'The GPS accuracy must be numeric.',
            'gps_accuracy.min' =>
                'The GPS accuracy may not be negative.',
            'gps_accuracy.max' =>
                'The GPS accuracy may not exceed 10000 meters.',
        ];
    }
}
