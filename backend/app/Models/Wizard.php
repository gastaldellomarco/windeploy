<?php
// app/Models/Wizard.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class Wizard extends Model
{
    use SoftDeletes;

    // Stati validi — usati nel Controller e nelle Form Request
    public const STATI = ['bozza', 'pronto', 'in_esecuzione', 'completato', 'errore'];

    protected $fillable = [
        'nome',
        'user_id',
        'template_id',
        'codice_univoco',
        'stato',
        'configurazione',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        // Cast a array: Eloquent serializza/deserializza automaticamente il JSON.
        // ATTENZIONE: questo espone password_encrypted come stringa cifrata nell'array.
        // La rimozione avviene a livello di WizardResource, NON qui.
        'configurazione' => 'array',
        'expires_at'     => 'datetime',
        'used_at'        => 'datetime',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    // Genera automaticamente codice univoco e expires_at al momento della creazione
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Wizard $wizard) {
            // Genera codice WD-XXXX se non già impostato
            if (empty($wizard->codice_univoco)) {
                do {
                    $codice = 'WD-' . strtoupper(Str::random(4));
                } while (static::where('codice_univoco', $codice)->exists());

                $wizard->codice_univoco = $codice;
            }

            // Imposta scadenza a +24h dalla creazione
            if (empty($wizard->expires_at)) {
                $wizard->expires_at = now()->addHours(24);
            }
        });
    }

    // Relazioni
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
        return $this->hasOne(ExecutionLog::class, 'wizard_id')->latestOfMany('started_at');
    }

    // Verifica se il wizard è ancora utilizzabile (non scaduto, non già usato)
    public function isUsabile(): bool
    {
        return $this->stato === 'pronto'
            && $this->used_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    // Cifra la password admin nel JSON configurazione prima del salvataggio.
    // Da chiamare nel Controller PRIMA di create() o update().
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

    // Decifra la password admin — SOLO per l'endpoint /api/agent/start (JWT protetto).
    // Mai chiamare in API generali o Resource pubbliche.
    public function decryptAdminPassword(): string
    {
        $config = $this->configurazione;
        return Crypt::decryptString($config['utente_admin']['password_encrypted']);
    }
}
