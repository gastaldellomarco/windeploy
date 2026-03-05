<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Authentication Guard
    |--------------------------------------------------------------------------
    |
    | Il guard di default è 'web' (sessione). Sanctum usa il proprio guard
    | separato ('sanctum'). Non cambiare questo default: le route agent
    | specificano esplicitamente 'auth:api' per non usare mai il default.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Tre guard separati per tre contesti distinti:
    | - 'web'     → sessione PHP standard (admin panel Laravel, se presente)
    | - 'sanctum' → token opaco Sanctum per la React web app (utenti umani)
    | - 'api'     → JWT stateless per l'agent Windows .exe (macchine)
    |
    | PERCHÉ SEPARATI: ogni guard ha ciclo di vita e storage diversi.
    | Sanctum salva i token in DB (personal_access_tokens), JWT è stateless
    | e firmato con secret. Mischiare i due causerebbe 401 o autenticazioni
    | incrociate tra utenti umani e agent macchina.
    |
    */

    'guards' => [

        // Guard web: sessione PHP, usato solo da eventuali view server-side
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],

        // Guard sanctum: per la React web app — token opaco salvato in DB
        // Usato da: middleware('auth:sanctum') su tutte le route /api/*
        // eccetto quelle agent
        'sanctum' => [
            'driver'   => 'sanctum',
            'provider' => 'users',
        ],

        // Guard api: JWT per l'agent Windows Python .exe
        // Driver 'jwt' registrato da tymon/jwt-auth via LaravelServiceProvider
        // Legge il token dall'header: Authorization: Bearer {jwt_token}
        // NON usa il DB per validare il token — verifica firma e claims
        'api' => [
            'driver'   => 'jwt',
            'provider' => 'users',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | Un solo provider 'users' basato su Eloquent è sufficiente per entrambi
    | i sistemi di autenticazione. Sia Sanctum che JWT usano App\Models\User
    | per recuperare l'utente autenticato dal database.
    |
    | NOTA: il modello User DEVE implementare JWTSubject per il guard api.
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => env('AUTH_MODEL', App\Models\User::class),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Reset
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
