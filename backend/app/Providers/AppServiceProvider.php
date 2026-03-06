<?php
// File: app/Providers/AppServiceProvider.php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use App\Services\EncryptionService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registra EncryptionService come singleton per evitare ricostruzioni ripetute
        $this->app->singleton(EncryptionService::class, function ($app) {
            return new EncryptionService();
        });
    }

    public function boot(): void
    {
        // Rate limiter per login: max 5 tentativi ogni 15 minuti per IP
        // Supporta CF-Connecting-IP per Cloudflare Tunnel
        RateLimiter::for('login', function (Request $request) {
            $ip = $request->header('CF-Connecting-IP') ?? $request->ip();

            return Limit::perMinutes(15, 5)
                ->by($ip)
                ->response(function (Request $req, array $headers) {
                    return response()->json([
                        'message' => 'Troppi tentativi di accesso. Riprova tra 15 minuti.',
                    ], 429, $headers);
                });
        });

        // Rate limiter per agent JWT: 120 req/min per token (fallback to IP)
        RateLimiter::for('agent', function (Request $request) {
            $key = $request->bearerToken() ?? $request->ip();
            return Limit::perMinute(120)->by($key);
        });

        // ──────────────────────────────────────────────────────────────
        // Rate limiter dedicato per POST /api/agent/auth
        //
        // Separato dal limiter 'agent' (usato per i log step con JWT)
        // perché la fase di autenticazione pre-JWT richiede una
        // protezione più aggressiva: l'endpoint è pubblico (no JWT),
        // mentre le route /agent/* successive sono già protette da auth.
        //
        // Due layer indipendenti:
        //   1. Per IP: blocca flood da singolo attacker
        //   2. Per wizard_code: blocca brute force distribuito su
        //      codici validi anche da IP diversi (botnet scenario)
        // ──────────────────────────────────────────────────────────────
        RateLimiter::for('agent_auth', function (Request $request) {
            return [
                // Massimo 10 richieste per minuto per IP sorgente
                Limit::perMinute(10)->by('ip:' . $request->ip()),

                // Massimo 5 richieste per minuto per codice wizard
                // (previene enumerazione di codici validi da IP multipli)
                Limit::perMinute(5)->by('code:' . $request->input('wizard_code', 'unknown')),
            ];
        });
    }
}
