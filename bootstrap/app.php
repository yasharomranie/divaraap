<?php

declare(strict_types=1);

use App\Http\Middleware\NormalizeLocalizedNumbers;
use App\Http\Middleware\ValidateRouteIdentifiers;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Modules\Demo\App\Http\Middleware\ResolveDemoRequest;
use Modules\Site\App\Http\Middleware\BootstrapAppData;
use Modules\Site\App\Http\Middleware\SetLocale;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [ValidateRouteIdentifiers::class], append: [
            ResolveDemoRequest::class,
            BootstrapAppData::class,
            SetLocale::class,
            NormalizeLocalizedNumbers::class,
        ]);

        $middleware->prependToPriorityList(SubstituteBindings::class, ValidateRouteIdentifiers::class);
        $middleware->appendToPriorityList(StartSession::class, ResolveDemoRequest::class);
        $middleware->appendToPriorityList(ResolveDemoRequest::class, BootstrapAppData::class);
        $middleware->appendToPriorityList(BootstrapAppData::class, SetLocale::class);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, ResolveDemoRequest::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})->create();
