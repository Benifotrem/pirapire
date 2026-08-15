<?php

use App\Http\Middleware\AuthenticateAdminMiniApp;
use App\Http\Middleware\AuthenticateCustomerMiniApp;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'miniapp.customer' => AuthenticateCustomerMiniApp::class,
            'miniapp.admin' => AuthenticateAdminMiniApp::class,
        ]);

        // Public site language switch (see App\Http\Controllers\LocaleController)
        // — deliberately not applied to the Filament admin panel, which has
        // its own middleware stack and stays Spanish-only.
        $middleware->web(append: [SetLocale::class]);

        // nginx (the `web` container's only client) sits behind Cloudflare;
        // trust it as the immediate proxy so Laravel reads the real client
        // IP/scheme from Cloudflare's X-Forwarded-* headers instead of
        // reporting every request as coming from the nginx container.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
