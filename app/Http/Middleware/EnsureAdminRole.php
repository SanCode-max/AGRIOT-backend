<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->rol !== 'admin') {
            return response()->json(['detail' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        return $next($request);
    }
}
