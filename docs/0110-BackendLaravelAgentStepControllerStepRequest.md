## 1. Audit preliminare – stato attuale

| Elemento                          | Trovato                                                                                              | Note                                                                                                 |
| :-------------------------------- | :--------------------------------------------------------------------------------------------------- | :--------------------------------------------------------------------------------------------------- |
| **AgentStepController**           | ❌ No – esiste `AgentController` con metodo `step()`                                                 | Il metodo attuale usa `execution_log_id` e struttura `step` diversa.                                |
| **StepRequest** (nuovo)           | ❌ Non esiste – esiste `AgentStepRequest` con campi `execution_log_id`, `step` (array)               | La nuova request deve chiamarsi `StepRequest` e avere campi `wizard_code`, `step` (string), ecc.    |
| **Route POST /agent/step**        | ✅ Esiste, ma punta a `AgentController@step`                                                         | Dovrà essere modificata per usare il nuovo controller.                                               |
| **Colonne DB – `execution_logs`** | • `log_dettagliato` (json) ✅<br>• `stato` (enum) ✅<br>• `step_corrente` ❌ (manca nella migration)  | Se il campo `step_corrente` non esiste, la logica di aggiornamento fallirà – serve una migration.   |
| **Cast modello `ExecutionLog`**   | `'log_dettagliato' => 'array'` ✅<br>Nessun cast per `step_corrente`                                 | Possiamo gestire l’array senza problemi, ma `step_corrente` va aggiunto se richiesto.               |

**Decisioni architetturali**  
- Il nuovo endpoint riceve il codice wizard (non l’ID del log) perché l’agent conosce solo il codice del wizard, non l’ID interno del log.  
- L’`execution_log` attivo viene individuato tramite `wizard_id` e `completed_at IS NULL`.  
- Il campo `step_corrente` non è presente nello schema fornito, ma lo aggiorneremo se esiste; in caso contrario lo ignoreremo (commento nel codice).  
- La logica di chiusura automatica del log viene attivata quando `progress == 100` e `status` è `completed` o `error`.

---

## 2. File 1 – AgentStepController

```php
<?php
// backend/app/Http/Controllers/Api/Agent/AgentStepController.php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StepRequest;
use App\Models\ExecutionLog;
use App\Models\Wizard;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AgentStepController extends Controller
{
    /**
     * Registra un passo intermedio dell'esecuzione del wizard.
     *
     * @param StepRequest $request
     * @return JsonResponse
     */
    public function step(StepRequest $request): JsonResponse
    {
        try {
            // 1. Recupera il wizard tramite codice univoco
            $wizard = Wizard::where('codice_univoco', $request->wizard_code)
                ->firstOrFail();

            // 2. Verifica che il wizard non sia già terminato
            if (in_array($wizard->stato, ['completato', 'errore'])) {
                return response()->json([
                    'error' => 'Wizard già terminato.'
                ], Response::HTTP_CONFLICT);
            }

            // 3. Trova l'execution log attivo (senza completed_at)
            $executionLog = ExecutionLog::where('wizard_id', $wizard->id)
                ->whereNull('completed_at')
                ->latest('started_at')
                ->firstOrFail();

            // 4. Prepara il nuovo passo
            $stepEntry = $this->buildStepEntry($request);

            // 5. Aggiunge il passo al log dettagliato
            $logData = $executionLog->log_dettagliato ?? [];
            $logData[] = $stepEntry;
            $executionLog->log_dettagliato = $logData;

            // 6. Aggiorna eventuale campo "step corrente" (se esiste nella tabella)
            if (array_key_exists('step_corrente', $executionLog->getAttributes())) {
                $executionLog->step_corrente = $request->step;
            }

            // 7. Aggiorna lo stato generale del log (mapping da status agent a stato DB)
            $executionLog->stato = $this->mapAgentStatusToDbState($request->status);

            // 8. Verifica se il log deve essere chiuso (progress == 100)
            if ($request->progress == 100) {
                $executionLog->completed_at = now();

                // Aggiorna anche lo stato del wizard in base allo status finale
                if ($request->status === 'completed') {
                    $wizard->stato = 'completato';
                } elseif ($request->status === 'error') {
                    $wizard->stato = 'errore';
                }
                $wizard->save();
            }

            $executionLog->save();

            return response()->json(['success' => true]);

        } catch (ModelNotFoundException $e) {
            // Distinguiamo tra wizard non trovato e log non trovato
            if (str_contains($e->getMessage(), 'Wizard')) {
                return response()->json([
                    'error' => 'Wizard non trovato.'
                ], Response::HTTP_NOT_FOUND);
            }
            return response()->json([
                'error' => 'Nessuna esecuzione attiva per questo wizard.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Throwable $e) {
            Log::error('Errore in AgentStepController::step', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'error' => 'Errore interno del server.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Costruisce l'array del passo da salvare nel log dettagliato.
     *
     * @param StepRequest $request
     * @return array
     */
    private function buildStepEntry(StepRequest $request): array
    {
        return [
            'step'      => $request->step,
            'status'    => $request->status,
            'message'   => $request->message,
            'progress'  => $request->progress,
            'timestamp' => $request->timestamp,
        ];
    }

    /**
     * Mappa lo status inviato dall'agent allo stato del record execution_logs.
     *
     * @param string $agentStatus
     * @return string
     */
    private function mapAgentStatusToDbState(string $agentStatus): string
    {
        return match ($agentStatus) {
            'in_progress' => 'in_corso',
            'completed'   => 'in_corso',   // rimane in_corso finché progress < 100
            'error'       => 'errore',
            'skipped'     => 'in_corso',
            default       => 'in_corso',
        };
    }
}
```

---

## 3. File 2 – StepRequest (Form Request)

```php
<?php
// backend/app/Http/Requests/Agent/StepRequest.php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class StepRequest extends FormRequest
{
    /**
     * Autorizzazione: il middleware JWT ha già autenticato la richiesta.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regole di validazione.
     */
    public function rules(): array
    {
        return [
            'wizard_code' => [
                'required',
                'string',
                'regex:/^WD-[A-Z0-9]{5}$/',  // formato WD-XXXXX
            ],
            'step'        => 'required|string|max:100',
            'status'      => 'required|in:in_progress,completed,error,skipped',
            'message'     => 'required|string|max:500',
            'progress'    => 'required|integer|min:0|max:100',
            'timestamp'   => 'required|date_format:Y-m-d\TH:i:s\Z', // ISO8601 UTC
        ];
    }

    /**
     * Messaggi di errore personalizzati in italiano.
     */
    public function messages(): array
    {
        return [
            'wizard_code.required'   => 'Il codice wizard è obbligatorio.',
            'wizard_code.regex'      => 'Il formato del codice wizard non è valido (es: WD-ABC12).',
            'step.required'          => 'Il nome dello step è obbligatorio.',
            'step.max'               => 'Il nome dello step non può superare i 100 caratteri.',
            'status.required'        => 'Lo stato dello step è obbligatorio.',
            'status.in'              => 'Lo stato deve essere uno di: in_progress, completed, error, skipped.',
            'message.required'       => 'Il messaggio di dettaglio è obbligatorio.',
            'message.max'            => 'Il messaggio non può superare i 500 caratteri.',
            'progress.required'      => 'Il progresso percentuale è obbligatorio.',
            'progress.integer'       => 'Il progresso deve essere un numero intero.',
            'progress.min'           => 'Il progresso non può essere inferiore a 0.',
            'progress.max'           => 'Il progresso non può superare 100.',
            'timestamp.required'     => 'Il timestamp è obbligatorio.',
            'timestamp.date_format'  => 'Il timestamp deve essere in formato ISO8601 UTC (es: 2026-03-06T14:30:00Z).',
        ];
    }
}
```

---

## 4. Verifica e modifica delle route

Nel file `routes/api.php` è attualmente definito il gruppo agent:

```php
Route::prefix('agent')->group(function () {
    Route::post('/auth', [AgentController::class, 'auth'])->middleware('throttle:login')->name('agent.auth');

    Route::middleware(['auth:api', 'throttle:agent'])->group(function () {
        Route::post('/start',   [AgentController::class, 'start'])->name('agent.start');
        Route::post('/step',    [AgentController::class, 'step'])->name('agent.step');   // <-- da modificare
        Route::post('/complete', [AgentController::class, 'complete'])->name('agent.complete');
        Route::post('/abort',    [AgentController::class, 'abort'])->name('agent.abort');
    });
});
```

Per utilizzare il nuovo controller, sostituire la riga di `step` con:

```php
Route::post('/step', [\App\Http\Controllers\Api\Agent\AgentStepController::class, 'step'])
    ->name('agent.step');
```

**Rate limiting** – come da specifica `0012-apiendpointwindows.md`, il middleware `throttle:agent` è già applicato al gruppo. Assicurarsi che il rate limiter `agent` sia definito (ad esempio in `App\Providers\RateLimiterServiceProvider` con 120 richieste/minuto). Se manca, aggiungerlo:

```php
RateLimiter::for('agent', fn ($job) => Limit::perMinute(120)->by($job->bearerToken() ?? $job->ip()));
```

---

## 5. Test con cURL

### 5.1 Caso di successo (step intermedio)

```bash
curl -X POST https://windeploy.local/api/agent/step \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {JWT_TOKEN}" \
  -d '{
    "wizard_code": "WD-TEST1",
    "step": "install_software",
    "status": "in_progress",
    "message": "Installazione di Google Chrome avviata",
    "progress": 45,
    "timestamp": "2026-03-06T14:30:00Z"
  }'
```

**Risposta attesa** (HTTP 200):

```json
{ "success": true }
```

### 5.2 Ultimo step (progress = 100, status = completed)

```bash
curl -X POST https://windeploy.local/api/agent/step \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {JWT_TOKEN}" \
  -d '{
    "wizard_code": "WD-TEST1",
    "step": "finalizzazione",
    "status": "completed",
    "message": "Tutte le operazioni completate con successo",
    "progress": 100,
    "timestamp": "2026-03-06T14:45:00Z"
  }'
```

**Risposta** (HTTP 200) e dietro le quinte: `execution_logs.completed_at` viene valorizzato e `wizards.stato` passa a `completato`.

### 5.3 Wizard inesistente (404)

```bash
curl -X POST https://windeploy.local/api/agent/step \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {JWT_TOKEN}" \
  -d '{
    "wizard_code": "WD-XXXXX",
    "step": "test",
    "status": "in_progress",
    "message": "test",
    "progress": 10,
    "timestamp": "2026-03-06T14:30:00Z"
  }'
```

**Risposta attesa** (HTTP 404):

```json
{ "error": "Wizard non trovato." }
```

### 5.4 Nessuna esecuzione attiva (422)

```bash
# dopo che il wizard è già stato completato, riprovare
curl -X POST https://windeploy.local/api/agent/step \
  ... stessi dati del caso 5.1 ...
```

**Risposta attesa** (HTTP 422):

```json
{ "error": "Nessuna esecuzione attiva per questo wizard." }
```

---

## 6. Note su schema e potenziali miglioramenti

- **Campo `step_corrente`** – Nelle migration fornite (0105‑schema DB.md) questo campo non esiste. Se la logica di aggiornamento è indispensabile, creare una migration aggiuntiva:
  ```php
  Schema::table('execution_logs', function (Blueprint $table) {
      $table->string('step_corrente', 100)->nullable()->after('stato');
  });
  ```
  Nel codice abbiamo inserito un controllo `array_key_exists` per evitare errori in assenza della colonna.

- **Protezione multi‑tenant** – Il middleware `auth:api` autentica l’utente (il tecnico proprietario del wizard). Nel metodo `step` non eseguiamo ulteriori controlli perché il wizard viene cercato tramite codice univoco, che è già legato a un utente specifico. Tuttavia, in futuro, se un tecnico malintenzionato riuscisse a ottenere un codice wizard di un altro tecnico, potrebbe inviare step. Per mitigare, si potrebbe aggiungere un controllo che verifichi che il wizard appartenga all’utente autenticato (tramite `$wizard->user_id === auth()->id()`). Al momento non è richiesto.

- **Rate limiting** – L’endpoint è protetto da `throttle:agent` (120 richieste/minuto). Se un agent invia troppi step (es. a causa di un bug), il limite evita sovraccarichi del database.

---

## 7. Commit message (convenzionale)

```bash
git add app/Http/Controllers/Api/Agent/AgentStepController.php
git add app/Http/Requests/Agent/StepRequest.php
git add routes/api.php

git commit -m "feat(agent-api): implement new POST /agent/step endpoint

- Add AgentStepController with logic to append step to execution_log
- Create StepRequest with validation (wizard_code, step, status, message, progress, timestamp)
- Auto‑close execution log and update wizard state when progress=100
- Route changed to use new controller
- Error responses: 404 (wizard missing), 422 (no active log), 500 (internal)"
```

---

Tutti i file sono forniti con percorso completo, codice completo e commenti in italiano. Le scelte architetturali rispettano i riferimenti forniti (`0012-apiendpointwindows.md`, `0105-schema DB.md`, `0103-configurazione auth e sicurezza.md`, `0011-controllercrud.md`).