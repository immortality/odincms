<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\FrontendMaintenance;
use App\Http\Middleware\LocalePrefix;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'locale' => SetLocale::class,
        ]);

        // /ru/blog/… is routed as /blog/… in Russian (before routing, so routes are defined once).
        $middleware->prepend(LocalePrefix::class);
        $middleware->append(SecurityHeaders::class);

        // Every web page, including the login pages, gets the visitor's language.
        // Maintenance mode (Settings) closes the public site, never the admin.
        $middleware->web(append: [SetLocale::class, FrontendMaintenance::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
