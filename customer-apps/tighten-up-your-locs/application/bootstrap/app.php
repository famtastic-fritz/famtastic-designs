<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/admin/health',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['owner' => \App\Http\Middleware\OwnerOnly::class]);
        $middleware->append(\App\Http\Middleware\PrivateHeaders::class);
        $middleware->redirectGuestsTo('/admin/login');
        $middleware->redirectUsersTo('/admin');
        $middleware->validateCsrfTokens(except: ['api/booking-request/site-dffd4cb9c3aa47fd', 'api/newsletter/signup']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
