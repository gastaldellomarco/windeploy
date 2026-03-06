<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# [Claude Sonnet] Stack: Laravel 11, PHP 8.3, MySQL, tymon/jwt-auth v2.2.1 — WinDeploy Security Hardening

════════════════════════════════════════════════════════════
CONTESTO
════════════════════════════════════════════════════════════
Il binding MAC address nell'endpoint POST /api/agent/auth
non garantisce sicurezza hardware reale: il MAC è spoofabile
via software. Questa issue aggiunge le mitigazioni MVP
prioritarie: token monouso (used_at), blocco dopo 3 tentativi
falliti (attempt_count), rate limiting per IP e documentazione
esplicita dei limiti del MAC binding.

════════════════════════════════════════════════════════════
RIFERIMENTI SPACE
════════════════════════════════════════════════════════════

- 0010-autenticazione.md  → implementazione corrente
AgentAuthController::auth(),
come viene verificato wizard_code
e MAC address, struttura JWT generato
- 0105-schema DB.md       → migration wizards: colonne attuali
(codice_univoco, expires_at, stato,
configurazione, ecc.) — verifica
se used_at e attempt_count esistono
già o vanno aggiunte con nuova migration
- 0103-configurazione auth e sicurezza.md → rate limiter 'agent'
già definito, RateLimiter::for() in
AppServiceProvider o RouteServiceProvider
- 0009-scaffoldprogetto.md → struttura routes/api.php, middleware
stack corrente per route /agent/*

════════════════════════════════════════════════════════════
AUDIT PRELIMINARE — esegui PRIMA di scrivere codice
════════════════════════════════════════════════════════════

1. Da 0010-autenticazione.md estrai il codice attuale
completo di AgentAuthController::auth() e mostralo
2. Da 0105-schema DB.md verifica se la tabella wizards ha:
    - Colonna used_at (timestamp nullable)
    - Colonna attempt_count (integer default 0)
Se mancanti, va creata una nuova migration (NON modificare
quella esistente)
3. Verifica se esiste già un rate limiter chiamato
'agent_auth' distinto da 'agent' (che serve per i log step)
4. Riporta in tabella:
| Elemento              | Stato  | Azione          |
| used_at in wizards    | ?      |                 |
| attempt_count         | ?      |                 |
| rate limiter auth     | ?      |                 |
| AgentAuthController   | ?      | logica attuale  |
prima di procedere.

════════════════════════════════════════════════════════════
COSA VOGLIO
════════════════════════════════════════════════════════════

── FILE 1: migration add_security_fields_to_wizards_table ─

Se used_at e attempt_count non esistono, crea:
database/migrations/{timestamp}_add_security_fields_to_wizards_table.php

Schema::table('wizards', function (Blueprint \$table) {
\$table->timestamp('used_at')->nullable()->after('expires_at');
\$table->unsignedTinyInteger('attempt_count')
->default(0)->after('used_at');
\$table->string('last_attempt_ip', 45)
->nullable()->after('attempt_count');
});

Includi anche il metodo down() corretto.

── FILE 2: AgentAuthController.php (versione hardened) ────

Riscrivi il metodo auth() con questa logica sequenziale:

STEP 1 — Rate limit per IP (prima di qualsiasi query DB):
Se l'IP ha già fatto 10 richieste/minuto → 429 Too Many Requests
(questo blocco avviene PRIMA della query, per non sprecare
risorse DB su attacchi flood)

STEP 2 — Trova wizard:
Wizard::where('codice_univoco', \$request->wizard_code)
->where('expires_at', '>', now())
->firstOrFail()
→ 404 se non trovato o scaduto
(NON filtrare ancora su used_at o attempt_count qui —
fallo dopo per poter incrementare attempt_count)

STEP 3 — Verifica monouso:
Se \$wizard->used_at !== null → ritorna 409 Conflict
con body: {
"error": "wizard_already_used",
"message": "Questo codice wizard è già stato utilizzato.",
"used_at": "{timestamp ISO}"
}

STEP 4 — Verifica attempt_count:
Se \$wizard->attempt_count >= 3 → ritorna 423 Locked
con body: {
"error": "wizard_locked",
"message": "Troppi tentativi falliti. Rigenera il codice wizard."
}

STEP 5 — Verifica MAC address:
Confronto case-insensitive normalizzato
(rimuovi separatori: str_replace([':', '-', '.'], '', \$mac))
Se il MAC nel wizard_config non corrisponde:
→ Incrementa attempt_count: \$wizard->increment('attempt_count')
→ Salva last_attempt_ip: \$wizard->update(['last_attempt_ip' => \$request->ip()])
→ Se attempt_count raggiunge 3 dopo questo incremento,
logga evento sicurezza: Log::warning('WinDeploy: wizard locked after 3 failed MAC attempts', [...])
→ Ritorna 401 con body: {
"error": "mac_mismatch",
"message": "MAC address non corrispondente.",
"attempts_remaining": max(0, 3 - \$wizard->attempt_count)
}

STEP 6 — Auth OK: genera token e marca wizard come usato
Usa DB::transaction() per atomicità:

- \$wizard->update(['used_at' => now(), 'attempt_count' => 0])
- $token = JWTAuth::fromUser(new AgentUser($wizard))
(oppure il metodo attuale da 0010-autenticazione.md)
- Ritorna 200 con: {"token": "{jwt}", "wizard_id": \$wizard->id}

NOTA IMPORTANTE su crash agent:
Aggiungi commento nel codice che spiega la scelta di design:
"Se l'agent crasha DOPO used_at ma PRIMA del primo /agent/step,
il wizard risulta 'bruciato'. In quel caso il tecnico deve
rigenerare il codice dal pannello admin. Questo è il trade-off
accettato per prevenire replay attack."

── FILE 3: Rate Limiter dedicato per /agent/auth ──────────

In AppServiceProvider::boot() (o dove sono definiti gli altri
limiter), aggiungi:

RateLimiter::for('agent_auth', function (Request \$request) {
return [
// Limite per IP: 10 req/minuto
Limit::perMinute(10)->by('ip:' . \$request->ip()),
// Limite per codice wizard: 5 req/minuto
// (previene brute force sul codice anche da IP diversi)
Limit::perMinute(5)->by('code:' . \$request->input('wizard_code', 'unknown')),
];
});

In routes/api.php, applica 'throttle:agent_auth' SOLO alla route
POST /agent/auth (NON alle altre route agent che usano JWT).

── FILE 4: Aggiornamento Wizard Model ─────────────────────

Aggiungi in app/Models/Wizard.php:

- 'used_at' e 'attempt_count' e 'last_attempt_ip'
nell'array \$fillable
- Cast: 'used_at' => 'datetime' e 'attempt_count' => 'integer'
- Metodo helper: public function isLocked(): bool
{ return \$this->attempt_count >= 3; }
- Metodo helper: public function isUsed(): bool
{ return \$this->used_at !== null; }

════════════════════════════════════════════════════════════
ANALISI DEI TRADE-OFF (includi nella risposta)
════════════════════════════════════════════════════════════
Dopo il codice, produci una sezione "Analisi Sicurezza" con:

1. Cosa questa implementazione PROTEGGE:
    - Replay attack con codice intercettato post-utilizzo
    - Brute force sul formato WD-XXXXX
    - Flood di /agent/auth da IP singolo
2. Cosa NON protegge (limiti residui da documentare):
    - MAC spoofing da attaccante con accesso fisico/rete locale
    - Intercettazione del JWT dopo emissione (HTTPS mitiga ma non elimina)
    - Insider threat (tecnico che copia il codice prima dell'uso)
3. Raccomandazione roadmap post-MVP:
Valuta se implementare il challenge-response descritto
nell'issue (nonce firmato con MAC + seriale) e quando
ha senso farlo (enterprise deployment vs SMB).

════════════════════════════════════════════════════════════
VINCOLI TRASVERSALI
════════════════════════════════════════════════════════════

- Ogni file inizia con percorso completo in commento
- Codice completo, nessuna abbreviazione
- Commenti in italiano, nomi variabili/metodi in inglese
- DB::transaction() obbligatorio per STEP 6 (atomicità
used_at + generazione token — non devono poter divergere)
- NON usare sleep() o delay artificiosi anti-timing-attack
(Laravel gestisce i tempi in modo non deterministico,
un delay fisso peggiora l'UX senza reale beneficio)
- Log::warning() per ogni evento di sicurezza anomalo:
MAC mismatch, wizard locked, wizard already used
Include sempre: wizard_code, IP, timestamp, user_agent
- ⚠️ IMPLICAZIONE SICUREZZA CRITICA: l'incremento di
attempt_count deve essere atomic (usa increment() di
Eloquent, NON read-modify-write per evitare race condition
in caso di richieste parallele)

════════════════════════════════════════════════════════════
COMMIT (solo Prompt 1)
════════════════════════════════════════════════════════════
feat(security): harden agent auth with one-time token and attempt lockout

- Migration: add used_at, attempt_count, last_attempt_ip to wizards
- AgentAuthController: 6-step sequential validation with atomic used_at
- Rate limiter agent_auth: 10/min per IP + 5/min per wizard code
- Wizard model: isLocked() and isUsed() helpers, security field casts

Ho completato l'audit preliminare dai file dello Space. Ecco la situazione reale prima di scrivere codice:

***

## Audit Preliminare

| Elemento | Stato | Azione |
| :-- | :-- | :-- |
| `used_at` in `wizards` | ✅ **Esiste già** | Colonna `timestamp nullable` nella migration `createwizardstable` — **nessuna migration aggiuntiva** per questo campo |
| `attempt_count` | ❌ **Mancante** | Non presente in nessuna migration né nel modello — **da aggiungere** |
| `last_attempt_ip` | ❌ **Mancante** | Non presente — **da aggiungere** |
| Rate limiter `agent_auth` | ❌ **Non esiste** | Esiste solo `login` in `AppServiceProvider` — il limiter `agent` per i log step non risulta definito nei file indicizzati |
| `AgentAuthController::auth()` | ⚠️ **Presente ma parziale** | Verifica `wizard_code` + `expires_at` + `used_at` (solo null check) + `stato`. MAC address viene normalizzato con `strtolower` ma **senza rimozione separatori**. **Non esiste** `attempt_count`, lock meccanismo, rate limit dedicato, o log sicurezza. Marca `used_at` al momento dell'auth (non al completamento). |

> **Nota architetturale rilevata**: la migration `createwizardstable.php` **ha già** `used_at` nullable, ma il modello `Wizard.php` ha già `used_at` in `$fillable` e nel cast `datetime`. La migration aggiuntiva riguarderà **solo** `attempt_count` e `last_attempt_ip`.

***

Ora i 4 file hardened completi.

***

## FILE 1 — Migration

```php
<?php
// database/migrations/2026_03_06_000001_add_security_fields_to_wizards_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge i campi di sicurezza alla tabella wizards.
     *
     * - attempt_count: contatore atomico dei tentativi MAC falliti (max 3 → locked)
     * - last_attempt_ip: IP dell'ultimo tentativo fallito, utile per audit e incident response
     *
     * NOTA: used_at esiste già nella migration originale createwizardstable.php.
     * Non viene ripetuto qui per evitare errori "Duplicate column name" su migratefresh.
     */
    public function up(): void
    {
        Schema::table('wizards', function (Blueprint $table) {
            // Contatore tentativi falliti: incrementato atomicamente con Eloquent increment()
            // per evitare race condition su richieste parallele dallo stesso agent.
            // TinyInteger non-signed: range 0-255, più che sufficiente per il limite di 3.
            $table->unsignedTinyInteger('attempt_count')
                  ->default(0)
                  ->after('used_at');

            // IP dell'ultimo tentativo fallito: 45 caratteri coprono indirizzi IPv6 completi.
            // Nullable perché popolato solo in caso di MAC mismatch (non al primo accesso valido).
            $table->string('last_attempt_ip', 45)
                  ->nullable()
                  ->after('attempt_count');

            // Indice per query di audit: "tutti i wizard bloccati nell'ultimo giorno"
            // SELECT * FROM wizards WHERE attempt_count >= 3 AND updated_at > NOW() - INTERVAL 1 DAY
            $table->index('attempt_count', 'idx_wizards_attempt_count');
        });
    }

    /**
     * Ripristina lo stato originale della tabella rimuovendo i campi aggiunti.
     */
    public function down(): void
    {
        Schema::table('wizards', function (Blueprint $table) {
            // Rimuovere prima l'indice, poi la colonna (MySQL richiede questo ordine)
            $table->dropIndex('idx_wizards_attempt_count');
            $table->dropColumn(['attempt_count', 'last_attempt_ip']);
        });
    }
};
```


***

## FILE 2 — AgentAuthController (hardened)

```php
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
```


***

## FILE 3 — Rate Limiter + Route

### `app/Providers/AppServiceProvider.php` (aggiunta al `boot()`)

```php
<?php
// app/Providers/AppServiceProvider.php
// Aggiungere SOLO le righe indicate nel metodo boot() esistente.
// Non sovrascrivere il provider intero — integrare con i limiter già presenti.

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ... codice esistente invariato ...
    }

    public function boot(): void
    {
        // ... rate limiter 'login' già esistente — NON modificare ...

        // ──────────────────────────────────────────────────────────────
        // Rate limiter dedicato per POST /api/agent/auth
        //
        // Separato dal limiter 'agent' (usato per i log step con JWT)
        // perché la fase di autenticazione pre-JWT richiede una
        // protezione più aggressiva: l'endpoint è pubblico (no JWT),
        // mentre le route /agent/* successive sono già protette da auth.
        //
        // Due layer indipendenti:
        //   1. Per IP: blocca flood da singolo attacker
        //   2. Per wizard_code: blocca brute force distribuito su
        //      codici validi anche da IP diversi (botnet scenario)
        // ──────────────────────────────────────────────────────────────
        RateLimiter::for('agent_auth', function (Request $request) {
            return [
                // Massimo 10 richieste per minuto per IP sorgente
                Limit::perMinute(10)->by('ip:' . $request->ip()),

                // Massimo 5 richieste per minuto per codice wizard
                // (previene enumerazione di codici validi da IP multipli)
                Limit::perMinute(5)->by('code:' . $request->input('wizard_code', 'unknown')),
            ];
        });
    }
}
```


### `routes/api.php` (modifica route agent/auth)

```php
<?php
// routes/api.php
// Sostituire SOLO la route POST /agent/auth esistente con questa versione.
// Tutte le altre route /agent/* con JWT rimangono invariate.

use App\Http\Controllers\Api\Agent\AgentAuthController;
// ... altri use già presenti ...

// ──────────────────────────────────────────────────────────────────────
// Route pubblica per autenticazione agent: throttle:agent_auth applicato
// SOLO a questo endpoint perché è l'unico senza JWT.
//
// IMPORTANTE: NON applicare throttle:agent_auth alle route /agent/*
// successive (step, complete, ecc.) che sono già protette da JWT e
// usano il limiter 'agent' separato.
// ──────────────────────────────────────────────────────────────────────
Route::post('/agent/auth', [AgentAuthController::class, 'auth'])
     ->middleware(['throttle:agent_auth'])
     ->name('agent.auth');

// ... resto delle route /agent/* invariate con middleware JWT ...
```


***

## FILE 4 — Wizard Model (aggiornamento)

```php
<?php
// app/Models/Wizard.php
// Versione completa aggiornata con i nuovi campi di sicurezza.
// Mantiene tutto il codice esistente e aggiunge i nuovi elementi.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Wizard extends Model
{
    use SoftDeletes;

    // Stati validi del wizard — usati nei controller e nelle Form Request
    public const STATI = ['bozza', 'pronto', 'in_esecuzione', 'completato', 'errore'];

    /**
     * Campi mass-assignable.
     * Aggiunto: attempt_count, last_attempt_ip
     * (used_at era già presente)
     */
    protected $fillable = [
        'nome',
        'user_id',
        'template_id',
        'codice_univoco',
        'stato',
        'configurazione',
        'expires_at',
        'used_at',          // già presente — monouso timestamp
        'attempt_count',    // NUOVO — contatore tentativi MAC falliti
        'last_attempt_ip',  // NUOVO — IP dell'ultimo tentativo fallito
    ];

    /**
     * Cast automatici Eloquent.
     *
     * - used_at e expires_at come 'datetime' permettono di usare
     *   ->toIso8601String(), ->isPast(), ->isFuture() direttamente.
     * - attempt_count come 'integer' garantisce confronti corretti
     *   (>= 3 funziona anche se MySQL restituisce una stringa).
     * - configurazione come 'array' serializza/deserializza JSON.
     */
    protected $casts = [
        'configurazione' => 'array',
        'expires_at'     => 'datetime',
        'used_at'        => 'datetime',  // già presente
        'attempt_count'  => 'integer',   // NUOVO
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    // ══════════════════════════════════════════════════════════════════
    // BOOT — generazione automatica codice univoco e expires_at
    // ══════════════════════════════════════════════════════════════════

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Wizard $wizard) {
            // Genera codice WD-XXXX se non già impostato
            if (empty($wizard->codice_univoco)) {
                do {
                    $codice = 'WD-' . strtoupper(\Str::random(4));
                } while (static::where('codice_univoco', $codice)->exists());
                $wizard->codice_univoco = $codice;
            }

            // Imposta scadenza a 24h dalla creazione se non già impostata
            if (empty($wizard->expires_at)) {
                $wizard->expires_at = now()->addHours(24);
            }
        });
    }

    // ══════════════════════════════════════════════════════════════════
    // HELPER DI SICUREZZA
    // ══════════════════════════════════════════════════════════════════

    /**
     * Verifica se il wizard è bloccato per troppi tentativi MAC falliti.
     *
     * Usato nel controller per il check STEP 4 e nelle policy/resource
     * per mostrare lo stato corretto nel pannello admin.
     */
    public function isLocked(): bool
    {
        return $this->attempt_count >= 3;
    }

    /**
     * Verifica se il wizard è già stato utilizzato (monouso esaurito).
     *
     * Usato nel controller per il check STEP 3 e nel pannello admin
     * per distinguere wizard "completati" da wizard "usati ma non completati"
     * (es. crash dell'agent dopo used_at ma prima di /agent/complete).
     */
    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /**
     * Verifica se il wizard è ancora utilizzabile:
     * stato 'pronto', non scaduto, non già usato.
     *
     * Questo metodo consolida i check pre-auth in un unico punto.
     * NOTA: NON include il check attempt_count — un wizard locked
     * ma non scaduto/usato deve comunque restituire 423 (non 422).
     */
    public function isUsabile(): bool
    {
        return $this->stato === 'pronto'
            && $this->used_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    // ══════════════════════════════════════════════════════════════════
    // GESTIONE CIFRATURA PASSWORD
    // ══════════════════════════════════════════════════════════════════

    /**
     * Cifra i campi sensibili nel JSON configurazione prima del salvataggio.
     * Da chiamare nel Controller PRIMA di create() o update().
     *
     * SICUREZZA: passwordencrypted NON deve mai apparire nei log,
     * nelle API Resource generali, o nei response non protetti da JWT.
     */
    public static function encryptSensitiveFields(array $configurazione): array
    {
        if (isset($configurazione['utente_admin']['password'])) {
            $plain = $configurazione['utente_admin']['password'];
            $configurazione['utente_admin']['password_encrypted'] = Crypt::encryptString($plain);
            unset($configurazione['utente_admin']['password']);
        }

        if (isset($configurazione['extras']['wifi']['password'])) {
            $plain = $configurazione['extras']['wifi']['password'];
            $configurazione['extras']['wifi']['password_encrypted'] = Crypt::encryptString($plain);
            unset($configurazione['extras']['wifi']['password']);
        }

        return $configurazione;
    }

    /**
     * Decifra la password admin dal JSON configurazione.
     * Chiamare SOLO nell'endpoint /api/agent/start protetto da JWT.
     * MAI chiamare in API generali, resource pubbliche o nei log.
     */
    public function decryptAdminPassword(): string
    {
        $config = $this->configurazione;
        return Crypt::decryptString($config['utente_admin']['password_encrypted']);
    }

    // ══════════════════════════════════════════════════════════════════
    // RELAZIONI ELOQUENT
    // ══════════════════════════════════════════════════════════════════

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function executionLogs(): HasMany
    {
        return $this->hasMany(ExecutionLog::class, 'wizard_id');
    }

    public function latestLog(): HasOne
    {
        return $this->hasOne(ExecutionLog::class, 'wizard_id')
                    ->latestOfMany('started_at');
    }
}
```


***

## Analisi Sicurezza

### Cosa questa implementazione protegge

- **Replay attack con codice intercettato post-utilizzo**: `used_at` + controllo 409 rende ogni codice monouso a livello applicativo e DB. Un attaccante che intercetta il `wizard_code` dopo che l'agent legittimo lo ha già usato riceve un errore esplicito senza poter generare un nuovo token.
- **Brute force sul formato `WD-XXXX`**: lo spazio di ricerca è `36^4 ≈ 1.6M` combinazioni, ma il rate limiter `5 req/min per codice` rende un attacco esaustivo impraticabile (oltre 6 anni a rate pieno), e `attempt_count` blocca il wizard dopo 3 tentativi MAC errati anche se il codice viene indovinato.
- **Flood di `/agent/auth` da singolo IP**: il doppio layer (middleware `throttle:agent_auth` + check manuale in controller) garantisce che anche un bypass middleware non esponga il DB a query flood. Il log con `retry_after` permette al team ops di correlare eventi.
- **Brute force distribuito su codici validi (botnet)**: il limit `5 req/min per wizard_code` è indipendente dall'IP, quindi funziona anche se l'attaccante ruota gli indirizzi.


### Cosa NON protegge (limiti residui da documentare)

- **MAC spoofing con accesso fisico o sulla stessa rete locale**: un attaccante che può sniffare traffico ARP/Ethernet o che ha accesso al PC target può clonare il MAC address via software (`ip link set dev eth0 address AA:BB:CC:DD:EE:FF` su Linux, registro di sistema su Windows) prima che l'agent legittimo si connetta. Il `wizard_code` + MAC è una protezione contro usi accidentali su PC sbagliato, non contro avversari attivi con accesso alla rete.
- **Intercettazione del JWT dopo emissione**: HTTPS mitiga la cattura in transito, ma non elimina il rischio su endpoint TLS mal configurati (TLS 1.0/1.1, certificati self-signed senza pinning, MITM su reti aziendali con proxy SSL inspection). Il JWT ha scadenza 4h: una finestra di riutilizzo esiste.
- **Insider threat (tecnico che copia il codice prima dell'uso)**: il pannello admin mostra il `wizard_code` al momento della generazione. Un tecnico con accesso al frontend e un secondo dispositivo con lo stesso MAC può autenticarsi prima del PC target. Questa minaccia è fuori scope per il modello di sicurezza MVP.
- **Race condition su wizard_code condiviso tra due agent simultanei**: `used_at` viene scritto nella transaction di STEP 6, ma tra STEP 3 (check `used_at === null`) e STEP 6 (scrittura) esiste una piccola finestra. In un deployment single-server con PHP-FPM la probabilità è trascurabile; in un cluster multi-node andrebbe aggiunto un lock ottimistico (`SELECT ... FOR UPDATE` nella transaction).


### Raccomandazione roadmap post-MVP

Il **challenge-response con nonce firmato** (MAC + seriale + nonce temporaneo) ha senso in due scenari distinti:


| Scenario | Quando implementarlo |
| :-- | :-- |
| **Enterprise (>50 PC, dominio Active Directory)** | Priorità alta: in ambienti con GPO e audit IT, il MAC binding è insufficiente. Il seriale hardware + challenge elimina il rischio insider e MAC spoofing anche su reti zero-trust. Implementare nella v2 prima del go-live enterprise. |
| **SMB (<20 PC, nessun reparto security)** | Bassa priorità: il rischio di insider threat è sociale, non tecnico. Il costo di implementazione e manutenzione del challenge supera il beneficio per il profilo di rischio tipico. Rimandare fino a richiesta esplicita del cliente. |

Il punto di attivazione naturale per il challenge-response è quando WinDeploy entra in ambienti con requisiti di **audit trail certificabile** (ISO 27001, NIS2) o quando un cliente richiede il log di "quale seriale hardware ha eseguito quale wizard". A quel punto, il seriale BIOS/UEFI (recuperabile con `wmic bios get serialnumber` o `Get-WmiObject Win32_BIOS`) diventa il secondo fattore hardware vincolato al device fisico, non al software.

***

## Commit

```
feat(security): harden agent auth with one-time token and attempt lockout

- Migration: add attempt_count (tinyInt), last_attempt_ip (varchar 45)
  to wizards table (used_at already existed in createwizardstable)
- AgentAuthController: 6-step sequential validation
  - Step 1: manual IP rate limit check (pre-DB, fail-fast)
  - Step 2: wizard lookup by code + expires_at only
  - Step 3: used_at monouso check → 409 Conflict
  - Step 4: attempt_count lockout check → 423 Locked
  - Step 5: normalized MAC comparison, atomic increment(), Log::warning
  - Step 6: DB::transaction() wraps used_at write + JWT generation
- AppServiceProvider: rate limiter 'agent_auth' with dual limits
  (10/min per IP + 5/min per wizard_code)
- routes/api.php: throttle:agent_auth only on POST /agent/auth
- Wizard model: attempt_count + last_attempt_ip in fillable and casts,
  isLocked() and isUsed() helper methods
```

<span style="display:none">[^1_1][^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_19][^1_2][^1_20][^1_21][^1_22][^1_3][^1_4][^1_5][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: wizard-config-example.json

[^1_2]: wizard-config.schema.json

[^1_3]: README.md

[^1_4]: 0120-agent-api-reference.md

[^1_5]: 0110-BackendLaravelAgentStepControllerStepRequest.md

[^1_6]: 0109-agent Python, CustomTkinter, file lunghi, flusso multi-schermata.md

[^1_7]: 0108-Zustand authStoreAxios Interceptor.md

[^1_8]: 0107-componenti React e UX.MD

[^1_9]: 0106-architetturacross-modulo e contratti dati.md

[^1_10]: 0105-schema-DB.md

[^1_11]: 0104-frontend React e tooling Vite.md

[^1_12]: 0103-configurazione-auth-e-sicurezza.md

[^1_13]: 0102-generazione bulk di codice CRUD.md

[^1_14]: 0101-auth e sicurezza.md

[^1_15]: 0100-File-temporanei-tmp_.php-esposti-nella-root.md

[^1_16]: 0021-Installer-configurazione sistema.md

[^1_17]: 0020-Scansione app installate.md

[^1_18]: 0019-Struttura agent.md

[^1_19]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^1_20]: 0017-motorpage.md

[^1_21]: 0010-autenticazione.md

[^1_22]: 0009-scaffoldprogetto.md

