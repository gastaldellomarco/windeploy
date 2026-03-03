<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes: ->middleware('role:admin') or ->middleware('role:admin,tecnico')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // User role must match one of the allowed roles
        if (! in_array($user->ruolo, $roles, true)) {
            return response()->json([
                'message' => 'Forbidden. Insufficient role.',
            ], 403);
        }

        return $next($request);
    }
}
