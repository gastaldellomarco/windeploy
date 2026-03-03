<?php
// app/Models/Wizard.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use App\Models\User;
use App\Models\Template;

class Wizard extends Model
{
    /**
     * Stati validi per il wizard (usato nei filtri)
     */
    public const STATI = [
        'bozza',
        'pronto',
        'in_esecuzione',
        'completato',
        'errore',
    ];

    /**
     * Relazione con l'utente proprietario del wizard
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relazione con il template (nullable)
     */
    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id');
    }
    protected $fillable = [
        'nome', 'user_id', 'template_id', 'codice_univoco',
        'stato', 'configurazione', 'expires_at', 'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    'used_at'    => 'datetime',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    ];

    /**
     * Override getter: decifra la password admin prima di restituire la configurazione.
     * La password viene decifrata SOLO qui, mai salvata in chiaro.
     */
    public function getConfigurazione(): array
    {
        $config = json_decode($this->attributes['configurazione'], true);
        return $config; // La struttura è già con password encrypted nel campo
    }

    /**
     * Cifra la password admin prima del salvataggio.
     * Chiamare questo metodo nel Controller prima di $wizard->save()
     */
    public static function encryptAdminPassword(array $configurazione): array
    {
        if (isset($configurazione['utente_admin']['password'])) {
            $plain = $configurazione['utente_admin']['password'];
            $configurazione['utente_admin']['password_encrypted'] = Crypt::encryptString($plain);
            unset($configurazione['utente_admin']['password']); // rimuovi il campo plain
        }
        return $configurazione;
    }

    /**
     * Decifra la password admin — usato solo dall'endpoint dedicato all'agent.
     * Non includere mai questa operazione nelle API generiche.
     */
    public static function decryptAdminPassword(array $configurazione): string
    {
        return Crypt::decryptString(
            $configurazione['utente_admin']['password_encrypted']
        );
    }
}
