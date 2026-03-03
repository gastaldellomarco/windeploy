<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle login and issue an 8h Sanctum token.
     */
    public function login(LoginRequest $request)
    {
        // Find active user by email
        $user = User::where('email', $request->input('email'))
            ->where('attivo', true)
            ->first();

        // Validate credentials
        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            // Do not leak which field is wrong
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        // Update audit fields (last login + IP)
        $user->forceFill([
            'last_login'    => now(),
            'last_login_ip' => $request->ip(), // On XAMPP this uses REMOTE_ADDR
        ])->save();

        // Optional: revoke previous "web" tokens for this user to keep a single active SPA session
        $user->tokens()->where('name', 'web')->delete();

        // Create a Sanctum token with 8h expiration
        // Laravel 12 Sanctum allows custom expiration per token via third argument.[web:15]
        $token = $user->createToken(
            name: 'web',
            abilities: ['*'],
            expiresAt: now()->addHours(8)
        );

        return response()->json([
            'token'            => $token->plainTextToken,
            'token_expires_at' => $token->accessToken['expires_at'] ?? null,
            'user' => [
                'id'    => $user->id,
                'name'  => $user->nome,
                'email' => $user->email,
                'role'  => $user->ruolo,
            ],
        ]);
    }

    /**
     * Logout and revoke the current Sanctum token.
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            // Revoke only the token used for this request
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    /**
     * Return current authenticated user info.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id'    => $user->id,
            'name'  => $user->nome,
            'email' => $user->email,
            'role'  => $user->ruolo,
        ]);
    }
}
