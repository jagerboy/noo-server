<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // Proteksi HTTP Security Headers secara global untuk semua respon Web & REST API
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'principal/dashboard/export-chart*',
            'edp/dashboard/export-chart*',
            'dashboard/export-chart*',
        ]);

        $middleware->alias([
            'distributor.auth' => \App\Http\Middleware\EnsureDistributorAuthenticated::class,
            'spv.auth' => \App\Http\Middleware\EnsureSpvAuthenticated::class,
        ]);

        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('spv*')) {
                return route('spv_login.create');
            }
            if ($request->is('edp*') || $request->is('principal*')) {
                return route('edp_login.create');
            }
            return route('distributor_login.create');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
