<?php

use App\Http\Middleware\EnsureQbankAccess;
use App\Http\Middleware\EnsureSubscribed;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Every response, including the admin panel, JSON and the webhook.
        $middleware->append(SecurityHeaders::class);
        $middleware->alias(['qbank' => EnsureQbankAccess::class, 'subscribed' => EnsureSubscribed::class]);
        // Stripe signs its webhook instead (StripeWebhookController).
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
