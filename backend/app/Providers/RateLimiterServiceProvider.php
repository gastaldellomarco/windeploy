<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimiterServiceProvider extends ServiceProvider
{
    /**
     * Registra i rate limiter custom dell'applicazione.
     */
    public function boot(): void
    {
        // Rate limiter per login agent: 5 tentativi per minuto per IP
        // Usato da: ->middleware('throttle:login') su POST /api/agent/auth
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Troppi tentativi di login. Riprova tra un minuto.',
                    ], 429);
                });
        });

        // Rate limiter per agent autenticato: 120 richieste/minuto per token JWT
        // Usato da: ->middleware('throttle:agent') nelle route protette
        RateLimiter::for('agent', function (Request $request) {
            // Chiave basata sul token JWT (non IP) per limitare per agent, non per rete
            $key = $request->bearerToken() ?? $request->ip();
            return Limit::perMinute(120)
                ->by($key)
                ->response(function () {
                    return response()->json([
                        'message' => 'Rate limit superato. Rallenta le richieste.',
                    ], 429);
                });
        });
    }
}
