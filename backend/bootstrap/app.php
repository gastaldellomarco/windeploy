<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        /*
        |--------------------------------------------------------------------------
        | Global middleware
        |--------------------------------------------------------------------------
        |
        | Registriamo qui il middleware TrustProxies, così che Laravel
        | gestisca correttamente X-Forwarded-* se in futuro passerai
        | dietro Nginx/Cloudflare (come previsto dal progetto WinDeploy).[file:8]
        |
        */

        $middleware->use([
            TrustProxies::class,
        ]);

        // Use our application Authenticate middleware (prevents redirect to 'login' route)
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            // Spatie permission middleware aliases used in routes (role, permission, role_or_permission)
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

    // Ensure framework won't fallback to route('login') for unauthenticated requests.
    // WinDeploy is API-first: always return null for guest redirects so exceptions
    // produce 401 JSON instead of trying to redirect to a missing login route.
    $middleware->redirectTo(guests: fn () => null);

        // Qui puoi aggiungere altri middleware globali se necessario
        // es. \Illuminate\Http\Middleware\HandleCors::class, ecc.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Gestione eccezioni personalizzata: intercetta AuthenticationException
        // e restituisce JSON 401 per rotte API o richieste che si aspettano JSON.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'error' => 'token_invalid_or_missing',
                ], 401);
            }
            // altrimenti lasciare che il framework gestisca il redirect/altro
            return null;
        });
    })
    ->create();
