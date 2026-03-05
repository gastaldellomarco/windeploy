<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paths abilitati al CORS
    |--------------------------------------------------------------------------
    | 'api/*'              → tutte le route REST di WinDeploy
    | 'sanctum/csrf-cookie' → necessario per il flusso SPA Sanctum
    |--------------------------------------------------------------------------
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
    |--------------------------------------------------------------------------
    | Metodi HTTP consentiti
    |--------------------------------------------------------------------------
    | '*' consente GET, POST, PUT, PATCH, DELETE, OPTIONS
    | OPTIONS è critico — è il preflight CORS del browser
    |--------------------------------------------------------------------------
    */
    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Origini consentite
    |--------------------------------------------------------------------------
    | SVILUPPO LOCALE (XAMPP):
    | - http://localhost:5173     → Vite dev server
    | - http://windeploy.local    → Se si apre il frontend via Apache VHost
    |
    | ATTENZIONE: NON aggiungere http://windeploy.local.api qui.
    | Le chiamate partono sempre da localhost:5173 (Vite), non dall'API stessa.
    |--------------------------------------------------------------------------
    */
    'allowed_origins' => [
        'http://localhost:5173',
        'http://windeploy.local',
    ],

    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Header consentiti
    |--------------------------------------------------------------------------
    | '*' consente Authorization, Content-Type, Accept, X-Requested-With, ecc.
    |--------------------------------------------------------------------------
    */
    'allowed_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Header esposti al browser
    |--------------------------------------------------------------------------
    */
    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Cache preflight CORS (secondi)
    |--------------------------------------------------------------------------
    | 0 = nessuna cache (utile in sviluppo per vedere subito i cambiamenti)
    | In produzione imposta a 3600 per ridurre le richieste OPTIONS
    |--------------------------------------------------------------------------
    */
    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Credenziali (cookie, Authorization header)
    |--------------------------------------------------------------------------
    | true → obbligatorio per Sanctum + withCredentials: true in Axios
    | ATTENZIONE: quando supports_credentials è true, allowed_origins
    | NON può contenere '*' — deve essere una lista esplicita (già così sopra)
    |--------------------------------------------------------------------------
    */
    'supports_credentials' => true,

];
