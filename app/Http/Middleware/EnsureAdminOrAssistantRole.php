<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminOrAssistantRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->user()?->rol, ['admin', 'asistente'], true)) {
            return response()->json(['detail' => 'No tienes permisos para gestionar cultivos.'], 403);
        }

        return $next($request);
    }
}
