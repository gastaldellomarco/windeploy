Ecco i file necessari per gli endpoint dedicati all'agent Windows, con autenticazione JWT, aggiornamento dei log, salvataggio report e broadcast event.

---

## 📁 Controllers

### `app/Http/Controllers/Api/Agent/AgentController.php`

```php
<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\AgentAuthRequest;
use App\Http\Requests\Agent\AgentStartRequest;
use App\Http\Requests\Agent\AgentStepRequest;
use App\Http\Requests\Agent\AgentCompleteRequest;
use App\Http\Requests\Agent\AgentAbortRequest;
use App\Models\Wizard;
use App\Models\ExecutionLog;
use App\Models\Report;
use App\Events\ExecutionLogUpdated;
use Illuminate\Support\Facades\Crypt;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;
use Carbon\Carbon;

class AgentController extends Controller
{
    /**
     * 1. Autenticazione iniziale con codice wizard e MAC address.
     */
    public function auth(AgentAuthRequest $request)
    {
        $wizardCode = strtoupper($request->input('codice_wizard'));
        $macAddress = strtolower($request->input('mac_address'));

        $wizard = Wizard::where('codice_univoco', $wizardCode)->first();

        if (!$wizard) {
            return response()->json(['message' => 'Codice wizard non valido.'], 404);
        }

        // Verifica scadenza
        if ($wizard->expires_at && $wizard->expires_at->isPast()) {
            return response()->json(['message' => 'Codice wizard scaduto.'], 410);
        }

        // Verifica già utilizzato
        if ($wizard->used_at !== null || $wizard->stato === 'completato') {
            return response()->json(['message' => 'Codice wizard già utilizzato.'], 410);
        }

        // Verifica stato valido
        if ($wizard->stato !== 'pronto') {
            return response()->json(['message' => 'Wizard non pronto per l\'esecuzione.'], 422);
        }

        $now = Carbon::now();
        $expiry = $now->copy()->addHours(4);

        // Prepara payload JWT con wizard_id e mac_address
        $payload = JWTFactory::customClaims([
            'sub'         => $wizard->id,
            'wizard_id'   => $wizard->id,
            'mac_address' => $macAddress,
            'type'        => 'agent',
            'iat'         => $now->timestamp,
            'exp'         => $expiry->timestamp,
        ])->make();

        $token = JWTAuth::encode($payload)->get();

        // Restituisci token e configurazione completa del wizard
        // Attenzione: la password utente admin è cifrata; la decifriamo per l'agent
        $config = $wizard->configurazione;
        if (isset($config['utente_admin']['password_encrypted'])) {
            $config['utente_admin']['password'] = Crypt::decryptString($config['utente_admin']['password_encrypted']);
            unset($config['utente_admin']['password_encrypted']);
        }
        // Stessa cosa per eventuale password Wi-Fi
        if (isset($config['extras']['wifi']['password_encrypted'])) {
            $config['extras']['wifi']['password'] = Crypt::decryptString($config['extras']['wifi']['password_encrypted']);
            unset($config['extras']['wifi']['password_encrypted']);
        }

        return response()->json([
            'token'          => $token,
            'expires_in'     => 4 * 3600,
            'wizard_config'  => $config,
        ]);
    }

    /**
     * 2. Avvio esecuzione: crea record execution_log.
     */
    public function start(AgentStartRequest $request)
    {
        $payload = JWTAuth::parseToken()->getPayload();
        $wizardId = $payload->get('wizard_id');
        $macAddress = $payload->get('mac_address');

        $wizard = Wizard::findOrFail($wizardId);

        // Verifica che il wizard non sia già in esecuzione
        $existing = ExecutionLog::where('wizard_id', $wizardId)
            ->whereIn('stato', ['avviato', 'in_corso'])
            ->first();
        if ($existing) {
            return response()->json(['message' => 'Wizard già in esecuzione.'], 409);
        }

        $data = $request->validated();

        $executionLog = ExecutionLog::create([
            'wizard_id'          => $wizardId,
            'tecnico_user_id'    => $wizard->user_id,
            'pc_nome_originale'  => $data['pc_info']['nome_originale'],
            'hardware_info'      => [
                'cpu'             => $data['pc_info']['cpu'] ?? null,
                'ram_gb'          => $data['pc_info']['ram'] ?? null,
                'disco_gb'        => $data['pc_info']['disco'] ?? null,
                'windows_version' => $data['pc_info']['windows_version'] ?? null,
            ],
            'stato'              => 'avviato',
            'log_dettagliato'    => [],
            'started_at'         => now(),
        ]);

        // Aggiorna stato wizard
        $wizard->stato = 'in_esecuzione';
        $wizard->used_at = now();
        $wizard->save();

        return response()->json([
            'execution_log_id' => $executionLog->id,
            'ok'               => true,
        ]);
    }

    /**
     * 3. Aggiornamento step.
     */
    public function step(AgentStepRequest $request)
    {
        $payload = JWTAuth::parseToken()->getPayload();
        $wizardId = $payload->get('wizard_id');

        $data = $request->validated();

        $executionLog = ExecutionLog::where('id', $data['execution_log_id'])
            ->where('wizard_id', $wizardId)
            ->firstOrFail();

        // Verifica che il log sia ancora aperto
        if (!in_array($executionLog->stato, ['avviato', 'in_corso'])) {
            return response()->json(['message' => 'Esecuzione già completata o abortita.'], 422);
        }

        // Prepara step con timestamp
        $step = $data['step'];
        $step['timestamp'] = now()->toIso8601String();

        // Appendi al log dettagliato
        $log = $executionLog->log_dettagliato ?? [];
        $log[] = $step;
        $executionLog->log_dettagliato = $log;

        // Aggiorna step corrente se presente
        if (isset($step['nome'])) {
            $executionLog->step_corrente = $step['nome'];
        }

        // Se lo step è di tipo "errore" possiamo cambiare stato? No, aspettiamo complete/abort.
        $executionLog->stato = 'in_corso'; // se era avviato passa in corso
        $executionLog->save();

        // Broadcast evento per monitor realtime
        broadcast(new ExecutionLogUpdated($executionLog))->toOthers();

        return response()->json(['ok' => true]);
    }

    /**
     * 4. Completamento esecuzione.
     */
    public function complete(AgentCompleteRequest $request)
    {
        $payload = JWTAuth::parseToken()->getPayload();
        $wizardId = $payload->get('wizard_id');

        $data = $request->validated();

        $executionLog = ExecutionLog::where('id', $data['execution_log_id'])
            ->where('wizard_id', $wizardId)
            ->firstOrFail();

        if ($executionLog->stato === 'completato') {
            return response()->json(['message' => 'Esecuzione già completata.'], 422);
        }

        // Aggiorna log con eventuale step finale? Già inviato via step.
        $executionLog->pc_nome_nuovo = $data['pc_nome_nuovo'];
        $executionLog->stato = 'completato';
        $executionLog->completed_at = now();

        // Aggiungi eventuale sommario al log dettagliato (opzionale)
        $log = $executionLog->log_dettagliato ?? [];
        $log[] = [
            'step'      => 'sommario_finale',
            'timestamp' => now()->toIso8601String(),
            'esito'     => 'ok',
            'dettaglio' => $data['sommario'] ?? null,
        ];
        $executionLog->log_dettagliato = $log;
        $executionLog->save();

        // Aggiorna stato wizard
        $wizard = Wizard::find($wizardId);
        $wizard->stato = 'completato';
        $wizard->save();

        // Salva report HTML
        $report = Report::create([
            'execution_log_id' => $executionLog->id,
            'html_content'     => $data['report_html'],
        ]);

        // Broadcast evento finale
        broadcast(new ExecutionLogUpdated($executionLog))->toOthers();

        return response()->json([
            'ok'         => true,
            'report_url' => route('api.reports.show', $report->id), // assicurati di avere la route
        ]);
    }

    /**
     * 5. Abort esecuzione per errore grave.
     */
    public function abort(AgentAbortRequest $request)
    {
        $payload = JWTAuth::parseToken()->getPayload();
        $wizardId = $payload->get('wizard_id');

        $data = $request->validated();

        $executionLog = ExecutionLog::where('id', $data['execution_log_id'])
            ->where('wizard_id', $wizardId)
            ->firstOrFail();

        if (in_array($executionLog->stato, ['completato', 'abortito'])) {
            return response()->json(['message' => 'Esecuzione già terminata.'], 422);
        }

        $executionLog->stato = 'abortito';
        $executionLog->completed_at = now();

        $log = $executionLog->log_dettagliato ?? [];
        $log[] = [
            'step'      => 'abort',
            'timestamp' => now()->toIso8601String(),
            'esito'     => 'errore',
            'dettaglio' => $data['motivo'] ?? 'Errore grave non specificato',
        ];
        $executionLog->log_dettagliato = $log;
        $executionLog->save();

        // Aggiorna wizard
        $wizard = Wizard::find($wizardId);
        $wizard->stato = 'errore';
        $wizard->save();

        broadcast(new ExecutionLogUpdated($executionLog))->toOthers();

        return response()->json(['ok' => true]);
    }
}
```

---

## 📁 Eventi per realtime

### `app/Events/ExecutionLogUpdated.php`

```php
<?php

namespace App\Events;

use App\Models\ExecutionLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ExecutionLogUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public $executionLog;

    public function __construct(ExecutionLog $executionLog)
    {
        $this->executionLog = $executionLog;
    }

    public function broadcastOn()
    {
        // Canale privato per il tecnico che ha avviato il wizard
        // Oppure pubblico con identificativo del wizard
        return new Channel('wizard.' . $this->executionLog->wizard_id);
    }

    public function broadcastAs()
    {
        return 'execution.updated';
    }

    public function broadcastWith()
    {
        return [
            'execution_log_id' => $this->executionLog->id,
            'wizard_id'        => $this->executionLog->wizard_id,
            'stato'            => $this->executionLog->stato,
            'step_corrente'    => $this->executionLog->step_corrente,
            'log_dettagliato'  => $this->executionLog->log_dettagliato,
            'updated_at'       => $this->executionLog->updated_at,
        ];
    }
}
```

---

## 📁 Form Request

### `app/Http/Requests/Agent/AgentAuthRequest.php`

```php
<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentAuthRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'codice_wizard' => 'required|string|max:10',
            'mac_address'   => [
                'required',
                'string',
                'max:17',
                'regex:/^([0-9A-Fa-f]{2}([:-])){5}([0-9A-Fa-f]{2})$/',
            ],
        ];
    }

    public function messages()
    {
        return [
            'codice_wizard.required' => 'Il codice wizard è obbligatorio.',
            'mac_address.required'   => 'L\'indirizzo MAC è obbligatorio.',
            'mac_address.regex'      => 'Formato MAC non valido (es. 00:11:22:33:44:55).',
        ];
    }
}
```

### `app/Http/Requests/Agent/AgentStartRequest.php`

```php
<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentStartRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'pc_info'                     => 'required|array',
            'pc_info.nome_originale'      => 'required|string|max:100',
            'pc_info.cpu'                  => 'nullable|string|max:255',
            'pc_info.ram'                  => 'nullable|integer|min:1',
            'pc_info.disco'                 => 'nullable|integer|min:1',
            'pc_info.windows_version'       => 'nullable|string|max:50',
        ];
    }
}
```

### `app/Http/Requests/Agent/AgentStepRequest.php`

```php
<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentStepRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'execution_log_id'   => 'required|integer|exists:execution_logs,id',
            'step'               => 'required|array',
            'step.nome'          => 'required|string|max:100',
            'step.stato'         => 'required|in:completato,errore,avviso',
            'step.messaggio'     => 'nullable|string|max:1000',
        ];
    }
}
```

### `app/Http/Requests/Agent/AgentCompleteRequest.php`

```php
<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentCompleteRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'execution_log_id'   => 'required|integer|exists:execution_logs,id',
            'report_html'        => 'required|string',
            'pc_nome_nuovo'      => 'required|string|max:100',
            'sommario'           => 'sometimes|array',
            'sommario.installati' => 'sometimes|array',
            'sommario.rimossi'    => 'sometimes|array',
            'sommario.errori'     => 'sometimes|array',
        ];
    }
}
```

### `app/Http/Requests/Agent/AgentAbortRequest.php`

```php
<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentAbortRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'execution_log_id' => 'required|integer|exists:execution_logs,id',
            'motivo'           => 'required|string|max:1000',
        ];
    }
}
```

---

## 📁 Configurazione rate limiting

In `app/Providers/AppServiceProvider.php` aggiungi un rate limiter specifico per gli endpoint agent.

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

public function boot()
{
    RateLimiter::for('agent', function (Request $request) {
        // Identifica il chiamante tramite il token JWT (o wizard_id se presente)
        $key = optional(JWTAuth::parseToken()->getPayload())->get('wizard_id') ?: $request->ip();
        return Limit::perMinute(120)->by($key);
    });
}
```

Poi applica il middleware `throttle:agent` alle route dell'agent.

---

## 📁 Routes

In `routes/api.php` aggiungi le route per l'agent, protette dal middleware `throttle:agent` e con autenticazione JWT (tranne auth).

```php
use App\Http\Controllers\Api\Agent\AgentController;

Route::prefix('agent')->group(function () {
    Route::post('auth', [AgentController::class, 'auth'])
        ->middleware('throttle:login') // 5 tentativi
        ->name('agent.auth');

    Route::middleware(['auth:api', 'throttle:agent'])->group(function () {
        Route::post('start', [AgentController::class, 'start'])->name('agent.start');
        Route::post('step', [AgentController::class, 'step'])->name('agent.step');
        Route::post('complete', [AgentController::class, 'complete'])->name('agent.complete');
        Route::post('abort', [AgentController::class, 'abort'])->name('agent.abort');
    });
});
```

Nota: `auth:api` deve utilizzare il guard JWT. Assicurati che in `config/auth.php` il guard `api` sia configurato per JWT (es. `driver` => `jwt`).

---

## 📁 Broadcast Channels (opzionale per Echo)

Se usi broadcasting con Pusher o Redis, configura il canale in `routes/channels.php`:

```php
use App\Models\ExecutionLog;

Broadcast::channel('wizard.{wizardId}', function ($user, $wizardId) {
    // Autorizza l'utente a ricevere aggiornamenti per quel wizard
    // Puoi controllare se l'utente è admin o tecnico proprietario
    return true; // implementa logica appropriata
});
```

---

## 📁 Modello ExecutionLog (da aggiornare)

Assicurati che il modello `ExecutionLog` abbia il casting per `log_dettagliato` e `hardware_info`:

```php
protected $casts = [
    'hardware_info'   => 'array',
    'log_dettagliato' => 'array',
    'started_at'      => 'datetime',
    'completed_at'    => 'datetime',
];
```

---

## 📁 Riepilogo file creati

```
app/Http/Controllers/Api/Agent/AgentController.php
app/Events/ExecutionLogUpdated.php
app/Http/Requests/Agent/AgentAuthRequest.php
app/Http/Requests/Agent/AgentStartRequest.php
app/Http/Requests/Agent/AgentStepRequest.php
app/Http/Requests/Agent/AgentCompleteRequest.php
app/Http/Requests/Agent/AgentAbortRequest.php
```

Crea le cartelle mancanti (`Agent` sotto `Controllers/Api` e sotto `Requests`).

Ora hai tutti gli endpoint per l'agent Windows con autenticazione JWT, log dettagliato, report e realtime updates.