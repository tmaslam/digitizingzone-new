<?php

use App\Http\Middleware\DetectSite;
use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureCustomerAuthenticated;
use App\Http\Middleware\EnsureSupervisorAuthenticated;
use App\Http\Middleware\EnsureTeamAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'detect.site' => DetectSite::class,
            'admin.auth' => EnsureAdminAuthenticated::class,
            'customer.auth' => EnsureCustomerAuthenticated::class,
            'team.auth' => EnsureTeamAuthenticated::class,
            'supervisor.auth' => EnsureSupervisorAuthenticated::class,
        ]);

        // Payment providers must be able to call back into the application without a browser CSRF token.
        $middleware->validateCsrfTokens(except: [
            'payment-proceed.php',
            'successpay.php',
            'successpay1.php',
            'successpay-paypal.php',
            'payment-notification.php',
            'webhooks/stripe',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
