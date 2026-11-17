<?php

use App\Http\Middleware\EnforceProductionOrigin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustConfiguredProxies;
use App\Support\HttpSecurity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(SecurityHeaders::class);
        $middleware->replace(TrustProxies::class, TrustConfiguredProxies::class);
        $middleware->append(EnforceProductionOrigin::class);
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->preventRequestForgery(except: ['payments/toyyibpay/callback']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request): void {
            if ($request->routeIs('login.store') && ! $request->expectsJson()) {
                $exception->redirectTo(route('login'));
            }
        });
        $exceptions->respond(fn (Response $response) => HttpSecurity::apply($response, request()));
        $exceptions->dontFlash(['sandbox_secret_key', 'production_secret_key', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'payments/toyyibpay/callback') || $request->expectsJson(),
        );
    })->create();
