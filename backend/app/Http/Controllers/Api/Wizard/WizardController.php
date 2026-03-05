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
    protected EncryptionService $encryption;

    public function __construct(EncryptionService $encryption)
    {
        $this->encryption = $encryption;
    }

    /**
     * Elenco wizard con filtri.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Wizard::with('user', 'template');

        if ($user->ruolo !== 'admin') {
            $query->where('user_id', $user->id);
        } elseif ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('stato') && in_array($request->stato, Wizard::STATI)) {
            $query->where('stato', $request->stato);
        }

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
     * Crea un nuovo wizard.
     */
    public function store(WizardStoreRequest $request)
    {
        $user = $request->user();

        if ($user->ruolo === 'viewer') {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validated();

        // Genera codice univoco
        $data['codice_univoco'] = $this->generateUniqueCode();

        // Cifra password con EncryptionService (usa wizard id ancora non noto, quindi salt provvisorio)
        // Dovremo salvare prima il wizard per avere l'id, poi aggiornare la configurazione cifrata.
        // Oppure usiamo il codice_univoco come salt (disponibile subito).
        $config = $data['configurazione'];
        $salt = $data['codice_univoco']; // salt basato sul codice (univoco e noto subito)

        if (isset($config['utente_admin']['password'])) {
            $plain = $config['utente_admin']['password'];
            $config['utente_admin']['password_encrypted'] = $this->encryption->encryptForWizard($plain, $salt);
            unset($config['utente_admin']['password']);
        }
        if (isset($config['extras']['wifi']['password'])) {
            $plain = $config['extras']['wifi']['password'];
            $config['extras']['wifi']['password_encrypted'] = $this->encryption->encryptForWizard($plain, $salt);
            unset($config['extras']['wifi']['password']);
        }
        $data['configurazione'] = $config;

        $data['user_id'] = $user->id;
        $data['stato'] = 'bozza';
        $data['expires_at'] = now()->addHours(24);

        $wizard = Wizard::create($data);

        return new WizardResource($wizard);
    }

    /**
     * Mostra dettaglio wizard.
     */
    public function show(Wizard $wizard)
    {
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
        if (Gate::denies('update', $wizard)) {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

        if ($wizard->stato !== 'bozza') {
            return response()->json(['message' => 'Solo i wizard in bozza possono essere modificati.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validated();

        // Se viene fornita una nuova password, cifrarla
        if (isset($data['configurazione']['utente_admin']['password'])) {
            $plain = $data['configurazione']['utente_admin']['password'];
            $salt = $wizard->codice_univoco;
            $data['configurazione']['utente_admin']['password_encrypted'] = $this->encryption->encryptForWizard($plain, $salt);
            unset($data['configurazione']['utente_admin']['password']);
        }

        $wizard->update($data);

        return new WizardResource($wizard);
    }

    /**
     * Soft delete wizard.
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
     * Genera un nuovo codice univoco e resetta expires_at.
     */
    public function generateCode(WizardGenerateCodeRequest $request, Wizard $wizard)
    {
        if (Gate::denies('update', $wizard)) {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

        $nuovoCodice = $this->generateUniqueCode();

        $wizard->codice_univoco = $nuovoCodice;
        $wizard->expires_at = now()->addHours(24);
        $wizard->save();

        return response()->json([
            'codice_univoco' => $nuovoCodice,
            'expires_at'     => $wizard->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Monitor polling: restituisce l'execution log associato al wizard.
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
            return response()->json([
                'wizard' => new WizardResource($wizard),
                'execution' => null,
                'message' => 'Nessuna esecuzione avviata.'
            ]);
        }

        return new ExecutionLogResource($log);
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