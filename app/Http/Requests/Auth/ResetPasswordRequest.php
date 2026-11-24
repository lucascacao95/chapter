<?php

namespace App\Http\Requests\Auth;

class ResetPasswordRequest extends EmailRequest
{
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255'], 'token' => ['required', 'string'], 'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed', function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_string($value) && strlen($value) > 72) {
                $fail('A senha é muito longa. Use uma senha menor.');
            }
        }]];
    }
}
