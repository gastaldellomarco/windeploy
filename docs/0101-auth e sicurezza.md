<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Sei un senior Laravel security developer. Progetto: WinDeploy

Stack: Laravel 11, PHP 8.3, Sanctum + JWT (tymon/jwt-auth),
spatie/laravel-permission, MySQL 8

═══ CONTESTO ═══
Le route esistono già in routes/api.php ma il controller è vuoto.
Il sistema ha DUE tipi di autenticazione:

- Sanctum: per la web app React (utenti umani)
- JWT monouso: per l'agent Windows .exe (macchine)

Ruoli utente: admin / tecnico / viewer

═══ FILE DA ALLEGARE PRIMA DI INVIARE ═══
→ backend/routes/api.php
→ backend/app/Http/Controllers/Api/Auth/AuthController.php (anche se vuoto)
→ backend/app/Models/User.php
→ backend/database/migrations/*_create_users_table.php
→ backend/config/auth.php
→ backend/.env (oscura i valori reali, serve la struttura)

═══ IMPLEMENTA COMPLETAMENTE ═══

1. app/Http/Controllers/Api/Auth/AuthController.php
Metodi: login, logout, me, refresh
    - login: valida email+password, ritorna token Sanctum + dati utente
{token, user:{id, nome, email, ruolo}}
    - logout: invalida il token corrente
    - me: ritorna utente autenticato con ruolo
    - refresh: rinnova il token (se scaduto)
    - Rate limiting: max 5 tentativi login per IP, blocco 15 minuti
    - Legge IP reale da header CF-Connecting-IP (Cloudflare Tunnel)
2. app/Http/Requests/Auth/LoginRequest.php
Validazione: email required|email, password required|string|min:8
3. app/Http/Resources/UserResource.php
Campi: id, nome, email, ruolo, last_login, created_at
4. app/Http/Middleware/CheckRole.php
Middleware che verifica il ruolo tramite spatie/permission
Uso nelle route: middleware('role:admin') o middleware('role:admin|tecnico')
5. Aggiornamento bootstrap/app.php o Kernel.php per registrare il middleware
6. Seeder per creare il primo utente admin:
email: [admin@windeploy.local](mailto:admin@windeploy.local) | password: Admin@1234! (da cambiare)

Codice Laravel 11 completo, commentato in italiano.
Ogni file con il percorso completo in intestazione.
Niente teoria, solo codice operativo.

AuthController.php
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
    public function login(LoginRequest \$request)
    {
        // Find active user by email
        \$user = User::where('email', \$request->input('email'))
            ->where('attivo', true)
            ->first();

// Validate credentials
        if (! $user || ! Hash::check($request->input('password'), \$user->password)) {
            // Do not leak which field is wrong
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

// Update audit fields (last login + IP)
        \$user->forceFill([
            'last_login'    => now(),
            'last_login_ip' => \$request->ip(), // On XAMPP this uses REMOTE_ADDR
        ])->save();

// Optional: revoke previous "web" tokens for this user to keep a single active SPA session
        \$user->tokens()->where('name', 'web')->delete();

// Create a Sanctum token with 8h expiration
        // Laravel 12 Sanctum allows custom expiration per token via third argument.[web:15]
        \$token = \$user->createToken(
            name: 'web',
            abilities: ['*'],
            expiresAt: now()->addHours(8)
        );

return response()->json([
            'token'            => \$token->plainTextToken,
            'token_expires_at' => \$token->accessToken['expires_at'] ?? null,
            'user' => [
                'id'    => \$user->id,
                'name'  => \$user->nome,
                'email' => \$user->email,
                'role'  => \$user->ruolo,
            ],
        ]);
    }

/**
     * Logout and revoke the current Sanctum token.
     */
    public function logout(Request \$request)
    {
        \$user = \$request->user();

if (\$user \&\& \$user->currentAccessToken()) {
            // Revoke only the token used for this request
            \$user->currentAccessToken()->delete();
        }

return response()->json([
            'message' => 'Logged out.',
        ]);
    }

/**
     * Return current authenticated user info.
     */
    public function me(Request \$request)
    {
        \$user = \$request->user();

return response()->json([
            'id'    => \$user->id,
            'name'  => \$user->nome,
            'email' => \$user->email,
            'role'  => \$user->ruolo,
        ]);
    }
}

user.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

/**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected \$fillable = [
        'nome',          // <-- invece di 'name'
        'email',
        'password',
        'ruolo',
        'attivo',
        'last_login',
        'last_login_ip',
    ];

/**
     * Accessor opzionale per compatibilità con 'name'
     * (es. se in futuro qualche pacchetto lo usa).
     */
    public function getNameAttribute(): ?string
    {
        return \$this->attributes['nome'] ?? null;
    }

/**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return \$this->getKey();
    }

/**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }
}

2026_27_02_000001_create_users_table.php
<?php
// database/migrations/2024_01_01_000001_create_users_table.php — CORRETTA

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint \$table) {
            \$table->id();
            \$table->string('nome', 100);
            \$table->string('email')->unique();
            \$table->string('password');
            \$table->enum('ruolo', ['admin', 'tecnico', 'viewer'])->default('tecnico');
            \$table->boolean('attivo')->default(true);
           
            // ✅ AGGIUNTO: timestamps standard Laravel
            \$table->timestamps();  // created_at + updated_at
           
            \$table->timestamp('last_login')->nullable();
            \$table->string('last_login_ip', 45)->nullable();
            \$table->softDeletes();  // deleted_at

// Indici
            \$table->index('ruolo');
            \$table->index('attivo');
            \$table->index(['email', 'attivo']);
        });
    }

public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

auth.php
<?php

return [

/*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
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
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        // API guard used by routes protected with 'auth:api'
        'api' => [
            // Use JWT for API authentication (the agent sends a Bearer JWT)
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],

/*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],

// 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

/*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

/*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
.env

# File: C:\xampp\htdocs\windeploy\backend\.env

APP_NAME=WinDeploy
APP_ENV=local
APP_KEY=            \# php artisan key:generate
APP_DEBUG=true
APP_TIMEZONE=Europe/Rome

# Allinea con il virtual host del backend in httpd-vhosts.conf

APP_URL=http://windeploy.local.api
ASSET_URL=http://windeploy.local.api

# ── DATABASE ──

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=windeploy
DB_USERNAME=
DB_PASSWORD=

# ── CACHE / SESSIONI ──

CACHE_STORE=file
SESSION_DRIVER=file
SESSION_LIFETIME=480
SESSION_SECURE_COOKIE=false

# ── QUEUE ──

QUEUE_CONNECTION=sync

# ── LOGGING ──

LOG_CHANNEL=daily
LOG_LEVEL=debug

# ── JWT (agent Windows) ──

JWT_SECRET=          \# php artisan jwt:secret
JWT_TTL=240
JWT_REFRESH_TTL=20160   \# 2 settimane; non mettere 0

# ── SANCTUM (SPA React) ──

# windeploy.local = virtual host frontend, localhost:5173 = Vite dev server

SANCTUM_STATEFUL_DOMAINS=localhost:5173,windeploy.local

# Disabilita DebugBar per le richieste API (evita HTML injection)

DEBUGBAR_ENABLED=false

# Broadcasting: usa 'log' in locale (evita eccezioni Pusher/Redis non configurati)

BROADCAST_DRIVER=log

# ── MAIL ──

MAIL_MAILER=log
[MAIL_FROM_ADDRESS=noreply@windeploy.local](mailto:MAIL_FROM_ADDRESS=noreply@windeploy.local)
MAIL_FROM_NAME=WinDeploy

Ho tutta la base di codice necessaria. Ora genero l'implementazione completa e aggiornata tenendo conto dell'`AuthController.php` già allegato (che ha già login/logout/me ma manca di: rate limiting con CF-Connecting-IP, metodo `refresh`, `UserResource`, `LoginRequest`, `CheckRole`, seeder, e registrazione middleware).

***

## Struttura file coinvolti

```
app/
├── Http/
│   ├── Controllers/Api/Auth/
│   │   └── AuthController.php          ← aggiornato (+ refresh + rate limit + CF IP)
│   ├── Middleware/
│   │   └── CheckRole.php               ← nuovo
│   ├── Requests/Auth/
│   │   └── LoginRequest.php            ← nuovo
│   └── Resources/
│       └── UserResource.php            ← nuovo
├── Providers/
│   └── AppServiceProvider.php          ← aggiunto rate limiter 'login'
bootstrap/
└── app.php                             ← registrazione alias middleware 'role'
database/seeders/
└── AdminUserSeeder.php                 ← nuovo
```


***

## 1. `AuthController.php` — Versione completa

```php
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
```

> ⚠️ **Nota sicurezza `refresh`**: Sanctum non supporta refresh token scaduti. Se il token è già scaduto, il guard `auth:sanctum` restituisce 401 prima di entrare nel metodo. Per un vero "silent refresh", valuta in futuro un secondo token a lunga scadenza (`refresh_token` separato) oppure il cookie-based stateful Sanctum per SPA.[^1_1]

***

## 2. `LoginRequest.php`

```php
<?php
// File: app/Http/Requests/Auth/LoginRequest.php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * L'endpoint di login è pubblico.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regole di validazione per il login.
     */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ];
    }

    /**
     * Messaggi di errore personalizzati.
     */
    public function messages(): array
    {
        return [
            'email.required'    => 'L\'email è obbligatoria.',
            'email.email'       => 'Formato email non valido.',
            'password.required' => 'La password è obbligatoria.',
            'password.min'      => 'La password deve essere di almeno 8 caratteri.',
        ];
    }
}
```


***

## 3. `UserResource.php`

```php
<?php
// File: app/Http/Resources/UserResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Trasforma il modello User in array JSON.
     * Non espone mai la password o campi sensibili.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'nome'       => $this->nome,
            'email'      => $this->email,
            'ruolo'      => $this->ruolo,
            'last_login' => $this->last_login?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```


***

## 4. `CheckRole.php` — Middleware con Spatie

```php
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
```

> ⚠️ **Nota Spatie**: nel modello `User` hai già `use HasRoles`, quindi `$user->hasRole()` funziona correttamente. Spatie usa la propria tabella `roles` separata dalla colonna `ruolo` nell'enum. **Se usi solo la colonna `ruolo` come fonte di verità** (come da migration), puoi togliere `HasRoles` dal modello o mantenere entrambi in sync tramite seeder. Scegli una sola strategia di ruolo per evitare inconsistenze.[^1_2]

***

## 5. `bootstrap/app.php` — Registrazione middleware alias

```php
<?php
// File: bootstrap/app.php
// Aggiungere la sezione ->withMiddleware() esistente

use App\Http\Middleware\CheckRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Alias per il middleware di controllo ruolo
        // Uso: ->middleware('role:admin') o ->middleware('role:admin|tecnico')
        $middleware->alias([
            'role' => CheckRole::class,
        ]);

        // Trustare gli header Cloudflare per IP reale (Cloudflare Tunnel in produzione)
        // In locale (XAMPP) non ha effetto, ma non crea problemi
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function ($exceptions) {
        //
    })
    ->create();
```


***

## 6. Rate Limiter in `AppServiceProvider.php`

```php
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
```


***

## 7. `AdminUserSeeder.php`

```php
<?php
// File: database/seeders/AdminUserSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea l'utente admin iniziale per WinDeploy.
     *
     * ⚠️ SICUREZZA: cambia la password immediatamente dopo il primo accesso.
     * Non committare questo seeder con password reali nel repository.
     * In produzione usa variabili d'ambiente o un vault.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@windeploy.local'],
            [
                'nome'     => 'Amministratore',
                'email'    => 'admin@windeploy.local',
                'password' => Hash::make('Admin@1234!'),
                'ruolo'    => 'admin',
                'attivo'   => true,
            ]
        );

        $this->command->info('✅ Admin creato: admin@windeploy.local / Admin@1234!');
        $this->command->warn('⚠️  Cambia la password al primo accesso!');
    }
}
```

Registra il seeder in `DatabaseSeeder.php`:

```php
// File: database/seeders/DatabaseSeeder.php
public function run(): void
{
    $this->call([
        AdminUserSeeder::class,
    ]);
}
```

Esecuzione:

```bash
php artisan db:seed --class=AdminUserSeeder
```


***

## Checklist operativa finale

- [ ] Eseguire `php artisan migrate` per creare la tabella `personal_access_tokens` (Sanctum)
- [ ] Verificare che `config/sanctum.php` abbia `expiration` null (usiamo `expiresAt` per-token)[^1_1]
- [ ] Aggiungere `throttle:login` alla route `POST /api/auth/login` in `api.php` (già presente nella tua versione)[^1_2]
- [ ] Aggiungere `throttle:login` alla route `POST /api/agent/auth` in `api.php`[^1_2]
- [ ] Eseguire `php artisan db:seed --class=AdminUserSeeder`
- [ ] Cambiare password admin al primo accesso
- [ ] **Decisione da prendere**: usare Spatie `roles` table OR colonna `ruolo` enum — non entrambe in parallelo senza sync

***

## ⚠️ Trade-off e rischi

| Aspetto | Rischio | Mitigazione |
| :-- | :-- | :-- |
| `refresh` con Sanctum | Non gestisce token scaduti, richiede re-login | Accettabile per tool interno; valuta cookie stateful per UX migliore |
| Doppio sistema ruoli (Spatie + enum `ruolo`) | Desync silenzioso tra le due fonti | Scegli una sola fonte, rimuovi l'altra o mantienile in sync via observer |
| CF-Connecting-IP senza TrustProxies | In produzione senza tunnel attivo, l'IP può essere falsificato nell'header | `trustProxies(at: '*')` già inserito in `bootstrap/app.php`; in prod limita agli IP Cloudflare |
| Seeder con password hardcoded | Se committato, la password è in git history | Usare `.env` per la password del seeder oppure cancellare dalla history post-setup |

<span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_19][^1_20][^1_21][^1_3][^1_4][^1_5][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: 0010-autenticazione.md

[^1_2]: api.php

[^1_3]: 0100-File temporanei tmp_.php esposti nella root.md

[^1_4]: 0021-Installer-configurazione sistema.md

[^1_5]: 0020-Scansione app installate.md

[^1_6]: 0019-Struttura agent.md

[^1_7]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^1_8]: 0017-motorpage.md

[^1_9]: 0016-wizards.md

[^1_10]: 0015-dashboard.md

[^1_11]: 0014-Login.md

[^1_12]: 0013-SetupprogettoReact.md

[^1_13]: 0012-apiendpointwindows.md

[^1_14]: 0011-controllercrud.md

[^1_15]: 0009-scaffoldprogetto.md

[^1_16]: 0008-db.md

[^1_17]: 0007-cheatsheet.md

[^1_18]: 0006-viteconfigjs.md

[^1_19]: 0005-filedotenv.md

[^1_20]: 0004-Strutturacartelle.md

[^1_21]: 0003-setupiniziale.md

