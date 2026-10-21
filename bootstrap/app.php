<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Throwable;
use App\Http\Middleware\EnsureUserPasswordChanged;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureAdminOrAssistantRole;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render terminates TLS at its proxy and forwards the original scheme in X-Forwarded-Proto.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->alias([
            'password.changed' => EnsureUserPasswordChanged::class,
            'role.admin' => EnsureAdminRole::class,
            'role.crop-manager' => EnsureAdminOrAssistantRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API failures stay JSON even when a client omits Accept: application/json.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
