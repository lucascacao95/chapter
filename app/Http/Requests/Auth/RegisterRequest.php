<?php

namespace App\Http\Requests\Auth;

class RegisterRequest extends EmailRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed', function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_string($value) && strlen($value) > 72) {
                $fail('A senha é muito longa. Use uma senha menor.');
            }
        }]];
    }
}
