<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\StagingBasicAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Ensure storage directories exist (Wasmer Edge deploys from git without them)
foreach ([
    __DIR__.'/../storage/framework/views',
    __DIR__.'/../storage/framework/cache/data',
    __DIR__.'/../storage/framework/sessions',
    __DIR__.'/../storage/logs',
] as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'role' => CheckRole::class,
            'verified.email' => EnsureEmailVerified::class,
        ]);
        $middleware->web(prepend: [
            StagingBasicAuth::class,
        ]);
        $middleware->web(append: [
            SetLocale::class,
            EnsureInstalled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
