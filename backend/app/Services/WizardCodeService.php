<?php

namespace App\Services;

use App\Models\Wizard;
use Carbon\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Servizio per la generazione e gestione dei codici univoci dei wizard.
 */
class WizardCodeService
{
    /**
     * Genera un codice univoco non salvato (utile per la creazione del wizard).
     * Formato: WD- + 5 caratteri alfanumerici uppercase.
     *
     * @return string
     */
    public function generateUnique(): string
    {
        do {
            $code = 'WD-' . strtoupper(Str::random(5));
            $exists = Wizard::where('codice_univoco', $code)->exists();
        } while ($exists);

        return $code;
    }

    /**
     * Genera un codice univoco e lo salva sul wizard specificato.
     * In caso di collisioni tenta fino a $maxAttempts volte.
     *
     * @param int $wizardId
     * @return string
     * @throws RuntimeException
     */
    public function generate(int $wizardId): string
    {
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $code = 'WD-' . strtoupper(Str::random(5));
            $attempt++;
            $exists = Wizard::where('codice_univoco', $code)->exists();
        } while ($exists && $attempt < $maxAttempts);

        if ($exists) {
            throw new RuntimeException('Impossibile generare un codice univoco dopo ' . $maxAttempts . ' tentativi.');
        }

        $wizard = Wizard::findOrFail($wizardId);
        $wizard->codice_univoco = $code;
        $wizard->expires_at = Carbon::now()->addHours(24);
        $wizard->save();

        return $code;
    }
}