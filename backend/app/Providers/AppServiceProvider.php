<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Login rate limiter: max 5 attempts per 15 minutes per IP.
        // Relax the limiter substantially when running in local/dev to
        // avoid blocking developer tests. Production environments keep
        // the strict limit.
        RateLimiter::for('login', function (Request $request) {
            $isLocal = app()->environment('local') || env('APP_DEBUG', false);
            $maxAttempts = $isLocal ? 1000 : 5;
            $decayMinutes = 15;

            return Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many login attempts. Try again later.',
                    ], 429, $headers);
                });
        });

        // Agent rate limiter: 120 requests per minute keyed by JWT wizard_id or IP
        RateLimiter::for('agent', function (Request $request) {
            // Identify caller via JWT token payload (wizard_id) or fallback to IP
            $key = optional(JWTAuth::parseToken()->getPayload())->get('wizard_id') ?: $request->ip();
            return Limit::perMinute(120)->by($key);
        });

        // You can define other rate limiters here (api, uploads, etc.)[web:11][web:37]
    }
}
