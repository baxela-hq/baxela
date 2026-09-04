<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Modules\Core\Exceptions\ExceptionHelper;
use Modules\Core\Exceptions\ExceptionMapper;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {

            if (! $request->expectsJson()) {
                return null;
            }

            // Error messages localize by the Accept-Language header; the
            // translator otherwise renders in the configured app locale.
            app()->setLocale($request->getPreferredLanguage(['en', 'fa']));

            $mapper = app(ExceptionMapper::class);
            $exception = $mapper->map($e);

            return response()->json(
                ExceptionHelper::format_exception_response($exception),
                $exception->httpStatus
            );
        });
    })->create();
