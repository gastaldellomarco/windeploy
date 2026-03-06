Di seguito i controller completi per WinDeploy, sviluppati in Laravel 12, con gestione dei ruoli (admin, tecnico, viewer), validazioni tramite Form Request, risorse API e autorizzazioni esplicite.

---

## 1. WizardController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WizardStoreRequest;
use App\Http\Requests\WizardUpdateRequest;
use App\Http\Resources\WizardResource;
use App\Models\Wizard;
use App\Models\ExecutionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class WizardController extends Controller
{
    /**
     * Elenco wizard con filtri.
     * - Admin: tutti i wizard
     * - Tecnico/Viewer: solo i propri wizard
     * Filtri: ?stato=bozza&da_data=2025-01-01&a_data=2025-12-31&user_id=2 (solo admin)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Wizard::with('user', 'template');

        // Filtro per utente (non admin vedono solo i propri)
        if ($user->ruolo !== 'admin') {
            $query->where('user_id', $user->id);
        } elseif ($request->has('user_id') && $request->user_id) {
            // Admin può filtrare per user_id specifico
            $query->where('user_id', $request->user_id);
        }

        // Filtro per stato
        if ($request->has('stato') && in_array($request->stato, Wizard::STATI)) {
            $query->where('stato', $request->stato);
        }

        // Filtro per data creazione (range)
        if ($request->has('da_data') && $request->da_data) {
            $query->whereDate('created_at', '>=', $request->da_data);
        }
        if ($request->has('a_data') && $request->a_data) {
            $query->whereDate('created_at', '<=', $request->a_data);
        }

        $wizards = $query->latest()->paginate(20);

        return WizardResource::collection($wizards);
    }

    /**
     * Crea un nuovo wizard (solo admin/tecnico).
     */
    public function store(WizardStoreRequest $request)
    {
        $user = $request->user();

        // Viewer non può creare
        if ($user->ruolo === 'viewer') {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validated();

        // Genera codice univoco
        $data['codice_univoco'] = $this->generateUniqueCode();

        // Imposta user_id e stato iniziale
        $data['user_id'] = $user->id;
        $data['stato'] = 'bozza';

        // Salva wizard
        $wizard = Wizard::create($data);

        return new WizardResource($wizard);
    }

    /**
     * Mostra dettaglio wizard.
     */
    public function show(Wizard $wizard)
    {
        // Autorizzazione: viewer e tecnico possono vedere solo i propri, admin tutto
        if (Gate::denies('view', $wizard)) {
            return response()->json(['message' => 'Accesso negato.'], Response::HTTP_FORBIDDEN);
        }

        return new WizardResource($wizard->load('user', 'template'));
    }

    /**
     * Aggiorna wizard (solo se stato = bozza e proprietario/admin).
     */
    public function update(WizardUpdateRequest $request, Wizard $wizard)
    {
        // Autorizzazione
        if (Gate::denies('update', $wizard)) {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

        // Solo se in bozza
        if ($wizard->stato !== 'bozza') {
            return response()->json(['message' => 'Solo i wizard in bozza possono essere modificati.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $wizard->update($request->validated());

        return new WizardResource($wizard);
    }

    /**
     * Soft delete wizard (solo proprietario/admin).
     */
    public function destroy(Wizard $wizard)
    {
        if (Gate::denies('delete', $wizard)) {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

        $wizard->delete();

        return response()->json(['message' => 'Wizard eliminato.'], Response::HTTP_OK);
    }

    /**
     * Rigenera il codice univoco del wizard (solo se bozza e proprietario/admin).
     */
    public function generateCode(Wizard $wizard)
    {
        if (Gate::denies('update', $wizard)) {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

        if ($wizard->stato !== 'bozza') {
            return response()->json(['message' => 'Solo i wizard in bozza possono cambiare codice.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $wizard->codice_univoco = $this->generateUniqueCode();
        $wizard->save();

        return response()->json([
            'codice_univoco' => $wizard->codice_univoco
        ]);
    }

    /**
     * Restituisce lo stato in tempo reale dell'esecuzione (ultimo log).
     */
    public function monitor(Wizard $wizard)
    {
        if (Gate::denies('view', $wizard)) {
            return response()->json(['message' => 'Accesso negato.'], Response::HTTP_FORBIDDEN);
        }

        $log = ExecutionLog::where('wizard_id', $wizard->id)
            ->latest('started_at')
            ->first();

        if (!$log) {
            return response()->json(['message' => 'Nessuna esecuzione avviata.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'wizard_id' => $wizard->id,
            'execution_log_id' => $log->id,
            'stato' => $log->stato,
            'step_corrente' => $log->step_corrente,
            'log_dettagliato' => $log->log_dettagliato,
            'started_at' => $log->started_at,
            'completed_at' => $log->completed_at,
        ]);
    }

    /**
     * Genera un codice univoco nel formato WD-XXXX (6 caratteri totali).
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = 'WD-' . strtoupper(Str::random(4));
        } while (Wizard::where('codice_univoco', $code)->exists());

        return $code;
    }
}
```

---

## 2. TemplateController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TemplateStoreRequest;
use App\Http\Requests\TemplateUpdateRequest;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class TemplateController extends Controller
{
    /**
     * Elenco template.
     * - Admin: tutti i template
     * - Tecnico: globali + personali
     * - Viewer: solo globali (o nessuno? qui diamo globali + propri per viewer? per semplicità diamo globali)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Template::with('user');

        if ($user->ruolo === 'admin') {
            // Admin vede tutto
        } elseif ($user->ruolo === 'tecnico') {
            // Tecnico vede globali + propri
            $query->where(function ($q) use ($user) {
                $q->where('scope', 'globale')
                  ->orWhere('user_id', $user->id);
            });
        } else { // viewer
            // Viewer vede solo globali
            $query->where('scope', 'globale');
        }

        // Filtri aggiuntivi (es. nome, descrizione)
        if ($request->has('nome')) {
            $query->where('nome', 'like', '%' . $request->nome . '%');
        }

        $templates = $query->latest()->paginate(20);

        return TemplateResource::collection($templates);
    }

    /**
     * Crea un nuovo template.
     */
    public function store(TemplateStoreRequest $request)
    {
        $user = $request->user();

        // Viewer non può creare
        if ($user->ruolo === 'viewer') {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validated();
        $data['user_id'] = $user->id;

        // Se l'utente non è admin, forza scope = personale
        if ($user->ruolo !== 'admin' && ($data['scope'] ?? 'personale') === 'globale') {
            return response()->json(['message' => 'Solo gli admin possono creare template globali.'], Response::HTTP_FORBIDDEN);
        }

        $template = Template::create($data);

        return new TemplateResource($template);
    }

    /**
     * Mostra dettaglio template.
     */
    public function show(Template $template)
    {
        $user = request()->user();

        // Admin vede tutto, altrimenti deve essere proprietario o globale
        if ($user->ruolo !== 'admin') {
            if ($template->scope === 'personale' && $template->user_id !== $user->id) {
                return response()->json(['message' => 'Accesso negato.'], Response::HTTP_FORBIDDEN);
            }
        }

        return new TemplateResource($template->load('user'));
    }

    /**
     * Aggiorna template.
     */
    public function update(TemplateUpdateRequest $request, Template $template)
    {
        $user = $request->user();

        // Admin può modificare qualsiasi template
        if ($user->ruolo === 'admin') {
            // ok
        } else {
            // Tecnico può modificare solo i propri personali
            if ($template->user_id !== $user->id) {
                return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
            }
            // Non può trasformare in globale
            if ($request->has('scope') && $request->scope === 'globale') {
                return response()->json(['message' => 'Non puoi trasformare un template personale in globale.'], Response::HTTP_FORBIDDEN);
            }
        }

        $template->update($request->validated());

        return new TemplateResource($template);
    }

    /**
     * Elimina template (soft delete).
     */
    public function destroy(Template $template)
    {
        $user = request()->user();

        // Admin può eliminare qualsiasi template
        if ($user->ruolo === 'admin') {
            $template->delete();
            return response()->json(['message' => 'Template eliminato.']);
        }

        // Tecnico può eliminare solo i propri personali
        if ($template->user_id === $user->id && $template->scope === 'personale') {
            $template->delete();
            return response()->json(['message' => 'Template eliminato.']);
        }

        return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
    }
}
```

---

## 3. SoftwareLibraryController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SoftwareLibraryStoreRequest;
use App\Http\Requests\SoftwareLibraryUpdateRequest;
use App\Http\Resources\SoftwareLibraryResource;
use App\Models\SoftwareLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // per chiamata a winget.run (esempio)
use Symfony\Component\HttpFoundation\Response;

class SoftwareLibraryController extends Controller
{
    /**
     * Elenco software (accessibile a tutti).
     * Filtri: categoria, attivo, tipo, search (nome)
     */
    public function index(Request $request)
    {
        $query = SoftwareLibrary::query();

        if ($request->has('attivo')) {
            $query->where('attivo', $request->boolean('attivo'));
        }

        if ($request->has('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('search')) {
            $query->where('nome', 'like', '%' . $request->search . '%');
        }

        $software = $query->latest()->paginate(20);

        return SoftwareLibraryResource::collection($software);
    }

    /**
     * Crea nuovo software (solo admin).
     */
    public function store(SoftwareLibraryStoreRequest $request)
    {
        $user = $request->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(['message' => 'Solo gli admin possono aggiungere software.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validated();
        $data['aggiunto_da'] = $user->id;

        $software = SoftwareLibrary::create($data);

        return new SoftwareLibraryResource($software);
    }

    /**
     * Mostra dettaglio software.
     */
    public function show(SoftwareLibrary $softwareLibrary)
    {
        // Accessibile a tutti (anche viewer)
        return new SoftwareLibraryResource($softwareLibrary);
    }

    /**
     * Aggiorna software (solo admin).
     */
    public function update(SoftwareLibraryUpdateRequest $request, SoftwareLibrary $softwareLibrary)
    {
        $user = $request->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(['message' => 'Solo gli admin possono modificare software.'], Response::HTTP_FORBIDDEN);
        }

        $softwareLibrary->update($request->validated());

        return new SoftwareLibraryResource($softwareLibrary);
    }

    /**
     * Elimina software (soft delete, solo admin).
     */
    public function destroy(SoftwareLibrary $softwareLibrary)
    {
        $user = request()->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

        $softwareLibrary->delete();

        return response()->json(['message' => 'Software eliminato.']);
    }

    /**
     * Attiva/disattiva software (solo admin).
     */
    public function toggleActive(SoftwareLibrary $softwareLibrary)
    {
        $user = request()->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

        $softwareLibrary->attivo = !$softwareLibrary->attivo;
        $softwareLibrary->save();

        return new SoftwareLibraryResource($softwareLibrary);
    }

    /**
     * Cerca software su winget.run (o altra fonte) e restituisce lista.
     * Accessibile a tutti (per ricerca in fase di creazione wizard).
     */
    public function searchWinget(Request $request)
    {
        $request->validate(['query' => 'required|string|min:2']);

        // Esempio: chiamata a winget.run API (non ufficiale, solo dimostrativa)
        // In produzione potresti usare un database locale di pacchetti winget
        $response = Http::get('https://api.winget.run/v2/packages/search', [
            'query' => $request->query,
            'take' => 20,
        ]);

        if ($response->failed()) {
            return response()->json(['message' => 'Servizio di ricerca non disponibile.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $packages = $response->json()['Packages'] ?? [];

        // Mappa i dati in un formato utile per il frontend
        $results = collect($packages)->map(function ($pkg) {
            return [
                'id' => $pkg['Id'],
                'nome' => $pkg['Name'],
                'versione' => $pkg['Latest']['Version'] ?? null,
                'publisher' => $pkg['Publisher'],
                'tipo' => 'winget',
                'identificatore' => $pkg['Id'],
            ];
        });

        return response()->json($results);
    }
}
```

---

## 4. ReportController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /**
     * Elenco report con filtri (data, tecnico).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Report::with('executionLog.wizard.user');

        // Viewer e tecnico vedono solo i report dei wizard che possono vedere?
        // Per semplicità, admin vede tutto, gli altri vedono solo report associati a wizard di loro proprietà o eseguiti da loro?
        // Dalla descrizione, viewer può vedere dashboard e report, quindi probabilmente tutti i report.
        // Qui seguiamo: admin tutto, tecnico vede i report dei wizard che ha creato, viewer tutti (sola lettura).
        // Adattabile.

        if ($user->ruolo === 'tecnico') {
            // Tecnico vede report dei wizard di sua proprietà
            $query->whereHas('executionLog.wizard', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif ($user->ruolo === 'viewer') {
            // Viewer vede tutti i report (sola lettura)
            // nessun filtro aggiuntivo
        } // admin nessun filtro

        // Filtri
        if ($request->has('da_data')) {
            $query->whereDate('created_at', '>=', $request->da_data);
        }
        if ($request->has('a_data')) {
            $query->whereDate('created_at', '<=', $request->a_data);
        }
        if ($request->has('tecnico_id') && $user->ruolo === 'admin') {
            $query->whereHas('executionLog.wizard', function ($q) use ($request) {
                $q->where('user_id', $request->tecnico_id);
            });
        }

        $reports = $query->latest()->paginate(20);

        return ReportResource::collection($reports);
    }

    /**
     * Mostra dettaglio report con contenuto HTML.
     */
    public function show(Report $report)
    {
        // Autorizzazione: simile a index
        if (Gate::denies('view', $report)) {
            return response()->json(['message' => 'Accesso negato.'], Response::HTTP_FORBIDDEN);
        }

        return new ReportResource($report);
    }

    /**
     * Download del file HTML.
     */
    public function download(Report $report)
    {
        if (Gate::denies('view', $report)) {
            return response()->json(['message' => 'Accesso negato.'], Response::HTTP_FORBIDDEN);
        }

        // Imposta nome file
        $filename = 'report_' . $report->executionLog->wizard->codice_univoco . '_' . $report->created_at->format('Ymd_His') . '.html';

        return response()->streamDownload(function () use ($report) {
            echo $report->html_content;
        }, $filename, ['Content-Type' => 'text/html']);
    }

    /**
     * Elimina report (solo admin).
     */
    public function destroy(Report $report)
    {
        $user = request()->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(['message' => 'Solo gli admin possono eliminare report.'], Response::HTTP_FORBIDDEN);
        }

        $report->delete();

        return response()->json(['message' => 'Report eliminato.']);
    }
}
```

---

## Form Request (esempi)

Ecco alcuni esempi di Form Request necessari. (Per brevità ne mostro solo uno per tipo; il pattern è simile.)

### WizardStoreRequest

```php
<?php

namespace App\Http\Requests;

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
```

### TemplateStoreRequest

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TemplateStoreRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nome' => 'required|string|max:150',
            'descrizione' => 'nullable|string',
            'scope' => 'sometimes|in:globale,personale',
            'configurazione' => 'required|array',
            // ... validazione struttura configurazione (simile a wizard)
        ];
    }
}
```

### SoftwareLibraryStoreRequest

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SoftwareLibraryStoreRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nome' => 'required|string|max:150',
            'versione' => 'nullable|string|max:50',
            'publisher' => 'nullable|string|max:150',
            'tipo' => 'required|in:winget,exe,msi',
            'identificatore' => 'required|string|max:255',
            'categoria' => 'nullable|string|max:100',
            'icona_url' => 'nullable|url|max:500',
            'attivo' => 'boolean',
        ];
    }
}
```

---

## Policy (esempio per autorizzazioni)

Per utilizzare `Gate::denies()` nei controller, creare le policy. Ecco un esempio per WizardPolicy:

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wizard;

class WizardPolicy
{
    public function view(User $user, Wizard $wizard)
    {
        return $user->ruolo === 'admin' || $wizard->user_id === $user->id;
    }

    public function update(User $user, Wizard $wizard)
    {
        return ($user->ruolo === 'admin' || $wizard->user_id === $user->id);
    }

    public function delete(User $user, Wizard $wizard)
    {
        return ($user->ruolo === 'admin' || $wizard->user_id === $user->id);
    }
}
```

Registrare le policy in `AuthServiceProvider`.

---

## Note finali

- I controller utilizzano risorse (`WizardResource`, ecc.) che non sono state mostrate ma devono essere create per formattare l'output JSON secondo le necessità del frontend.
- La generazione del codice univoco è implementata nel `WizardController`.
- La funzione `searchWinget` è un esempio di chiamata a un servizio esterno; in produzione potrebbe essere necessario un caching o un database locale dei pacchetti winget.
- Tutte le risposte di errore utilizzano codici HTTP appropriati e messaggi chiari.
- Le autorizzazioni sono gestite tramite policy o controlli diretti sul ruolo dell'utente, in base ai requisiti.

Questi controller coprono le funzionalità richieste per WinDeploy.