<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.pack_api.key');

        // Si no hay clave configurada, cerramos por defecto en vez de dejar pasar.
        if (empty($expected)) {
            return response()->json([
                'success' => false,
                'message' => 'API key not configured.',
            ], 500);
        }

        $provided = $request->header('X-Api-Key', '');

        // hash_equals evita timing attacks (no cortocircuita en el primer char distinto).
        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}