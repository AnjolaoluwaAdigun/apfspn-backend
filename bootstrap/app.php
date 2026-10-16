<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EncryptPayload;
use App\Http\Middleware\EnsureAccessToken;
use App\Support\JsonResponser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::prefix('api/v1')->name('v1.')->middleware('api')->group(function () {
                Route::group([], base_path('routes/api/v1/public.php'));

                Route::prefix('member')->name('member.')->group(base_path('routes/api/v1/member.php'));

                Route::prefix('admin')->name('admin.')->group(base_path('routes/api/v1/admin.php'));
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'token' => EnsureAccessToken::class,
        ]);

        $middleware->appendToGroup('api', EncryptPayload::class);

        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport([
            ApiException::class,
            ValidationException::class,
            AuthenticationException::class,
            AuthorizationException::class,
            ModelNotFoundException::class,
            NotFoundHttpException::class,
            ThrottleRequestsException::class,
            UnauthorizedException::class,
        ]);

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $e->getPrevious();

            if ($previous instanceof ModelNotFoundException || $previous instanceof AuthorizationException) {
                $e = $previous;
            }

            return match (true) {
                $e instanceof ApiException => JsonResponser::error($e->getMessage(), $e->status(), $e->payload()),
                $e instanceof ValidationException => JsonResponser::error($e->validator->errors()->first(), 422, $e->errors()),
                $e instanceof AuthenticationException => JsonResponser::error('Unauthenticated.', 401),
                $e instanceof AuthorizationException, $e instanceof UnauthorizedException => JsonResponser::error('You are not allowed to perform this action.', 403),
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => JsonResponser::error('Resource not found.', 404),
                $e instanceof ThrottleRequestsException => JsonResponser::error(
                    'Too many requests. Please try again later.',
                    429,
                    ['retry_after_seconds' => (int) ($e->getHeaders()['Retry-After'] ?? 60)],
                )->withHeaders($e->getHeaders()),
                $e instanceof HttpExceptionInterface => JsonResponser::error(
                    Response::$statusTexts[$e->getStatusCode()] ?? 'Request failed.',
                    $e->getStatusCode(),
                )->withHeaders($e->getHeaders()),
                default => JsonResponser::error('Something went wrong. Please try again later.', 500),
            };
        });
    })->create();
