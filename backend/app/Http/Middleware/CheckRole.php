<?php
// File: app/Http/Middleware/CheckRole.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Verifica che l'utente autenticato abbia uno dei ruoli richiesti.
     *
     * Usa Spatie/laravel-permission via $user->hasRole() se il modello ha HasRoles.
     * Fallback sulla colonna 'ruolo' per compatibilità con l'enum DB.
     *
     * Uso nelle route:
     *   ->middleware('role:admin')
     *   ->middleware('role:admin|tecnico')   ← pipe come separatore
     *
     * ⚠️ Deve essere usato DOPO auth:sanctum (utente già autenticato).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Se l'utente non è autenticato (guard non eseguito prima), rifiuta
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Normalizza: supporta sia 'role:admin|tecnico' che 'role:admin,tecnico'
        $allowedRoles = collect($roles)
            ->flatMap(fn ($r) => explode('|', $r))
            ->map(fn ($r) => trim($r))
            ->toArray();

        // Controlla con Spatie se disponibile, altrimenti usa la colonna enum
        $hasRole = method_exists($user, 'hasRole')
            ? $user->hasRole($allowedRoles)
            : in_array($user->ruolo, $allowedRoles, true);

        if (! $hasRole) {
            return response()->json([
                'message' => 'Accesso negato. Ruolo insufficiente.',
            ], 403);
        }

        return $next($request);
    }
}
