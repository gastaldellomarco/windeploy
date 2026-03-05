<?php
// File: app/Http/Controllers/Api/Auth/AuthController.php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login utente web (React SPA).
     * Emette un token Sanctum con scadenza 8h.
     * Rate limiting: 5 tentativi / 15 min per IP (definito in AppServiceProvider).
     * Supporta IP reale da Cloudflare Tunnel (header CF-Connecting-IP).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Legge IP reale: prima CF-Connecting-IP (Cloudflare Tunnel), poi fallback standard
        $clientIp = $request->header('CF-Connecting-IP') ?? $request->ip();

        // Cerca utente attivo per email
        $user = User::where('email', $request->input('email'))
            ->where('attivo', true)
            ->first();

        // Credenziali errate: risposta generica per non rivelare quale campo è sbagliato
        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenziali non valide.'],
            ]);
        }

        // Aggiorna campi audit: ultimo login e IP
        $user->forceFill([
            'last_login'    => now(),
            'last_login_ip' => $clientIp,
        ])->save();

        // Revoca token 'web' precedenti: una sola sessione SPA attiva per utente
        $user->tokens()->where('name', 'web')->delete();

        // Crea token Sanctum con scadenza 8h
        $token = $user->createToken(
            name: 'web',
            abilities: ['*'],
            expiresAt: now()->addHours(8)
        );

        return response()->json([
            'token'            => $token->plainTextToken,
            'token_expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user'             => new UserResource($user),
        ]);
    }

    /**
     * Logout: revoca solo il token corrente della richiesta.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logout effettuato.']);
    }

    /**
     * Ritorna i dati dell'utente autenticato con ruolo.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()));
    }

    /**
     * Refresh token: emette un nuovo token Sanctum e revoca quello corrente.
     *
     * ⚠️ Sanctum non ha refresh token nativo come OAuth2.
     * Questa implementazione emette un nuovo token 8h e invalida il vecchio.
     * Il client React deve salvare il nuovo token in risposta.
     *
     * Sicurezza: chiamata solo se il token corrente è ancora valido (auth:sanctum).
     * Se il token è già scaduto, Sanctum rifiuta la richiesta con 401 prima di arrivare qui.
     * Per gestire token scaduti côté client, il frontend deve rifare il login.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoca il token corrente
        $user->currentAccessToken()->delete();

        // Emette nuovo token 8h
        $token = $user->createToken(
            name: 'web',
            abilities: ['*'],
            expiresAt: now()->addHours(8)
        );

        return response()->json([
            'token'            => $token->plainTextToken,
            'token_expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user'             => new UserResource($user),
        ]);
    }
}
