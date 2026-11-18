<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias usados en las rutas
        $middleware->alias([
            'role'   => \App\Http\Middleware\CheckRoleLevel::class,
            'framed' => \App\Http\Middleware\FramedOnly::class,
            'force.password' => \App\Http\Middleware\ForcePasswordChange::class,
        ]);

        // Cabeceras de seguridad en todas las respuestas web
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->expectsJson() && $request->routeIs(
                'register.store', 'login.store', 'password.recover.store',
                'profile.password', 'admin.users.store', 'admin.users.temp',
            )) {
                return response()->json([
                    'message' => $exception->validator->errors()->first(),
                    'errors' => $exception->errors(),
                ], $exception->status);
            }
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
