<?php

namespace App\Http\Requests\Auth;

class LoginRequest extends EmailRequest
{
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255'], 'password' => ['required', 'string']];
    }
}
