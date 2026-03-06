<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Sei un senior software architect. Progetto: WinDeploy

Stack: React 18 (TypeScript/JS) | Laravel 11 PHP 8.3 | Python 3.11 agent
Problema: 3 moduli in 3 linguaggi diversi leggono e scrivono lo stesso
oggetto JSON (wizardConfig) senza un contratto condiviso formale.
Rischio concreto: chiavi diverse tra moduli (es. pcName vs pc_name),
campi mancanti, validazioni incoerenti → bug silenziosi impossibili da debuggare.

═══ FILE DA ALLEGARE PRIMA DI INVIARE ═══
→ frontend/src/pages/WizardBuilder/index.jsx (o .tsx)
→ frontend/src/pages/WizardBuilder/steps/ (tutti gli step)
→ frontend/src/api/wizards.js (chiamata POST /api/wizards)
→ backend/app/Http/Controllers/Api/Wizard/WizardController.php
→ backend/app/Http/Requests/Wizard/StoreWizardRequest.php (se esiste)
→ backend/app/Models/Wizard.php
→ backend/database/migrations/*_create_wizards_table.php
→ agent/api_client.py
→ agent/config.py
→ agent/system_config.py
→ agent/installer.py
→ docs/ o 0001-projectdescription.txt (specifica flusso dati)
→ docs/ o 0016-wizards.md (specifica wizard builder)

═══ AUDIT PRELIMINARE — FALLO PRIMA DI SCRIVERE QUALSIASI COSA ═══

Leggi tutti i file allegati e costruisci una tabella comparativa
delle chiavi JSON attualmente usate nei 3 moduli:


| Campo | Frontend usa | Backend salva | Agent legge | Conflitto? |
| :-- | :-- | :-- | :-- | :-- |
| nome PC | ??? | ??? | ??? | ??? |
| utente admin | ??? | ??? | ??? | ??? |
| software lista | ??? | ??? | ??? | ??? |
| ... | ... | ... | ... | ... |

Segnala ogni mismatch trovato PRIMA di procedere.

═══ COSA VOGLIO ═══

1. SCHEMA CONDIVISO — docs/schemas/wizard-config.schema.json
Crea il file JSON Schema v7 completo che definisce l'intera struttura.
Usa snake_case per tutte le chiavi (convenzione Python + PHP).
Struttura obbligatoria:

{
"version": "1.0",           // per gestire future breaking change
"pc_name": string,          // max 15 char, alfanumerici e trattini
"admin_user": {
"username": string,       // no spazi
"password_encrypted": string  // AES-256-GCM, mai in chiaro
},
"software": [               // lista software da installare
{
"id": number,           // FK → software_library
"winget_id": string,    // identificatore per installazione
"nome": string,         // display only
"action": "install"     // sempre install in questo array
}
],
"bloatware": [              // app pre-selezionate per rimozione
{
"package_name": string, // nome per winget/registro
"display_name": string, // nome leggibile
"selected": boolean     // true = da rimuovere
}
],
"power_plan": {
"tipo": "balanced"|"high_performance"|"power_saver"|"custom",
"screen_timeout_ac": number,   // minuti, null = mai
"sleep_timeout_ac": number,    // minuti, null = mai
"cpu_min_percent": number,     // 0-100
"cpu_max_percent": number      // 0-100
},
"extras": {
"timezone": string,            // es. "Europe/Rome"
"language": string,            // es. "it-IT"
"wallpaper_url": string|null,  // URL relativo su server
"wifi": {
"ssid": string,
"password_encrypted": string // AES-256-GCM
}|null,
"windows_update": "auto"|"download_only"|"manual"
}
}

Includi: required[], type, minLength/maxLength dove rilevante,
pattern per pc_name, enum per i campi a valori fissi,
descrizioni in italiano per ogni campo.
2. TYPESCRIPT INTERFACE — frontend/src/types/WizardConfig.ts
Trascrivi lo stesso schema in TypeScript interface esportabile.
Usata dal Wizard Builder per tipizzare lo stato e il payload POST.
Includi: type guards, valori di default, tipo per ogni campo optional.
Aggiungi un oggetto WIZARD_CONFIG_DEFAULT con tutti i valori
iniziali (utile per inizializzare lo stato del wizard builder).
3. VALIDAZIONE BACKEND — backend/app/Http/Requests/Wizard/StoreWizardRequest.php
FormRequest Laravel con rules() che valida OGNI campo dello schema:
    - pc_name: required|string|max:15|regex:/^[a-zA-Z0-9\\-]+\$/
    - admin_user.username: required|string|no spazi
    - admin_user.password_encrypted: required|string (già cifrata dal backend)
    - software: array, ogni elemento ha id (exists:software_library,id)
    - bloatware: array di stringhe
    - power_plan.tipo: required|in:balanced,high_performance,power_saver,custom
    - extras: nullable, ogni sottocampo validato se presente
Aggiungi messages() con messaggi di errore in italiano.
Aggiungi il metodo prepareForValidation() se serve normalizzare
l'input prima della validazione.
4. VALIDAZIONE FRONTEND — frontend/src/utils/validateWizardConfig.ts
Funzione validateWizardConfig(config: WizardConfig): ValidationResult
che valida il payload prima del POST e restituisce:
{
valid: boolean,
errors: Record<string, string[]>  // campo → lista errori
}
Stesse regole della FormRequest Laravel — le due validazioni
devono essere speculari e produrre gli stessi errori sugli stessi campi.
5. DOCUMENTAZIONE AGENT — agent/SCHEMA.md
File Markdown che l'agent developer usa come riferimento:
    - Tabella di tutti i campi con tipo Python e come leggerli dal dict
(es. config["admin_user"]["username"])
    - Quali campi sono sempre presenti vs opzionali (nullable)
    - Come decifrare password_encrypted (rimanda a EncryptionService)
    - Esempio completo di wizardConfig Python dict con dati fake
    - Cosa fare se un campo atteso non è presente nel JSON
(valori di default da usare, errori da loggare)
6. PAYLOAD DI ESEMPIO CONDIVISO — docs/schemas/wizard-config-example.json
Un unico file JSON con un esempio completo e realistico
che soddisfa tutte le regole dello schema.
Questo file viene usato da:
    - Test unitari PHP (assert che FormRequest lo accetti)
    - Test JS (assert che validateWizardConfig() ritorni valid:true)
    - Test Python (assert che l'agent lo deserializzi senza KeyError)
    - Postman collection come payload di esempio per /api/wizards
7. FIX DEI MISMATCH TROVATI NELL'AUDIT:
Per ogni conflitto trovato nella tabella dell'audit preliminare:
    - Indica quale modulo va modificato (frontend, backend o agent)
    - Fornisci il diff esatto del codice da cambiare
    - Spiega perché hai scelto quella convenzione e non l'altra
8. COMANDI GIT:
Un commit per i file docs/schemas/ (contratto condiviso)
Un commit separato per le fix nei singoli moduli (frontend, backend, agent)
Messaggi convenzionali corretti per ogni commit.

Ogni file con percorso completo in intestazione.
Codice e JSON completi, niente abbreviazioni.
Commenti e descrizioni in italiano.
Parti dall'audit — non scrivere nulla finché non hai la tabella comparativa.

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

StoreWizardRequest.php
<?php

namespace App\Http\Requests\Wizard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WizardStoreRequest extends FormRequest
{
    public function authorize()
    {
        // Autorizzazione già gestita nel controller, qui solo validazione
        return true;
    }

public function rules()
    {
        return [
            'nome' => 'required|string|max:150',
            'template_id' => 'nullable|exists:templates,id',
            'configurazione' => 'required|array',
            'configurazione.nome_pc' => 'required|string|max:100',
            'configurazione.utente_admin' => 'required|array',
            'configurazione.utente_admin.username' => 'required|string|max:50',
            // password_encrypted non viene inviata, ma password in chiaro se presente
            'configurazione.utente_admin.password' => 'sometimes|string|min:6|max:128',
            'configurazione.software_installa' => 'array',
            'configurazione.software_installa.*' => 'integer|exists:software_library,id',
            'configurazione.bloatware_default' => 'array',
            'configurazione.bloatware_default.*' => 'string|max:255',
            'configurazione.power_plan' => 'array',
            'configurazione.extras' => 'array',
        ];
    }
}
wizard.php
<?php
// app/Models/Wizard.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class Wizard extends Model
{
    use SoftDeletes;

// Stati validi — usati nel Controller e nelle Form Request
    public const STATI = ['bozza', 'pronto', 'in_esecuzione', 'completato', 'errore'];

protected \$fillable = [
        'nome',
        'user_id',
        'template_id',
        'codice_univoco',
        'stato',
        'configurazione',
        'expires_at',
        'used_at',
    ];

protected \$casts = [
        // Cast a array: Eloquent serializza/deserializza automaticamente il JSON.
        // ATTENZIONE: questo espone password_encrypted come stringa cifrata nell'array.
        // La rimozione avviene a livello di WizardResource, NON qui.
        'configurazione' => 'array',
        'expires_at'     => 'datetime',
        'used_at'        => 'datetime',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => 'datetime',
    ];

// Genera automaticamente codice univoco e expires_at al momento della creazione
    protected static function boot(): void
    {
        parent::boot();

static::creating(function (Wizard $wizard) {
            // Genera codice WD-XXXX se non già impostato
            if (empty($wizard->codice_univoco)) {
                do {
                    \$codice = 'WD-' . strtoupper(Str::random(4));
                } while (static::where('codice_univoco', \$codice)->exists());

\$wizard->codice_univoco = \$codice;
            }

// Imposta scadenza a +24h dalla creazione
            if (empty(\$wizard->expires_at)) {
                \$wizard->expires_at = now()->addHours(24);
            }
        });
    }

// Relazioni
    public function user(): BelongsTo
    {
        return \$this->belongsTo(User::class, 'user_id');
    }

public function template(): BelongsTo
    {
        return \$this->belongsTo(Template::class, 'template_id');
    }

public function executionLogs(): HasMany
    {
        return \$this->hasMany(ExecutionLog::class, 'wizard_id');
    }

public function latestLog(): HasOne
    {
        return \$this->hasOne(ExecutionLog::class, 'wizard_id')->latestOfMany('started_at');
    }

// Verifica se il wizard è ancora utilizzabile (non scaduto, non già usato)
    public function isUsabile(): bool
    {
        return \$this->stato === 'pronto'
            \&\& $this->used_at === null
            && ($this->expires_at === null || \$this->expires_at->isFuture());
    }

// Cifra la password admin nel JSON configurazione prima del salvataggio.
    // Da chiamare nel Controller PRIMA di create() o update().
    public static function encryptSensitiveFields(array $configurazione): array
    {
        if (isset($configurazione['utente_admin']['password'])) {
            \$plain = \$configurazione['utente_admin']['password'];
            $configurazione['utente_admin']['password_encrypted'] = Crypt::encryptString($plain);
            unset(\$configurazione['utente_admin']['password']);
        }

if (isset(\$configurazione['extras']['wifi']['password'])) {
            \$plain = \$configurazione['extras']['wifi']['password'];
            $configurazione['extras']['wifi']['password_encrypted'] = Crypt::encryptString($plain);
            unset(\$configurazione['extras']['wifi']['password']);
        }

return \$configurazione;
    }

// Decifra la password admin — SOLO per l'endpoint /api/agent/start (JWT protetto).
    // Mai chiamare in API generali o Resource pubbliche.
    public function decryptAdminPassword(): string
    {
        \$config = $this->configurazione;
        return Crypt::decryptString($config['utente_admin']['password_encrypted']);
    }
}
create_wizards_table.php
<?php
// database/migrations/2024_01_01_000004_create_wizards_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wizards', function (Blueprint \$table) {
            \$table->id();
            \$table->string('nome', 255);

// cascadeOnDelete: se l'utente viene eliminato, i suoi wizard spariscono.
            // ATTENZIONE: questo causa cascade anche su execution_logs se non protetto.
            // La protezione è su execution_logs.wizard_id con restrictOnDelete.
            \$table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

// nullOnDelete: se il template viene eliminato (soft o hard),
            // il wizard mantiene la propria configurazione JSON autonoma.
            // Il template è solo "da dove è partito", non è necessario per l'esecuzione.
            \$table->foreignId('template_id')
                  ->nullable()
                  ->constrained('templates')
                  ->nullOnDelete();

// Formato WD-XXXX dove XXXX sono 4 caratteri alfanumerici uppercase.
            // UNIQUE a livello DB: l'agent usa questo come chiave di lookup primaria.
            \$table->string('codice_univoco', 10)->unique();

\$table->enum('stato', [
                'bozza',        // in costruzione, non ancora distribuibile
                'pronto',       // generato con codice, pronto per l'agent
                'in_esecuzione',// agent sta lavorando
                'completato',   // esecuzione terminata con successo
                'errore',       // esecuzione fallita
            ])->default('bozza');

// ⚠️ SICUREZZA CRITICA: questo campo contiene utente_admin.password_encrypted
            // e potenzialmente extras.wifi.password_encrypted.
            // REGOLA: mai esporre nelle API generali — vedi WizardResource che li rimuove.
            // La decifrazione avviene SOLO nell'endpoint /api/agent/start (JWT protetto).
            \$table->json('configurazione');

\$table->timestamps(); // created_at + updated_at standard

// expires_at: +24h da created_at, impostato nel Model boot() o nel Controller.
            // Lo scheduled job (app/Console/Commands/PurgeExpiredWizards) usa questo indice.
            \$table->timestamp('expires_at')->nullable();

// used_at: impostato quando l'agent esegue /api/agent/start.
            // Rende il wizard "monouso" — un secondo tentativo riceve 409 Conflict.
            \$table->timestamp('used_at')->nullable();

\$table->softDeletes();

// Indici critici per performance (codice_univoco è già unique sopra)
            \$table->index('stato');
            \$table->index('user_id');
            \$table->index('expires_at');      // per il job di pulizia schedulato
            \$table->index(['stato', 'user_id']); // dashboard: wizard per tecnico+stato
        });
    }

public function down(): void
    {
        Schema::dropIfExists('wizards');
    }
};
api_client.py

# File: api_client.py

# Path: windeploy\agent\api_client.py

"""agent/api_client.py
Small class-based HTTP client for the agent UI.

Provides a thin wrapper around requests so screens can delegate network
calls and receive the raw Response object for status-code-level handling.
"""
from typing import Optional
import requests
import json
import socket
import os
import platform
import shutil

try:
    import psutil
except Exception:
    psutil = None

class APIClient:
    """Minimal HTTP client used by the GUI.

Contract (authenticate_wizard):
    - inputs: codicewizard (str), macaddress (str)
    - output: requests.Response (raw response)
    - errors: raises requests exceptions (ConnectionError, Timeout, etc.)
    """

def __init__(self, base_url: Optional[str] = None, timeout: Optional[int] = None):
        \# Import config lazily to avoid circular import problems during module
        \# import time when UI modules import this client.
        try:
            from agent.config import API_URL as _API_URL, REQUESTS_TIMEOUT as _REQUESTS_TIMEOUT
        except Exception:
            _API_URL = None
            _REQUESTS_TIMEOUT = None

self.base_url = base_url or _API_URL
        self.timeout = timeout or _REQUESTS_TIMEOUT
        self.session = requests.Session()
        \# Always request JSON from the API endpoints to avoid HTML responses
        self.session.headers.update({"Accept": "application/json"})
        self.token = None

def set_token(self, token: str):
        """Attach a Bearer token to be used for subsequent requests."""
        self.token = token
        self.session.headers.update({"Authorization": f"Bearer {token}"})

def send_step(self, execution_log_id: int, step_name: str, status: str, message: str = None) -> requests.Response:
        """POST /agent/step to update execution progress.

Payload shape expected by backend:
        {
          "execution_log_id": <int>,
          ```          "step": { "name": <str>, "status": <str>, "message": <str> }          ```
        }
        """
        payload = {
            "execution_log_id": execution_log_id,
            "step": {
                "nome": step_name,
                "status": status,
                "message": message,
            }
        }
        return self.session.post(f"{self.base_url}/agent/step", json=payload, timeout=self.timeout)

def _get_disk_gb(self):
        try:
            total, used, free = shutil.disk_usage('/')
            return round(total / (1024 ** 3), 1)
        except Exception:
            return None

def _get_ram_gb(self):
        try:
            if psutil:
                return round(psutil.virtual_memory().total / (1024 ** 3), 1)
            \# fallback: try to read from environment or return None
            return None
        except Exception:
            return None

def _get_cpu_info(self):
        try:
            if psutil:
                return psutil.cpu_count(logical=False) or psutil.cpu_count()
            \# fallback to platform processor info
            return platform.processor() or None
        except Exception:
            return None

def start_execution(self, wizard_config: dict, token: Optional[str] = None) -> requests.Response:
        """POST /agent/start including pc_info payload.

Builds a `pc_info` object with hostname, cpu, ram, disk, windowsversion.
        Returns the raw requests.Response so caller can inspect status and body.
        """
        \# Normalize types to what backend validation expects:
        \# - cpu: string
        \# - ram: integer (GB)
        \# - disco: integer (GB)
        raw_cpu = self._get_cpu_info()
        raw_ram = self._get_ram_gb()
        raw_disco = self._get_disk_gb()

cpu = str(raw_cpu) if raw_cpu is not None else None
        try:
            ram = int(raw_ram) if raw_ram is not None else None
        except Exception:
            ram = None
        try:
            disco = int(raw_disco) if raw_disco is not None else None
        except Exception:
            disco = None

pc_info = {
            "nome_originale": socket.gethostname() or os.environ.get("COMPUTERNAME", "PC-NON-SPECIFICATO"),
            "cpu": cpu,
            "ram": ram,
            "disco": disco,
            "windows_version": platform.release(),
        }

\# backend expects `pc_info` snake_case key
        payload = {"pc_info": pc_info}

\# Debug: print the payload that will be sent to /agent/start so we can
        \# verify the presence of pcinfo.nomeoriginale (helps debug 422 validation).
        try:
            print(f"POST /agent/start payload: {json.dumps(payload, ensure_ascii=False)}", flush=True)
        except Exception:
            \# best-effort logging; don't fail the request if printing fails
            try:
                print("POST /agent/start payload: <unserializable payload>", flush=True)
            except Exception:
                pass

if token:
            self.set_token(token)

return self.session.post(f"{self.base_url}/agent/start", json=payload, timeout=self.timeout)

def authenticate_wizard(self, codicewizard: str, macaddress: str) -> requests.Response:
        """POST /agent/auth with JSON payload and return the raw Response.

This method intentionally returns the raw requests.Response so the
        caller can inspect status_code, headers and body as needed.
        """
        \# The backend expects snake_case keys: codice_wizard and mac_address
        payload = {
            "codice_wizard": codicewizard,
            "mac_address": macaddress,
        }
        return self.session.post(f"{self.base_url}/agent/auth", json=payload, timeout=self.timeout)

config.py

# File: config.py

# Path: windeploy\agent\config.py

# agent/config.py

import os

# --- API Configuration ---

# Default to the backend virtual host used in the local development .env

# (backend/.env sets APP_URL=http://windeploy.local.api). Keep the

# environment override for production or custom installs.

API_URL = os.getenv("WINDEPLOY_API_URL", "http://windeploy.local.api/api")
AGENT_VERSION = "1.0.0"

# Development flag (set WINDEPLOY_DEV=1 to enable developer helpers like

# auto-opening saved backend responses)

DEV_MODE = os.getenv("WINDEPLOY_DEV", "0") in ("1", "true", "True")

# --- Network \& Timeouts ---

# Timeout per le richieste HTTPS in secondi (evita il blocco dell'agent se la rete cade)

REQUESTS_TIMEOUT = 10

# --- UI Palette (Tema Scuro / Blu Aziendale) ---

COLORS = {
    "primary": "\#2563EB",       \# Blue-600 (Main buttons)
    "primary_hover": "\#1D4ED8", \# Blue-700 (Hover state)
    "bg_main": "\#111827",       \# Gray-900 (Main background)
    "bg_card": "\#1F2937",       \# Gray-800 (Frames \& Cards)
    "text_main": "\#F9FAFB",     \# Gray-50  (Primary text)
    "text_muted": "\#9CA3AF",    \# Gray-400 (Secondary text)
    "error": "\#EF4444",         \# Red-500  (Error labels)
    "success": "\#10B981",       \# Emerald-500 (Success indicators)
    "border": "\#374151"         \# Gray-700 (Input borders)
}

# --- Window Settings ---

WINDOW_WIDTH = 900
WINDOW_HEIGHT = 650

system_config.py

# File: system_config.py

# Path: windeploy\agent\system_config.py

import subprocess

class SystemConfig:
   
    def _run_ps(self, cmd_str: str) -> bool:
        """ Helper to execute raw PowerShell commands silently """
        try:
            cmd = ["powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", cmd_str]
            subprocess.run(cmd, check=True, creationflags=subprocess.CREATE_NO_WINDOW)
            return True
        except subprocess.CalledProcessError:
            return False

def rename_pc(self, new_name: str) -> bool:
        """ Renames the target machine (Requires reboot to take effect) """
        ps_cmd = f"Rename-Computer -NewName '{new_name}' -Force"
        return self._run_ps(ps_cmd)

def create_admin_user(self, username: str, password: str) -> bool:
        """
        Creates a local administrator.
        SECURITY: Passed via stdin pipe to prevent password leaking in process arguments.
        """
        ps_script = f"""
        \$passwordText = [Console]::In.ReadLine()
        \$securePassword = ConvertTo-SecureString \$passwordText -AsPlainText -Force
        New-LocalUser -Name '{username}' -Password \$securePassword -FullName '{username}' -Description 'WinDeploy Local Admin'
        Add-LocalGroupMember -Group 'Administrators' -Member '{username}'
        """
        try:
            cmd = ["powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", "-"]
            process = subprocess.Popen(
                cmd,
                stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                text=True, creationflags=subprocess.CREATE_NO_WINDOW
            )
            \# Send password to standard input
            stdout, stderr = process.communicate(input=password + "\n")
            return process.returncode == 0
        except Exception:
            return False

def remove_setup_account(self) -> bool:
        """ Removes the initial Microsoft account or setup user that ran OOBE """
        ps_cmd = (
            "\$CurrentUser = $env:USERNAME; "
            "$Users = Get-LocalUser | Where-Object { \$_.Enabled -eq \$true -and \$_.Name -ne 'Administrator' -and \$_.Name -ne $CurrentUser }; "
            "if ($Users.Count -gt 0) { Remove-LocalUser -Name \$Users[0].Name }"
        )
        return self._run_ps(ps_cmd)

def apply_power_plan(self, plan_config: dict) -> bool:
        """ Modifies Windows Powercfg states based on wizard presets or custom rules """
        try:
            plan_type = plan_config.get("type", "preset")
            if plan_type == "preset" and plan_config.get("guid"):
                subprocess.run(["powercfg", "/setactive", plan_config["guid"]], check=True, creationflags=subprocess.CREATE_NO_WINDOW)
           
            elif plan_type == "custom":
                \# Iterates over keys like 'standby-timeout-ac', 'monitor-timeout-dc', etc.
                for setting, value in plan_config.get("settings", {}).items():
                    subprocess.run(["powercfg", "/change", setting, str(value)], check=True, creationflags=subprocess.CREATE_NO_WINDOW)
            return True
        except Exception:
            return False

def apply_extras(self, extras: dict) -> bool:
        """ Applies optional configurations like Timezone, Registry modifications, etc. """
        try:
            if "timezone" in extras:
                subprocess.run(["tzutil", "/s", extras["timezone"]], check=True, creationflags=subprocess.CREATE_NO_WINDOW)

if "language" in extras:
                self._run_ps(f"Set-WinUserLanguageList -LanguageList '{extras['language']}' -Force")

if "wallpaper" in extras:
                \# Modifying registry
                path = extras["wallpaper"]
                subprocess.run(["reg", "add", r"HKCU\Control Panel\Desktop", "/v", "Wallpaper", "/t", "REG_SZ", "/d", path, "/f"], check=True, creationflags=subprocess.CREATE_NO_WINDOW)
                \# Force refresh wallpaper
                subprocess.run(["RUNDLL32.EXE", "user32.dll,UpdatePerUserSystemParameters", "1", "True"], check=False, creationflags=subprocess.CREATE_NO_WINDOW)

if "windows_update" in extras:
                policy = extras["windows_update"]
                \# 2 = Notify download, 3 = Auto download notify install, 4 = Auto install
                au_options = {"manual": "2", "download_only": "3", "automatic": "4"}
                opt = au_options.get(policy, "3")
               
                subprocess.run(["reg", "add", r"HKLM\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU", "/v", "AUOptions", "/t", "REG_DWORD", "/d", opt, "/f"], check=True, creationflags=subprocess.CREATE_NO_WINDOW)
                subprocess.run(["reg", "add", r"HKLM\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU", "/v", "NoAutoUpdate", "/t", "REG_DWORD", "/d", "0", "/f"], check=True, creationflags=subprocess.CREATE_NO_WINDOW)

return True
        except Exception:
            return False

installer.py

# File: installer.py

# Path: windeploy\agent\installer.py

import subprocess
import os
import requests

class Installer:
    def __init__(self):
        self.timeout_uninstall = 120
        self.timeout_install = 300

def uninstall_app(self, app: dict) -> bool:
        """
        Attempts to uninstall an app based on provided dictionary keys.
        Falls back to different methods if previous ones are missing.
        """
        try:
            \# 1. Windows Store Apps (AppxPackage)
            if app.get("is_store_app") and app.get("PackageFullName"):
                cmd = ["powershell", "-NoProfile", "-Command", f"Remove-AppxPackage -Package '{app['PackageFullName']}' -ErrorAction Stop"]
                subprocess.run(cmd, timeout=self.timeout_uninstall, check=True, creationflags=subprocess.CREATE_NO_WINDOW)
                return True

\# 2. QuietUninstallString (Perfect silent uninstall)
            if app.get("QuietUninstallString"):
                subprocess.run(app["QuietUninstallString"], timeout=self.timeout_uninstall, check=True, creationflags=subprocess.CREATE_NO_WINDOW)
                return True
               
            \# 3. Standard UninstallString with silent heuristics appended
            if app.get("UninstallString"):
                cmd_str = app["UninstallString"]
                cmd_lower = cmd_str.lower()
               
                \# Append correct silent flags based on installer type
                if "msiexec" in cmd_lower and "/qn" not in cmd_lower:
                    cmd_str += " /qn"
                elif "/s" not in cmd_lower and "/silent" not in cmd_lower:
                    cmd_str += " /S"
                   
                subprocess.run(cmd_str, timeout=self.timeout_uninstall, check=True, creationflags=subprocess.CREATE_NO_WINDOW)
                return True
               
            return False
           
        except subprocess.TimeoutExpired:
            return False
        except subprocess.CalledProcessError:
            return False
        except Exception:
            return False

def install_winget(self, package_id: str) -> bool:
        """ Installs a package silently using Windows Package Manager """
        try:
            cmd = [
                "winget", "install", "--id", package_id,
                "--silent", "--accept-package-agreements", "--accept-source-agreements"
            ]
            subprocess.run(cmd, timeout=self.timeout_install, check=True, creationflags=subprocess.CREATE_NO_WINDOW)
            return True
        except (subprocess.TimeoutExpired, subprocess.CalledProcessError):
            return False

def install_file(self, file_path: str, silent_args: str = "/S") -> bool:
        """ Executes a local installer silently """
        try:
            if not os.path.exists(file_path):
                return False
           
            cmd_str = f'"{file_path}" {silent_args}'
           
            \# MSI files require specific arguments
            if file_path.lower().endswith(".msi"):
                cmd_str = f'msiexec /i "{file_path}" /qn /norestart'
               
            subprocess.run(cmd_str, timeout=self.timeout_install, check=True, creationflags=subprocess.CREATE_NO_WINDOW)
            return True
        except (subprocess.TimeoutExpired, subprocess.CalledProcessError):
            return False

def download_and_install(self, url: str, name: str) -> bool:
        """ Downloads custom software from WinDeploy Server and executes it """
        try:
            temp_dir = os.environ.get("TEMP", "C:\\Temp")
            file_path = os.path.join(temp_dir, name)
           
            \# Stream download to avoid loading large files in RAM
            response = requests.get(url, stream=True, timeout=60)
            response.raise_for_status()
           
            with open(file_path, 'wb') as f:
                for chunk in response.iter_content(chunk_size=8192):
                    f.write(chunk)
                   
            success = self.install_file(file_path)
           
            \# Secure cleanup
            if os.path.exists(file_path):
                os.remove(file_path)
               
            return success
        except Exception:
            return False

Ora ho tutti i dati necessari. Procedo con l'audit comparativo completo prima di scrivere qualsiasi codice.

***

## ⚠️ AUDIT PRELIMINARE — Tabella Comparativa Chiavi JSON

Analisi effettuata leggendo: `buildApiPayload()` nel frontend, `WizardStoreRequest.php` + `WizardController.php` nel backend, `screen_progress.py` + `system_config.py` + `api_client.py` nell'agent.


| Campo | Frontend invia (`buildApiPayload`) | Backend valida (`WizardStoreRequest`) | Agent legge (`screen_progress.py`) | Conflitto? |
| :-- | :-- | :-- | :-- | :-- |
| Nome PC | `configurazione.nome_pc` | `configurazione.nome_pc` | `wizard_config.get("pc_name")` | 🔴 **CRITICO**: agent usa `pc_name`, gli altri `nome_pc` |
| Utente admin (oggetto) | `configurazione.utente_admin` | `configurazione.utente_admin` | `wizard_config.get("admin_user")` | 🔴 **CRITICO**: agent usa `admin_user`, gli altri `utente_admin` |
| Username admin | `utente_admin.username` | `utente_admin.username` | `admin_user.get("username")` | ✅ Coerente (solo se il parent key è corretto) |
| Password admin (plain) | `utente_admin.password` | `utente_admin.password` | `admin_user.get("password")` | ✅ Coerente |
| Password admin (encrypted) | non inviata | non validata | non letta in screen_progress | ⚠️ AgentController la decifra: `config['utente_admin']['password_encrypted']` → mix di convenzioni |
| Lista software | `configurazione.software_installa` (array di `integer ID`) | `configurazione.software_installa.*` integer | `wizard_config.get("software")` → `sw.get("identifier")` o `sw.get("type")` | 🔴 **CRITICO**: frontend invia array di ID interi, agent si aspetta array di oggetti con chiavi `identifier`, `name`, `type`, `url` |
| Bloatware | `configurazione.bloatware_default` (array di stringhe) | `configurazione.bloatware_default.*` string | non letto direttamente da screen_progress (passato come `apps_to_remove` separato) | 🟡 **WARNING**: struttura inconsistente tra layers |
| Power plan (tipo) | `configurazione.power_plan.tipo` = `"preset"` o `"custom"` | non validato granularmente | `wizard_config.get("power_plan")` → `plan_config.get("type")` | 🔴 **CRITICO**: backend salva `tipo`, agent legge `type` |
| Power plan preset | `power_plan.params.preset` = `"balanced"/"high_performance"/"power_saver"` | non validato | `plan_config.get("guid")` | 🔴 **CRITICO**: frontend manda nome stringa, agent si aspetta GUID Windows |
| Power plan custom (timeout monitor) | `power_plan.params.monitor_timeout_ac` | non validato | `settings["monitor-timeout-ac"]` (powercfg) | 🟡 **WARNING**: underscore vs trattino |
| Power plan custom (timeout sleep) | `power_plan.params.sleep_timeout_ac` | non validato | `settings["sleep-timeout-ac"]` | 🟡 **WARNING**: underscore vs trattino |
| Power plan cpu min | `power_plan.params.cpu_min_percent` | non validato | `settings["cpu-min-percent"]`? | 🔴 **CRITICO**: non esiste un'opzione powercfg `/change cpu-min-percent` — chiave inventata |
| Extras (oggetto) | `configurazione.extras` | `configurazione.extras` array | `wizard_config.get("extras")` | ✅ Coerente |
| Extras timezone | `extras.timezone` | non validato granularmente | `extras["timezone"]` | ✅ Coerente |
| Extras language | `extras.language` | non validato | `extras["language"]` | ✅ Coerente |
| Extras keyboard | `extras.keyboard_layout` | non validato | non letto (letto come `language` in `system_config.py`) | 🟡 **WARNING**: campo inviato ma non consumato dall'agent |
| Extras wallpaper | inviato come `wallpaper` (file FormData) | non validato | `extras["wallpaper"]` (path locale?) | 🔴 **CRITICO**: frontend invia file binario, agent si aspetta un path stringa |
| Extras WiFi | `extras.wifi.ssid` + `extras.wifi.password` | non validato | `extras` → `system_config.apply_extras()` legge `extras["wifi"]`? | 🔴 **CRITICO**: campo non letto dall'agent in `apply_extras` |
| Extras windows_update | `extras.windows_update.policy` | non validato | `extras["windows_update"]` (stringa diretta) | 🔴 **CRITICO**: frontend invia oggetto `{policy: "auto"}`, agent si aspetta stringa `"auto"` |
| Extras remove_microsoft_account | `configurazione.remove_microsoft_account` (fuori da `extras`) | non validato | `admin_user.get("remove_setup_account")` | 🔴 **CRITICO**: frontend mette fuori da `utente_admin`, agent lo cerca dentro `admin_user` |
| Software type (winget/exe) | array di soli ID interi | array di interi | `sw.get("type")` == `"winget"` | 🔴 **CRITICO**: backend risolve il tipo da DB, non lo serializza nella config JSON salvata |
| `wizard_config` servito da AgentController | — | `config = wizard->configurazione` (raw JSON del DB) | `wizard_config` ricevuto da `/api/agent/auth` | ✅ ma eredita tutti i mismatch sopra |


***

## Riepilogo Mismatch Critici (🔴) Prima di Procedere

**8 conflitti bloccanti** identificati:

1. `nome_pc` (PHP/Frontend) ↔ `pc_name` (Agent) → KeyError silenzioso, PC non viene rinominato
2. `utente_admin` (PHP/Frontend) ↔ `admin_user` (Agent) → tutta la sezione admin viene saltata
3. `software_installa: [int]` (Frontend) ↔ `software: [{name, identifier, type, url}]` (Agent) → il loop software non installa nulla
4. `power_plan.tipo` (PHP) ↔ `plan_config.get("type")` (Agent) → il power plan non viene applicato
5. `power_plan.params.preset = "balanced"` ↔ `plan_config.get("guid")` → l'agent non ha il GUID per `powercfg /setactive`
6. `extras.windows_update: {policy: "auto"}` ↔ `extras["windows_update"]` stringa → `au_options.get({policy:"auto"})` ritorna sempre `"3"` (default)
7. `remove_microsoft_account` fuori da `utente_admin` ↔ `admin_user.get("remove_setup_account")` → la rimozione account OOBE non avviene mai
8. `extras.wifi` inviato ma non consumato in `apply_extras()` → WiFi mai configurato

***

Ora procedo con tutti i deliverable richiesti.

***

## 1. `docs/schemas/wizard-config.schema.json`

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "$id": "https://windeploy.local/schemas/wizard-config.schema.json",
  "title": "WizardConfig",
  "description": "Contratto condiviso tra Frontend React, Backend Laravel e Agent Python per la configurazione del wizard di provisioning WinDeploy. Versione: 1.0",
  "type": "object",
  "required": ["version", "pc_name", "admin_user", "software", "bloatware", "power_plan"],
  "additionalProperties": false,
  "properties": {

    "version": {
      "type": "string",
      "const": "1.0",
      "description": "Versione dello schema. Incrementare in caso di breaking change."
    },

    "pc_name": {
      "type": "string",
      "minLength": 1,
      "maxLength": 15,
      "pattern": "^[a-zA-Z0-9]([a-zA-Z0-9\\-]*[a-zA-Z0-9])?$",
      "description": "Nome del PC di destinazione. Massimo 15 caratteri alfanumerici e trattini. Non può iniziare o finire con trattino. Limite imposto da NetBIOS/Windows."
    },

    "admin_user": {
      "type": "object",
      "required": ["username"],
      "additionalProperties": false,
      "description": "Credenziali dell'utente amministratore locale da creare sul PC.",
      "properties": {
        "username": {
          "type": "string",
          "minLength": 1,
          "maxLength": 50,
          "pattern": "^[a-zA-Z0-9._-]+$",
          "description": "Nome utente senza spazi o caratteri speciali."
        },
        "password": {
          "type": "string",
          "minLength": 6,
          "maxLength": 128,
          "description": "Password in chiaro. SOLO in transito frontend→backend su HTTPS. Il backend la sostituisce immediatamente con password_encrypted e rimuove questo campo."
        },
        "password_encrypted": {
          "type": "string",
          "description": "Password cifrata AES-256-GCM con EncryptionService. Presente nel JSON salvato nel DB e servito all'agent. Mai restituita nelle API di listing."
        },
        "remove_setup_account": {
          "type": "boolean",
          "default": false,
          "description": "Se true, l'agent rimuoverà l'account Microsoft/OOBE di setup iniziale dopo aver creato l'utente admin locale."
        }
      }
    },

    "software": {
      "type": "array",
      "description": "Lista dei software da installare sul PC di destinazione.",
      "items": {
        "type": "object",
        "required": ["id", "winget_id", "name", "type"],
        "additionalProperties": false,
        "properties": {
          "id": {
            "type": "integer",
            "minimum": 1,
            "description": "Chiave primaria nella tabella software_library del backend."
          },
          "winget_id": {
            "type": "string",
            "minLength": 1,
            "description": "Identificatore winget per installazione silenziosa (es. 'Google.Chrome'). Usato dall'agent per winget install --id."
          },
          "name": {
            "type": "string",
            "minLength": 1,
            "description": "Nome visualizzabile del software (solo display, non usato per installazione)."
          },
          "type": {
            "type": "string",
            "enum": ["winget", "exe", "msi"],
            "description": "Tipo di installazione: winget usa il package manager, exe/msi scaricano dal server WinDeploy."
          },
          "download_url": {
            "type": ["string", "null"],
            "format": "uri",
            "description": "URL di download dal server WinDeploy. Obbligatorio se type='exe' o type='msi', null se type='winget'."
          }
        }
      }
    },

    "bloatware": {
      "type": "array",
      "description": "Lista delle app pre-selezionate per la rimozione. L'agent mostrerà questa lista all'utente che potrà deselezionare singole voci.",
      "items": {
        "type": "object",
        "required": ["package_name", "display_name", "selected"],
        "additionalProperties": false,
        "properties": {
          "package_name": {
            "type": "string",
            "minLength": 1,
            "description": "Nome del pacchetto AppX o identificatore per winget uninstall (es. 'Microsoft.XboxApp')."
          },
          "display_name": {
            "type": "string",
            "minLength": 1,
            "description": "Nome leggibile mostrato all'utente durante la selezione (es. 'Xbox')."
          },
          "selected": {
            "type": "boolean",
            "default": true,
            "description": "true = selezionata per rimozione. L'utente può deselezionare sull'agent prima dell'esecuzione."
          }
        }
      }
    },

    "power_plan": {
      "type": "object",
      "required": ["type"],
      "additionalProperties": false,
      "description": "Configurazione del piano di risparmio energetico di Windows.",
      "properties": {
        "type": {
          "type": "string",
          "enum": ["balanced", "high_performance", "power_saver", "custom"],
          "description": "Tipo di piano: preset di Windows o configurazione manuale."
        },
        "screen_timeout_ac": {
          "type": ["integer", "null"],
          "minimum": 1,
          "maximum": 60,
          "description": "Timeout spegnimento schermo con alimentazione AC, in minuti. null = mai. Usato solo se type='custom'."
        },
        "sleep_timeout_ac": {
          "type": ["integer", "null"],
          "minimum": 1,
          "maximum": 120,
          "description": "Timeout sospensione sistema con alimentazione AC, in minuti. null = mai. Usato solo se type='custom'."
        },
        "cpu_min_percent": {
          "type": "integer",
          "minimum": 0,
          "maximum": 100,
          "default": 5,
          "description": "Percentuale minima di frequenza CPU. Usato solo se type='custom'. Corrisponde a powercfg /setacvalueindex ... PROCTHROTTLEMIN."
        },
        "cpu_max_percent": {
          "type": "integer",
          "minimum": 0,
          "maximum": 100,
          "default": 100,
          "description": "Percentuale massima di frequenza CPU. Usato solo se type='custom'. Corrisponde a powercfg /setacvalueindex ... PROCTHROTTLEMAX."
        }
      }
    },

    "extras": {
      "type": ["object", "null"],
      "additionalProperties": false,
      "description": "Configurazioni opzionali aggiuntive. L'intero oggetto è nullable.",
      "properties": {
        "timezone": {
          "type": "string",
          "minLength": 1,
          "description": "Fuso orario nel formato IANA (es. 'Europe/Rome'). Usato dall'agent con tzutil /s."
        },
        "language": {
          "type": "string",
          "pattern": "^[a-z]{2}-[A-Z]{2}$",
          "description": "Codice lingua e regione (es. 'it-IT'). Usato con Set-WinUserLanguageList."
        },
        "keyboard_layout": {
          "type": "string",
          "pattern": "^[a-z]{2}-[A-Z]{2}$",
          "description": "Layout tastiera (es. 'it-IT'). Può differire dalla lingua dell'interfaccia."
        },
        "wallpaper_url": {
          "type": ["string", "null"],
          "description": "URL relativo sul server WinDeploy per scaricare lo sfondo aziendale (es. '/storage/wallpapers/azienda.jpg'). null = nessun wallpaper personalizzato."
        },
        "wifi": {
          "oneOf": [
            {
              "type": "object",
              "required": ["ssid"],
              "additionalProperties": false,
              "properties": {
                "ssid": {
                  "type": "string",
                  "minLength": 1,
                  "maxLength": 32,
                  "description": "Nome della rete WiFi (SSID)."
                },
                "password": {
                  "type": "string",
                  "description": "Password WiFi in chiaro. SOLO in transito frontend→backend. Backend la cifra immediatamente."
                },
                "password_encrypted": {
                  "type": "string",
                  "description": "Password WiFi cifrata AES-256-GCM. Presente nel JSON del DB e servito all'agent."
                }
              }
            },
            { "type": "null" }
          ],
          "description": "Configurazione WiFi aziendale. null = nessuna configurazione WiFi automatica."
        },
        "windows_update": {
          "type": "string",
          "enum": ["auto", "download_only", "manual"],
          "description": "Policy di Windows Update: auto=installa automaticamente, download_only=scarica senza installare, manual=notifica senza scaricare."
        }
      }
    }
  }
}
```


***

## 2. `frontend/src/types/WizardConfig.ts`

```typescript
// frontend/src/types/WizardConfig.ts
// Contratto TypeScript condiviso per la configurazione del Wizard WinDeploy.
// Speculare a docs/schemas/wizard-config.schema.json v1.0
// Convenzione chiavi: snake_case (coerente con PHP e Python).

// ─── Tipi primitivi ───────────────────────────────────────────────────────────

export type SoftwareType = "winget" | "exe" | "msi";

export type PowerPlanType =
  | "balanced"
  | "high_performance"
  | "power_saver"
  | "custom";

export type WindowsUpdatePolicy = "auto" | "download_only" | "manual";

// ─── Sotto-interfacce ─────────────────────────────────────────────────────────

export interface AdminUser {
  username: string;
  /** Password in chiaro. Presente SOLO in transito (form → POST). Il backend la cifra e rimuove. */
  password?: string;
  /** Password cifrata AES-256-GCM. Presente nel payload restituito dall'agent auth endpoint. */
  password_encrypted?: string;
  /** Se true, l'agent rimuove l'account OOBE/Microsoft al termine dell'installazione. */
  remove_setup_account: boolean;
}

export interface SoftwareItem {
  /** FK → tabella software_library */
  id: number;
  /** Identificatore per winget install --id */
  winget_id: string;
  /** Nome visualizzabile (solo display) */
  name: string;
  /** Tipo di installazione */
  type: SoftwareType;
  /** URL di download dal server WinDeploy. null se type='winget' */
  download_url: string | null;
}

export interface BloatwareItem {
  /** Nome pacchetto AppX o winget ID (es. 'Microsoft.XboxApp') */
  package_name: string;
  /** Nome leggibile mostrato all'utente */
  display_name: string;
  /** true = preselezionata per rimozione */
  selected: boolean;
}

export interface PowerPlan {
  type: PowerPlanType;
  /** Timeout schermo (minuti, AC). null = mai. Solo per type='custom'. */
  screen_timeout_ac: number | null;
  /** Timeout sospensione (minuti, AC). null = mai. Solo per type='custom'. */
  sleep_timeout_ac: number | null;
  /** % frequenza CPU minima. Solo per type='custom'. */
  cpu_min_percent: number;
  /** % frequenza CPU massima. Solo per type='custom'. */
  cpu_max_percent: number;
}

export interface WifiConfig {
  ssid: string;
  /** In chiaro solo in transito. Backend cifra immediatamente. */
  password?: string;
  /** Cifrata AES-256-GCM. Presente nel payload per l'agent. */
  password_encrypted?: string;
}

export interface ExtrasConfig {
  /** Fuso orario IANA (es. 'Europe/Rome') */
  timezone?: string;
  /** Lingua interfaccia (es. 'it-IT') */
  language?: string;
  /** Layout tastiera (es. 'it-IT') */
  keyboard_layout?: string;
  /** URL relativo wallpaper su server WinDeploy. null = nessun wallpaper. */
  wallpaper_url?: string | null;
  /** Configurazione WiFi. null = nessuna. */
  wifi?: WifiConfig | null;
  /** Policy Windows Update */
  windows_update?: WindowsUpdatePolicy;
}

// ─── Interfaccia principale ───────────────────────────────────────────────────

export interface WizardConfig {
  /** Versione schema. Attualmente "1.0". */
  version: "1.0";
  /** Nome del PC di destinazione (max 15 char, alfanumerici e trattini). */
  pc_name: string;
  /** Credenziali dell'utente amministratore locale. */
  admin_user: AdminUser;
  /** Lista software da installare. */
  software: SoftwareItem[];
  /** Lista bloatware pre-selezionata per rimozione. */
  bloatware: BloatwareItem[];
  /** Configurazione piano energetico. */
  power_plan: PowerPlan;
  /** Configurazioni opzionali aggiuntive. */
  extras?: ExtrasConfig | null;
}

// ─── Payload POST verso /api/wizards ─────────────────────────────────────────

/** Shape del body inviato dal frontend al backend per creare un wizard. */
export interface CreateWizardPayload {
  nome: string;
  template_id?: number | null;
  note_interne?: string | null;
  configurazione: WizardConfig;
  /** File immagine wallpaper. Inviato solo se presente (multipart/form-data). */
  wallpaper?: File;
}

// ─── Type Guards ──────────────────────────────────────────────────────────────

export function isWizardConfig(value: unknown): value is WizardConfig {
  if (typeof value !== "object" || value === null) return false;
  const v = value as Record<string, unknown>;
  return (
    v.version === "1.0" &&
    typeof v.pc_name === "string" &&
    typeof v.admin_user === "object" &&
    Array.isArray(v.software) &&
    Array.isArray(v.bloatware) &&
    typeof v.power_plan === "object"
  );
}

export function isSoftwareItem(value: unknown): value is SoftwareItem {
  if (typeof value !== "object" || value === null) return false;
  const v = value as Record<string, unknown>;
  return (
    typeof v.id === "number" &&
    typeof v.winget_id === "string" &&
    typeof v.name === "string" &&
    (v.type === "winget" || v.type === "exe" || v.type === "msi")
  );
}

// ─── Valori di default ────────────────────────────────────────────────────────

/** Oggetto di default per inizializzare lo stato del Wizard Builder.
 *  Usato in buildEmptyWizard() come struttura base. */
export const WIZARD_CONFIG_DEFAULT: WizardConfig = {
  version: "1.0",
  pc_name: "",
  admin_user: {
    username: "",
    password: "",
    remove_setup_account: false,
  },
  software: [],
  bloatware: [],
  power_plan: {
    type: "balanced",
    screen_timeout_ac: 15,
    sleep_timeout_ac: 30,
    cpu_min_percent: 5,
    cpu_max_percent: 100,
  },
  extras: {
    timezone: "Europe/Rome",
    language: "it-IT",
    keyboard_layout: "it-IT",
    wallpaper_url: null,
    wifi: null,
    windows_update: "auto",
  },
};
```


***

## 3. `backend/app/Http/Requests/Wizard/WizardStoreRequest.php`

⚠️ **Nota sicurezza**: il file attuale si chiama `WizardStoreRequest` ma nel controller viene importato come `WizardStoreRequest`. Il file fisico trovato nell'allegato ha nome `StoreWizardRequest.php` con classe `WizardStoreRequest` — manteniamo il nome della classe ma uniformiamo il file.

```php
<?php
// backend/app/Http/Requests/Wizard/WizardStoreRequest.php

namespace App\Http\Requests\Wizard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WizardStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorizzazione per ruolo gestita nel Controller.
        // Qui solo validazione strutturale del payload.
        return true;
    }

    /**
     * Normalizza il payload prima della validazione.
     * Se 'configurazione' arriva come stringa JSON (multipart/form-data),
     * la decodifica in array prima che le rules vengano applicate.
     */
    public function prepareForValidation(): void
    {
        if ($this->has('configurazione') && is_string($this->input('configurazione'))) {
            $decoded = json_decode($this->input('configurazione'), true);
            if (is_array($decoded)) {
                $this->merge(['configurazione' => $decoded]);
            }
        }

        // Normalizza template_id: stringa vuota → null
        if ($this->has('template_id') && $this->input('template_id') === '') {
            $this->merge(['template_id' => null]);
        }

        // Normalizza note_interne: stringa vuota → null
        if ($this->has('note_interne') && $this->input('note_interne') === '') {
            $this->merge(['note_interne' => null]);
        }
    }

    public function rules(): array
    {
        return [
            // ── Campi radice wizard ──────────────────────────────────────────
            'nome'        => 'required|string|max:150',
            'template_id' => 'nullable|exists:templates,id',
            'note_interne'=> 'nullable|string|max:1000',
            'wallpaper'   => 'nullable|file|image|max:5120', // max 5 MB

            // ── Configurazione (oggetto root) ─────────────────────────────────
            'configurazione'         => 'required|array',
            'configurazione.version' => 'required|string|in:1.0',

            // ── Nome PC ──────────────────────────────────────────────────────
            'configurazione.pc_name' => [
                'required',
                'string',
                'min:1',
                'max:15',
                'regex:/^[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?$/',
            ],

            // ── Utente Admin ─────────────────────────────────────────────────
            'configurazione.admin_user'                      => 'required|array',
            'configurazione.admin_user.username'             => [
                'required',
                'string',
                'min:1',
                'max:50',
                'regex:/^[a-zA-Z0-9._-]+$/',
            ],
            // La password arriva in chiaro solo in fase di creazione.
            // Il Controller la cifra immediatamente con EncryptionService.
            'configurazione.admin_user.password'             => 'sometimes|required|string|min:6|max:128',
            'configurazione.admin_user.remove_setup_account' => 'sometimes|boolean',

            // ── Software da installare ────────────────────────────────────────
            'configurazione.software'               => 'present|array',
            'configurazione.software.*.id'          => 'required|integer|exists:software_library,id',
            'configurazione.software.*.winget_id'   => 'required|string|min:1|max:255',
            'configurazione.software.*.name'        => 'required|string|min:1|max:255',
            'configurazione.software.*.type'        => 'required|string|in:winget,exe,msi',
            'configurazione.software.*.download_url'=> 'nullable|url|max:2048',

            // ── Bloatware ─────────────────────────────────────────────────────
            'configurazione.bloatware'                       => 'present|array',
            'configurazione.bloatware.*.package_name'        => 'required|string|min:1|max:255',
            'configurazione.bloatware.*.display_name'        => 'required|string|min:1|max:255',
            'configurazione.bloatware.*.selected'            => 'required|boolean',

            // ── Power Plan ────────────────────────────────────────────────────
            'configurazione.power_plan'                      => 'required|array',
            'configurazione.power_plan.type'                 => 'required|string|in:balanced,high_performance,power_saver,custom',
            'configurazione.power_plan.screen_timeout_ac'    => 'nullable|integer|min:1|max:60',
            'configurazione.power_plan.sleep_timeout_ac'     => 'nullable|integer|min:1|max:120',
            'configurazione.power_plan.cpu_min_percent'      => 'sometimes|integer|min:0|max:100',
            'configurazione.power_plan.cpu_max_percent'      => 'sometimes|integer|min:0|max:100',

            // ── Extras (opzionali) ────────────────────────────────────────────
            'configurazione.extras'                          => 'nullable|array',
            'configurazione.extras.timezone'                 => 'sometimes|nullable|string|min:1|max:100',
            'configurazione.extras.language'                 => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^[a-z]{2}-[A-Z]{2}$/',
            ],
            'configurazione.extras.keyboard_layout'          => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^[a-z]{2}-[A-Z]{2}$/',
            ],
            'configurazione.extras.wallpaper_url'            => 'sometimes|nullable|string|max:2048',
            'configurazione.extras.wifi'                     => 'sometimes|nullable|array',
            'configurazione.extras.wifi.ssid'                => 'required_with:configurazione.extras.wifi|string|min:1|max:32',
            'configurazione.extras.wifi.password'            => 'sometimes|string|min:1|max:128',
            'configurazione.extras.windows_update'           => 'sometimes|nullable|string|in:auto,download_only,manual',
        ];
    }

    public function messages(): array
    {
        return [
            // ── Campi radice ──────────────────────────────────────────────────
            'nome.required'        => 'Il nome del wizard è obbligatorio.',
            'nome.max'             => 'Il nome del wizard non può superare 150 caratteri.',
            'template_id.exists'   => 'Il template selezionato non esiste.',
            'wallpaper.image'      => 'Il wallpaper deve essere un\'immagine (JPG, PNG, GIF, ecc.).',
            'wallpaper.max'        => 'Il wallpaper non può superare 5 MB.',

            // ── Versione schema ───────────────────────────────────────────────
            'configurazione.required'        => 'La configurazione è obbligatoria.',
            'configurazione.version.required'=> 'La versione dello schema è obbligatoria.',
            'configurazione.version.in'      => 'Versione schema non supportata. Attesa: 1.0.',

            // ── Nome PC ───────────────────────────────────────────────────────
            'configurazione.pc_name.required' => 'Il nome PC è obbligatorio.',
            'configurazione.pc_name.max'      => 'Il nome PC non può superare 15 caratteri (limite Windows).',
            'configurazione.pc_name.regex'    => 'Il nome PC può contenere solo lettere, numeri e trattini, e non può iniziare o finire con un trattino.',

            // ── Utente Admin ──────────────────────────────────────────────────
            'configurazione.admin_user.required'             => 'La sezione utente admin è obbligatoria.',
            'configurazione.admin_user.username.required'    => 'Lo username dell\'admin è obbligatorio.',
            'configurazione.admin_user.username.max'         => 'Lo username non può superare 50 caratteri.',
            'configurazione.admin_user.username.regex'       => 'Lo username può contenere solo lettere, numeri, punti, underscore e trattini (nessuno spazio).',
            'configurazione.admin_user.password.required'    => 'La password è obbligatoria.',
            'configurazione.admin_user.password.min'         => 'La password deve contenere almeno 6 caratteri.',
            'configurazione.admin_user.password.max'         => 'La password non può superare 128 caratteri.',

            // ── Software ──────────────────────────────────────────────────────
            'configurazione.software.*.id.required'       => 'Ogni software deve avere un ID valido.',
            'configurazione.software.*.id.exists'         => 'Il software con ID :input non esiste nella libreria.',
            'configurazione.software.*.winget_id.required'=> 'Ogni software deve avere un identificatore winget.',
            'configurazione.software.*.type.in'           => 'Il tipo di installazione deve essere: winget, exe o msi.',
            'configurazione.software.*.download_url.url'  => 'L\'URL di download del software non è un URL valido.',

            // ── Bloatware ─────────────────────────────────────────────────────
            'configurazione.bloatware.*.package_name.required' => 'Ogni voce bloatware deve avere un nome pacchetto.',
            'configurazione.bloatware.*.selected.required'     => 'Lo stato di selezione del bloatware è obbligatorio.',

            // ── Power Plan ────────────────────────────────────────────────────
            'configurazione.power_plan.required'               => 'La configurazione del power plan è obbligatoria.',
            'configurazione.power_plan.type.required'          => 'Il tipo di power plan è obbligatorio.',
            'configurazione.power_plan.type.in'                => 'Il tipo di power plan deve essere: balanced, high_performance, power_saver o custom.',
            'configurazione.power_plan.screen_timeout_ac.min'  => 'Il timeout schermo deve essere almeno 1 minuto.',
            'configurazione.power_plan.screen_timeout_ac.max'  => 'Il timeout schermo non può superare 60 minuti.',
            'configurazione.power_plan.sleep_timeout_ac.min'   => 'Il timeout sospensione deve essere almeno 1 minuto.',
            'configurazione.power_plan.sleep_timeout_ac.max'   => 'Il timeout sospensione non può superare 120 minuti.',
            'configurazione.power_plan.cpu_min_percent.min'    => 'La percentuale CPU minima non può essere inferiore a 0.',
            'configurazione.power_plan.cpu_min_percent.max'    => 'La percentuale CPU minima non può superare 100.',
            'configurazione.power_plan.cpu_max_percent.max'    => 'La percentuale CPU massima non può superare 100.',

            // ── Extras ────────────────────────────────────────────────────────
            'configurazione.extras.language.regex'             => 'La lingua deve essere nel formato es. it-IT.',
            'configurazione.extras.keyboard_layout.regex'      => 'Il layout tastiera deve essere nel formato es. it-IT.',
            'configurazione.extras.wifi.ssid.required_with'    => 'L\'SSID è obbligatorio quando si configura il WiFi.',
            'configurazione.extras.wifi.ssid.max'              => 'L\'SSID non può superare 32 caratteri.',
            'configurazione.extras.windows_update.in'          => 'La policy Windows Update deve essere: auto, download_only o manual.',
        ];
    }

    /**
     * Validazione custom post-rules: cpu_min_percent non deve superare cpu_max_percent.
     * Aggiunge un errore manuale se la regola non è rispettata.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $config = $this->input('configurazione', []);
            $powerPlan = $config['power_plan'] ?? [];

            if (
                isset($powerPlan['cpu_min_percent'], $powerPlan['cpu_max_percent']) &&
                $powerPlan['cpu_min_percent'] > $powerPlan['cpu_max_percent']
            ) {
                $validator->errors()->add(
                    'configurazione.power_plan.cpu_min_percent',
                    'La percentuale CPU minima non può superare quella massima.'
                );
            }
        });
    }
}
```


***

## 4. `frontend/src/utils/validateWizardConfig.ts`

```typescript
// frontend/src/utils/validateWizardConfig.ts
// Validazione lato frontend del payload WizardConfig prima del POST /api/wizards.
// Le regole sono speculari a WizardStoreRequest.php — ogni modifica a una
// deve essere replicata nell'altra.

import type { WizardConfig } from "../types/WizardConfig";

export interface ValidationResult {
  valid: boolean;
  /** Mappa campo → lista di messaggi di errore. */
  errors: Record<string, string[]>;
}

/** Aggiunge un errore al record degli errori. */
function addError(
  errors: Record<string, string[]>,
  field: string,
  message: string
): void {
  if (!errors[field]) errors[field] = [];
  errors[field].push(message);
}

/** Valida il nome PC secondo le regole Windows + backend. */
function validatePcName(
  name: string,
  errors: Record<string, string[]>
): void {
  const field = "configurazione.pc_name";
  if (!name || name.trim().length === 0) {
    addError(errors, field, "Il nome PC è obbligatorio.");
    return;
  }
  if (name.length > 15) {
    addError(errors, field, "Il nome PC non può superare 15 caratteri (limite Windows).");
  }
  if (!/^[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?$/.test(name)) {
    addError(
      errors,
      field,
      "Il nome PC può contenere solo lettere, numeri e trattini, e non può iniziare o finire con un trattino."
    );
  }
}

/** Valida la sezione admin_user. */
function validateAdminUser(
  adminUser: WizardConfig["admin_user"],
  errors: Record<string, string[]>
): void {
  if (!adminUser) {
    addError(errors, "configurazione.admin_user", "La sezione utente admin è obbligatoria.");
    return;
  }

  const username = adminUser.username?.trim() ?? "";
  if (!username) {
    addError(errors, "configurazione.admin_user.username", "Lo username dell'admin è obbligatorio.");
  } else {
    if (username.length > 50) {
      addError(errors, "configurazione.admin_user.username", "Lo username non può superare 50 caratteri.");
    }
    if (!/^[a-zA-Z0-9._-]+$/.test(username)) {
      addError(
        errors,
        "configurazione.admin_user.username",
        "Lo username può contenere solo lettere, numeri, punti, underscore e trattini (nessuno spazio)."
      );
    }
  }

  const password = adminUser.password ?? "";
  if (!password) {
    addError(errors, "configurazione.admin_user.password", "La password è obbligatoria.");
  } else {
    if (password.length < 6) {
      addError(errors, "configurazione.admin_user.password", "La password deve contenere almeno 6 caratteri.");
    }
    if (password.length > 128) {
      addError(errors, "configurazione.admin_user.password", "La password non può superare 128 caratteri.");
    }
  }
}

/** Valida ogni elemento dell'array software. */
function validateSoftware(
  software: WizardConfig["software"],
  errors: Record<string, string[]>
): void {
  if (!Array.isArray(software)) {
    addError(errors, "configurazione.software", "La lista software deve essere un array.");
    return;
  }
  software.forEach((sw, idx) => {
    const base = `configurazione.software.${idx}`;
    if (!sw.id || typeof sw.id !== "number") {
      addError(errors, `${base}.id`, "Ogni software deve avere un ID numerico valido.");
    }
    if (!sw.winget_id?.trim()) {
      addError(errors, `${base}.winget_id`, "Ogni software deve avere un identificatore winget.");
    }
    if (!sw.name?.trim()) {
      addError(errors, `${base}.name`, "Ogni software deve avere un nome.");
    }
    if (!["winget", "exe", "msi"].includes(sw.type)) {
      addError(errors, `${base}.type`, "Il tipo deve essere: winget, exe o msi.");
    }
    if (sw.download_url !== null && sw.download_url !== undefined) {
      try {
        new URL(sw.download_url);
      } catch {
        addError(errors, `${base}.download_url`, "L'URL di download non è un URL valido.");
      }
    }
  });
}

/** Valida ogni elemento dell'array bloatware. */
function validateBloatware(
  bloatware: WizardConfig["bloatware"],
  errors: Record<string, string[]>
): void {
  if (!Array.isArray(bloatware)) {
    addError(errors, "configurazione.bloatware", "La lista bloatware deve essere un array.");
    return;
  }
  bloatware.forEach((item, idx) => {
    const base = `configurazione.bloatware.${idx}`;
    if (!item.package_name?.trim()) {
      addError(errors, `${base}.package_name`, "Ogni voce bloatware deve avere un nome pacchetto.");
    }
    if (!item.display_name?.trim()) {
      addError(errors, `${base}.display_name`, "Ogni voce bloatware deve avere un nome visualizzabile.");
    }
    if (typeof item.selected !== "boolean") {
      addError(errors, `${base}.selected`, "Lo stato di selezione del bloatware deve essere true o false.");
    }
  });
}

/** Valida la sezione power_plan. */
function validatePowerPlan(
  powerPlan: WizardConfig["power_plan"],
  errors: Record<string, string[]>
): void {
  if (!powerPlan) {
    addError(errors, "configurazione.power_plan", "La configurazione del power plan è obbligatoria.");
    return;
  }
  const validTypes = ["balanced", "high_performance", "power_saver", "custom"];
  if (!validTypes.includes(powerPlan.type)) {
    addError(
      errors,
      "configurazione.power_plan.type",
      "Il tipo di power plan deve essere: balanced, high_performance, power_saver o custom."
    );
  }
  if (powerPlan.type === "custom") {
    if (powerPlan.screen_timeout_ac !== null && powerPlan.screen_timeout_ac !== undefined) {
      if (powerPlan.screen_timeout_ac < 1 || powerPlan.screen_timeout_ac > 60) {
        addError(errors, "configurazione.power_plan.screen_timeout_ac", "Il timeout schermo deve essere tra 1 e 60 minuti.");
      }
    }
    if (powerPlan.sleep_timeout_ac !== null && powerPlan.sleep_timeout_ac !== undefined) {
      if (powerPlan.sleep_timeout_ac < 1 || powerPlan.sleep_timeout_ac > 120) {
        addError(errors, "configurazione.power_plan.sleep_timeout_ac", "Il timeout sospensione deve essere tra 1 e 120 minuti.");
      }
    }
    if (powerPlan.cpu_min_percent < 0 || powerPlan.cpu_min_percent > 100) {
      addError(errors, "configurazione.power_plan.cpu_min_percent", "La percentuale CPU minima deve essere tra 0 e 100.");
    }
    if (powerPlan.cpu_max_percent < 0 || powerPlan.cpu_max_percent > 100) {
      addError(errors, "configurazione.power_plan.cpu_max_percent", "La percentuale CPU massima deve essere tra 0 e 100.");
    }
    if (powerPlan.cpu_min_percent > powerPlan.cpu_max_percent) {
      addError(
        errors,
        "configurazione.power_plan.cpu_min_percent",
        "La percentuale CPU minima non può superare quella massima."
      );
    }
  }
}

/** Valida la sezione extras (opzionale). */
function validateExtras(
  extras: WizardConfig["extras"],
  errors: Record<string, string[]>
): void {
  if (!extras) return; // Extras è nullable, assenza è valida

  const langRegex = /^[a-z]{2}-[A-Z]{2}$/;

  if (extras.language && !langRegex.test(extras.language)) {
    addError(errors, "configurazione.extras.language", "La lingua deve essere nel formato es. it-IT.");
  }
  if (extras.keyboard_layout && !langRegex.test(extras.keyboard_layout)) {
    addError(errors, "configurazione.extras.keyboard_layout", "Il layout tastiera deve essere nel formato es. it-IT.");
  }
  if (extras.windows_update && !["auto", "download_only", "manual"].includes(extras.windows_update)) {
    addError(errors, "configurazione.extras.windows_update", "La policy Windows Update deve essere: auto, download_only o manual.");
  }
  if (extras.wifi !== null && extras.wifi !== undefined) {
    if (!extras.wifi.ssid?.trim()) {
      addError(errors, "configurazione.extras.wifi.ssid", "L'SSID è obbligatorio quando si configura il WiFi.");
    } else if (extras.wifi.ssid.length > 32) {
      addError(errors, "configurazione.extras.wifi.ssid", "L'SSID non può superare 32 caratteri.");
    }
    if (!extras.wifi.password?.trim()) {
      addError(errors, "configurazione.extras.wifi.password", "La password WiFi è obbligatoria quando si configura il WiFi.");
    }
  }
}

/**
 * Valida il payload WizardConfig prima del POST /api/wizards.
 * Restituisce { valid: true, errors: {} } se tutto è corretto,
 * oppure { valid: false, errors: { campo: [messaggi] } } con dettaglio.
 *
 * Le regole sono speculari a WizardStoreRequest.php.
 */
export function validateWizardConfig(config: WizardConfig): ValidationResult {
  const errors: Record<string, string[]> = {};

  if (!config) {
    return { valid: false, errors: { configurazione: ["La configurazione è obbligatoria."] } };
  }

  if (config.version !== "1.0") {
    addError(errors, "configurazione.version", "Versione schema non supportata. Attesa: 1.0.");
  }

  validatePcName(config.pc_name, errors);
  validateAdminUser(config.admin_user, errors);
  validateSoftware(config.software, errors);
  validateBloatware(config.bloatware, errors);
  validatePowerPlan(config.power_plan, errors);
  validateExtras(config.extras, errors);

  return {
    valid: Object.keys(errors).length === 0,
    errors,
  };
}
```


***

## 5. `agent/SCHEMA.md`

```markdown
# agent/SCHEMA.md
# Riferimento Schema WizardConfig per lo Sviluppo dell'Agent Python

> **Versione schema**: `1.0`
> **File di riferimento normativo**: `docs/schemas/wizard-config.schema.json`
> **Ultimo aggiornamento**: 2026-03-04

---

## Struttura del dict Python

Il dict `wizard_config` è ricevuto dall'endpoint `/api/agent/auth` nella chiave
`wizard_config` del body JSON di risposta. Viene passato ai vari moduli come argomento.

### Accesso ai campi principali

| Campo                             | Tipo Python        | Come accedervi                                        | Obbligatorio |
|-----------------------------------|--------------------|-------------------------------------------------------|--------------|
| `version`                         | `str`              | `config["version"]`                                   | ✅ Sì        |
| `pc_name`                         | `str`              | `config["pc_name"]`                                   | ✅ Sì        |
| `admin_user`                      | `dict`             | `config["admin_user"]`                                | ✅ Sì        |
| `admin_user.username`             | `str`              | `config["admin_user"]["username"]`                    | ✅ Sì        |
| `admin_user.password_encrypted`   | `str`              | `config["admin_user"]["password_encrypted"]`          | ✅ Sì (in esecuzione) |
| `admin_user.remove_setup_account` | `bool`             | `config["admin_user"].get("remove_setup_account", False)` | ⚠️ Opzionale |
| `software`                        | `list[dict]`       | `config["software"]`                                  | ✅ Sì (può essere `[]`) |
| `software[n].id`                  | `int`              | `config["software"][n]["id"]`                         | ✅ Sì        |
| `software[n].winget_id`           | `str`              | `config["software"][n]["winget_id"]`                  | ✅ Sì        |
| `software[n].name`                | `str`              | `config["software"][n]["name"]`                       | ✅ Sì        |
| `software[n].type`                | `str`              | `config["software"][n]["type"]`                       | ✅ Sì        |
| `software[n].download_url`        | `str \| None`      | `config["software"][n].get("download_url")`           | ⚠️ Opzionale |
| `bloatware`                       | `list[dict]`       | `config["bloatware"]`                                 | ✅ Sì (può essere `[]`) |
| `bloatware[n].package_name`       | `str`              | `config["bloatware"][n]["package_name"]`              | ✅ Sì        |
| `bloatware[n].display_name`       | `str`              | `config["bloatware"][n]["display_name"]`              | ✅ Sì        |
| `bloatware[n].selected`           | `bool`             | `config["bloatware"][n]["selected"]`                  | ✅ Sì        |
| `power_plan`                      | `dict`             | `config["power_plan"]`                                | ✅ Sì        |
| `power_plan.type`                 | `str`              | `config["power_plan"]["type"]`                        | ✅ Sì        |
| `power_plan.screen_timeout_ac`    | `int \| None`      | `config["power_plan"].get("screen_timeout_ac")`       | ⚠️ Opzionale |
| `power_plan.sleep_timeout_ac`     | `int \| None`      | `config["power_plan"].get("sleep_timeout_ac")`        | ⚠️ Opzionale |
| `power_plan.cpu_min_percent`      | `int`              | `config["power_plan"].get("cpu_min_percent", 5)`      | ⚠️ Opzionale |
| `power_plan.cpu_max_percent`      | `int`              | `config["power_plan"].get("cpu_max_percent", 100)`    | ⚠️ Opzionale |
| `extras`                          | `dict \| None`     | `config.get("extras")`                                | ⚠️ Nullable  |
| `extras.timezone`                 | `str`              | `config["extras"].get("timezone", "Europe/Rome")`     | ⚠️ Opzionale |
| `extras.language`                 | `str`              | `config["extras"].get("language", "it-IT")`           | ⚠️ Opzionale |
| `extras.keyboard_layout`          | `str`              | `config["extras"].get("keyboard_layout")`             | ⚠️ Opzionale |
| `extras.wallpaper_url`            | `str \| None`      | `config["extras"].get("wallpaper_url")`               | ⚠️ Opzionale |
| `extras.wifi`                     | `dict \| None`     | `config["extras"].get("wifi")`                        | ⚠️ Nullable  |
| `extras.wifi.ssid`                | `str`              | `config["extras"]["wifi"]["ssid"]`                    | Se wifi presente |
| `extras.wifi.password_encrypted`  | `str`              | `config["extras"]["wifi"]["password_encrypted"]`      | Se wifi presente |
| `extras.windows_update`           | `str`              | `config["extras"].get("windows_update", "auto")`      | ⚠️ Opzionale |

---

## Come decifrare `password_encrypted`

Le password cifrate (admin e WiFi) vengono decifrate **dal backend** prima di servire
il `wizard_config` all'agent tramite `/api/agent/auth`. L'agent riceve la password
**in chiaro** nella risposta JSON dell'autenticazione.

```python
# In AgentController.php (backend):
# config['admin_user']['password_encrypted'] viene decifrato con EncryptionService
# e restituito come config['admin_user']['password'] (in chiaro).
# L'agent NON deve mai implementare decifratura locale.

# Accesso corretto nell'agent:
password = wizard_config["admin_user"]["password"]  # in chiaro, dalla risposta /auth
```

> ⚠️ **Sicurezza**: l'agent riceve la password in chiaro SOLO via HTTPS JWT-autenticato.
> Non loggare mai `wizard_config["admin_user"]["password"]` in file di log o stdout.

---

## Mappatura `power_plan.type` → GUID Windows

L'agent deve tradurre il valore stringa in GUID per `powercfg /setactive`:


| Schema `type` | GUID Windows |
| :-- | :-- |
| `balanced` | `381b4222-f694-41f0-9685-ff5bb260df2e` |
| `high_performance` | `8c5e7fda-e8bf-4a96-9a85-a6e23a8c635c` |
| `power_saver` | `a1841308-3541-4fab-bc81-f71556f20b4a` |
| `custom` | — (usa `screen_timeout_ac`, `sleep_timeout_ac`, `cpu_min_percent`, `cpu_max_percent`) |

Per `type = "custom"`, usare `powercfg /setacvalueindex SCHEME_CURRENT` con i
sottoguid appropriati, non `powercfg /change` (che non esiste per CPU throttling).

---

## Gestione campi mancanti — Valori di default e log

Se un campo atteso non è presente nel JSON ricevuto:

```python
# Pattern consigliato: usa .get() con default esplicito
pc_name = wizard_config.get("pc_name", "")
if not pc_name:
    logging.error("SCHEMA ERROR: 'pc_name' mancante nel wizard_config. Rinomina PC saltata.")
    # NON interrompere l'intera esecuzione, salta solo questo step

# Per oggetti annidati, verifica prima il parent
admin_user = wizard_config.get("admin_user", {})
username = admin_user.get("username", "")
if not username:
    logging.error("SCHEMA ERROR: 'admin_user.username' mancante. Step admin saltato.")

# Per liste, fallback a lista vuota
software_list = wizard_config.get("software", [])
bloatware_list = wizard_config.get("bloatware", [])
```

**Regola**: mai propagare `KeyError` all'utente finale. Logga l'errore, salta lo step,
continua con gli altri. Il fallimento parziale è preferibile all'abort completo.

---

## Esempio completo dict Python (dati fake)

```python
wizard_config_example = {
    "version": "1.0",
    "pc_name": "PC-CONT-01",
    "admin_user": {
        "username": "admin-locale",
        "password": "P@ssw0rd!2024",      # in chiaro solo in transito
        "remove_setup_account": True
    },
    "software": [
        {
            "id": 1,
            "winget_id": "Google.Chrome",
            "name": "Google Chrome",
            "type": "winget",
            "download_url": None
        },
        {
            "id": 5,
            "winget_id": "7zip.7zip",
            "name": "7-Zip",
            "type": "winget",
            "download_url": None
        }
    ],
    "bloatware": [
        {
            "package_name": "Microsoft.XboxApp",
            "display_name": "Xbox",
            "selected": True
        },
        {
            "package_name": "Microsoft.OneDrive",
            "display_name": "OneDrive",
            "selected": True
        }
    ],
    "power_plan": {
        "type": "balanced"
        # screen_timeout_ac, sleep_timeout_ac omessi → preset non usa parametri custom
    },
    "extras": {
        "timezone": "Europe/Rome",
        "language": "it-IT",
        "keyboard_layout": "it-IT",
        "wallpaper_url": "/storage/wallpapers/azienda.jpg",
        "wifi": {
            "ssid": "Rete-Aziendale",
            "password": "WiFiS3cret!"   # in chiaro solo dall'endpoint /auth
        },
        "windows_update": "download_only"
    }
}
```

```

***

## 6. `docs/schemas/wizard-config-example.json`

```json
{
  "version": "1.0",
  "pc_name": "PC-CONT-01",
  "admin_user": {
    "username": "admin-locale",
    "password": "P@ssw0rd!2024",
    "remove_setup_account": true
  },
  "software": [
    {
      "id": 1,
      "winget_id": "Google.Chrome",
      "name": "Google Chrome",
      "type": "winget",
      "download_url": null
    },
    {
      "id": 5,
      "winget_id": "7zip.7zip",
      "name": "7-Zip",
      "type": "winget",
      "download_url": null
    },
    {
      "id": 12,
      "winget_id": "",
      "name": "Gestionale Aziendale v3",
      "type": "exe",
      "download_url": "https://windeploy.local.api/storage/software/gestionale-v3.exe"
    }
  ],
  "bloatware": [
    {
      "package_name": "Microsoft.XboxApp",
      "display_name": "Xbox",
      "selected": true
    },
    {
      "package_name": "Microsoft.OneDrive",
      "display_name": "OneDrive",
      "selected": true
    },
    {
      "package_name": "king.com.CandyCrushSaga",
      "display_name": "Candy Crush Saga",
      "selected": true
    },
    {
      "package_name": "Microsoft.BingNews",
      "display_name": "Microsoft News",
      "selected": false
    }
  ],
  "power_plan": {
    "type": "custom",
    "screen_timeout_ac": 15,
    "sleep_timeout_ac": 30,
    "cpu_min_percent": 5,
    "cpu_max_percent": 100
  },
  "extras": {
    "timezone": "Europe/Rome",
    "language": "it-IT",
    "keyboard_layout": "it-IT",
    "wallpaper_url": "/storage/wallpapers/azienda.jpg",
    "wifi": {
      "ssid": "Rete-Aziendale",
      "password": "WiFiS3cret!"
    },
    "windows_update": "download_only"
  }
}
```


***

## 7. Fix dei Mismatch — Diff Esatto per Modulo

### FIX 1 — Frontend: `buildApiPayload()` in `WizardBuilderPage.jsx`

**Modulo da modificare**: Frontend
**Convenzione scelta**: `snake_case` (coerente con PHP e Python, evita doppia conversione)

```diff
// In src/pages/Wizards/WizardBuilderPage.jsx — funzione buildApiPayload()

- function buildApiPayload() {
-   const configurazione = {
-     nome_pc: wizard.pcName.formattedPreview,
-     utente_admin: {
-       username: wizard.localAdmin.username,
-       password: wizard.localAdmin.password,
-     },
-     software_installa: wizard.software.selectedIds,
-     bloatware_default: wizard.bloatware.preselected,
-     power_plan: wizard.powerPlan.manual
-       ? {
-           tipo: "custom",
-           params: {
-             monitor_timeout_ac: wizard.powerPlan.screenTimeoutMin,
-             sleep_timeout_ac: wizard.powerPlan.sleepNever ? 0 : wizard.powerPlan.sleepTimeoutMin,
-             cpu_min_percent: wizard.powerPlan.cpuMinPercent,
-             cpu_max_percent: wizard.powerPlan.cpuMaxPercent,
-           },
-         }
-       : { tipo: "preset", params: { preset: wizard.powerPlan.preset } },
-     extras: {
-       timezone: wizard.extras.timezone,
-       language: wizard.extras.language,
-       keyboard_layout: wizard.extras.keyboardLayout,
-       windows_update: { policy: wizard.extras.windowsUpdatePolicy },
-     },
-     remove_microsoft_account: wizard.localAdmin.removeMicrosoftSetupAccount,
-     wifi: wizard.extras.wifiEnabled
-       ? { ssid: wizard.extras.wifiSsid, password: wizard.extras.wifiPassword }
-       : null,
-   };

+ function buildApiPayload() {
+   // Software: il frontend ora invia oggetti completi (non solo ID).
+   // I dati del software selezionato devono essere cachati nello stato dal Step4.
+   // Vedi FIX 4 per l'aggiornamento dello stato software.
+   const configurazione = {
+     version: "1.0",
+     pc_name: wizard.pcName.formattedPreview,
+     admin_user: {
+       username: wizard.localAdmin.username,
+       password: wizard.localAdmin.password,
+       remove_setup_account: wizard.localAdmin.removeMicrosoftSetupAccount,
+     },
+     software: wizard.software.selectedItems,   // array di oggetti SoftwareItem, non solo ID
+     bloatware: wizard.bloatware.preselected.map((pkg) => ({
+       package_name: pkg,
+       display_name: pkg.split(".").pop() ?? pkg,
+       selected: true,
+     })),
+     power_plan: wizard.powerPlan.manual
+       ? {
+           type: "custom",
+           screen_timeout_ac: wizard.powerPlan.screenTimeoutMin,
+           sleep_timeout_ac: wizard.powerPlan.sleepNever ? null : wizard.powerPlan.sleepTimeoutMin,
+           cpu_min_percent: wizard.powerPlan.cpuMinPercent,
+           cpu_max_percent: wizard.powerPlan.cpuMaxPercent,
+         }
+       : {
+           type: wizard.powerPlan.preset,       // "balanced" | "high_performance" | "power_saver"
+           screen_timeout_ac: null,
+           sleep_timeout_ac: null,
+           cpu_min_percent: 5,
+           cpu_max_percent: 100,
+         },
+     extras: {
+       timezone: wizard.extras.timezone,
+       language: wizard.extras.language,
+       keyboard_layout: wizard.extras.keyboardLayout,
+       wallpaper_url: null,                     // URL assegnato dal backend dopo upload
+       wifi: wizard.extras.wifiEnabled
+         ? { ssid: wizard.extras.wifiSsid, password: wizard.extras.wifiPassword }
+         : null,
+       windows_update: wizard.extras.windowsUpdatePolicy,  // stringa diretta, non oggetto
+     },
+   };
```


### FIX 2 — Frontend: stato `software` in Step4 deve salvare oggetti completi

```diff
// In src/pages/Wizards/WizardBuilderPage.jsx — buildEmptyWizard()

  software: {
-   selectedIds: [],
+   selectedIds: [],          // mantenuto per compatibilità UI (checkbox)
+   selectedItems: [],        // aggiunto: array di SoftwareItem completi per il payload
  },

// In src/pages/Wizards/steps/Step4Software.jsx — funzione toggle()

- function toggle(id) {
-   const current = Array.isArray(wizard.software.selectedIds) ? wizard.software.selectedIds : [];
-   const has = current.includes(id);
-   const next = has ? current.filter((x) => x !== id) : [...current, id];
-   dispatch({ type: "PATCH_PATH", payload: { path: ["software", "selectedIds"], value: next } });
- }

+ function toggle(id, softwareItem) {
+   const currentIds = Array.isArray(wizard.software.selectedIds) ? wizard.software.selectedIds : [];
+   const currentItems = Array.isArray(wizard.software.selectedItems) ? wizard.software.selectedItems : [];
+   const has = currentIds.includes(id);
+   const nextIds = has ? currentIds.filter((x) => x !== id) : [...currentIds, id];
+   const nextItems = has
+     ? currentItems.filter((x) => x.id !== id)
+     : [...currentItems, {
+         id: softwareItem.id,
+         winget_id: softwareItem.winget_id ?? softwareItem.wingetId ?? "",
+         name: softwareItem.name ?? softwareItem.nome ?? "",
+         type: softwareItem.type ?? softwareItem.tipo ?? "winget",
+         download_url: softwareItem.download_url ?? null,
+       }];
+   dispatch({ type: "PATCH_PATH", payload: { path: ["software", "selectedIds"], value: nextIds } });
+   dispatch({ type: "PATCH_PATH", payload: { path: ["software", "selectedItems"], value: nextItems } });
+ }
```


### FIX 3 — Agent: `screen_progress.py` — lettura campi con nuove chiavi

```diff
# In agent/gui/screens/screen_progress.py — metodo execution_thread()

# ── Rinomina PC ──
- pc_name = wizard_config.get("pc_name")   # già corretto se FIX 1 applicato
+ pc_name = wizard_config.get("pc_name")

# ── Utente Admin ──
- admin_user = wizard_config.get("admin_user", {})   # già corretto se FIX 1 applicato
- username = admin_user.get("username")
- password = admin_user.get("password")
+ admin_user = wizard_config.get("admin_user", {})
+ username = admin_user.get("username")
+ password = admin_user.get("password")      # in chiaro da /api/agent/auth
+ remove_setup = admin_user.get("remove_setup_account", False)

# ── Software ──
- software_list = wizard_config.get("software", [])
- for sw in software_list:
-     sw_name = sw.get("name", "App")
-     if sw.get("type") == "winget":
-         success = installer.install_winget(sw.get("identifier"))   # ← ERA SBAGLIATO
-     else:
-         success = installer.download_and_install(sw.get("url"), sw.get("filename", "setup.exe"))

+ software_list = wizard_config.get("software", [])
+ for sw in software_list:
+     sw_name = sw.get("name", "App")
+     if sw.get("type") == "winget":
+         success = installer.install_winget(sw["winget_id"])        # ← CORRETTO
+     else:
+         download_url = sw.get("download_url")
+         if download_url:
+             success = installer.download_and_install(download_url, f"{sw_name}.exe")
+         else:
+             logging.error(f"SCHEMA ERROR: download_url mancante per {sw_name}")
+             success = False
```


### FIX 4 — Agent: `system_config.py` — `apply_power_plan()` e `apply_extras()`

```diff
# In agent/system_config.py

+ # GUID Windows per i preset di power plan
+ POWER_PLAN_GUIDS = {
+     "balanced":         "381b4222-f694-41f0-9685-ff5bb260df2e",
+     "high_performance": "8c5e7fda-e8bf-4a96-9a85-a6e23a8c635c",
+     "power_saver":      "a1841308-3541-4fab-bc81-f71556f20b4a",
+ }
+
+ # GUID sottocategoria per CPU throttling (schema SCHEME_CURRENT)
+ CPU_THROTTLE_MIN_GUID = "893dee8e-2bef-41e0-89c6-b55d0929964c"  # PROCTHROTTLEMIN
+ CPU_THROTTLE_MAX_GUID = "bc5038f7-23e0-4960-96da-33abaf5935ec"  # PROCTHROTTLEMAX
+ SUBGROUP_PROCESSOR   = "54533251-82be-4824-96c1-47b60b740d00"

  def apply_power_plan(self, plan_config: dict) -> bool:
      try:
-         plan_type = plan_config.get("type", "preset")
-         if plan_type == "preset" and plan_config.get("guid"):
-             subprocess.run(["powercfg", "/setactive", plan_config["guid"]], ...)
-         elif plan_type == "custom":
-             for setting, value in plan_config.get("settings", {}).items():
-                 subprocess.run(["powercfg", "/change", setting, str(value)], ...)

+         plan_type = plan_config.get("type", "balanced")
+
+         if plan_type in POWER_PLAN_GUIDS:
+             guid = POWER_PLAN_GUIDS[plan_type]
+             subprocess.run(["powercfg", "/setactive", guid],
+                            check=True, creationflags=subprocess.CREATE_NO_WINDOW)
+
+         elif plan_type == "custom":
+             # Prima attiva balanced come base
+             subprocess.run(["powercfg", "/setactive", POWER_PLAN_GUIDS["balanced"]],
+                            check=True, creationflags=subprocess.CREATE_NO_WINDOW)
+
+             screen = plan_config.get("screen_timeout_ac")
+             if screen is not None:
+                 subprocess.run(["powercfg", "/change", "monitor-timeout-ac", str(screen)],
+                                check=True, creationflags=subprocess.CREATE_NO_WINDOW)
+
+             sleep = plan_config.get("sleep_timeout_ac")
+             subprocess.run(
+                 ["powercfg", "/change", "standby-timeout-ac", str(sleep) if sleep else "0"],
+                 check=True, creationflags=subprocess.CREATE_NO_WINDOW)
+
+             cpu_min = plan_config.get("cpu_min_percent", 5)
+             cpu_max = plan_config.get("cpu_max_percent", 100)
+             # Usa setacvalueindex per CPU throttling (l'unico metodo corretto)
+             subprocess.run(
+                 ["powercfg", "/setacvalueindex", "SCHEME_CURRENT",
+                  SUBGROUP_PROCESSOR, CPU_THROTTLE_MIN_GUID, str(cpu_min)],
+                 check=True, creationflags=subprocess.CREATE_NO_WINDOW)
+             subprocess.run(
+                 ["powercfg", "/setacvalueindex", "SCHEME_CURRENT",
+                  SUBGROUP_PROCESSOR, CPU_THROTTLE_MAX_GUID, str(cpu_max)],
+                 check=True, creationflags=subprocess.CREATE_NO_WINDOW)
+             subprocess.run(["powercfg", "/setactive", "SCHEME_CURRENT"],
+                            check=True, creationflags=subprocess.CREATE_NO_WINDOW)

          return True
      except Exception:
          return False


  def apply_extras(self, extras: dict) -> bool:
      try:
          if "timezone" in extras:
              subprocess.run(["tzutil", "/s", extras["timezone"]], ...)

          if "language" in extras:
              self._run_ps(f"Set-WinUserLanguageList -LanguageList '{extras['language']}' -Force")

-         if "wallpaper" in extras:                        # ← ERA SBAGLIATO
-             path = extras["wallpaper"]
+         if extras.get("wallpaper_url"):                  # ← CORRETTO (chiave e logica)
+             # L'agent scarica il wallpaper dall'URL del server prima di applicarlo
+             import urllib.request, tempfile, os
+             wallpaper_url = extras["wallpaper_url"]
+             # Costruisce URL assoluto se relativo
+             if wallpaper_url.startswith("/"):
+                 from agent.config import API_URL
+                 base = API_URL.replace("/api", "")
+                 wallpaper_url = base + wallpaper_url
+             tmp = tempfile.NamedTemporaryFile(suffix=".jpg", delete=False)
+             urllib.request.urlretrieve(wallpaper_url, tmp.name)
+             path = tmp.name
              subprocess.run(["reg", "add", r"HKCU\Control Panel\Desktop",
                              "/v", "Wallpaper", "/t", "REG_SZ", "/d", path, "/f"], ...)
              subprocess.run(["RUNDLL32.EXE", "user32.dll,UpdatePerUserSystemParameters",
                              "1", "True"], ...)

-         if "windows_update" in extras:
-             policy = extras["windows_update"]
-             au_options = {"manual": "2", "download_only": "3", "automatic": "4"}
+         if "windows_update" in extras:
+             policy = extras["windows_update"]
+             # Mappa aggiornata con chiavi coerenti allo schema (auto, non automatic)
+             au_options = {"manual": "2", "download_only": "3", "auto": "4"}
              opt = au_options.get(policy, "3")
              ...

+         # ── Configurazione WiFi (era completamente mancante) ──────────────
+         if extras.get("wifi"):
+             wifi = extras["wifi"]
+             ssid = wifi.get("ssid", "")
+             password = wifi.get("password", "")   # in chiaro da /api/agent/auth
+             if ssid and password:
+                 ps_script = f"""
+                 $password = '{password}'
+                 $securePassword = ConvertTo-SecureString -String $password -AsPlainText -Force
+                 $profile = New-WifiProfile -Ssid '{ssid}' -Password $securePassword -Authentication WPA2PSK
+                 Add-WifiProfile -Profile $profile
+                 Connect-WifiProfile -Ssid '{ssid}'
+                 """
+                 self._run_ps(ps_script)

          return True
      except Exception:
          return False
```


### FIX 5 — Agent: `api_client.py` — `applyTemplateById` nel frontend

```diff
# In src/pages/Wizards/WizardBuilderPage.jsx — applyTemplateById()
# Il template salvato nel DB usa le vecchie chiavi. Aggiornare il mapping.

- if (cfg.nome_pc) next.pcName.raw = String(cfg.nome_pc);
- if (cfg.utente_admin?.username) next.localAdmin.username = String(cfg.utente_admin.username);
- if (Array.isArray(cfg.software_installa)) { ... }
- if (Array.isArray(cfg.bloatware_default)) { ... }
- if (cfg.power_plan?.tipo === "preset") { ... }
- if (cfg.power_plan?.tipo === "custom") { ... }

+ if (cfg.pc_name) next.pcName.raw = String(cfg.pc_name);
+ if (cfg.admin_user?.username) next.localAdmin.username = String(cfg.admin_user.username);
+ if (cfg.admin_user?.remove_setup_account !== undefined)
+   next.localAdmin.removeMicrosoftSetupAccount = Boolean(cfg.admin_user.remove_setup_account);
+ if (Array.isArray(cfg.software)) {
+   next.software.selectedIds = cfg.software.map((sw) => sw.id).filter(Boolean);
+   next.software.selectedItems = cfg.software;
+ }
+ if (Array.isArray(cfg.bloatware)) {
+   next.bloatware.preselected = cfg.bloatware.map((b) => b.package_name);
+ }
+ if (cfg.power_plan?.type === "balanced" || cfg.power_plan?.type === "high_performance" || cfg.power_plan?.type === "power_saver") {
+   next.powerPlan.preset = cfg.power_plan.type;
+   next.powerPlan.manual = false;
+ }
+ if (cfg.power_plan?.type === "custom") {
+   next.powerPlan.manual = true;
+   if (typeof cfg.power_plan.screen_timeout_ac === "number")
+     next.powerPlan.screenTimeoutMin = cfg.power_plan.screen_timeout_ac;
+   if (typeof cfg.power_plan.sleep_timeout_ac === "number")
+     next.powerPlan.sleepTimeoutMin = cfg.power_plan.sleep_timeout_ac;
+   if (typeof cfg.power_plan.cpu_min_percent === "number")
+     next.powerPlan.cpuMinPercent = cfg.power_plan.cpu_min_percent;
+   if (typeof cfg.power_plan.cpu_max_percent === "number")
+     next.powerPlan.cpuMaxPercent = cfg.power_plan.cpu_max_percent;
+ }
+ if (cfg.extras?.windows_update)
+   next.extras.windowsUpdatePolicy = cfg.extras.windows_update;
```


***

## 8. Comandi Git

```bash
# ── COMMIT 1: contratto condiviso (docs + types) ─────────────────────────────
git add \
  docs/schemas/wizard-config.schema.json \
  docs/schemas/wizard-config-example.json \
  frontend/src/types/WizardConfig.ts \
  agent/SCHEMA.md

git commit -m "docs(schema): add shared WizardConfig contract v1.0

- docs/schemas/wizard-config.schema.json: JSON Schema Draft-07 completo
  con tutti i campi, pattern, enum e descrizioni in italiano
- docs/schemas/wizard-config-example.json: payload di esempio valido
  per test unitari PHP, JS e Python
- frontend/src/types/WizardConfig.ts: TypeScript interface + type guards
  + WIZARD_CONFIG_DEFAULT per inizializzazione stato wizard builder
- agent/SCHEMA.md: riferimento Python con tabella campi, GUID power plan,
  accesso ai dict e pattern di gestione campi mancanti

BREAKING CHANGE: le chiavi JSON passano da nome_pc/utente_admin/software_installa
a pc_name/admin_user/software (snake_case uniforme).
Tutti i moduli devono aggiornare i riferimenti secondo il diff nel commit successivo."


# ── COMMIT 2: fix nei moduli (frontend + backend + agent) ────────────────────
git add \
  frontend/src/pages/Wizards/WizardBuilderPage.jsx \
  frontend/src/pages/Wizards/steps/Step4Software.jsx \
  frontend/src/utils/validateWizardConfig.ts \
  backend/app/Http/Requests/Wizard/WizardStoreRequest.php \
  agent/system_config.py \
  agent/gui/screens/screen_progress.py

git commit -m "fix(wizard): align JSON keys across frontend/backend/agent (closes #schema-mismatch)

Frontend (WizardBuilderPage.jsx):
- buildApiPayload(): rinomina nome_pc→pc_name, utente_admin→admin_user,
  software_installa→software, bloatware_default→bloatware
- power_plan: tipo→type, struttura flat anziché params annidati
- extras.windows_update: da {policy: string} a stringa diretta
- remove_microsoft_account spostato dentro admin_user.remove_setup_account
- applyTemplateById(): aggiornato mapping chiavi per template caricati da DB
- software.selectedItems aggiunto per trasportare oggetti completi al payload

Step4Software.jsx:
- toggle(): ora salva sia selectedIds (per UI) che selectedItems (per payload)
- ogni SoftwareItem include winget_id, name, type, download_url

validateWizardConfig.ts:
- nuova utility di validazione speculare a WizardStoreRequest.php

Backend (WizardStoreRequest.php):
- aggiornate rules() con nuove chiavi snake_case
- software_installa→software con validazione oggetti completi
- bloatware_default→bloatware con validazione struttura {package_name, display_name, selected}
- power_plan.tipo→power_plan.type
- aggiunto prepareForValidation() per configurazione JSON da multipart
- aggiunto withValidator() per validazione cpu_min > cpu_max

Agent (system_config.py):
- apply_power_plan(): tipo→type, GUID espliciti, custom usa setacvalueindex
- apply_extras(): wallpaper→wallpaper_url con download da URL, automatic→auto,
  aggiunta sezione wifi mancante

Agent (screen_progress.py):
- lettura da pc_name, admin_user, software[n].winget_id (non identifier)
- gestione remove_setup_account da admin_user"
```


***

## ⚠️ Note Finali di Sicurezza e Scalabilità

**Rischio residuo critico — localStorage con password**: il wizard builder salva il draft completo in `localStorage` incluse password admin e WiFi in chiaro. Con lo schema v1.0, questo comportamento è invariato. Soluzione consigliata: escludere `admin_user.password` e `extras.wifi.password` dalla serializzazione del draft, sostituendoli con un flag `password_set: true` che indica all'utente di reinserirla se il draft viene ripreso.[^1_1]

**Validazione `cpu_min_percent` vs `cpu_max_percent`**: la regola cross-field (`cpu_min > cpu_max`) è stata implementata in `withValidator()` nel backend e in `validatePowerPlan()` nel frontend. Assicurarsi che entrambi i lati siano aggiornati in modo sincrono ad ogni futura modifica dei range.[^1_1]

**Template DB con vecchie chiavi**: i template già salvati nel database usano le chiavi precedenti (`nome_pc`, `utente_admin`, ecc.). Il FIX 5 aggiorna `applyTemplateById()` per le nuove chiavi, ma i template esistenti continueranno a usare le vecchie. Valutare una **migrazione dei dati** o un **fallback duale** (leggi prima `pc_name`, poi `nome_pc` come fallback) nella funzione `applyTemplateById()` durante il periodo di transizione.
<span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_19][^1_2][^1_20][^1_3][^1_4][^1_5][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: 0016-wizards.md

[^1_2]: 0105-schema DB.md

[^1_3]: 0104-frontend React e tooling Vite.md

[^1_4]: 0102-generazione bulk di codice CRUD.md

[^1_5]: 0103-configurazione auth e sicurezza.md

[^1_6]: 0101-auth e sicurezza.md

[^1_7]: 0100-File temporanei tmp_.php esposti nella root.md

[^1_8]: 0021-Installer-configurazione-sistema.md

[^1_9]: 0020-Scansione app installate.md

[^1_10]: 0019-Struttura agent.md

[^1_11]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^1_12]: 0017-motorpage.md

[^1_13]: 0015-dashboard.md

[^1_14]: 0014-Login.md

[^1_15]: 0013-SetupprogettoReact.md

[^1_16]: 0012-apiendpointwindows.md

[^1_17]: 0011-controllercrud.md

[^1_18]: 0010-autenticazione.md

[^1_19]: 0009-scaffoldprogetto.md

[^1_20]: 0008-db.md

