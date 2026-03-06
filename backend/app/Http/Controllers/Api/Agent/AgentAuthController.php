<?php
// app/Http/Controllers/Api/Agent/AgentAuthController.php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\AgentAuthRequest;
use App\Models\Wizard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;
use Carbon\Carbon;

class AgentAuthController extends Controller
{
    /**
     * Autentica l'agent Windows tramite wizard_code e MAC address.
     *
     * Flusso sequenziale a 6 step con progressive security gates:
     * ogni step fallisce in modo rapido (fail-fast) senza esporre
     * informazioni sugli step successivi all'attaccante.
     */
    public function auth(AgentAuthRequest $request): JsonResponse
    {
        // ══════════════════════════════════════════════════════════════
        // STEP 1 — Rate limit per IP (PRIMA di qualsiasi query DB)
        //
        // Obiettivo: bloccare flood da singolo IP senza consumare
        // connessioni DB. Il rate limiter 'agent_auth' è definito in
        // AppServiceProvider e applicato anche a livello di route
        // tramite middleware throttle:agent_auth.
        //
        // Questo check manuale è un secondo layer di difesa nel caso
        // il middleware venga bypassato o rimosso per errore.
        // ══════════════════════════════════════════════════════════════
        $ipKey = 'agent_auth_ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            $seconds = RateLimiter::availableIn($ipKey);

            Log::warning('WinDeploy: rate limit raggiunto su /agent/auth', [
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'retry_in'   => $seconds . 's',
                'timestamp'  => now()->toIso8601String(),
            ]);

            return response()->json([
                'error'      => 'rate_limit_exceeded',
                'message'    => 'Troppi tentativi. Riprova tra ' . $seconds . ' secondi.',
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($ipKey, 60); // finestra di 60 secondi

        // ══════════════════════════════════════════════════════════════
        // STEP 2 — Trova wizard valido (non scaduto)
        //
        // NON filtrare su used_at o attempt_count qui: il wizard deve
        // essere trovato anche se "usato" o "bloccato" per poter
        // incrementare l'attempt_count e loggare correttamente.
        // Un 404 su wizard inesistente/scaduto non incrementa attempt_count
        // perché non c'è un wizard a cui attribuire il tentativo.
        // ══════════════════════════════════════════════════════════════
        $wizard = Wizard::where('codice_univoco', $request->input('wizard_code'))
                        ->where('expires_at', '>', now())
                        ->first();

        if (! $wizard) {
            Log::warning('WinDeploy: tentativo auth con codice wizard non valido o scaduto', [
                'wizard_code' => $request->input('wizard_code'),
                'ip'          => $request->ip(),
                'user_agent'  => $request->userAgent(),
                'timestamp'   => now()->toIso8601String(),
            ]);

            return response()->json([
                'error'   => 'wizard_not_found',
                'message' => 'Codice wizard non valido o scaduto.',
            ], 404);
        }

        // ══════════════════════════════════════════════════════════════
        // STEP 3 — Verifica monouso (used_at)
        //
        // Se il wizard è già stato usato, restituisce 409 Conflict.
        //
        // TRADE-OFF ACCETTATO (crash recovery):
        // Se l'agent crasha DOPO che used_at è stato scritto ma PRIMA
        // del primo /agent/step, il wizard risulta "bruciato" e non
        // può essere riutilizzato. In quel caso il tecnico deve
        // rigenerare il codice dal pannello admin.
        // Questo è il trade-off scelto per prevenire replay attack:
        // sicurezza > comodità operativa in caso di crash raro.
        // ══════════════════════════════════════════════════════════════
        if ($wizard->used_at !== null) {
            Log::warning('WinDeploy: tentativo di riuso wizard già utilizzato', [
                'wizard_code' => $wizard->codice_univoco,
                'wizard_id'   => $wizard->id,
                'used_at'     => $wizard->used_at->toIso8601String(),
                'ip'          => $request->ip(),
                'user_agent'  => $request->userAgent(),
                'timestamp'   => now()->toIso8601String(),
            ]);

            return response()->json([
                'error'   => 'wizard_already_used',
                'message' => 'Questo codice wizard è già stato utilizzato.',
                'used_at' => $wizard->used_at->toIso8601String(),
            ], 409);
        }

        // ══════════════════════════════════════════════════════════════
        // STEP 4 — Verifica attempt_count (lockout)
        //
        // Se il wizard ha già accumulato 3 o più tentativi MAC falliti,
        // è bloccato. Il tecnico deve rigenerare il codice.
        // Questo blocco viene controllato PRIMA della verifica MAC
        // per non eseguire ulteriore logica su wizard già compromessi.
        // ══════════════════════════════════════════════════════════════
        if ($wizard->attempt_count >= 3) {
            Log::warning('WinDeploy: tentativo auth su wizard bloccato (attempt_count >= 3)', [
                'wizard_code'   => $wizard->codice_univoco,
                'wizard_id'     => $wizard->id,
                'attempt_count' => $wizard->attempt_count,
                'last_attempt_ip' => $wizard->last_attempt_ip,
                'ip'            => $request->ip(),
                'user_agent'    => $request->userAgent(),
                'timestamp'     => now()->toIso8601String(),
            ]);

            return response()->json([
                'error'   => 'wizard_locked',
                'message' => 'Troppi tentativi falliti. Rigenera il codice wizard.',
            ], 423);
        }

        // ══════════════════════════════════════════════════════════════
        // STEP 5 — Verifica MAC address
        //
        // Normalizzazione: rimozione di separatori (:, -, .) e
        // conversione in lowercase per confronto case-insensitive.
        // Questo gestisce le varianti di formato:
        //   AA:BB:CC:DD:EE:FF  →  aabbccddeeff
        //   AA-BB-CC-DD-EE-FF  →  aabbccddeeff
        //   AABB.CCDD.EEFF     →  aabbccddeeff
        //
        // Il MAC atteso è nel campo 'mac_address' di configurazione JSON.
        // ══════════════════════════════════════════════════════════════
        $normalizeMAC = fn(string $mac): string =>
            strtolower(str_replace([':', '-', '.'], '', trim($mac)));

        $providedMAC  = $normalizeMAC($request->input('mac_address', ''));
        $config       = $wizard->configurazione;
        $expectedMAC  = $normalizeMAC($config['mac_address'] ?? '');

        if ($providedMAC !== $expectedMAC) {
            // Incremento atomico: usa Eloquent increment() per evitare
            // race condition su richieste parallele (read-modify-write
            // non è atomico, increment() usa UPDATE SET col = col + 1).
            $wizard->increment('attempt_count');

            // Aggiorna last_attempt_ip per audit (operazione separata,
            // non critica per atomicità — il dato serve solo per log).
            $wizard->update(['last_attempt_ip' => $request->ip()]);

            // Rileggi attempt_count fresco dal DB dopo l'increment
            $wizard->refresh();

            // Se questo tentativo ha portato il contatore a 3, logga
            // evento di blocco con priorità più alta (evento sicurezza).
            if ($wizard->attempt_count >= 3) {
                Log::warning('WinDeploy: wizard bloccato dopo 3 tentativi MAC falliti', [
                    'wizard_code'    => $wizard->codice_univoco,
                    'wizard_id'      => $wizard->id,
                    'attempt_count'  => $wizard->attempt_count,
                    'last_attempt_ip' => $wizard->last_attempt_ip,
                    'ip'             => $request->ip(),
                    'user_agent'     => $request->userAgent(),
                    'timestamp'      => now()->toIso8601String(),
                ]);
            } else {
                Log::warning('WinDeploy: MAC address non corrispondente su wizard', [
                    'wizard_code'      => $wizard->codice_univoco,
                    'wizard_id'        => $wizard->id,
                    'attempt_count'    => $wizard->attempt_count,
                    'attempts_remaining' => max(0, 3 - $wizard->attempt_count),
                    'ip'               => $request->ip(),
                    'user_agent'       => $request->userAgent(),
                    'timestamp'        => now()->toIso8601String(),
                ]);
            }

            return response()->json([
                'error'             => 'mac_mismatch',
                'message'          => 'MAC address non corrispondente.',
                'attempts_remaining' => max(0, 3 - $wizard->attempt_count),
            ], 401);
        }

        // ══════════════════════════════════════════════════════════════
        // STEP 6 — Auth OK: genera JWT e marca wizard come usato
        //
        // DB::transaction() garantisce atomicità tra la scrittura di
        // used_at e la generazione del token. Se JWTFactory fallisce,
        // il rollback annulla l'aggiornamento del wizard, evitando
        // che il wizard venga "bruciato" senza che l'agent abbia
        // ricevuto un token valido.
        //
        // attempt_count viene azzerato a 0 per pulizia (il wizard
        // è ora marcato used_at, quindi il lock da attempt_count
        // non sarà mai più raggiunto — ma 0 è più corretto di 2).
        // ══════════════════════════════════════════════════════════════
        $token = DB::transaction(function () use ($wizard) {
            $now    = Carbon::now();
            $expiry = $now->copy()->addHours(4);

            // Segna il wizard come usato e azzera i tentativi
            $wizard->update([
                'used_at'       => $now,
                'attempt_count' => 0,
            ]);

            // Costruisce il payload JWT custom con i claim necessari
            // all'agent per identificarsi nelle chiamate successive.
            // Il MAC address nel JWT permette ai middleware delle route
            // /agent/* di verificare che il token non sia usato da un
            // altro PC (protezione anti cross-PC replay).
            $payload = JWTFactory::customClaims([
                'sub'         => $wizard->id,       // subject: wizard ID
                'wizard_id'   => $wizard->id,
                'wizard_code' => $wizard->codice_univoco,
                'mac_address' => strtolower(
                    str_replace([':', '-', '.'], '', $wizard->configurazione['mac_address'] ?? '')
                ),
                'type'        => 'agent',
                'iat'         => $now->timestamp,
                'exp'         => $expiry->timestamp,
            ])->make();

            return JWTAuth::encode($payload);
        });

        Log::info('WinDeploy: agent autenticato con successo', [
            'wizard_code' => $wizard->codice_univoco,
            'wizard_id'   => $wizard->id,
            'ip'          => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'timestamp'   => now()->toIso8601String(),
        ]);

        return response()->json([
            'token'     => $token->get(),
            'wizard_id' => $wizard->id,
            'expires_in' => 4 * 60 * 60, // 4 ore in secondi
        ], 200);
    }
}
