# ⚠️ FILE DEPRECATO

**Questo file era una richiesta di audit per implementazione JWT.**

L'implementazione è completata e documentata in:  
→ **`docs/0101-auth e sicurezza.md`** (implementazione finale)  
→ **`docs/0120-agent-api-reference.md`** (endpoint agent)

---

<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Sei un senior Laravel security developer. Progetto: WinDeploy

Stack: Laravel 11, PHP 8.3, tymon/jwt-auth, Sanctum, MySQL 8

═══ CONTESTO ═══
Il progetto usa DUE sistemi di autenticazione paralleli:

- Sanctum: per la web app React (guard 'sanctum', utenti umani)
- JWT (tymon/jwt-auth): per l'agent Windows .exe (guard 'api', macchine)

Le route agent usano middleware('auth:api') ma il guard 'api' con
driver jwt non è configurato in config/auth.php — risultato: tutte
le chiamate /api/agent/\* falliscono con 401 anche con token valido.

═══ FILE DA ALLEGARE PRIMA DI INVIARE ═══
→ backend/config/auth.php (attuale)
→ backend/routes/api.php (sezione route agent)
→ backend/app/Http/Controllers/Api/Agent/AgentController.php
→ backend/composer.json (per verificare versione tymon/jwt-auth installata)
→ backend/composer.lock (sezione tymon se presente)
→ backend/config/jwt.php (se già pubblicato, altrimenti segnalalo)
→ backend/bootstrap/app.php o app/Http/Kernel.php

═══ COSA VOGLIO ═══

1. VERIFICA INSTALLAZIONE:
   Dimmi esattamente come verificare che tymon/jwt-auth sia installato
   e quale versione è compatibile con Laravel 11.
   Se non è installato: comando composer esatto da usare.
   Se è installato ma non pubblicato: comandi per pubblicare e generare secret.
2. CONFIG/AUTH.PHP COMPLETO:
   Fornisci il file config/auth.php completo con: - guard 'web' (session, default Laravel) - guard 'sanctum' (per web app React) - guard 'api' (driver jwt, per agent Windows) - provider 'users' correttamente configurato
   Spiega in commenti inline perché ogni guard è separato.
3. MODELLO USER:
   Verifica che app/Models/User.php implementi
   Tymon\\JWTAuth\\Contracts\\JWTSubject con i metodi: - getJWTIdentifier() - getJWTCustomClaims()
   Fornisci il trait/interface da aggiungere se mancante.
   ATTENZIONE: User già usa HasApiTokens di Sanctum —
   verifica che i due non confliggano.
4. AGENTCONTROLLER — USO CORRETTO DEL GUARD:
   Nel metodo auth() che genera il token JWT, verifica che usi:
   auth('api')->attempt() oppure JWTAuth::fromUser()
   NON auth()->attempt() che userebbe il guard di default (Sanctum).
   Mostra il codice corretto per generazione e risposta token.

Nei metodi start/step/complete/abort, verifica che il middleware
auth:api legga il token dall'header Authorization: Bearer {token}
e che JWTAuth::parseToken()->authenticate() funzioni correttamente. 5. THROTTLE AGENT:
Le route usano throttle:agent — verifica che questo throttle
sia definito in RouteServiceProvider o bootstrap/app.php.
Se manca: fornisci la definizione corretta (120 richieste/minuto per token). 6. TEST SEQUENZA COMPLETA:
Fornisci la sequenza di chiamate Postman/curl per verificare
che tutto funzioni:
a) POST /api/agent/auth → ricevo JWT
b) POST /api/agent/start con header Authorization: Bearer {JWT} → 200
c) Chiamata senza token → 401
d) Token Sanctum su route agent → 401 (guard sbagliato)
Includi i payload JSON esatti per ogni chiamata. 7. COMANDI GIT:
Commit message convenzionale per questa fix di configurazione.

Ogni file modificato con percorso completo in intestazione.
Codice completo, niente abbreviazioni. Commenti in italiano.

api.php

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Wizard\WizardController;
use App\Http\Controllers\Api\Template\TemplateController;
use App\Http\Controllers\Api\Software\SoftwareController;
use App\Http\Controllers\Api\Report\ReportController;
use App\Http\Controllers\Api\Agent\AgentController;
use App\Http\Controllers\Api\User\UserController;

/*
|--------------------------------------------------------------------------


| API Routes |
| :-- |
|  |
| Tutte queste rotte sono automaticamente prefissate con /api |
| grazie alla configurazione di Laravel 11 in bootstrap/app.php. |
|  |

*/

/*
|--------------------------------------------------------------------------


| Health check API (opzionale, /api/ping) |
| :-- |

*/
Route::get('/ping', function () {
    return response()->json(['status' => 'ok']);
});

/*
|--------------------------------------------------------------------------
| AUTH (Sanctum) - Login/logout/refresh/me per web app React


| Prefix: /api/auth |
| :-- |

*/
Route::prefix('auth')->group(function () {
    // Login utente (email/password) -> crea sessione/token Sanctum
    Route::post('/login', [AuthController::class, 'login'])
        ->name('auth.login');

// Logout utente -> revoca token/sessione corrente
    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('auth.logout');

// Refresh token/sessione (se usi un meccanismo di refresh custom)
    Route::post('/refresh', [AuthController::class, 'refresh'])
        ->middleware('auth:sanctum')
        ->name('auth.refresh');

// Restituisce i dati dell'utente autenticato
    Route::get('/me', [AuthController::class, 'me'])
        ->middleware('auth:sanctum')
        ->name('auth.me');
});

/*
|--------------------------------------------------------------------------


| API protette per WEB APP (middleware: auth:sanctum) |
| :-- |
|  |
| Tutte le rotte qui dentro richiedono autenticazione web app. |
|  |

*/
Route::middleware('auth:sanctum')->group(function () {

/*
    |----------------------------------------------------------------------
    | WIZARDS - CRUD + genera codice + monitor
    | Prefix implicito: /api/wizards
    |----------------------------------------------------------------------
    */

// CRUD completo dei wizard (index, store, show, update, destroy)
    Route::apiResource('wizards', WizardController::class);

// Genera codice univoco per il wizard (es. WD-7A3F) + scadenza
    Route::post('wizards/{wizard}/generate-code', [WizardController::class, 'generateCode'])
        ->name('wizards.generate-code');

// Monitor real-time stato esecuzione wizard (polling logs)
    Route::get('wizards/{wizard}/monitor', [WizardController::class, 'monitor'])
        ->name('wizards.monitor');

/*
    |----------------------------------------------------------------------
    | TEMPLATES - CRUD
    | Prefix: /api/templates
    |----------------------------------------------------------------------
    */

// CRUD template wizard (globali + personali)
    Route::apiResource('templates', TemplateController::class);

/*
    |----------------------------------------------------------------------
    | SOFTWARE LIBRARY - CRUD + toggleActive
    | Prefix: /api/software
    |----------------------------------------------------------------------
    */

// CRUD libreria software (nome, versione, tipo winget/custom, ecc.)
    Route::apiResource('software', SoftwareController::class);

// Attiva/disattiva una entry software (non viene eliminata fisicamente)
    Route::patch('software/{software}/toggle-active', [SoftwareController::class, 'toggleActive'])
        ->name('software.toggle-active');

/*
    |----------------------------------------------------------------------
    | REPORTS - lista + dettaglio + download
    | Prefix: /api/reports
    |----------------------------------------------------------------------
    */

// Lista report con filtri (data, tecnico, stato, ecc.)
    Route::get('reports', [ReportController::class, 'index'])
        ->name('reports.index');

// Dettaglio singolo report (JSON + eventuale HTML embeddato)
    Route::get('reports/{report}', [ReportController::class, 'show'])
        ->name('reports.show');

// Download report HTML come file (Content-Disposition: attachment)
    Route::get('reports/{report}/download', [ReportController::class, 'download'])
        ->name('reports.download');

/*
    |----------------------------------------------------------------------
    | USERS (Admin only) - CRUD
    | Prefix: /api/users
    | Middleware extra: role:admin (Spatie)
    |----------------------------------------------------------------------
    */

Route::middleware('role:admin')->group(function () {
        // CRUD completo utenti (admin crea/modifica/disattiva utenti)
        Route::apiResource('users', UserController::class);
    });
});

/*
|--------------------------------------------------------------------------
| AGENT (JWT) - Endpoint per eseguibile Windows


| Prefix: /api/agent |
| :-- |
|  |
| Queste rotte sono pensate per l'agent Python (CustomTkinter) che gira |
| su Windows e comunica via HTTPS con il backend. |
|  |

*/
Route::prefix('agent')->group(function () {

// Autenticazione agent -> ritorna JWT per successive chiamate
    Route::post('/auth', [AgentController::class, 'auth'])
        ->middleware('throttle:login') // 5 tentativi
        ->name('agent.auth');

// Rotte protette da JWT (guard "api" con driver jwt)
    Route::middleware(['auth:api', 'throttle:agent'])->group(function () {

// Avvio esecuzione wizard su un PC (inizio sessione)
        Route::post('/start', [AgentController::class, 'start'])
            ->name('agent.start');

// Invio step intermedi (log, stato corrente, percentuale)
        Route::post('/step', [AgentController::class, 'step'])
            ->name('agent.step');

// Segnala completamento wizard (successo o con errori)
        Route::post('/complete', [AgentController::class, 'complete'])
            ->name('agent.complete');

// Abort manuale/forzato del wizard sul PC
        Route::post('/abort', [AgentController::class, 'abort'])
            ->name('agent.abort');
    });
});
AgentController.php
<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\AgentAbortRequest;
use App\Http\Requests\Agent\AgentAuthRequest;
use App\Http\Requests\Agent\AgentCompleteRequest;
use App\Http\Requests\Agent\AgentStartRequest;
use App\Http\Requests\Agent\AgentStepRequest;
use App\Models\ExecutionLog;
use App\Models\Report;
use App\Models\Wizard;
use App\Services\EncryptionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

class AgentController extends Controller
{
    protected EncryptionService \$encryption;

public function __construct(EncryptionService \$encryption)
    {
        \$this->encryption = \$encryption;
    }

/**
     * 1. Autenticazione iniziale con codice wizard e MAC address.
     */
    public function auth(AgentAuthRequest \$request)
    {
        $wizardCode = strtoupper($request->input('codice_wizard'));
        $macAddress = strtolower($request->input('mac_address'));

\$wizard = Wizard::where('codice_univoco', \$wizardCode)->first();

if (!\$wizard) {
            return response()->json(['message' => 'Codice wizard non valido.'], 404);
        }

// Verifica scadenza
        if (\$wizard->expires_at \&\& \$wizard->expires_at->isPast()) {
            return response()->json(['message' => 'Codice wizard scaduto.'], 410);
        }

// Verifica già utilizzato
        if (\$wizard->used_at !== null || \$wizard->stato === 'completato') {
            return response()->json(['message' => 'Codice wizard già utilizzato.'], 410);
        }

// Verifica stato valido
        if (\$wizard->stato !== 'pronto') {
            return response()->json(['message' => 'Wizard non pronto per l\'esecuzione.'], 422);
        }

\$now = Carbon::now();
        \$expiry = \$now->copy()->addHours(4);

// Prepara payload JWT con wizard_id e mac_address
        \$payload = JWTFactory::customClaims([
            'sub'         => \$wizard->id,
            'wizard_id'   => \$wizard->id,
            'mac_address' => \$macAddress,
            'type'        => 'agent',
            'iat'         => \$now->timestamp,
            'exp'         => \$expiry->timestamp,
        ])->make();

$token = JWTAuth::encode($payload)->get();

// Aggiorna stato wizard
        \$wizard->stato = 'in_esecuzione';
        \$wizard->save();

// Decifra le password per l'agent usando EncryptionService
        \$config = \$wizard->configurazione;
        \$salt = (string) $wizard->id; // o wizard->codice_univoco
        if (isset($config['utente_admin']['password_encrypted'])) {
            \$config['utente_admin']['password'] = \$this->encryption->decryptForWizard(
                \$config['utente_admin']['password_encrypted'],
                $salt
            );
            unset($config['utente_admin']['password_encrypted']);
        }
        if (isset(\$config['extras']['wifi']['password_encrypted'])) {
            \$config['extras']['wifi']['password'] = \$this->encryption->decryptForWizard(
                \$config['extras']['wifi']['password_encrypted'],
                $salt
            );
            unset($config['extras']['wifi']['password_encrypted']);
        }

return response()->json([
            'token'          => \$token,
            'expires_in'     => 4 * 3600,
            'wizard_config'  => \$config,
        ]);
    }

/**
     * 2. Avvio esecuzione: crea record execution_log.
     */
    public function start(AgentStartRequest \$request)
    {
        \$payload = JWTAuth::parseToken()->getPayload();
        \$wizardId = \$payload->get('wizard_id');
        \$macAddress = \$payload->get('mac_address');

$wizard = Wizard::findOrFail($wizardId);

// Verifica che il wizard non sia già in esecuzione
        \$existing = ExecutionLog::where('wizard_id', $wizardId)
            ->whereIn('stato', ['avviato', 'in_corso'])
            ->first();
        if ($existing) {
            return response()->json(['message' => 'Wizard già in esecuzione.'], 409);
        }

\$data = \$request->validated();

\$executionLog = ExecutionLog::create([
            'wizard_id'          => \$wizardId,
            'tecnico_user_id'    => \$wizard->user_id,
            'pc_nome_originale'  => \$data['pc_info']['nome_originale'],
            'hardware_info'      => [
                'cpu'             => \$data['pc_info']['cpu'] ?? null,
                'ram_gb'          => \$data['pc_info']['ram'] ?? null,
                'disco_gb'        => \$data['pc_info']['disco'] ?? null,
                'windows_version' => \$data['pc_info']['windows_version'] ?? null,
            ],
            'stato'              => 'avviato',
            'log_dettagliato'    => [],
            'started_at'         => now(),
        ]);

return response()->json([
            'execution_log_id' => \$executionLog->id,
            'ok'               => true,
        ]);
    }

/**
     * 3. Aggiornamento step.
     */
    public function step(AgentStepRequest \$request)
    {
        \$payload = JWTAuth::parseToken()->getPayload();
        \$wizardId = \$payload->get('wizard_id');

\$data = \$request->validated();

\$executionLog = ExecutionLog::where('id', \$data['execution_log_id'])
            ->where('wizard_id', \$wizardId)
            ->firstOrFail();

if (!in_array(\$executionLog->stato, ['avviato', 'in_corso'])) {
            return response()->json(['message' => 'Esecuzione già completata o abortita.'], 422);
        }

\$step = \$data['step'];
        \$step['timestamp'] = now()->toIso8601String();

\$log = \$executionLog->log_dettagliato ?? [];
        \$log[] = \$step;
        \$executionLog->log_dettagliato = \$log;

if (isset(\$step['nome'])) {
            \$executionLog->step_corrente = \$step['nome'];
        }

if (\$executionLog->stato === 'avviato') {
            \$executionLog->stato = 'in_corso';
        }
        \$executionLog->save();

// Eventuale broadcast per il frontend
        // broadcast(new ExecutionLogUpdated(\$executionLog))->toOthers();

return response()->json(['ok' => true]);
    }

/**
     * 4. Completamento esecuzione.
     */
    public function complete(AgentCompleteRequest \$request)
    {
        \$payload = JWTAuth::parseToken()->getPayload();
        \$wizardId = \$payload->get('wizard_id');

\$data = \$request->validated();

\$executionLog = ExecutionLog::where('id', \$data['execution_log_id'])
            ->where('wizard_id', \$wizardId)
            ->firstOrFail();

if (\$executionLog->stato === 'completato') {
            return response()->json(['message' => 'Esecuzione già completata.'], 422);
        }

\$executionLog->pc_nome_nuovo = \$data['pc_nome_nuovo'];
        \$executionLog->stato = 'completato';
        \$executionLog->completed_at = now();

\$log = \$executionLog->log_dettagliato ?? [];
        \$log[] = [
            'step'      => 'sommario_finale',
            'timestamp' => now()->toIso8601String(),
            'esito'     => 'ok',
            'dettaglio' => \$data['sommario'] ?? null,
        ];
        \$executionLog->log_dettagliato = \$log;
        \$executionLog->save();

$wizard = Wizard::find($wizardId);
        \$wizard->stato = 'completato';
        \$wizard->used_at = now();
        \$wizard->save();

\$report = Report::create([
            'execution_log_id' => \$executionLog->id,
            'html_content'     => \$data['report_html'],
        ]);

// broadcast(new ExecutionLogUpdated(\$executionLog))->toOthers();

return response()->json([
            'ok'         => true,
            'report_url' => route('api.reports.show', \$report->id),
        ]);
    }

/**
     * 5. Abort esecuzione per errore grave.
     */
    public function abort(AgentAbortRequest \$request)
    {
        \$payload = JWTAuth::parseToken()->getPayload();
        \$wizardId = \$payload->get('wizard_id');

\$data = \$request->validated();

\$executionLog = ExecutionLog::where('id', \$data['execution_log_id'])
            ->where('wizard_id', \$wizardId)
            ->firstOrFail();

if (in_array(\$executionLog->stato, ['completato', 'abortito'])) {
            return response()->json(['message' => 'Esecuzione già terminata.'], 422);
        }

\$executionLog->stato = 'abortito';
        \$executionLog->completed_at = now();

\$log = \$executionLog->log_dettagliato ?? [];
        \$log[] = [
            'step'      => 'abort',
            'timestamp' => now()->toIso8601String(),
            'esito'     => 'errore',
            'dettaglio' => \$data['motivo'] ?? 'Errore grave non specificato',
        ];
        \$executionLog->log_dettagliato = \$log;
        \$executionLog->save();

$wizard = Wizard::find($wizardId);
        \$wizard->stato = 'errore';
        \$wizard->save();

// broadcast(new ExecutionLogUpdated(\$executionLog))->toOthers();

return response()->json(['ok' => true]);
    }
}
composer.json
{
    "\$schema": "https://getcomposer.org/schema.json",
    "name": "laravel/laravel",
    "type": "project",
    "description": "The skeleton application for the Laravel framework.",
    "keywords": ["laravel", "framework"],
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "laravel/framework": "^12.0",
        "laravel/sanctum": "^4.0",
        "laravel/tinker": "^2.10.1",
        "spatie/laravel-permission": "^6.24",
        "tymon/jwt-auth": "^2.2"
    },
    "require-dev": {
        "barryvdh/laravel-debugbar": "^4.0",
        "fakerphp/faker": "^1.23",
        "laravel/pail": "^1.2.2",
        "laravel/pint": "^1.24",
        "laravel/sail": "^1.41",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.6",
        "phpunit/phpunit": "^11.5.3"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "setup": [
            "composer install",
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\"",
            "@php artisan key:generate",
            "@php artisan migrate --force",
            "npm install",
            "npm run build"
        ],
        "dev": [
            "Composer\\Config::disableProcessTimeout",
            "npx concurrently -c \"\#93c5fd,\#c4b5fd,\#fb7185,\#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1 --timeout=0\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,queue,logs,vite --kill-others"
        ],
        "test": [
            "@php artisan config:clear --ansi",
            "@php artisan test"
        ],
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi"
        ],
        "post-update-cmd": [
            "@php artisan vendor:publish --tag=laravel-assets --ansi --force"
        ],
        "post-root-package-install": [
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
        ],
        "post-create-project-cmd": [
            "@php artisan key:generate --ansi",
            "@php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\"",
            "@php artisan migrate --graceful --ansi"
        ],
        "pre-package-uninstall": [
            "Illuminate\\Foundation\\ComposerScripts::prePackageUninstall"
        ]
    },
    "extra": {
        "laravel": {
            "dont-discover": []
        }
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "php-http/discovery": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
composer.lock
            "name": "tymon/jwt-auth",
            "version": "2.2.1",
            "source": {
                "type": "git",
                "url": "https://github.com/tymondesigns/jwt-auth.git",
                "reference": "42381e56db1bf887c12e5302d11901d65cc74856"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/tymondesigns/jwt-auth/zipball/42381e56db1bf887c12e5302d11901d65cc74856",
                "reference": "42381e56db1bf887c12e5302d11901d65cc74856",
                "shasum": ""
            },
            "require": {
                "illuminate/auth": "^9.0|^10.0|^11.0|^12.0",
                "illuminate/contracts": "^9.0|^10.0|^11.0|^12.0",
                "illuminate/http": "^9.0|^10.0|^11.0|^12.0",
                "illuminate/support": "^9.0|^10.0|^11.0|^12.0",
                "lcobucci/jwt": "^4.0",
                "nesbot/carbon": "^2.69|^3.0",
                "php": "^8.0"
            },
            "require-dev": {
                "illuminate/console": "^9.0|^10.0|^11.0|^12.0",
                "illuminate/database": "^9.0|^10.0|^11.0|^12.0",
                "illuminate/routing": "^9.0|^10.0|^11.0|^12.0",
                "mockery/mockery": "^1.6",
                "phpunit/phpunit": "^9.4"
            },
            "type": "library",
            "extra": {
                "laravel": {
                    "aliases": {
                        "JWTAuth": "Tymon\\JWTAuth\\Facades\\JWTAuth",
                        "JWTFactory": "Tymon\\JWTAuth\\Facades\\JWTFactory"
                    },
                    "providers": [
                        "Tymon\\JWTAuth\\Providers\\LaravelServiceProvider"
                    ]
                },
                "branch-alias": {
                    "dev-2.x": "2.0-dev",
                    "dev-develop": "1.0-dev"
                }
            },
            "autoload": {
                "psr-4": {
                    "Tymon\\JWTAuth\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Sean Tymon",
                    "email": "tymon148@gmail.com",
                    "homepage": "https://tymon.xyz",
                    "role": "Developer"
                }
            ],
            "description": "JSON Web Token Authentication for Laravel and Lumen",
            "homepage": "https://github.com/tymondesigns/jwt-auth",
            "keywords": [
                "Authentication",
                "JSON Web Token",
                "auth",
                "jwt",
                "laravel"
            ],
            "support": {
                "issues": "https://github.com/tymondesigns/jwt-auth/issues",
                "source": "https://github.com/tymondesigns/jwt-auth"
            },
            "funding": [
                {
                    "url": "https://www.patreon.com/seantymon",
                    "type": "patreon"
                }
            ],
            "time": "2025-04-16T22:22:54+00:00"
        },

jwt.php
<?php

/*
 * This file is part of jwt-auth.
 *
 * (c) Sean Tymon [tymon148@gmail.com](mailto:tymon148@gmail.com)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [

/*
    |--------------------------------------------------------------------------
    | JWT Authentication Secret
    |--------------------------------------------------------------------------
    |
    | Don't forget to set this in your .env file, as it will be used to sign
    | your tokens. A helper command is provided for this:
    | `php artisan jwt:secret`
    |
    | Note: This will be used for Symmetric algorithms only (HMAC),
    | since RSA and ECDSA use a private/public key combo (See below).
    |
    */

'secret' => env('JWT_SECRET'),

/*
    |--------------------------------------------------------------------------
    | JWT Authentication Keys
    |--------------------------------------------------------------------------
    |
    | The algorithm you are using, will determine whether your tokens are
    | signed with a random string (defined in `JWT_SECRET`) or using the
    | following public \& private keys.
    |
    | Symmetric Algorithms:
    | HS256, HS384 \& HS512 will use `JWT_SECRET`.
    |
    | Asymmetric Algorithms:
    | RS256, RS384 \& RS512 / ES256, ES384 \& ES512 will use the keys below.
    |
    */

'keys' => [

/*
        |--------------------------------------------------------------------------
        | Public Key
        |--------------------------------------------------------------------------
        |
        | A path or resource to your public key.
        |
        | E.g. 'file://path/to/public/key'
        |
        */

'public' => env('JWT_PUBLIC_KEY'),

/*
        |--------------------------------------------------------------------------
        | Private Key
        |--------------------------------------------------------------------------
        |
        | A path or resource to your private key.
        |
        | E.g. 'file://path/to/private/key'
        |
        */

'private' => env('JWT_PRIVATE_KEY'),

/*
        |--------------------------------------------------------------------------
        | Passphrase
        |--------------------------------------------------------------------------
        |
        | The passphrase for your private key. Can be null if none set.
        |
        */

'passphrase' => env('JWT_PASSPHRASE'),

],

/*
    |--------------------------------------------------------------------------
    | JWT time to live
    |--------------------------------------------------------------------------
    |
    | Specify the length of time (in minutes) that the token will be valid for.
    | Defaults to 1 hour.
    |
    | You can also set this to null, to yield a never expiring token.
    | Some people may want this behaviour for e.g. a mobile app.
    | This is not particularly recommended, so make sure you have appropriate
    | systems in place to revoke the token if necessary.
    | Notice: If you set this to null you should remove 'exp' element from 'required_claims' list.
    |
    */

'ttl' => (int) env('JWT_TTL', 60),

/*
    |--------------------------------------------------------------------------
    | Refresh time to live
    |--------------------------------------------------------------------------
    |
    | Specify the length of time (in minutes) that the token can be refreshed
    | within. I.E. The user can refresh their token within a 2 week window of
    | the original token being created until they must re-authenticate.
    | Defaults to 2 weeks.
    |
    | You can also set this to null, to yield an infinite refresh time.
    | Some may want this instead of never expiring tokens for e.g. a mobile app.
    | This is not particularly recommended, so make sure you have appropriate
    | systems in place to revoke the token if necessary.
    |
    */

'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 20160),

/*
    |--------------------------------------------------------------------------
    | JWT hashing algorithm
    |--------------------------------------------------------------------------
    |
    | Specify the hashing algorithm that will be used to sign the token.
    |
    */

'algo' => env('JWT_ALGO', Tymon\JWTAuth\Providers\JWT\Provider::ALGO_HS256),

/*
    |--------------------------------------------------------------------------
    | Required Claims
    |--------------------------------------------------------------------------
    |
    | Specify the required claims that must exist in any token.
    | A TokenInvalidException will be thrown if any of these claims are not
    | present in the payload.
    |
    */

'required_claims' => [
        'iss',
        'iat',
        'exp',
        'nbf',
        'sub',
        'jti',
    ],

/*
    |--------------------------------------------------------------------------
    | Persistent Claims
    |--------------------------------------------------------------------------
    |
    | Specify the claim keys to be persisted when refreshing a token.
    | `sub` and `iat` will automatically be persisted, in
    | addition to the these claims.
    |
    | Note: If a claim does not exist then it will be ignored.
    |
    */

'persistent_claims' => [
        // 'foo',
        // 'bar',
    ],

/*
    |--------------------------------------------------------------------------
    | Lock Subject
    |--------------------------------------------------------------------------
    |
    | This will determine whether a `prv` claim is automatically added to
    | the token. The purpose of this is to ensure that if you have multiple
    | authentication models e.g. `App\User` \& `App\OtherPerson`, then we
    | should prevent one authentication request from impersonating another,
    | if 2 tokens happen to have the same id across the 2 different models.
    |
    | Under specific circumstances, you may want to disable this behaviour
    | e.g. if you only have one authentication model, then you would save
    | a little on token size.
    |
    */

'lock_subject' => true,

/*
    |--------------------------------------------------------------------------
    | Leeway
    |--------------------------------------------------------------------------
    |
    | This property gives the jwt timestamp claims some "leeway".
    | Meaning that if you have any unavoidable slight clock skew on
    | any of your servers then this will afford you some level of cushioning.
    |
    | This applies to the claims `iat`, `nbf` and `exp`.
    |
    | Specify in seconds - only if you know you need it.
    |
    */

'leeway' => (int) env('JWT_LEEWAY', 0),

/*
    |--------------------------------------------------------------------------
    | Blacklist Enabled
    |--------------------------------------------------------------------------
    |
    | In order to invalidate tokens, you must have the blacklist enabled.
    | If you do not want or need this functionality, then set this to false.
    |
    */

'blacklist_enabled' => env('JWT_BLACKLIST_ENABLED', true),

/*
    | -------------------------------------------------------------------------
    | Blacklist Grace Period
    | -------------------------------------------------------------------------
    |
    | When multiple concurrent requests are made with the same JWT,
    | it is possible that some of them fail, due to token regeneration
    | on every request.
    |
    | Set grace period in seconds to prevent parallel request failure.
    |
    */

'blacklist_grace_period' => (int) env('JWT_BLACKLIST_GRACE_PERIOD', 0),

/*
    |--------------------------------------------------------------------------
    | Cookies encryption
    |--------------------------------------------------------------------------
    |
    | By default Laravel encrypt cookies for security reason.
    | If you decide to not decrypt cookies, you will have to configure Laravel
    | to not encrypt your cookie token by adding its name into the \$except
    | array available in the middleware "EncryptCookies" provided by Laravel.
    | see [https://laravel.com/docs/master/responses\#cookies-and-encryption](https://laravel.com/docs/master/responses#cookies-and-encryption)
    | for details.
    |
    | Set it to true if you want to decrypt cookies.
    |
    */

'decrypt_cookies' => false,

/*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Specify the various providers used throughout the package.
    |
    */

'providers' => [

/*
        |--------------------------------------------------------------------------
        | JWT Provider
        |--------------------------------------------------------------------------
        |
        | Specify the provider that is used to create and decode the tokens.
        |
        */

'jwt' => Tymon\JWTAuth\Providers\JWT\Lcobucci::class,

/*
        |--------------------------------------------------------------------------
        | Authentication Provider
        |--------------------------------------------------------------------------
        |
        | Specify the provider that is used to authenticate users.
        |
        */

'auth' => Tymon\JWTAuth\Providers\Auth\Illuminate::class,

/*
        |--------------------------------------------------------------------------
        | Storage Provider
        |--------------------------------------------------------------------------
        |
        | Specify the provider that is used to store tokens in the blacklist.
        |
        */

'storage' => Tymon\JWTAuth\Providers\Storage\Illuminate::class,

],

];
\bootstrap\app.php
<?php
// File: bootstrap/app.php
// Path: backend\bootstrap\app.php

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
    ->withMiddleware(function (Middleware \$middleware) {
        // Alias per il middleware di controllo ruolo
        // Uso: ->middleware('role:admin') o ->middleware('role:admin|tecnico')
        \$middleware->alias([
            'role' => CheckRole::class,
        ]);

// Trustare gli header Cloudflare per IP reale (Cloudflare Tunnel in produzione)
        // In locale (XAMPP) non ha effetto, ma non crea problemi
        \$middleware->trustProxies(at: '*');

// Definizione throttle per agent: 120 richieste al minuto
    $middleware->throttleApi('agent', '120,1');
    })
    ->withExceptions(function ($exceptions) {
        //
    })
    ->create();

Ottimo. Ho tutto il necessario. Il file `auth.php` allegato ha già il guard `api` con driver `jwt` configurato correttamente. Ora analizzo tutto in dettaglio per fornire la diagnosi completa e i fix.

***

## 1. Verifica Installazione tymon/jwt-auth

**tymon/jwt-auth è già installato correttamente.** Dal `composer.lock` risulta la versione `2.2.1`, rilasciata il 16 aprile 2025, che supporta esplicitamente Laravel 12 (`illuminate/auth: ^9.0|^10.0|^11.0|^12.0`) [^1_1]. Non serve reinstallare nulla.

Verifica stato pubblicazione config e secret:

```bash
# Verifica se jwt.php è già pubblicato (esiste già — confermato dal file allegato)
ls -la backend/config/jwt.php

# Verifica se JWT_SECRET è presente in .env
grep "JWT_SECRET" backend/.env

# Se JWT_SECRET è vuoto o mancante, generalo così:
cd backend && php artisan jwt:secret

# Forza rigenerazione (ATTENZIONE: invalida tutti i token esistenti)
cd backend && php artisan jwt:secret --force
```

⚠️ **Sicurezza:** `jwt:secret` scrive `JWT_SECRET` nel `.env`. Non committare mai `.env` su Git. Assicurati che `.gitignore` includa `.env`.

***

## 2. Diagnosi: config/auth.php

**Buona notizia: il tuo `auth.php` allegato è già corretto.** Il guard `api` con driver `jwt` è presente. Il problema del 401 **non è in `auth.php`** ma altrove. Ecco il file completo con commenti esplicativi inline:

**`backend/config/auth.php`** — COMPLETO

```php
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
```


***

## 3. Modello User — JWTSubject + Sanctum

Questo è **il problema più probabile** se `auth.php` è già corretto. Il modello `User` deve implementare `JWTSubject` oltre a `HasApiTokens` di Sanctum. I due **non confliggono** perché operano su interfacce separate, ma entrambi devono essere presenti.

**`backend/app/Models/User.php`** — COMPLETO

```php
<?php

namespace App\Models;

// Contratto JWT: obbligatorio per il guard 'api' con driver jwt
use Tymon\JWTAuth\Contracts\JWTSubject;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// HasApiTokens: necessario per Sanctum (guard 'sanctum')
use Laravel\Sanctum\HasApiTokens;

// HasRoles: per Spatie laravel-permission (middleware 'role:admin')
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens; // Sanctum: gestisce personal_access_tokens
    use HasFactory;
    use Notifiable;
    use HasRoles;     // Spatie: ruoli admin/tecnico

    /**
     * Attributi mass-assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * Attributi nascosti nelle serializzazioni JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Cast automatici.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    // =========================================================================
    // Implementazione JWTSubject — OBBLIGATORIA per il guard 'api' (jwt)
    // =========================================================================

    /**
     * Restituisce l'identificatore univoco del soggetto JWT.
     * JWT salverà questo valore nel claim 'sub' del token.
     *
     * ATTENZIONE: nel AgentController il 'sub' è sovrascritta con wizard_id
     * tramite customClaims. Questo metodo è usato da JWTAuth::fromUser()
     * ma NON da JWTFactory::customClaims()->make() — in quel caso 'sub'
     * è impostato manualmente nel payload.
     *
     * @return mixed
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey(); // Restituisce $this->id (primary key)
    }

    /**
     * Restituisce claims personalizzati aggiuntivi da includere nel JWT.
     * Ritorna array vuoto: i custom claims per l'agent sono gestiti
     * direttamente in AgentController via JWTFactory::customClaims().
     *
     * @return array
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
```

⚠️ **Conflitto HasApiTokens vs JWTSubject:** NON c'è conflitto. `HasApiTokens` aggiunge metodi come `tokens()`, `createToken()` usati solo da Sanctum. `JWTSubject` è un'interfaccia con soli 2 metodi (`getJWTIdentifier`, `getJWTCustomClaims`). Nessuna sovrapposizione di metodi.

***

## 4. AgentController — Analisi Critica del Token JWT

Qui c'è un **problema architetturale importante** da correggere. Il tuo `AgentController::auth()` usa `JWTFactory::customClaims()` impostando `'sub' => $wizard->id`. Questo significa che il `sub` del token è l'ID del **wizard**, non di un `User`. Quando il middleware `auth:api` tenta di autenticare, cerca un `User` con `id = $wizard->id`, e se non esiste o non corrisponde, restituisce 401.

**Soluzione corretta — due opzioni:**

### Opzione A (Raccomandata): Usare un utente "agent" virtuale

Il guard JWT deve poter caricare un `User` dal `sub`. Usa `$wizard->user_id` come `sub`.

**`backend/app/Http/Controllers/Api/Agent/AgentController.php`** — metodo `auth()` corretto:

```php
/**
 * 1. Autenticazione iniziale con codice wizard e MAC address.
 * Genera un JWT con sub = user_id del tecnico proprietario del wizard.
 * Il guard 'api' userà sub per caricare l'utente autenticato.
 */
public function auth(AgentAuthRequest $request)
{
    $wizardCode = strtoupper($request->input('codice_wizard'));
    $macAddress = strtolower($request->input('mac_address'));

    $wizard = Wizard::where('codice_univoco', $wizardCode)->first();

    if (!$wizard) {
        return response()->json(['message' => 'Codice wizard non valido.'], 404);
    }

    if ($wizard->expires_at && $wizard->expires_at->isPast()) {
        return response()->json(['message' => 'Codice wizard scaduto.'], 410);
    }

    if ($wizard->used_at !== null || $wizard->stato === 'completato') {
        return response()->json(['message' => 'Codice wizard già utilizzato.'], 410);
    }

    if ($wizard->stato !== 'pronto') {
        return response()->json(['message' => 'Wizard non pronto per l\'esecuzione.'], 422);
    }

    $now    = Carbon::now();
    $expiry = $now->copy()->addHours(4);

    // CORREZIONE CRITICA: 'sub' deve essere l'ID di un User valido.
    // Usiamo wizard->user_id (tecnico proprietario) come soggetto JWT.
    // Il guard 'api' caricherà questo User per autenticare la richiesta.
    // wizard_id e mac_address sono claims custom aggiuntivi.
    $payload = JWTFactory::customClaims([
        'sub'         => $wizard->user_id,   // ← User reale, caricabile da guard
        'wizard_id'   => $wizard->id,         // ← custom claim per logica business
        'mac_address' => $macAddress,
        'type'        => 'agent',
        'iat'         => $now->timestamp,
        'exp'         => $expiry->timestamp,
    ])->make();

    $token = JWTAuth::encode($payload)->get();

    $wizard->stato = 'in_esecuzione';
    $wizard->save();

    // Decifra le password per l'agent
    $config = $wizard->configurazione;
    $salt   = (string) $wizard->id;

    if (isset($config['utente_admin']['password_encrypted'])) {
        $config['utente_admin']['password'] = $this->encryption->decryptForWizard(
            $config['utente_admin']['password_encrypted'],
            $salt
        );
        unset($config['utente_admin']['password_encrypted']);
    }

    if (isset($config['extras']['wifi']['password_encrypted'])) {
        $config['extras']['wifi']['password'] = $this->encryption->decryptForWizard(
            $config['extras']['wifi']['password_encrypted'],
            $salt
        );
        unset($config['extras']['wifi']['password_encrypted']);
    }

    return response()->json([
        'token'         => $token,
        'expires_in'    => 4 * 3600,
        'wizard_config' => $config,
    ]);
}
```

I metodi `start()`, `step()`, `complete()`, `abort()` usano `JWTAuth::parseToken()->getPayload()` per estrarre `wizard_id` — questo **è corretto** e non deve cambiare. Il middleware `auth:api` gestisce autonomamente la lettura dell'header `Authorization: Bearer {token}`.

***

## 5. Throttle Agent — bootstrap/app.php

Il tuo `bootstrap/app.php` usa `$middleware->throttleApi('agent', '120,1')` ma questo metodo **non esiste** in Laravel 11/12. Devi registrare il rate limiter in un Service Provider e usare `$middleware->alias()`.

**`backend/bootstrap/app.php`** — CORRETTO:

```php
<?php

use App\Http\Middleware\CheckRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__.'/../routes/web.php',
        api:      __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health:   '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => CheckRole::class,
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withProviders([
        // Registra i rate limiter all'avvio dell'app
        App\Providers\RateLimiterServiceProvider::class,
    ])
    ->withExceptions(function ($exceptions) {})
    ->create();
```

**`backend/app/Providers/RateLimiterServiceProvider.php`** — NUOVO FILE:

```php
<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimiterServiceProvider extends ServiceProvider
{
    /**
     * Registra i rate limiter custom dell'applicazione.
     */
    public function boot(): void
    {
        // Rate limiter per login agent: 5 tentativi per minuto per IP
        // Usato da: ->middleware('throttle:login') su POST /api/agent/auth
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Troppi tentativi di login. Riprova tra un minuto.',
                    ], 429);
                });
        });

        // Rate limiter per agent autenticato: 120 richieste/minuto per token JWT
        // Usato da: ->middleware('throttle:agent') nelle route protette
        RateLimiter::for('agent', function (Request $request) {
            // Chiave basata sul token JWT (non IP) per limitare per agent, non per rete
            $key = $request->bearerToken() ?? $request->ip();
            return Limit::perMinute(120)
                ->by($key)
                ->response(function () {
                    return response()->json([
                        'message' => 'Rate limit superato. Rallenta le richieste.',
                    ], 429);
                });
        });
    }
}
```

⚠️ **Sicurezza:** Usare `$request->bearerToken()` come chiave rate limiter è corretto per gli agent, ma se un attaccante conosce un token valido può calcolare il limite rimanente. Per scenari ad alto rischio valuta di combinare token + IP.

***

## 6. Test Sequenza Completa (curl)

```bash
# ─────────────────────────────────────────────────────────────────────────────
# a) POST /api/agent/auth → ricevo JWT
# ─────────────────────────────────────────────────────────────────────────────
curl -X POST https://windeploy.local.api/api/agent/auth \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "codice_wizard": "WD-7A3F",
    "mac_address": "aa:bb:cc:dd:ee:ff"
  }'

# Risposta attesa 200:
# {
#   "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
#   "expires_in": 14400,
#   "wizard_config": { ... }
# }

# Salva il token in una variabile per i test successivi:
JWT_TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."

# ─────────────────────────────────────────────────────────────────────────────
# b) POST /api/agent/start con Authorization Bearer → 200
# ─────────────────────────────────────────────────────────────────────────────
curl -X POST https://windeploy.local.api/api/agent/start \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -d '{
    "pc_info": {
      "nome_originale": "DESKTOP-XYZ123",
      "cpu": "Intel Core i5-12400",
      "ram": 16,
      "disco": 512,
      "windows_version": "Windows 11 Pro 23H2"
    }
  }'

# Risposta attesa 200:
# { "execution_log_id": 1, "ok": true }

# ─────────────────────────────────────────────────────────────────────────────
# c) Chiamata senza token → 401 (guard rifiuta)
# ─────────────────────────────────────────────────────────────────────────────
curl -X POST https://windeploy.local.api/api/agent/start \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"pc_info": {"nome_originale": "TEST"}}'

# Risposta attesa 401:
# { "message": "Unauthenticated." }

# ─────────────────────────────────────────────────────────────────────────────
# d) Token Sanctum su route agent → 401 (guard sbagliato)
# ─────────────────────────────────────────────────────────────────────────────
# Prima ottieni un token Sanctum dalla login web app:
curl -X POST https://windeploy.local.api/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "admin@windeploy.local", "password": "password"}'

# Poi usalo su una route agent (deve dare 401):
SANCTUM_TOKEN="il_token_sanctum_restituito"

curl -X POST https://windeploy.local.api/api/agent/start \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $SANCTUM_TOKEN" \
  -d '{"pc_info": {"nome_originale": "TEST"}}'

# Risposta attesa 401 — il guard 'api' (jwt) non riconosce token Sanctum opachi

# ─────────────────────────────────────────────────────────────────────────────
# e) Test step con execution_log_id ottenuto dal precedente /start
# ─────────────────────────────────────────────────────────────────────────────
curl -X POST https://windeploy.local.api/api/agent/step \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -d '{
    "execution_log_id": 1,
    "step": {
      "nome": "installazione_chrome",
      "stato": "completato",
      "percentuale": 25,
      "dettaglio": "Google Chrome 120.0 installato via winget"
    }
  }'
```


***

## 7. Riepilogo File Modificati e Commit Git

| File | Stato | Motivo |
| :-- | :-- | :-- |
| `config/auth.php` | ✅ Già corretto | Guard `api` con driver `jwt` presente |
| `app/Models/User.php` | ⚠️ Da modificare | Aggiungere `implements JWTSubject` + 2 metodi |
| `app/Http/Controllers/Api/Agent/AgentController.php` | ⚠️ Da modificare | `sub` deve essere `$wizard->user_id` non `$wizard->id` |
| `bootstrap/app.php` | ⚠️ Da modificare | Rimuovere `throttleApi()` inesistente |
| `app/Providers/RateLimiterServiceProvider.php` | 🆕 Da creare | Definisce `throttle:login` e `throttle:agent` |

```bash
# Comandi Git — conventional commits
git add app/Models/User.php
git commit -m "fix(auth): implement JWTSubject on User model for api guard"

git add app/Http/Controllers/Api/Agent/AgentController.php
git commit -m "fix(agent): use wizard->user_id as JWT sub claim for valid guard resolution"

git add bootstrap/app.php app/Providers/RateLimiterServiceProvider.php
git commit -m "feat(throttle): add RateLimiterServiceProvider with login and agent rate limits"

# Oppure commit unico:
git add app/Models/User.php \
        app/Http/Controllers/Api/Agent/AgentController.php \
        bootstrap/app.php \
        app/Providers/RateLimiterServiceProvider.php

git commit -m "fix(auth): resolve 401 on agent routes — JWT guard, User JWTSubject, rate limiters"
```


***

## Root Cause — Checklist Diagnostica

Ecco l'ordine di verifica per risolvere i 401 definitivamente:

1. **`php artisan config:clear && php artisan cache:clear`** — sempre dopo modifiche a `config/auth.php`
2. **`User implements JWTSubject`** — senza questo il guard `api` non sa come caricare l'utente dal token
3. **`JWT_SECRET` in `.env` non vuoto** — verifica con `php artisan jwt:secret` se assente
4. **`sub` nel JWT = ID di un User esistente in DB** — la correzione in `AgentController::auth()` con `$wizard->user_id` è critica
5. **`config/jwt.php` pubblicato** — già presente, confermato dal file allegato[^1_1]
6. **`throttle:agent` definito** — senza `RateLimiterServiceProvider` Laravel lancia un'eccezione 500 invece di 401, ma può mascherare il problema
<span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_19][^1_2][^1_20][^1_21][^1_3][^1_4][^1_5][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: auth.php

[^1_2]: 0100-File temporanei tmp_.php esposti nella root.md

[^1_3]: 0021-Installer-configurazione sistema.md

[^1_4]: 0020-Scansione app installate.md

[^1_5]: 0019-Struttura agent.md

[^1_6]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^1_7]: 0017-motorpage.md

[^1_8]: 0016-wizards.md

[^1_9]: 0015-dashboard.md

[^1_10]: 0014-Login.md

[^1_11]: 0013-SetupprogettoReact.md

[^1_12]: 0012-apiendpointwindows.md

[^1_13]: 0011-controllercrud.md

[^1_14]: 0010-autenticazione.md

[^1_15]: 0009-scaffoldprogetto.md

[^1_16]: 0008-db.md

[^1_17]: 0007-cheatsheet.md

[^1_18]: 0006-viteconfigjs.md

[^1_19]: 0005-filedotenv.md

[^1_20]: 0004-Strutturacartelle.md

[^1_21]: 0003-setupiniziale.md
