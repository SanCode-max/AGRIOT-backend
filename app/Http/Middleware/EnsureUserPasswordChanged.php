<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            return response()->json(['detail' => 'Debes cambiar tu contraseña antes de continuar.', 'must_change_password' => true], 403);
        }

        return $next($request);
    }
}
