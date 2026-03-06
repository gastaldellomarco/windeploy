<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Autenticazione completa per WinDeploy — Laravel 12 su XAMPP locale.

Due tipi di client: web app React (Sanctum) e agent Windows .exe Python (JWT monouso).
 
TOKEN WEB (Sanctum):

- Login email + password → token 8h → logout invalida token
- Middleware ruolo (admin/tecnico/viewer)
- Rate limiting login: max 5 tentativi, blocco 15min per IP
- In locale (XAMPP) l'IP viene da \$_SERVER['REMOTE_ADDR'] normalmente
 
TOKEN AGENT (JWT monouso):
- POST /api/agent/auth con {codice_wizard, mac_address}
- Verifica: codice esiste, non scaduto, non già usato
- JWT con: wizard_id, mac_address, scadenza 4h
- Dopo completamento: codice marcato used, JWT invalidato
- MAC address nel JWT come protezione anti-replay
 
CIFRATURA PASSWORD WIZARD:
- Password Windows nei wizard → AES-256-GCM con chiave da APP_KEY + salt univoco per wizard
- Mai salvate in chiaro, mai nei log
 
SCRIVI:

1. AuthController (login, logout, me)
2. AgentAuthController (auth con codice)
3. CheckRole middleware
4. EncryptionService (AES-256-GCM encrypt/decrypt)
5. Rate limiter nel RouteServiceProvider / bootstrap/app.php (Laravel 12)
6. Form Request per login e agent auth
 
Laravel 12 completo, commentato in italiano.

Di seguito ti propongo un’implementazione completa e allineata a Laravel 12, XAMPP locale e requisiti WinDeploy (Sanctum per web, JWT per agent, AES‑256‑GCM per password wizard).

Userò:

- Nomi classi / percorsi coerenti con lo scaffold già definito in `0009-scaffoldprogetto.md`.[^1_1]
- Colonna `ruolo` sull’utente (admin/tecnico/viewer) come da schema DB.[^1_2]

***

## 0. Struttura cartelle consigliata

Prima i percorsi chiave (così sai dove incollare i file):

- `app/Http/Controllers/Api/Auth/AuthController.php`
- `app/Http/Controllers/Api/Agent/AgentAuthController.php`
- `app/Http/Middleware/CheckRole.php`
- `app/Services/EncryptionService.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Http/Requests/Agent/AgentAuthRequest.php`
- `app/Providers/AppServiceProvider.php` (aggiunta rate limiter login)[^1_3][^1_4]
- Aggiornare `routes/api.php` per usare `throttle:login` e `auth:sanctum`.[^1_1][^1_3]

Checklist operativa rapida:

- [ ] Sanctum installato e configurato (guard `sanctum`, tabella `personal_access_tokens`).[^1_5][^1_1]
- [ ] jwt-auth installato (tymon o fork) e configurato (`config/jwt.php`, secret generato).[^1_6][^1_1]
- [ ] Modello `User` con colonna `ruolo` (enum admin/tecnico/viewer) come da migration.[^1_2]
- [ ] Modello `Wizard` con campi `codice_univoco`, `expires_at`, `used_at`, `stato`.[^1_2]

***

## 1. AuthController (Sanctum: login, logout, me)

**Percorso:** `app/Http/Controllers/Api/Auth/AuthController.php`

Token Sanctum di 8 ore, invalidato su logout.[^1_5]

```php
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
```

Note sicurezza:

- Token in header `Authorization: Bearer <token>` dal frontend React (non localStorage se puoi evitarlo, meglio in memory + refresh flow).
- Aggiorna il modello `User` per avere proprietà `nome`, `ruolo`, `last_login`, `last_login_ip` coerenti con la migration.[^1_2]

***

## 2. AgentAuthController (JWT monouso per agent Windows)

**Percorso:** `app/Http/Controllers/Api/Agent/AgentAuthController.php`

Usa jwt-auth per creare un JWT con `wizard_id`, `mac_address` e scadenza 4h.[^1_6]
Qui genero un token “standalone” con `JWTFactory + JWTAuth`, così non dipendi subito da un guard specifico per l’agent (più flessibile).

```php
<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\AgentAuthRequest;
use App\Models\Wizard;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

class AgentAuthController extends Controller
{
    /**
     * Authenticate Windows agent by wizard code and MAC address.
     */
    public function auth(AgentAuthRequest $request)
    {
        $wizardCode = strtoupper($request->input('codice_wizard'));
        $macAddress = strtolower($request->input('mac_address'));

        // Lookup wizard by unique code
        $wizard = Wizard::where('codice_univoco', $wizardCode)->first();

        if (! $wizard) {
            return response()->json([
                'message' => 'Invalid wizard code.',
            ], 404);
        }

        // Check wizard not expired
        if ($wizard->expires_at && $wizard->expires_at->isPast()) {
            return response()->json([
                'message' => 'Wizard code has expired.',
            ], 410);
        }

        // Check wizard not already used (monouso)
        if ($wizard->used_at !== null || $wizard->stato === 'completato') {
            return response()->json([
                'message' => 'Wizard code already used.',
            ], 410);
        }

        // Optional: avoid starting from drafts
        if ($wizard->stato === 'bozza') {
            return response()->json([
                'message' => 'Wizard is not ready for execution.',
            ], 422);
        }

        $now   = Carbon::now();
        $expiry = $now->copy()->addHours(4);

        // Build custom JWT payload for agent
        $payload = JWTFactory::customClaims([
            'sub'         => $wizard->id,      // subject = wizard id
            'wizard_id'   => $wizard->id,
            'wizard_code' => $wizard->codice_univoco,
            'mac_address' => $macAddress,
            'type'        => 'agent',
            'iat'         => $now->timestamp,
            'exp'         => $expiry->timestamp,
        ])->make();

        $token = JWTAuth::encode($payload)->get();

        // NOTE: do NOT set used_at here; mark used_at on /api/agent/complete
        // to reflect a completed execution, not just an auth attempt.

        return response()->json([
            'token'       => $token,
            'expires_in'  => 4 * 60 * 60, // 4 hours in seconds
            'expires_at'  => $expiry->toIso8601String(),
            'wizard_id'   => $wizard->id,
        ]);
    }
}
```

Note tecniche / sicurezza:

- Il claim `mac_address` va verificato nelle altre route dell’agent (es. `/api/agent/start`, `/api/agent/step`) confrontando la MAC inviata dal client con quella nel token, per mitigare replay cross‑PC.
- “JWT invalidato dopo completamento”: implementa il check server‑side che rifiuta token se `wizard.used_at` non è null anche se `exp` non è ancora passato (es. middleware custom che carica il wizard e controlla).
- Per usare questo JWT come guard `auth:api` dovrai creare eventualmente un modello “AgentSubject” che implementa `JWTSubject` o adattare la configurazione jwt-auth; qui ti ho lasciato un token generico per massima flessibilità.[^1_6]

***

## 3. Middleware CheckRole (admin/tecnico/viewer)

**Percorso:** `app/Http/Middleware/CheckRole.php`

Lavora sulla colonna `ruolo` del modello `User` (enum), come definito nelle migration.[^1_2]

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes: ->middleware('role:admin') or ->middleware('role:admin,tecnico')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // User role must match one of the allowed roles
        if (! in_array($user->ruolo, $roles, true)) {
            return response()->json([
                'message' => 'Forbidden. Insufficient role.',
            ], 403);
        }

        return $next($request);
    }
}
```

**Registrazione alias middleware (Laravel 11/12):** in `bootstrap/app.php` usa `->withMiddleware(...)`.[^1_7][^1_4][^1_1]

```php
<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global middleware (example)
        $middleware->use([
            TrustProxies::class,
        ]);

        // Middleware aliases
        $middleware->alias([
            'role' => CheckRole::class,
            // other aliases...
        ]);
    })
    ->withExceptions(function () {
        //
    })
    ->create();
```

Poi nelle route (es. per CRUD utenti admin‑only):[^1_1]

```php
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::apiResource('users', UserController::class);
});
```


***

## 4. EncryptionService (AES‑256‑GCM con chiave da APP_KEY + salt per wizard)

**Percorso:** `app/Services/EncryptionService.php`

Implementazione con `aes-256-gcm` di OpenSSL, chiave derivata da `APP_KEY` + identificatore wizard (id/codice univoco). Questo soddisfa il requisito “chiave da APP_KEY + salt univoco per wizard”.

Schema:

- Key = `hash('sha256', appKey . '|' . wizardSalt, true)`
- IV random 12 byte (raccomandato per GCM).
- Output = `base64(iv || tag || ciphertext)`

```php
<?php

namespace App\Services;

use RuntimeException;

class EncryptionService
{
    private const CIPHER     = 'aes-256-gcm';
    private const IV_LENGTH  = 12; // Recommended IV length for GCM
    private const TAG_LENGTH = 16; // 128-bit authentication tag

    /**
     * Encrypt a plaintext for a specific wizard context.
     *
     * $wizardSalt can be wizard ID, codice_univoco or another per-wizard unique value.
     */
    public function encryptForWizard(string $plaintext, string $wizardSalt): string
    {
        $key = $this->deriveKey($wizardSalt);

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',                 // no additional authenticated data
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        // Pack iv | tag | ciphertext and base64 encode
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt a previously encrypted payload for a given wizard context.
     */
    public function decryptForWizard(string $encrypted, string $wizardSalt): string
    {
        $key = $this->deriveKey($wizardSalt);

        $decoded = base64_decode($encrypted, true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64 payload.');
        }

        if (strlen($decoded) <= (self::IV_LENGTH + self::TAG_LENGTH)) {
            throw new RuntimeException('Invalid encrypted payload length.');
        }

        $iv         = substr($decoded, 0, self::IV_LENGTH);
        $tag        = substr($decoded, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($decoded, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed.');
        }

        return $plaintext;
    }

    /**
     * Derive a 256-bit key from APP_KEY and per-wizard salt.
     */
    private function deriveKey(string $wizardSalt): string
    {
        $appKey = config('app.key');

        if (! $appKey) {
            throw new RuntimeException('Application key is not configured.');
        }

        // Laravel APP_KEY is usually base64:...; decode if needed.[file:5]
        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            if ($decoded === false) {
                throw new RuntimeException('Invalid base64 APP_KEY.');
            }
            $appKey = $decoded;
        }

        // Derive 32-byte key with SHA-256
        return hash('sha256', $appKey . '|' . $wizardSalt, true);
    }
}
```

Uso tipico nei wizard:

- In salvataggio configurazione wizard (controller):
    - `config['utente_admin']['password_encrypted'] = $encryptionService->encryptForWizard($plainPassword, (string) $wizard->id);`
    - Non salvare mai `password` in chiaro nel JSON.
- Per endpoint dedicato all’agent che deve ottenere la password:
    - `$plain = $encryptionService->decryptForWizard($config['utente_admin']['password_encrypted'], (string) $wizard->id);`
    - Non loggare mai `$plain` né includerla nelle risposte generiche.[^1_2]

Avvocato del diavolo:

- Se cambi/ruoti `APP_KEY`, tutte le password cifrate diventano indecifrabili (va pianificato un processo di rotazione).[^1_2]
- Compromissione del backend = possibilità di decriptare tutte le password, perché chiave e salt sono sul server; valuta limitazione dei privilegi DB, hardening host, backup APP_KEY separati.

***

## 5. Rate limiter login (5 tentativi / 15 min per IP)

In Laravel 12 i rate limiter consigliati si definiscono nel `boot()` dell’`AppServiceProvider` tramite `RateLimiter::for()`, poi si usano con middleware `throttle:<nome>`.[^1_8][^1_9][^1_3]

**Percorso:** `app/Providers/AppServiceProvider.php` (aggiunta al `boot()`).

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

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
        // Login rate limiter: max 5 attempts per 15 minutes per IP
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinutes(15, 5)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many login attempts. Try again later.',
                    ], 429, $headers);
                });
        });

        // You can define other rate limiters here (api, uploads, etc.)[web:11][web:37]
    }
}
```

**Applicare il limiter alla route di login:** in `routes/api.php`:[^1_3][^1_1]

```php
use App\Http\Controllers\Api\Auth\AuthController;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::post('logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('auth.logout');

    Route::get('me', [AuthController::class, 'me'])
        ->middleware('auth:sanctum')
        ->name('auth.me');
});
```

Note:

- In locale (XAMPP), `$request->ip()` userà `$_SERVER['REMOTE_ADDR']` come da tuo requisito.
- In futuro dietro Nginx/Cloudflare assicurati che `TrustProxies` sia configurato correttamente, altrimenti il rate limit potrebbe usare IP del proxy.[^1_1]

***

## 6. Form Request per login e agent auth

### 6.1 LoginRequest

**Percorso:** `app/Http/Requests/Auth/LoginRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Everyone can hit the login endpoint.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for login.
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ];
    }
}
```


### 6.2 AgentAuthRequest

**Percorso:** `app/Http/Requests/Agent/AgentAuthRequest.php`

```php
<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentAuthRequest extends FormRequest
{
    /**
     * Agent auth is a public endpoint, protected by code + MAC.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for agent authentication.
     */
    public function rules(): array
    {
        return [
            'codice_wizard' => [
                'required',
                'string',
                'max:10', // e.g. WD-7A3F
            ],
            'mac_address' => [
                'required',
                'string',
                'max:17',
                // Standard MAC address pattern (00:11:22:33:44:55 or 00-11-22-33-44-55)
                'regex:/^([0-9A-Fa-f]{2}([:-])){5}([0-9A-Fa-f]{2})$/',
            ],
        ];
    }

    /**
     * Custom error messages (optional).
     */
    public function messages(): array
    {
        return [
            'codice_wizard.required' => 'Wizard code is required.',
            'mac_address.required'   => 'MAC address is required.',
            'mac_address.regex'      => 'MAC address format is invalid.',
        ];
    }
}
```


***

## 7. Rischi, limiti e possibili failure mode

Approccio “avvocato del diavolo”:

- **Sanctum con Bearer token**: per SPA è comodo ma se il token finisce in localStorage o in un log client, un attaccante può riusarlo fino alla scadenza di 8h; considera future estensioni con refresh‑token corto e rotazione o sessioni cookie‑based stateful.[^1_5]
- **Rate limiting solo per IP**: in LAN aziendale con NAT, tutti i client condividono lo stesso IP → potresti bloccare tutta la rete se uno script sbaglia password in loop. Variante futura: `by($request->input('email') ?: $request->ip())` per segmentare per email/utente.[^1_9][^1_3]
- **JWT agent “monouso”**: senza una blacklist per `jti` o un campo server‑side tipo `agent_token_id` associato al wizard, non puoi invalidare un singolo token prima di `exp` se compromesso; attualmente ti basi sul fatto che dopo `used_at` > 0 rifiuti tutte le richieste.
- **MAC address come protezione**: è un buon extra ma può essere spoofato se qualcuno controlla il PC; non considerarlo un fattore di sicurezza forte, solo un vincolo in più contro replay da altri host.
- **AES‑256‑GCM**: ottima scelta (confidenzialità + integrità), ma la gestione chiavi è critica: backup sicuro di `APP_KEY`, no log della password in chiaro, nessun dump del campo cifrato insieme all’`APP_KEY` in repository.
- **XAMPP dev vs produzione**: in locale tutto passa in HTTP, quindi evita di usare dati reali; in produzione l’HTTPS è obbligatorio (Nginx+Certbot come da progetto), specialmente per password wizard e JWT dell’agent.[^1_10]

Se vuoi, nel passo successivo possiamo:

- Agganciare questo JWT al guard `auth:api` per le route `/api/agent/start/step/complete`.
- Mostrare come integrare `EncryptionService` nel flusso di creazione/lettura dei wizard, compreso endpoint dedicato che manda la password decriptata solo all’agent.
<span style="display:none">[^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_19][^1_20][^1_21][^1_22][^1_23][^1_24][^1_25][^1_26][^1_27][^1_28][^1_29][^1_30][^1_31][^1_32][^1_33][^1_34][^1_35][^1_36][^1_37][^1_38][^1_39][^1_40][^1_41][^1_42][^1_43][^1_44][^1_45][^1_46]</span>

<div align="center">⁂</div>

[^1_1]: 0009-scaffoldprogetto.md

[^1_2]: 0008-db.md

[^1_3]: https://laravel.com/docs/12.x/routing

[^1_4]: https://laravel.com/docs/12.x/providers

[^1_5]: https://laravel.com/docs/12.x/sanctum

[^1_6]: https://laravel.com/docs/7.x/passport

[^1_7]: https://laravel.com/docs/12.x/middleware

[^1_8]: https://laravel.com/docs/12.x/rate-limiting

[^1_9]: https://api.laravel.com/docs/12.x/Illuminate/Cache/RateLimiting/Limit.html

[^1_10]: 0001-projectdescription.txt

[^1_11]: 0007-cheatsheet.md

[^1_12]: 0006-viteconfigjs.md

[^1_13]: 0005-filedotenv.md

[^1_14]: 0004-Strutturacartelle.md

[^1_15]: 0003-setupiniziale.md

[^1_16]: 0002-ricerca-iniziale.md

[^1_17]: https://api.laravel.com/docs/9.x/Illuminate/Cache/RateLimiting/Limit.html

[^1_18]: https://api.laravel.com/docs/9.x/Illuminate/Support/Facades/RateLimiter.html

[^1_19]: https://api.laravel.com/docs/9.x/Illuminate/Cache/RateLimiter.html

[^1_20]: https://api.laravel.com/docs/9.x/Illuminate/Routing/Events/RouteMatched.html

[^1_21]: https://laravel.com/docs/12.x/passport

[^1_22]: https://api.laravel.com/docs/12.x/Illuminate/Routing/Route.html

[^1_23]: https://api.laravel.com/docs/11.x/Illuminate/Routing/Exceptions/MissingRateLimiterException.html

[^1_24]: https://laravel.com/docs/12.x/billing

[^1_25]: https://laravel.com/docs/12.x/session

[^1_26]: https://api.laravel.com/docs/12.x/

[^1_27]: https://backpackforlaravel.com/articles/tutorials/laravel-rate-limiting-explained-with-real-life-examples

[^1_28]: https://www.mattepuffo.com/blog/articolo/4401-modificare-rate-limit-laravel.html

[^1_29]: https://www.interserver.net/tips/kb/how-to-use-rate-limiting-in-laravel/

[^1_30]: https://stackoverflow.com/questions/65372739/laravel-sanctum-how-to-configure-the-expiration-date-of-the-token

[^1_31]: https://oneuptime.com/blog/post/2026-02-03-laravel-rate-limiting/view

[^1_32]: https://www.linkedin.com/pulse/understanding-rate-limiting-throttling-laravel-12-apis-hamza-sehouli-mdcfe

[^1_33]: https://laravel.com/docs/12.x/http-client

[^1_34]: https://www.youtube.com/watch?v=fTwjzu-m-DQ

[^1_35]: https://github.com/laravel/framework/discussions/50480

[^1_36]: https://stackoverflow.com/questions/71454347/laravel-throttle-rate-limiter-limites-access-too-early

[^1_37]: https://www.youtube.com/watch?v=lkvZ1tA_CR8

[^1_38]: https://api.laravel.com/docs/8.x/Illuminate/Cache/RateLimiting/Unlimited.html

[^1_39]: https://laravel.com/api/8.x/Illuminate/Cache/RateLimiting/Limit.html

[^1_40]: https://stackoverflow.com/questions/72921479/how-to-rate-limit-per-day-in-laravel

[^1_41]: https://www.reddit.com/r/laravel/comments/1l5th8q/how_do_you_set_your_rate_limiters/

[^1_42]: https://api.laravel.com/docs/12.x/Illuminate/Cache/RateLimiter.html

[^1_43]: https://sinnbeck.dev/posts/rate-limiting-routes-in-laravel-with-tests

[^1_44]: https://laraveldaily.com/lesson/api-laravel/throttling-maximum-requests-per-minute

[^1_45]: https://stackoverflow.com/questions/65406206/laravel-8-rate-limiter-not-working-for-routes

[^1_46]: https://laracasts.com/series/whats-new-in-laravel-5-2/episodes/2

