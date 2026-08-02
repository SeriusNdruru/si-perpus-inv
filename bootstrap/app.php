<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsurePasswordSessionIsCurrent;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->group(base_path('routes/access_portal.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(
            fn (\Illuminate\Http\Request $request) => $request->is('siswa/*')
                ? route('student.login')
                : route('login')
        );
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'password.session' => EnsurePasswordSessionIsCurrent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tambahkan pelaporan exception khusus di sini bila dibutuhkan.
    })
    ->create();
