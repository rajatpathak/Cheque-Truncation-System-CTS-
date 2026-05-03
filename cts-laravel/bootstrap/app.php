<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AuditLogger;
use App\Http\Middleware\MakerCheckerMiddleware;
use App\Http\Middleware\CheckUserLimit;
use App\Exceptions\SignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        web: __DIR__ . '/../routes/web.php',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global API middleware
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Named middleware aliases
        $middleware->alias([
            'cts.audit'     => AuditLogger::class,
            'maker-checker' => MakerCheckerMiddleware::class,
            'cts.limit'     => CheckUserLimit::class,
            'role'          => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'    => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (SignatureException $e) {
            return $e->render();
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e) {
            return response()->json(['error' => 'UNAUTHENTICATED'], 401);
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['error' => 'UNAUTHORIZED'], 403);
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'NOT_FOUND', 'model' => class_basename($e->getModel())], 404);
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error'   => 'VALIDATION_ERROR',
                'errors'  => $e->errors(),
            ], 422);
        });
    })
    ->create();
