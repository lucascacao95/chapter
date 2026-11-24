<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NewPasswordController extends Controller
{
    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $status = DB::transaction(fn () => Password::reset($request->validated(), function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        }));
        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => 'O link de recuperação é inválido ou expirou. Solicite um novo link.']);
        }

        return redirect()->route('login')->with('status', 'Senha redefinida. Entre com sua nova senha.');
    }
}
