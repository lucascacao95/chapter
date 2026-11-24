<?php

namespace App\Providers;

use App\Models\Book;
use App\Models\User;
use App\Observers\BookObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Book::observe(BookObserver::class);
        User::observe(UserObserver::class);
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(hash('sha256', Str::lower(trim(is_string($request->input('email')) ? $request->input('email') : '')).'|'.$request->ip())));
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('recovery', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        ResetPassword::toMailUsing(fn (object $notifiable, string $token) => (new MailMessage)
            ->subject('Redefina sua senha — Minha Biblioteca')
            ->greeting('Olá!')
            ->line('Recebemos uma solicitação para redefinir a senha da sua conta.')
            ->action('Redefinir minha senha', rtrim(config('app.url'), '/').route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], absolute: false))
            ->line('Este link expira em 60 minutos e pode ser usado uma única vez.')
            ->line('Se você não solicitou a redefinição, ignore este e-mail.')
            ->salutation('Minha Biblioteca'));
    }
}
