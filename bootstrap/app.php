<?php

use App\Http\Middleware\EnsureActive;
use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\EnsureApprover;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureSupervisor;
use App\Http\Middleware\LogActivity;
use App\Http\Middleware\PhoneStaysPhone;
use App\Http\Middleware\ScalarQuery;
use App\Http\Middleware\TranslateLabels;
use App\Http\Middleware\TrackDevice;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['active' => EnsureActive::class, 'supervisor' => EnsureSupervisor::class, 'approver' => EnsureApprover::class, 'administrator' => EnsureAdministrator::class, 'module' => EnsureModuleAccess::class, 'super' => EnsureSuperAdmin::class]);
        $middleware->web(append: [ScalarQuery::class, PhoneStaysPhone::class, TrackDevice::class, LogActivity::class, TranslateLabels::class]);
        $middleware->preventRequestForgery(except: ['agent/report/*', 'd/*']);   // laporan agen Python dan layar sopir: dijaga token, bukan sesi
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Keluar dengan sesi / token yang sudah kedaluwarsa (halaman terbuka lama, login di tab lain): tujuannya sudah tercapai, jadi ke halaman masuk, bukan layar 419.
        // Formulir kedaluwarsa (token / sesi habis, mis. halaman masuk dibiarkan terbuka lama; menyegarkan halaman hasil POST mengulang POST yang sama dan 419 terus): kembali ke halaman asalnya (masuk bila gagal masuk atau keluar), dengan pesan, bukan layar 419.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }
            if ($request->routeIs('logout')) {
                return redirect()->route('login');
            }
            $to = $request->user() && ! $request->routeIs('login.attempt') ? url()->previous(route('home')) : route('login');

            return redirect()->to($to)->withInput($request->except('_token', 'password', 'password_confirmation'))->with('error', 'Halaman sudah kedaluwarsa. Silakan coba lagi.');
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
