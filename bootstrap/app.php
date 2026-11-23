<?php

use App\Http\Middleware\CheckRoleLevel;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\FramedOnly;
use App\Http\Middleware\RequireCsrfToken;
use App\Http\Middleware\SecurityHeaders;
use App\Services\Audit;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias usados en las rutas
        $middleware->alias([
            'role' => CheckRoleLevel::class,
            'framed' => FramedOnly::class,
            'active' => EnsureUserIsActive::class,
        ]);

        // Cabeceras de seguridad en todas las respuestas web
        $middleware->web(
            append: [SecurityHeaders::class],
            replace: [PreventRequestForgery::class => RequireCsrfToken::class],
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['admin_password']);
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->routeIs('admin.*')) {
                Audit::log('access_denied', Audit::DENIED, details: $request->route()->getName());
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
