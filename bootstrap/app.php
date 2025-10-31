<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;

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
        $exceptions->render(function (ValidationException $e, $request) {
            return response()->json([
                'message' => 'Erros de validação',
                'errors'  => $e->errors(),
            ], 400);
        });
        
        // Handler para exceções de autenticação do Passport
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Token inválido ou expirado.',
                    'error' => 'invalid_token'
                ], 400);
            }
            
            // Para outras rotas, retornar comportamento padrão
            return redirect()->guest(route('login'));
        });
        
        // Handler para exceções gerais do Laravel Passport
        $exceptions->render(function (\Laravel\Passport\Exceptions\OAuthServerException $e, $request) {
            return response()->json([
                'message' => 'Token inválido ou expirado.',
                'error' => 'invalid_token'
            ], 400);
        });
    })->create();
