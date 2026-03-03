<?php

namespace App\Http\Controllers\Api\Wizard;

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
     * Restituisce lo stato in tempo reale dell'esecuzione (ultimo log).
     */
    public function monitor(Wizard $wizard)
    {
        if (Gate::denies('view', $wizard)) {
            return response()->json(['message' => 'Accesso negato.'], Response::HTTP_FORBIDDEN);
        }

        // Use actual DB columns (snake_case in this project)
        $log = ExecutionLog::where('wizard_id', $wizard->id)
            ->latest('started_at')
            ->first();

        // Build a canonical response schema for the monitor endpoint.
        $wizardPayload = [
            'id' => $wizard->id,
            'name' => $wizard->nome ?? $wizard->name ?? null,
            'code' => $wizard->codice_univoco ?? null,
            'stato' => $wizard->stato,
        ];

        $pcPayload = null;
        $hardwarePayload = null;
        $steps = [];
        $summary = null;

        if ($log) {
            $pcPayload = ['name' => $log->pc_nome_originale ?? null];
            $hardwarePayload = $log->hardware_info ?? null;

            $rawSteps = is_array($log->log_dettagliato) ? $log->log_dettagliato : [];
            foreach ($rawSteps as $idx => $s) {
                $steps[] = [
                    'id' => $s['id'] ?? ($idx + 1),
                    'name' => $s['nome'] ?? $s['step'] ?? $s['name'] ?? 'Step ' . ($idx + 1),
                    'status' => $s['esito'] ?? $s['stato'] ?? $s['status'] ?? null,
                    'message' => $s['dettaglio'] ?? $s['messaggio'] ?? $s['message'] ?? null,
                    'timestamp' => isset($s['timestamp']) ? (string) $s['timestamp'] : null,
                ];
            }

            if (isset($log->sommario) && is_array($log->sommario)) {
                $summary = $log->sommario;
            }
        }

        $executionPayload = [
            'id' => $log ? $log->id : null,
            'wizardstato' => $wizard->stato,
            'executionstato' => $log ? $log->stato : null,
            'pending' => $log ? false : true,
            'started_at' => $log && $log->started_at ? $log->started_at->toIso8601String() : null,
            'completed_at' => $log && $log->completed_at ? $log->completed_at->toIso8601String() : null,
        ];

        $response = [
            'schema_version' => '1',
            'wizard' => $wizardPayload,
            'pc' => $pcPayload,
            'hardware' => $hardwarePayload,
            'execution' => $executionPayload,
            'steps' => $steps,
            'summary' => $summary,
            'legacy' => [
                'stato' => $log ? $log->stato : $wizard->stato,
                'executionlogid' => $log ? $log->id : null,
                'logdettagliato' => $log ? $log->log_dettagliato : null,
            ],
        ];

        if (!$log) {
            $response['message'] = 'Nessuna esecuzione avviata.';
        }

        return response()->json($response, Response::HTTP_OK);
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