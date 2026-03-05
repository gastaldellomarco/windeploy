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
    protected EncryptionService $encryption;

    public function __construct(EncryptionService $encryption)
    {
        $this->encryption = $encryption;
    }

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

        // Aggiorna stato wizard
        $wizard->stato = 'in_esecuzione';
        $wizard->save();

        // Decifra le password per l'agent usando EncryptionService
        $config = $wizard->configurazione;
        $salt = (string) $wizard->id; // o wizard->codice_univoco
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

        if (!in_array($executionLog->stato, ['avviato', 'in_corso'])) {
            return response()->json(['message' => 'Esecuzione già completata o abortita.'], 422);
        }

        $step = $data['step'];
        $step['timestamp'] = now()->toIso8601String();

        $log = $executionLog->log_dettagliato ?? [];
        $log[] = $step;
        $executionLog->log_dettagliato = $log;

        if (isset($step['nome'])) {
            $executionLog->step_corrente = $step['nome'];
        }

        if ($executionLog->stato === 'avviato') {
            $executionLog->stato = 'in_corso';
        }
        $executionLog->save();

        // Eventuale broadcast per il frontend
        // broadcast(new ExecutionLogUpdated($executionLog))->toOthers();

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

        $executionLog->pc_nome_nuovo = $data['pc_nome_nuovo'];
        $executionLog->stato = 'completato';
        $executionLog->completed_at = now();

        $log = $executionLog->log_dettagliato ?? [];
        $log[] = [
            'step'      => 'sommario_finale',
            'timestamp' => now()->toIso8601String(),
            'esito'     => 'ok',
            'dettaglio' => $data['sommario'] ?? null,
        ];
        $executionLog->log_dettagliato = $log;
        $executionLog->save();

        $wizard = Wizard::find($wizardId);
        $wizard->stato = 'completato';
        $wizard->used_at = now();
        $wizard->save();

        $report = Report::create([
            'execution_log_id' => $executionLog->id,
            'html_content'     => $data['report_html'],
        ]);

        // broadcast(new ExecutionLogUpdated($executionLog))->toOthers();

        return response()->json([
            'ok'         => true,
            'report_url' => route('api.reports.show', $report->id),
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

        $wizard = Wizard::find($wizardId);
        $wizard->stato = 'errore';
        $wizard->save();

        // broadcast(new ExecutionLogUpdated($executionLog))->toOthers();

        return response()->json(['ok' => true]);
    }
}