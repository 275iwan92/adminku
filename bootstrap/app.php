<?php

use App\Http\Middleware\EnsureActive;
use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EnsureSupervisor;
use App\Http\Middleware\PhoneStaysPhone;
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
        $middleware->alias(['active' => EnsureActive::class, 'supervisor' => EnsureSupervisor::class, 'administrator' => EnsureAdministrator::class, 'module' => EnsureModuleAccess::class, 'super' => \App\Http\Middleware\EnsureSuperAdmin::class]);
        $middleware->web(append: [\App\Http\Middleware\ScalarQuery::class, PhoneStaysPhone::class, \App\Http\Middleware\LogActivity::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Keluar dengan sesi / token yang sudah kedaluwarsa (halaman terbuka lama, login di tab lain): tujuannya sudah tercapai, jadi ke halaman masuk, bukan layar 419.
        $exceptions->render(fn (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) => $e->getStatusCode() === 419 && $request->routeIs('logout') ? redirect()->route('login') : null);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
