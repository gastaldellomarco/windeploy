<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Sei un senior Laravel developer. Progetto: WinDeploy

Stack: Laravel 11, PHP 8.3, spatie/laravel-permission, MySQL 8
AuthController e WizardController già implementati — mantieni consistenza di stile.

═══ FILE DA ALLEGARE PRIMA DI INVIARE ═══
→ backend/routes/api.php
→ backend/app/Models/ (tutti i modelli)
→ backend/database/migrations/ (tutte le migration)
→ backend/app/Http/Controllers/Api/Auth/AuthController.php (già fatto — usa come riferimento stile)
→ backend/app/Http/Controllers/Api/Wizard/WizardController.php (già fatto — usa come riferimento stile)
→ backend/app/Http/Resources/ (se esistono resource già create)

═══ IMPLEMENTA I 4 CONTROLLER RIMANENTI ═══

── SoftwareController ──
(app/Http/Controllers/Api/Software/SoftwareController.php)
Ruolo richiesto: solo admin per store/update/destroy/toggleActive, tutti per index/show

index(): lista software con filtri (attivo, categoria, tipo:winget/exe/msi), paginazione 20
show(): dettaglio singolo software
store(): crea software — valida {nome, versione, publisher, tipo, identificatore, categoria, attivo}
update(): modifica software
destroy(): soft delete o delete definitivo se non usato in nessun wizard
toggleActive(): inverte il campo 'attivo' (bool) — ritorna nuovo stato

── TemplateController ──
(app/Http/Controllers/Api/Template/TemplateController.php)
scope: 'globale' (solo admin crea/modifica) | 'personale' (ogni tecnico i propri)

index(): 2 query separate — template globali + template personali dell'utente loggato
show(): dettaglio, verifica ownership o scope globale
store(): crea template — stessa struttura JSON di wizard configurazione
update(): modifica — admin modifica tutti, tecnico solo i propri personali
destroy(): admin elimina tutti, tecnico solo i propri
duplicate(): crea copia del template con nome "Copia di {nome}"

── ReportController ──
(app/Http/Controllers/Api/Report/ReportController.php)
Ruolo: admin vede tutti, tecnico vede solo i propri

index(): lista report con filtri (data_da, data_a, tecnico_id, stato execution_log)
Includi join con execution_logs e users per mostrare nome tecnico + nome PC
Paginazione 20
show(): dettaglio report con html_content completo
download(): ritorna html_content come file scaricabile
Header: Content-Disposition: attachment; filename="report-{pc_nome}-{data}.html"
Content-Type: text/html
destroy(): solo admin, soft delete

── UserController ──
(app/Http/Controllers/Api/User/UserController.php)
Ruolo richiesto: solo admin per tutti i metodi

index(): lista utenti con filtri (ruolo, attivo), paginazione 20
show(): dettaglio utente + statistiche (n° wizard creati, ultimo accesso)
store(): crea utente — valida {nome, email, password, ruolo}
Password: genera automaticamente se non fornita, ritornala UNA SOLA VOLTA nella response
Assegna ruolo con spatie: $user->assignRole($request->ruolo)
update(): modifica utente — password opzionale (se non inviata non cambia)
destroy(): disattiva account (soft delete o campo attivo=false, MAI delete fisico)
toggleActive(): attiva/disattiva account
resetPassword(): genera nuova password casuale sicura (16 char), ritorna UNA SOLA VOLTA,
manda email all'utente (MAIL_MAILER=log in locale)

PER TUTTI I CONTROLLER:

- Form Request separata per ogni azione con validazione completa
- API Resource per output JSON coerente con gli altri controller già fatti
- Gestione errori: 404 se non trovato, 403 se non autorizzato, 422 se validazione fallisce
- Nessun dato sensibile (password) mai in output
- Commenti in italiano
- Ogni file con percorso completo in intestazione
- Codice completo, niente abbreviazioni o placeholder "// implementa qui"

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
\models\ExecutionLog.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionLog extends Model
{
    protected \$table = 'execution_logs';

// The execution_logs table in this project does not have Laravel's
    // automatic timestamp columns (created_at / updated_at). Disable
    // Eloquent timestamps to prevent SQL errors on insert.
    public \$timestamps = false;

protected \$fillable = [
        'wizard_id',
        'tecnico_user_id',
        'pc_nome_originale',
        'pc_nome_nuovo',
        'hardware_info',
        'stato',
        'step_corrente',
        'log_dettagliato',
        'started_at',
        'completed_at',
    ];

protected \$casts = [
        'hardware_info'   => 'array',
        'log_dettagliato' => 'array',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
    ];

public function wizard(): BelongsTo
    {
        return \$this->belongsTo(Wizard::class);
    }

public function tecnico(): BelongsTo
    {
        return \$this->belongsTo(User::class, 'tecnico_user_id');
    }
}
\models\Report.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected \$table = 'reports';

protected \$fillable = [
        'execution_log_id',
        'html_content',
    ];

protected \$casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

public function executionLog()
    {
        return \$this->belongsTo(ExecutionLog::class, 'execution_log_id');
    }
}
\models\SoftwareLibrary.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftwareLibrary extends Model
{
    use SoftDeletes;

protected \$table = 'software_library';

protected \$fillable = [
        'nome',
        'versione',
        'publisher',
        'tipo',
        'identificatore',
        'categoria',
        'icona_url',
        'aggiunto_da',
        'attivo',
    ];

protected \$casts = [
        'attivo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
\models\Template.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Template extends Model
{
    protected \$table = 'templates';

protected \$fillable = [
        'nome',
        'descrizione',
        'user_id',
        'scope',
        'configurazione',
    ];

protected \$casts = [
        'configurazione' => 'array',
    ];

public function wizards(): HasMany
    {
        return \$this->hasMany(Wizard::class, 'template_id');
    }

public function user(): BelongsTo
    {
        return \$this->belongsTo(User::class, 'user_id');
    }
}
\models\User.php
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
\models\Wizard.php
<?php
// app/Models/Wizard.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use App\Models\User;
use App\Models\Template;

class Wizard extends Model
{
    /**
     * Stati validi per il wizard (usato nei filtri)
     */
    public const STATI = [
        'bozza',
        'pronto',
        'in_esecuzione',
        'completato',
        'errore',
    ];

/**
     * Relazione con l'utente proprietario del wizard
     */
    public function user()
    {
        return \$this->belongsTo(User::class);
    }

/**
     * Relazione con il template (nullable)
     */
    public function template()
    {
        return \$this->belongsTo(Template::class, 'template_id');
    }
    protected \$fillable = [
        'nome', 'user_id', 'template_id', 'codice_univoco',
        'stato', 'configurazione', 'expires_at', 'used_at',
    ];

protected \$casts = [
        'expires_at' => 'datetime',
    'used_at'    => 'datetime',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    ];

/**
     * Override getter: decifra la password admin prima di restituire la configurazione.
     * La password viene decifrata SOLO qui, mai salvata in chiaro.
     */
    public function getConfigurazione(): array
    {
        $config = json_decode($this->attributes['configurazione'], true);
        return \$config; // La struttura è già con password encrypted nel campo
    }

/**
     * Cifra la password admin prima del salvataggio.
     * Chiamare questo metodo nel Controller prima di \$wizard->save()
     */
    public static function encryptAdminPassword(array $configurazione): array
    {
        if (isset($configurazione['utente_admin']['password'])) {
            \$plain = \$configurazione['utente_admin']['password'];
            $configurazione['utente_admin']['password_encrypted'] = Crypt::encryptString($plain);
            unset(\$configurazione['utente_admin']['password']); // rimuovi il campo plain
        }
        return \$configurazione;
    }

/**
     * Decifra la password admin — usato solo dall'endpoint dedicato all'agent.
     * Non includere mai questa operazione nelle API generiche.
     */
    public static function decryptAdminPassword(array \$configurazione): string
    {
        return Crypt::decryptString(
            \$configurazione['utente_admin']['password_encrypted']
        );
    }
}
AuthController.php
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
    public function login(LoginRequest \$request): JsonResponse
    {
        // Legge IP reale: prima CF-Connecting-IP (Cloudflare Tunnel), poi fallback standard
        \$clientIp = \$request->header('CF-Connecting-IP') ?? \$request->ip();

// Cerca utente attivo per email
        \$user = User::where('email', \$request->input('email'))
            ->where('attivo', true)
            ->first();

// Credenziali errate: risposta generica per non rivelare quale campo è sbagliato
        if (! $user || ! Hash::check($request->input('password'), \$user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenziali non valide.'],
            ]);
        }

// Aggiorna campi audit: ultimo login e IP
        \$user->forceFill([
            'last_login'    => now(),
            'last_login_ip' => \$clientIp,
        ])->save();

// Revoca token 'web' precedenti: una sola sessione SPA attiva per utente
        \$user->tokens()->where('name', 'web')->delete();

// Crea token Sanctum con scadenza 8h
        \$token = \$user->createToken(
            name: 'web',
            abilities: ['*'],
            expiresAt: now()->addHours(8)
        );

return response()->json([
            'token'            => \$token->plainTextToken,
            'token_expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user'             => new UserResource($user),
        ]);
    }

/**
     * Logout: revoca solo il token corrente della richiesta.
     */
    public function logout(Request \$request): JsonResponse
    {
        \$user = \$request->user();

if (\$user \&\& \$user->currentAccessToken()) {
            \$user->currentAccessToken()->delete();
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
    public function refresh(Request \$request): JsonResponse
    {
        \$user = \$request->user();

// Revoca il token corrente
        \$user->currentAccessToken()->delete();

// Emette nuovo token 8h
        \$token = \$user->createToken(
            name: 'web',
            abilities: ['*'],
            expiresAt: now()->addHours(8)
        );

return response()->json([
            'token'            => \$token->plainTextToken,
            'token_expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user'             => new UserResource($user),
        ]);
    }
}
WizardController.php
<?php

namespace App\Http\Controllers\Api\Wizard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wizard\WizardGenerateCodeRequest;
use App\Http\Requests\Wizard\WizardStoreRequest;
use App\Http\Requests\Wizard\WizardUpdateRequest;
use App\Http\Resources\ExecutionLogResource;
use App\Http\Resources\WizardResource;
use App\Models\ExecutionLog;
use App\Models\Wizard;
use App\Services\EncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class WizardController extends Controller
{
    protected EncryptionService \$encryption;

public function __construct(EncryptionService \$encryption)
    {
        \$this->encryption = \$encryption;
    }

/**
     * Elenco wizard con filtri.
     */
    public function index(Request \$request)
    {
        \$user = \$request->user();

\$query = Wizard::with('user', 'template');

if (\$user->ruolo !== 'admin') {
            \$query->where('user_id', $user->id);
        } elseif ($request->has('user_id') \&\& \$request->user_id) {
            \$query->where('user_id', \$request->user_id);
        }

if ($request->has('stato') && in_array($request->stato, Wizard::STATI)) {
            \$query->where('stato', \$request->stato);
        }

if (\$request->has('da_data') \&\& \$request->da_data) {
            \$query->whereDate('created_at', '>=', $request->da_data);
        }
        if ($request->has('a_data') \&\& \$request->a_data) {
            \$query->whereDate('created_at', '<=', \$request->a_data);
        }

\$wizards = \$query->latest()->paginate(20);

return WizardResource::collection(\$wizards);
    }

/**
     * Crea un nuovo wizard.
     */
    public function store(WizardStoreRequest \$request)
    {
        \$user = \$request->user();

if (\$user->ruolo === 'viewer') {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

\$data = \$request->validated();

// Genera codice univoco
        \$data['codice_univoco'] = \$this->generateUniqueCode();

// Cifra password con EncryptionService (usa wizard id ancora non noto, quindi salt provvisorio)
        // Dovremo salvare prima il wizard per avere l'id, poi aggiornare la configurazione cifrata.
        // Oppure usiamo il codice_univoco come salt (disponibile subito).
        \$config = \$data['configurazione'];
        \$salt = \$data['codice_univoco']; // salt basato sul codice (univoco e noto subito)

if (isset(\$config['utente_admin']['password'])) {
            \$plain = \$config['utente_admin']['password'];
            \$config['utente_admin']['password_encrypted'] = $this->encryption->encryptForWizard($plain, $salt);
            unset($config['utente_admin']['password']);
        }
        if (isset(\$config['extras']['wifi']['password'])) {
            \$plain = \$config['extras']['wifi']['password'];
            \$config['extras']['wifi']['password_encrypted'] = $this->encryption->encryptForWizard($plain, $salt);
            unset($config['extras']['wifi']['password']);
        }
        \$data['configurazione'] = \$config;

\$data['user_id'] = \$user->id;
        \$data['stato'] = 'bozza';
        \$data['expires_at'] = now()->addHours(24);

$wizard = Wizard::create($data);

return new WizardResource(\$wizard);
    }

/**
     * Mostra dettaglio wizard.
     */
    public function show(Wizard \$wizard)
    {
        if (Gate::denies('view', \$wizard)) {
            return response()->json(['message' => 'Accesso negato.'], Response::HTTP_FORBIDDEN);
        }

return new WizardResource(\$wizard->load('user', 'template'));
    }

/**
     * Aggiorna wizard (solo se stato = bozza e proprietario/admin).
     */
    public function update(WizardUpdateRequest \$request, Wizard \$wizard)
    {
        if (Gate::denies('update', \$wizard)) {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

if (\$wizard->stato !== 'bozza') {
            return response()->json(['message' => 'Solo i wizard in bozza possono essere modificati.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

\$data = \$request->validated();

// Se viene fornita una nuova password, cifrarla
        if (isset(\$data['configurazione']['utente_admin']['password'])) {
            \$plain = \$data['configurazione']['utente_admin']['password'];
            \$salt = \$wizard->codice_univoco;
            \$data['configurazione']['utente_admin']['password_encrypted'] = $this->encryption->encryptForWizard($plain, $salt);
            unset($data['configurazione']['utente_admin']['password']);
        }

$wizard->update($data);

return new WizardResource(\$wizard);
    }

/**
     * Soft delete wizard.
     */
    public function destroy(Wizard \$wizard)
    {
        if (Gate::denies('delete', \$wizard)) {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

\$wizard->delete();

return response()->json(['message' => 'Wizard eliminato.'], Response::HTTP_OK);
    }

/**
     * Genera un nuovo codice univoco e resetta expires_at.
     */
    public function generateCode(WizardGenerateCodeRequest \$request, Wizard \$wizard)
    {
        if (Gate::denies('update', \$wizard)) {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

\$nuovoCodice = \$this->generateUniqueCode();

\$wizard->codice_univoco = \$nuovoCodice;
        \$wizard->expires_at = now()->addHours(24);
        \$wizard->save();

return response()->json([
            'codice_univoco' => \$nuovoCodice,
            'expires_at'     => \$wizard->expires_at->toIso8601String(),
        ]);
    }

/**
     * Monitor polling: restituisce l'execution log associato al wizard.
     */
    public function monitor(Wizard \$wizard)
    {
        if (Gate::denies('view', \$wizard)) {
            return response()->json(['message' => 'Accesso negato.'], Response::HTTP_FORBIDDEN);
        }

\$log = ExecutionLog::where('wizard_id', \$wizard->id)
            ->latest('started_at')
            ->first();

if (!$log) {
            return response()->json([
                'wizard' => new WizardResource($wizard),
                'execution' => null,
                'message' => 'Nessuna esecuzione avviata.'
            ]);
        }

return new ExecutionLogResource(\$log);
    }

/**
     * Genera un codice univoco nel formato WD-XXXX (6 caratteri totali).
     */
    private function generateUniqueCode(): string
    {
        do {
            \$code = 'WD-' . strtoupper(Str::random(4));
        } while (Wizard::where('codice_univoco', \$code)->exists());

return \$code;
    }
}
\resources\ExecutionLogResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExecutionLogResource extends JsonResource
{
    public function toArray(\$request)
    {
        return [
            'id'                => \$this->id,
            'wizard_id'         => \$this->wizard_id,
            'pc_nome_originale' => \$this->pc_nome_originale,
            'pc_nome_nuovo'     => \$this->pc_nome_nuovo,
            'hardware_info'     => \$this->hardware_info,
            'stato'             => \$this->stato,
            'step_corrente'     => \$this->step_corrente,
            'log_dettagliato'   => \$this->log_dettagliato,
            'started_at'        => \$this->started_at?->toIso8601String(),
            'completed_at'      => $this->completed_at?->toIso8601String(),
            'wizard'            => new WizardResource($this->whenLoaded('wizard')),
            'tecnico'           => new UserResource(\$this->whenLoaded('tecnico')),
        ];
    }
}
resources\ReportResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(\$request)
    {
        \$report = \$this->resource;

\$executionLog = \$report->executionLog ?? null;
        \$wizard = \$executionLog->wizard ?? null;
        \$user = \$wizard->user ?? null;

return [
            'id' => \$report->id,
            'created_at' => \$report->created_at ? \$report->created_at->toDateTimeString() : null,
            'execution_log' => \$executionLog ? [
                'id' => \$executionLog->id ?? null,
                'started_at' => \$executionLog->started_at ?? null,
                'completed_at' => \$executionLog->completed_at ?? null,
                'stato' => \$executionLog->stato ?? \$executionLog->status ?? null,
                'tecnico_nome' => \$executionLog->tecnico_nome ?? \$executionLog->technicianName ?? null,
                'pc_nome_nuovo' => \$executionLog->pc_nome_nuovo ?? \$executionLog->pcnome_nuovo ?? \$executionLog->pcnomeNuovo ?? null,
            ] : null,
            'wizard' => \$wizard ? [
                'id' => \$wizard->id ?? null,
                'nome' => \$wizard->nome ?? \$wizard->name ?? null,
                'codice_univoco' => \$wizard->codice_univoco ?? null,
            ] : null,
            'user' => \$user ? [
                'id' => \$user->id ?? null,
                'nome' => \$user->nome ?? $user->name ?? null,
            ] : null,
            // Include a download URL for convenience
            'download_url' => url('/api/reports/' . ($report->id ?? '') . '/download'),
        ];
    }
}
\resources\SoftwareLibraryResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SoftwareLibraryResource extends JsonResource
{
    public function toArray(Request \$request): array
    {
        return [
            'id' => \$this->id,
            'nome' => \$this->nome,
            'versione' => \$this->versione,
            'publisher' => \$this->publisher,
            'tipo' => \$this->tipo,
            'identificatore' => \$this->identificatore,
            'categoria' => \$this->categoria,
            'iconaurl' => \$this->iconaurl ?? $this->icona_url ?? null,
            'attivo' => (bool) ($this->attivo ?? false),

'createdat' => \$this->created_at?->toISOString(),
            'createdAt' => \$this->created_at?->toISOString(),
        ];
    }
}
\resources\TemplateResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(\$request)
    {
        return [
            'id' => \$this->id,
            'nome' => \$this->nome,
            'descrizione' => \$this->descrizione,
            'scope' => \$this->scope,
            'configurazione' => \$this->configurazione,
            'user' => \$this->whenLoaded('user', function () {
                return [
                    'id' => \$this->user?->id,
                    'nome' => \$this->user?->nome ?? \$this->user?->name ?? null,
                    'email' => $this->user?->email ?? null,
                ];
            }),
            'createdAt' => optional($this->created_at)->toISOString(),
        ];
    }
}
\resources\UserResource.php
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
    public function toArray(Request \$request): array
    {
        return [
            'id'         => \$this->id,
            'nome'       => \$this->nome,
            'email'      => \$this->email,
            'ruolo'      => \$this->ruolo,
            'last_login' => \$this->last_login?->toIso8601String(),
            'created_at' => \$this->created_at?->toIso8601String(),
        ];
    }
}
\resources\WizardResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WizardResource extends JsonResource
{
    public function toArray(\$request)
    {
        \$config = $this->configurazione;
        // Rimuovi eventuali campi cifrati dalla risposta pubblica
        if (isset($config['utente_admin']['password_encrypted'])) {
            unset($config['utente_admin']['password_encrypted']);
        }
        if (isset($config['extras']['wifi']['password_encrypted'])) {
            unset(\$config['extras']['wifi']['password_encrypted']);
        }

return [
            'id'               => \$this->id,
            'nome'             => \$this->nome,
            'codice_univoco'   => \$this->codice_univoco,
            'stato'            => \$this->stato,
            'configurazione'   => \$config,
            'expires_at'       => \$this->expires_at?->toIso8601String(),
            'used_at'          => \$this->used_at?->toIso8601String(),
            'created_at'       => \$this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
            'user'             => new UserResource($this->whenLoaded('user')),
            'template'         => new TemplateResource(\$this->whenLoaded('template')),
        ];
    }
}

Di seguito trovi la struttura di cartelle consigliata e **tutto** il codice completo per i 4 controller, le Form Request e le risorse aggiuntive necessarie, mantenendo stile e convenzioni degli esempi esistenti (AuthController, WizardController, Resources).[^1_1][^1_2]

***

## 1. Struttura cartelle / file coinvolti

Da creare/aggiornare nel backend Laravel:

- Controller
    - `app/Http/Controllers/Api/Software/SoftwareController.php`
    - `app/Http/Controllers/Api/Template/TemplateController.php`
    - `app/Http/Controllers/Api/Report/ReportController.php`
    - `app/Http/Controllers/Api/User/UserController.php`
- Form Request
    - `app/Http/Requests/Software/SoftwareIndexRequest.php`
    - `app/Http/Requests/Software/SoftwareStoreRequest.php`
    - `app/Http/Requests/Software/SoftwareUpdateRequest.php`
    - `app/Http/Requests/Template/TemplateIndexRequest.php`
    - `app/Http/Requests/Template/TemplateStoreRequest.php`
    - `app/Http/Requests/Template/TemplateUpdateRequest.php`
    - `app/Http/Requests/Report/ReportIndexRequest.php`
    - `app/Http/Requests/User/UserIndexRequest.php`
    - `app/Http/Requests/User/UserStoreRequest.php`
    - `app/Http/Requests/User/UserUpdateRequest.php`

Le API Resource già esistenti (UserResource, TemplateResource, SoftwareLibraryResource, ReportResource) vengono riutilizzate per garantire coerenza con i controller già presenti.[^1_1]

***

## 2. SoftwareController + Form Request

### Controller: SoftwareController

```php
<?php
// File: app/Http/Controllers/Api/Software/SoftwareController.php

namespace App\Http\Controllers\Api\Software;

use App\Http\Controllers\Controller;
use App\Http\Requests\Software\SoftwareIndexRequest;
use App\Http\Requests\Software\SoftwareStoreRequest;
use App\Http\Requests\Software\SoftwareUpdateRequest;
use App\Http\Resources\SoftwareLibraryResource;
use App\Models\SoftwareLibrary;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SoftwareController extends Controller
{
    /**
     * Lista software con filtri e paginazione (20 per pagina).
     * Accesso: tutti i ruoli autenticati.
     */
    public function index(SoftwareIndexRequest $request)
    {
        $query = SoftwareLibrary::query();

        // Filtro per stato attivo (true/false)
        if ($request->has('attivo')) {
            $query->where('attivo', $request->boolean('attivo'));
        }

        // Filtro per categoria
        if ($request->filled('categoria')) {
            $query->where('categoria', $request->input('categoria'));
        }

        // Filtro per tipo (winget / exe / msi)
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        // Ricerca testuale sul nome
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('nome', 'like', '%' . $search . '%');
        }

        $software = $query->latest()->paginate(20);

        return SoftwareLibraryResource::collection($software);
    }

    /**
     * Dettaglio singolo software.
     * Accesso: tutti i ruoli autenticati.
     */
    public function show(SoftwareLibrary $software)
    {
        return new SoftwareLibraryResource($software);
    }

    /**
     * Crea una nuova entry software.
     * Accesso: solo admin.
     */
    public function store(SoftwareStoreRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(
                ['message' => 'Solo gli admin possono aggiungere software.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $data = $request->validated();
        $data['aggiunto_da'] = $user->id;

        $software = SoftwareLibrary::create($data);

        return response()->json(new SoftwareLibraryResource($software), Response::HTTP_CREATED);
    }

    /**
     * Aggiorna una entry software esistente.
     * Accesso: solo admin.
     */
    public function update(SoftwareUpdateRequest $request, SoftwareLibrary $software)
    {
        $user = $request->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(
                ['message' => 'Solo gli admin possono modificare software.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $software->update($request->validated());

        return new SoftwareLibraryResource($software);
    }

    /**
     * Elimina (soft delete) una entry software.
     * Se in futuro vorrai fare hard delete condizionato,
     * qui è il punto in cui verificare l'utilizzo nei wizard.
     * Accesso: solo admin.
     */
    public function destroy(SoftwareLibrary $software)
    {
        $user = request()->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(
                ['message' => 'Azione non consentita.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Soft delete (SoftDeletes sul modello)
        $software->delete();

        return response()->json(['message' => 'Software eliminato.'], Response::HTTP_OK);
    }

    /**
     * Inverte il campo 'attivo' e restituisce il nuovo stato.
     * Accesso: solo admin.
     */
    public function toggleActive(SoftwareLibrary $software)
    {
        $user = request()->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(
                ['message' => 'Azione non consentita.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $software->attivo = ! (bool) $software->attivo;
        $software->save();

        return response()->json([
            'id'     => $software->id,
            'attivo' => (bool) $software->attivo,
        ]);
    }
}
```


### Form Request: SoftwareIndexRequest

```php
<?php
// File: app/Http/Requests/Software/SoftwareIndexRequest.php

namespace App\Http\Requests\Software;

use Illuminate\Foundation\Http\FormRequest;

class SoftwareIndexRequest extends FormRequest
{
    /**
     * Autorizzazione già gestita da Sanctum; qui sempre true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validazione filtri lista software.
     */
    public function rules(): array
    {
        return [
            'attivo'    => ['sometimes', 'boolean'],
            'categoria' => ['sometimes', 'string', 'max:100'],
            'tipo'      => ['sometimes', 'string', 'in:winget,exe,msi'],
            'search'    => ['sometimes', 'string', 'max:150'],
        ];
    }
}
```


### Form Request: SoftwareStoreRequest

```php
<?php
// File: app/Http/Requests/Software/SoftwareStoreRequest.php

namespace App\Http\Requests\Software;

use Illuminate\Foundation\Http\FormRequest;

class SoftwareStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Il controllo di ruolo è nel controller; qui solo validazione.
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'          => ['required', 'string', 'max:150'],
            'versione'      => ['nullable', 'string', 'max:50'],
            'publisher'     => ['nullable', 'string', 'max:150'],
            'tipo'          => ['required', 'string', 'in:winget,exe,msi'],
            'identificatore'=> ['required', 'string', 'max:255'],
            'categoria'     => ['nullable', 'string', 'max:100'],
            'icona_url'     => ['nullable', 'url', 'max:500'],
            'attivo'        => ['sometimes', 'boolean'],
        ];
    }
}
```


### Form Request: SoftwareUpdateRequest

```php
<?php
// File: app/Http/Requests/Software/SoftwareUpdateRequest.php

namespace App\Http\Requests\Software;

use Illuminate\Foundation\Http\FormRequest;

class SoftwareUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Per semplicità richiediamo tutti i campi principali anche in update.
     * Puoi allentare i vincoli con "sometimes" se vuoi un vero PATCH.
     */
    public function rules(): array
    {
        return [
            'nome'          => ['required', 'string', 'max:150'],
            'versione'      => ['nullable', 'string', 'max:50'],
            'publisher'     => ['nullable', 'string', 'max:150'],
            'tipo'          => ['required', 'string', 'in:winget,exe,msi'],
            'identificatore'=> ['required', 'string', 'max:255'],
            'categoria'     => ['nullable', 'string', 'max:100'],
            'icona_url'     => ['nullable', 'url', 'max:500'],
            'attivo'        => ['sometimes', 'boolean'],
        ];
    }
}
```

**Rischi / note**

- Il controllo ruolo via `$user->ruolo` replica il pattern di WizardController, mantenendo separato da Spatie roles ma coerente con il modello User.[^1_1]
- Per implementare “delete definitivo se non usato”, servirà analizzare i JSON `wizards.configurazione` (possibile `whereJsonContains`), con rischio di query pesanti su grandi volumi.[^1_2]

***

## 3. TemplateController + Form Request

### Controller: TemplateController

```php
<?php
// File: app/Http/Controllers/Api/Template/TemplateController.php

namespace App\Http\Controllers\Api\Template;

use App\Http\Controllers\Controller;
use App\Http\Requests\Template\TemplateIndexRequest;
use App\Http\Requests\Template\TemplateStoreRequest;
use App\Http\Requests\Template\TemplateUpdateRequest;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TemplateController extends Controller
{
    /**
     * Elenco template globali + personali dell'utente loggato.
     * Due liste distinte nello stesso payload.
     */
    public function index(TemplateIndexRequest $request): JsonResponse
    {
        $user = $request->user();

        $globalQuery = Template::with('user')
            ->where('scope', 'globale');

        $personalQuery = Template::with('user')
            ->where('scope', 'personale')
            ->where('user_id', $user->id);

        if ($request->filled('nome')) {
            $name = $request->input('nome');
            $globalQuery->where('nome', 'like', '%' . $name . '%');
            $personalQuery->where('nome', 'like', '%' . $name . '%');
        }

        $globalTemplates = $globalQuery->latest()->get();
        $personalTemplates = $personalQuery->latest()->get();

        return response()->json([
            'global'   => TemplateResource::collection($globalTemplates),
            'personal' => TemplateResource::collection($personalTemplates),
        ]);
    }

    /**
     * Crea un nuovo template.
     * - Admin: può creare globali o personali.
     * - Tecnico: solo personali (scope forzato a 'personale').
     */
    public function store(TemplateStoreRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        $data['user_id'] = $user->id;

        // Gestione scope in base al ruolo
        if ($user->ruolo === 'admin') {
            $data['scope'] = $data['scope'] ?? 'personale';
        } else {
            // Tecnici e viewer possono creare solo personali
            $data['scope'] = 'personale';
        }

        $template = Template::create($data);

        return new TemplateResource($template->load('user'));
    }

    /**
     * Dettaglio template.
     * - Scope globale: visibile a tutti.
     * - Scope personale: visibile solo al proprietario o admin.
     */
    public function show(Request $request, Template $template)
    {
        $user = $request->user();

        if (
            $template->scope === 'personale' &&
            $user->ruolo !== 'admin' &&
            $template->user_id !== $user->id
        ) {
            return response()->json(
                ['message' => 'Accesso negato.'],
                Response::HTTP_FORBIDDEN
            );
        }

        return new TemplateResource($template->load('user'));
    }

    /**
     * Aggiorna template.
     * - Admin: può modificare tutti.
     * - Tecnico: solo i propri personali, senza poterli trasformare in globali.
     */
    public function update(TemplateUpdateRequest $request, Template $template)
    {
        $user = $request->user();

        if ($user->ruolo === 'admin') {
            $data = $request->validated();
        } else {
            // Tecnico: solo proprietario di template personale
            if ($template->scope !== 'personale' || $template->user_id !== $user->id) {
                return response()->json(
                    ['message' => 'Azione non consentita.'],
                    Response::HTTP_FORBIDDEN
                );
            }

            $data = $request->validated();

            // Non permettere di cambiare lo scope in globale
            unset($data['scope']);
        }

        $template->update($data);

        return new TemplateResource($template->load('user'));
    }

    /**
     * Elimina template.
     * - Admin: può eliminare tutti.
     * - Tecnico: solo i propri personali.
     */
    public function destroy(Request $request, Template $template)
    {
        $user = $request->user();

        if ($user->ruolo === 'admin') {
            $template->delete();

            return response()->json(['message' => 'Template eliminato.'], Response::HTTP_OK);
        }

        if ($template->scope === 'personale' && $template->user_id === $user->id) {
            $template->delete();

            return response()->json(['message' => 'Template eliminato.'], Response::HTTP_OK);
        }

        return response()->json(
            ['message' => 'Azione non consentita.'],
            Response::HTTP_FORBIDDEN
        );
    }

    /**
     * Duplica un template.
     * - Admin: può duplicare qualsiasi template, mantenendo scope originale.
     * - Tecnico: può duplicare template globali o propri personali;
     *   la copia è sempre personale e assegnata all'utente.
     */
    public function duplicate(Request $request, Template $template)
    {
        $user = $request->user();

        // Autorizzazione vista come in show()
        if (
            $template->scope === 'personale' &&
            $user->ruolo !== 'admin' &&
            $template->user_id !== $user->id
        ) {
            return response()->json(
                ['message' => 'Accesso negato.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $newTemplate = $template->replicate();
        $newTemplate->nome = 'Copia di ' . $template->nome;

        if ($user->ruolo === 'admin') {
            // Mantieni scope e proprietario originale
            $newTemplate->user_id = $template->user_id;
            $newTemplate->scope = $template->scope;
        } else {
            // Tecnico: copia personale di proprietà del tecnico
            $newTemplate->user_id = $user->id;
            $newTemplate->scope = 'personale';
        }

        $newTemplate->save();

        return new TemplateResource($newTemplate->load('user'));
    }
}
```


### Form Request: TemplateIndexRequest

```php
<?php
// File: app/Http/Requests/Template/TemplateIndexRequest.php

namespace App\Http\Requests\Template;

use Illuminate\Foundation\Http\FormRequest;

class TemplateIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'string', 'max:150'],
        ];
    }
}
```


### Form Request: TemplateStoreRequest

```php
<?php
// File: app/Http/Requests/Template/TemplateStoreRequest.php

namespace App\Http\Requests\Template;

use Illuminate\Foundation\Http\FormRequest;

class TemplateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validazione struttura template.
     * La configurazione replica la struttura JSON dei wizard.
     */
    public function rules(): array
    {
        return [
            'nome'           => ['required', 'string', 'max:150'],
            'descrizione'    => ['nullable', 'string'],
            'scope'          => ['sometimes', 'string', 'in:globale,personale'],
            'configurazione' => ['required', 'array'],
            // Qui puoi allineare le regole alla configurazione dei wizard:
            'configurazione.nome_pc'                 => ['sometimes', 'string', 'max:100'],
            'configurazione.utente_admin'            => ['sometimes', 'array'],
            'configurazione.utente_admin.username'   => ['sometimes', 'string', 'max:50'],
            'configurazione.utente_admin.password'   => ['sometimes', 'string', 'min:6', 'max:128'],
            'configurazione.software_installa'       => ['sometimes', 'array'],
            'configurazione.software_installa.*.id'  => ['sometimes', 'integer'],
            'configurazione.bloatware_default'       => ['sometimes', 'array'],
            'configurazione.bloatware_default.*'     => ['sometimes', 'string', 'max:255'],
            'configurazione.power_plan'              => ['sometimes', 'array'],
            'configurazione.extras'                  => ['sometimes', 'array'],
        ];
    }
}
```


### Form Request: TemplateUpdateRequest

```php
<?php
// File: app/Http/Requests/Template/TemplateUpdateRequest.php

namespace App\Http\Requests\Template;

use Illuminate\Foundation\Http\FormRequest;

class TemplateUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'           => ['required', 'string', 'max:150'],
            'descrizione'    => ['nullable', 'string'],
            'scope'          => ['sometimes', 'string', 'in:globale,personale'],
            'configurazione' => ['required', 'array'],
            'configurazione.nome_pc'                 => ['sometimes', 'string', 'max:100'],
            'configurazione.utente_admin'            => ['sometimes', 'array'],
            'configurazione.utente_admin.username'   => ['sometimes', 'string', 'max:50'],
            'configurazione.utente_admin.password'   => ['sometimes', 'string', 'min:6', 'max:128'],
            'configurazione.software_installa'       => ['sometimes', 'array'],
            'configurazione.software_installa.*.id'  => ['sometimes', 'integer'],
            'configurazione.bloatware_default'       => ['sometimes', 'array'],
            'configurazione.bloatware_default.*'     => ['sometimes', 'string', 'max:255'],
            'configurazione.power_plan'              => ['sometimes', 'array'],
            'configurazione.extras'                  => ['sometimes', 'array'],
        ];
    }
}
```

**Rischi / note**

- Duplicare template globali come personali per i tecnici evita escalation di permessi (nessun tecnico può creare/alterare template globali).[^1_1]
- La configurazione è un JSON arbitrario: è fondamentale mantenere la validazione aggiornata con l’evoluzione del wizard per evitare input malformati che rompano l’agent.[^1_2]

***

## 4. ReportController + Form Request

### Controller: ReportController

```php
<?php
// File: app/Http/Controllers/Api/Report/ReportController.php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportIndexRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /**
     * Lista report con filtri e join su execution_logs + users.
     * - Admin: vede tutti.
     * - Tecnico: vede solo i propri (execution_logs.tecnico_user_id = user.id).
     */
    public function index(ReportIndexRequest $request)
    {
        $user = $request->user();

        $query = Report::with(['executionLog.tecnico', 'executionLog.wizard']);

        // Restrizione per ruolo tecnico
        if ($user->ruolo !== 'admin') {
            $query->whereHas('executionLog', function ($q) use ($user) {
                $q->where('tecnico_user_id', $user->id);
            });
        }

        // Filtro data_da / data_a sul created_at del report
        if ($request->filled('data_da')) {
            $query->whereDate('created_at', '>=', $request->input('data_da'));
        }

        if ($request->filled('data_a')) {
            $query->whereDate('created_at', '<=', $request->input('data_a'));
        }

        // Filtro per tecnico (solo admin)
        if ($request->filled('tecnico_id') && $user->ruolo === 'admin') {
            $tecnicoId = (int) $request->input('tecnico_id');

            $query->whereHas('executionLog', function ($q) use ($tecnicoId) {
                $q->where('tecnico_user_id', $tecnicoId);
            });
        }

        // Filtro per stato dell'execution_log
        if ($request->filled('stato')) {
            $stato = $request->input('stato');

            $query->whereHas('executionLog', function ($q) use ($stato) {
                $q->where('stato', $stato);
            });
        }

        $reports = $query->latest()->paginate(20);

        return ReportResource::collection($reports);
    }

    /**
     * Dettaglio report con html_content completo.
     * Applica le stesse regole di visibilità di index().
     */
    public function show(Request $request, Report $report)
    {
        $user = $request->user();
        $report->load('executionLog.tecnico', 'executionLog.wizard.user');

        if (! $this->canViewReport($user->ruolo, $user->id, $report)) {
            return response()->json(
                ['message' => 'Accesso negato.'],
                Response::HTTP_FORBIDDEN
            );
        }

        return response()->json([
            'report'       => new ReportResource($report),
            'html_content' => $report->html_content,
        ]);
    }

    /**
     * Download del report come file HTML.
     * Content-Disposition: attachment; filename="report-{pc_nome}-{data}.html"
     */
    public function download(Request $request, Report $report)
    {
        $user = $request->user();
        $report->load('executionLog');

        if (! $this->canViewReport($user->ruolo, $user->id, $report)) {
            return response()->json(
                ['message' => 'Accesso negato.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $executionLog = $report->executionLog;
        $pcName = $executionLog?->pc_nome_nuovo
            ?? $executionLog?->pc_nome_originale
            ?? 'pc';

        $datePart = $report->created_at
            ? $report->created_at->format('Ymd-His')
            : now()->format('Ymd-His');

        $filename = sprintf('report-%s-%s.html', $pcName, $datePart);

        return response(
            $report->html_content,
            Response::HTTP_OK,
            [
                'Content-Type'        => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    /**
     * Elimina (soft delete se abilitato sul modello) un report.
     * Accesso: solo admin.
     */
    public function destroy(Request $request, Report $report)
    {
        $user = $request->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(
                ['message' => 'Solo gli admin possono eliminare report.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $report->delete();

        return response()->json(['message' => 'Report eliminato.'], Response::HTTP_OK);
    }

    /**
     * Regole di visibilità report basate su ruolo + tecnico.
     */
    private function canViewReport(string $ruolo, int $userId, Report $report): bool
    {
        if ($ruolo === 'admin') {
            return true;
        }

        $executionLog = $report->executionLog;

        if (! $executionLog) {
            return false;
        }

        return (int) $executionLog->tecnico_user_id === $userId;
    }
}
```


### Form Request: ReportIndexRequest

```php
<?php
// File: app/Http/Requests/Report/ReportIndexRequest.php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class ReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data_da'    => ['sometimes', 'date'],
            'data_a'     => ['sometimes', 'date', 'after_or_equal:data_da'],
            'tecnico_id' => ['sometimes', 'integer', 'exists:users,id'],
            'stato'      => ['sometimes', 'string', 'in:avviato,incorso,completato,errore,abortito'],
        ];
    }
}
```

**Rischi / note**

- Il filtro per stato usa i valori previsti in `execution_logs.stato`; se in futuro cambiano (es. `in_corso` vs `incorso`) dovrai allineare la lista `in:` per non ottenere 0 match.[^1_2]
- La cancellazione dei report è delicata: se il modello non usa `SoftDeletes`, qui stai facendo hard delete, in contrasto con il requisito “soft delete”; controlla che il modello e la migration siano allineati prima di andare in produzione.[^1_2]

***

## 5. UserController + Form Request + sicurezza password

### Controller: UserController

```php
<?php
// File: app/Http/Controllers/Api/User/UserController.php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserIndexRequest;
use App\Http\Requests\User\UserStoreRequest;
use App\Http\Requests\User\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Wizard;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    /**
     * Lista utenti con filtri (ruolo, attivo), paginazione 20.
     * Accesso: solo admin (già gestito da middleware role:admin sulle route).
     */
    public function index(UserIndexRequest $request)
    {
        $query = User::query();

        if ($request->filled('ruolo')) {
            $query->where('ruolo', $request->input('ruolo'));
        }

        if ($request->has('attivo')) {
            $query->where('attivo', $request->boolean('attivo'));
        }

        $users = $query->latest()->paginate(20);

        return UserResource::collection($users);
    }

    /**
     * Dettaglio utente + statistiche di base.
     * - numero wizard creati
     * - ultimo accesso
     */
    public function show(User $user): JsonResponse
    {
        $wizardsCount = Wizard::where('user_id', $user->id)->count();

        return response()->json([
            'user'  => new UserResource($user),
            'stats' => [
                'wizards_count' => $wizardsCount,
                'last_login'    => $user->last_login?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Crea un nuovo utente.
     * - Password: se non fornita, viene generata automaticamente.
     * - La password generata viene restituita UNA SOLA VOLTA nella response.
     * - Assegna ruolo Spatie in base a $request->ruolo.
     */
    public function store(UserStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $plainPassword = $data['password'] ?? Str::random(16);
        $data['password'] = Hash::make($plainPassword);
        $data['attivo'] = $data['attivo'] ?? true;

        $user = User::create([
            'nome'     => $data['nome'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'ruolo'    => $data['ruolo'],
            'attivo'   => $data['attivo'],
        ]);

        // Allinea ruoli Spatie con campo ruolo
        $user->assignRole($data['ruolo']);

        return response()->json([
            'user'              => new UserResource($user),
            'generated_password'=> $request->filled('password') ? null : $plainPassword,
        ], Response::HTTP_CREATED);
    }

    /**
     * Aggiorna un utente esistente.
     * - Password opzionale: se non inviata non viene modificata.
     * - Se cambia il ruolo, sincronizza anche le Spatie roles.
     */
    public function update(UserUpdateRequest $request, User $user)
    {
        $data = $request->validated();

        if (isset($data['nome'])) {
            $user->nome = $data['nome'];
        }

        if (isset($data['email'])) {
            $user->email = $data['email'];
        }

        if (array_key_exists('attivo', $data)) {
            $user->attivo = $data['attivo'];
        }

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if (isset($data['ruolo']) && $data['ruolo'] !== $user->ruolo) {
            $user->ruolo = $data['ruolo'];
            $user->syncRoles([$data['ruolo']]);
        }

        $user->save();

        return new UserResource($user);
    }

    /**
     * Disattiva un account utente.
     * - Mai hard delete: setta solo attivo = false.
     */
    public function destroy(User $user): JsonResponse
    {
        $user->attivo = false;
        $user->save();

        return response()->json(['message' => 'Utente disattivato.'], Response::HTTP_OK);
    }

    /**
     * Attiva / disattiva un account utente.
     */
    public function toggleActive(User $user)
    {
        $user->attivo = ! (bool) $user->attivo;
        $user->save();

        return new UserResource($user);
    }

    /**
     * Reset password:
     * - Genera una password casuale sicura (16 caratteri).
     * - La ritorna UNA SOLA VOLTA.
     * - Invia una email (MAIL_MAILER=log in locale).
     */
    public function resetPassword(User $user): JsonResponse
    {
        $plainPassword = Str::random(16);
        $user->password = Hash::make($plainPassword);
        $user->save();

        // Invia una mail semplice con la nuova password (finisce nel log in dev)
        Mail::raw(
            "Ciao {$user->nome},\n\n" .
            "la tua password è stata reimpostata.\n" .
            "Nuova password: {$plainPassword}\n\n" .
            "Ti consigliamo di cambiarla al primo accesso.\n",
            function ($message) use ($user) {
                $message->to($user->email);
                $message->subject('Reset password WinDeploy');
            }
        );

        return response()->json([
            'user'         => new UserResource($user),
            'new_password' => $plainPassword,
        ], Response::HTTP_OK);
    }
}
```


### Form Request: UserIndexRequest

```php
<?php
// File: app/Http/Requests/User/UserIndexRequest.php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role:admin è già nel middleware di rotta
    }

    public function rules(): array
    {
        return [
            'ruolo'  => ['sometimes', 'string', 'in:admin,tecnico,viewer'],
            'attivo' => ['sometimes', 'boolean'],
        ];
    }
}
```


### Form Request: UserStoreRequest

```php
<?php
// File: app/Http/Requests/User/UserStoreRequest.php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // solo admin tramite middleware
    }

    public function rules(): array
    {
        return [
            'nome'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['sometimes', 'string', 'min:8', 'max:128'],
            'ruolo'    => ['required', 'string', 'in:admin,tecnico,viewer'],
            'attivo'   => ['sometimes', 'boolean'],
        ];
    }
}
```


### Form Request: UserUpdateRequest

```php
<?php
// File: app/Http/Requests/User/UserUpdateRequest.php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // solo admin tramite middleware
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'nome'     => ['sometimes', 'string', 'max:100'],
            'email'    => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $userId],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:128'],
            'ruolo'    => ['sometimes', 'string', 'in:admin,tecnico,viewer'],
            'attivo'   => ['sometimes', 'boolean'],
        ];
    }
}
```

**Rischi / note**

- Restituire la password in chiaro in `store`/`resetPassword` è inevitabile per UX, ma va considerato un punto sensibile: assicurati che i log HTTP e APM non registrino il body di queste response.[^1_3]
- L’uso contemporaneo di campo `ruolo` e Spatie roles richiede disciplina: qualsiasi cambio ruolo va sempre sincronizzato (`syncRoles`) per evitare discrepanze lato autorizzazione middleware.[^1_4][^1_1]

***

### Checklist operativa finale

- [ ] Copia i 4 controller nelle rispettive cartelle sotto `app/Http/Controllers/Api/...`.
- [ ] Crea tutte le Form Request indicate nelle relative cartelle `app/Http/Requests/...`.
- [ ] Verifica che i namespace e gli use statement corrispondano al tuo progetto.
- [ ] Aggiungi le route mancanti per `templates.duplicate`, `software.toggle-active`, `users.toggleActive`, `users.resetPassword` se non sono già in `routes/api.php`.
- [ ] Allinea eventuali differenze di enum `stato` tra `execution_logs` in DB e la lista nel `ReportIndexRequest`.[^1_2]
- [ ] Esegui `php artisan test` o almeno qualche chiamata Postman per scenari: admin/tecnico, 403/404, validazione 422.
<span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_19][^1_20][^1_5][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: 0011-controllercrud.md

[^1_2]: 0008-db.md

[^1_3]: 0010-autenticazione.md

[^1_4]: 0013-SetupprogettoReact.md

[^1_5]: 0100-File-temporanei-tmp_.php-esposti-nella-root.md

[^1_6]: 0021-Installer-configurazione sistema.md

[^1_7]: 0020-Scansione app installate.md

[^1_8]: 0019-Struttura agent.md

[^1_9]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^1_10]: 0017-motorpage.md

[^1_11]: 0016-wizards.md

[^1_12]: 0015-dashboard.md

[^1_13]: 0014-Login.md

[^1_14]: 0012-apiendpointwindows.md

[^1_15]: 0009-scaffoldprogetto.md

[^1_16]: 0007-cheatsheet.md

[^1_17]: 0006-viteconfigjs.md

[^1_18]: 0005-filedotenv.md

[^1_19]: 0004-Strutturacartelle.md

[^1_20]: 0003-setupiniziale.md

