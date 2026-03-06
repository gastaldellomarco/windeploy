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