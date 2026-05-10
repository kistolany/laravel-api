<?php

use App\Exceptions\ApiException;
use App\Traits\ApiResponseTrait;
use App\Enums\ResponseMessage;
use App\Enums\ResponseStatus;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.jwt' => \App\Http\Middleware\JwtMiddleware::class,
            'auth.unified' => \App\Http\Middleware\UnifiedJwtMiddleware::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Always return JSON for API responses
        $exceptions->shouldRenderJsonWhen(fn () => true);

        // Standardize "route not found" responses
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            $responder = new class {
                use ApiResponseTrait;
            };

            $trace = config('app.debug') ? $e->getMessage() : '';

            return $responder->error(
                ResponseMessage::RESOURCE_NOT_FOUND,
                ResponseStatus::NOT_FOUND,
                $trace
            );
        });
    })->create();
