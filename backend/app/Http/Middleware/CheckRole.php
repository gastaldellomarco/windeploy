<?php
// backend/app/Http/Middleware/CheckRole.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $allowedRoles = collect($roles)
            ->flatMap(fn ($roleGroup) => preg_split('/[|,]/', $roleGroup))
            ->map(fn ($role) => trim((string) $role))
            ->filter()
            ->values()
            ->all();

        $userRole = strtolower((string) ($user->ruolo ?? $user->role ?? ''));

        if ($userRole !== '' && in_array($userRole, $allowedRoles, true)) {
            return $next($request);
        }

        if (method_exists($user, 'hasRole') && $user->hasRole($allowedRoles)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Accesso negato. Ruolo insufficiente.',
            'debug' => [
                'user_id' => $user->id ?? null,
                'user_ruolo' => $user->ruolo ?? null,
                'allowed_roles' => $allowedRoles,
                'spatie_roles' => method_exists($user, 'getRoleNames')
                    ? $user->getRoleNames()->values()->all()
                    : [],
            ],
        ], 403);
    }
}
