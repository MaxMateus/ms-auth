<?php

use App\Exceptions\Auth\AccountNotVerifiedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\InvalidTokenException;
use App\Exceptions\InvalidCpfException;
use App\Exceptions\Mfa\InvalidMfaCodeException;
use App\Exceptions\Mfa\MfaMethodNotFoundException;
use App\Exceptions\UserAlreadyExistsException;
use App\Exceptions\UserNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Exceptions\OAuthServerException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Registrar o middleware para uso específico em rotas
        $middleware->alias([
            'validate.json' => \App\Http\Middleware\ValidateJsonContentType::class,
        ]);

        $middleware->redirectTo(function ($request) {
            return $request->is('api/*') ? null : '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $json = static function ($request, string $message, int $status, ?array $errors = null) {
            if (!$request->expectsJson() && !$request->is('api/*')) {
                return null;
            }

            $body = ['message' => $message];

            if ($errors) {
                $body['errors'] = $errors;
            }

            return response()->json($body, $status);
        };

        $exceptions->render(function (ValidationException $exception, $request) use ($json) {
            $errors = $exception->errors();
            $firstMessage = collect($errors)->flatten()->first() ?? 'Erros de validação';

            return $json($request, $firstMessage, 422, $errors);
        });

        $exceptions->render(function (InvalidCredentialsException $exception, $request) use ($json) {
            return $json($request, $exception->getMessage(), 401);
        });

        $exceptions->render(function (AccountNotVerifiedException $exception, $request) use ($json) {
            return $json($request, $exception->getMessage(), 403);
        });

        $exceptions->render(function (InvalidTokenException $exception, $request) use ($json) {
            return $json($request, $exception->getMessage(), 400);
        });

        $exceptions->render(function (UserAlreadyExistsException $exception, $request) use ($json) {
            return $json($request, $exception->getMessage(), 409, $exception->conflicts());
        });

        $exceptions->render(function (InvalidCpfException $exception, $request) use ($json) {
            return $json($request, $exception->getMessage(), 422);
        });

        $exceptions->render(function (UserNotFoundException $exception, $request) use ($json) {
            return $json($request, $exception->getMessage(), 404);
        });

        $exceptions->render(function (InvalidMfaCodeException $exception, $request) use ($json) {
            return $json($request, $exception->getMessage(), 422);
        });

        $exceptions->render(function (MfaMethodNotFoundException $exception, $request) use ($json) {
            return $json($request, $exception->getMessage(), $exception->status());
        });

        $exceptions->render(function (AuthenticationException $exception, $request) use ($json) {
            return $json($request, 'Token inválido ou expirado.', 401) ?? redirect()->guest(route('login'));
        });

        $exceptions->render(function (OAuthServerException $exception, $request) use ($json) {
            return $json($request, 'Token inválido ou expirado.', 400);
        });
    })->create();
