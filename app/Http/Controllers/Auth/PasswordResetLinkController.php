<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetLinkController extends Controller
{
    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        try {
            Password::sendResetLink($request->safe()->only(['email']));
        } catch (\Throwable $exception) {
            Log::warning('Password reset email could not be sent.', ['exception_type' => $exception::class]);
            throw ValidationException::withMessages(['email' => 'Não foi possível solicitar a recuperação agora. Tente novamente em instantes.']);
        }

        return back()->with('status', 'Se houver uma conta com este e-mail, você receberá um link para redefinir sua senha.');
    }
}
