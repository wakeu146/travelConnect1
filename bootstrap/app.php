<?php

use App\Http\Middleware\EnsureAdminSession;
use App\Http\Middleware\EnsureAgencyOwner;
use App\Http\Middleware\EnsurePendingAdminLogin;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\UpdateUserPresence;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SetLocale::class);
        $middleware->appendToGroup('web', UpdateUserPresence::class);
        $middleware->alias([
            'agency-owner' => EnsureAgencyOwner::class,
            'admin.pending' => EnsurePendingAdminLogin::class,
            'admin.session' => EnsureAdminSession::class,
        ]);
    })
    ->withCommands()
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
