<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response) {
            if ($response->getStatusCode() === 429 && request()->header('X-Inertia')) {
                return back()->withErrors(['email' => 'Muitas tentativas. Aguarde um minuto e tente novamente.']);
            }

            return $response;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
