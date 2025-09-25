<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateJsonContentType
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verifica se o Content-Type é application/json
        $contentType = $request->header('Content-Type');
        
        // Para requisições POST, PUT, PATCH que têm body, exigir application/json
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']) && 
            !str_contains($contentType, 'application/json')) {
            
            return response()->json([
                'message' => 'Content-Type must be application/json',
            ], 400); // 415 Unsupported Media Type
        }

        return $next($request);
    }
}
