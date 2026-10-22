<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware(['web', 'auth', 'role:Teacher'])->prefix('teacher')->name('teacher.')->group(base_path('routes/teacher.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'portal.student' => \App\Http\Middleware\ResolvePortalStudent::class,
        ]);
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('portal*') || $request->is('student*')) {
                return route('portal.login');
            }
            return route('login');
        });
        $middleware->redirectUsersTo(fn ($request) => \App\Services\LoginDestination::for($request->user()));
        $middleware->web(append: [\App\Http\Middleware\NoCache::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response) {
            if ($response->getStatusCode() === 419 && ! request()->expectsJson()) {
                if (request()->is('portal*') || request()->is('student*')) {
                    return redirect()->route('portal.login')->with('status', 'Your session expired. Please sign in again.');
                }
                return redirect()->route('login')->with('status', 'Your session expired. Please sign in again.');
            }
            return $response;
        });
    })->create();
