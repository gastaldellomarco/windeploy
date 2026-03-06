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
