<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt extends BaseMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // Intentar obtener el usuario desde el token, sin buscarlo en la BD
            $payload = JWTAuth::parseToken()->getPayload();
            $userId = $payload->get('sub');
            if (!$userId) {
                return response()->json(['error' => 'Usuario no encontrado'], Response::HTTP_UNAUTHORIZED);
            }
            // Agregar el user_id del token a la solicitud
            $request->attributes->set('user_id', $userId);
        } catch (Exception $e) {
            return response()->json(['error' => 'Token inválido'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
