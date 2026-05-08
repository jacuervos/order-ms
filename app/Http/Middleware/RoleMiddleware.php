<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        try {
            // Obtener el payload del token
            $payload = JWTAuth::parseToken()->getPayload();
            $userRole = $payload->get('rol');
            // Mensaje por autenticación
            if (!in_array($userRole, $roles)) {
                return response()->json(['error' => 'No Tiene Permisos Para Seguir'], Response::HTTP_FORBIDDEN);
            }

            // Guardar el rol en la request
            $request->attributes->set('user_role', $userRole);

            return $next($request);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token inválido'], Response::HTTP_UNAUTHORIZED);
        }
    }
}
