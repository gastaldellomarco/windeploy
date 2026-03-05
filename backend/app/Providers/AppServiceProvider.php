<?php
// File: app/Providers/AppServiceProvider.php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

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

        // Rate limiter per agent JWT: 10 req/min per IP
        RateLimiter::for('agent', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
