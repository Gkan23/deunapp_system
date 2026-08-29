<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
            ],
            'remember' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (
            $this->has('email')
            && is_string($this->input('email'))
        ) {
            $this->merge([
                'email' => strtolower(
                    trim($this->input('email'))
                ),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $authenticated = Auth::attempt(
            [
                'email' => $this->string(
                    'email'
                )->toString(),
                'password' => $this->string(
                    'password'
                )->toString(),
            ],
            $this->boolean('remember')
        );

        if (! $authenticated) {
            RateLimiter::hit(
                $this->throttleKey()
            );

            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.',
            ]);
        }

        $user = Auth::user();

        $isActive = $user->accountStatus()
            ->where('status_name', 'ACTIVE')
            ->exists();

        if (! $isActive) {
            Auth::logout();

            RateLimiter::clear(
                $this->throttleKey()
            );

            throw ValidationException::withMessages([
                'email' => 'The account is not active.',
            ]);
        }

        RateLimiter::clear(
            $this->throttleKey()
        );
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts(
            $this->throttleKey(),
            5
        )) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn(
            $this->throttleKey()
        );

        throw ValidationException::withMessages([
            'email' => sprintf(
                'Too many login attempts. Please try again in %d seconds.',
                $seconds
            ),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower(
                $this->string('email')->toString()
            ).'|'.$this->ip()
        );
    }
}