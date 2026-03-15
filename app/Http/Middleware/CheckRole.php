<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Não autenticado',
                'error' => 'Você precisa estar autenticado para acessar este recurso.',
            ], 401);
        }

        // Admin can do everything
        if ($user->role === 'admin') {
            return $next($request);
        }

        if (!in_array($user->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso negado',
                'error' => 'Você não tem permissão para acessar este recurso.',
            ], 403);
        }

        return $next($request);
    }
}
