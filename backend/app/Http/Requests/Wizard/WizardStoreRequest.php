<?php
// backend/app/Http/Requests/Wizard/WizardStoreRequest.php

namespace App\Http\Requests\Wizard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WizardStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorizzazione per ruolo gestita nel Controller.
        // Qui solo validazione strutturale del payload.
        return true;
    }

    /**
     * Normalizza il payload prima della validazione.
     * Se 'configurazione' arriva come stringa JSON (multipart/form-data),
     * la decodifica in array prima che le rules vengano applicate.
     */
    public function prepareForValidation(): void
    {
        if ($this->has('configurazione') && is_string($this->input('configurazione'))) {
            $decoded = json_decode($this->input('configurazione'), true);
            if (is_array($decoded)) {
                $this->merge(['configurazione' => $decoded]);
            }
        }

        // Normalizza template_id: stringa vuota → null
        if ($this->has('template_id') && $this->input('template_id') === '') {
            $this->merge(['template_id' => null]);
        }

        // Normalizza note_interne: stringa vuota → null
        if ($this->has('note_interne') && $this->input('note_interne') === '') {
            $this->merge(['note_interne' => null]);
        }
    }

    public function rules(): array
    {
        return [
            // ── Campi radice wizard ──────────────────────────────────────────
            'nome'        => 'required|string|max:150',
            'template_id' => 'nullable|exists:templates,id',
            'note_interne'=> 'nullable|string|max:1000',
            'wallpaper'   => 'nullable|file|image|max:5120', // max 5 MB

            // ── Configurazione (oggetto root) ─────────────────────────────────
            'configurazione'         => 'required|array',
            'configurazione.version' => 'required|string|in:1.0',

            // ── Nome PC ──────────────────────────────────────────────────────
            'configurazione.pc_name' => [
                'required',
                'string',
                'min:1',
                'max:15',
                'regex:/^[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?$/',
            ],

            // ── Utente Admin ─────────────────────────────────────────────────
            'configurazione.admin_user'                      => 'required|array',
            'configurazione.admin_user.username'             => [
                'required',
                'string',
                'min:1',
                'max:50',
                'regex:/^[a-zA-Z0-9._-]+$/',
            ],
            // La password arriva in chiaro solo in fase di creazione.
            // Il Controller la cifra immediatamente con EncryptionService.
            'configurazione.admin_user.password'             => 'sometimes|required|string|min:6|max:128',
            'configurazione.admin_user.remove_setup_account' => 'sometimes|boolean',

            // ── Software da installare ────────────────────────────────────────
            'configurazione.software'               => 'present|array',
            'configurazione.software.*.id'          => 'required|integer|exists:software_library,id',
            'configurazione.software.*.winget_id'   => 'required|string|min:1|max:255',
            'configurazione.software.*.name'        => 'required|string|min:1|max:255',
            'configurazione.software.*.type'        => 'required|string|in:winget,exe,msi',
            'configurazione.software.*.download_url'=> 'nullable|url|max:2048',

            // ── Bloatware ─────────────────────────────────────────────────────
            'configurazione.bloatware'                       => 'present|array',
            'configurazione.bloatware.*.package_name'        => 'required|string|min:1|max:255',
            'configurazione.bloatware.*.display_name'        => 'required|string|min:1|max:255',
            'configurazione.bloatware.*.selected'            => 'required|boolean',

            // ── Power Plan ────────────────────────────────────────────────────
            'configurazione.power_plan'                      => 'required|array',
            'configurazione.power_plan.type'                 => 'required|string|in:balanced,high_performance,power_saver,custom',
            'configurazione.power_plan.screen_timeout_ac'    => 'nullable|integer|min:1|max:60',
            'configurazione.power_plan.sleep_timeout_ac'     => 'nullable|integer|min:1|max:120',
            'configurazione.power_plan.cpu_min_percent'      => 'sometimes|integer|min:0|max:100',
            'configurazione.power_plan.cpu_max_percent'      => 'sometimes|integer|min:0|max:100',

            // ── Extras (opzionali) ────────────────────────────────────────────
            'configurazione.extras'                          => 'nullable|array',
            'configurazione.extras.timezone'                 => 'sometimes|nullable|string|min:1|max:100',
            'configurazione.extras.language'                 => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^[a-z]{2}-[A-Z]{2}$/',
            ],
            'configurazione.extras.keyboard_layout'          => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^[a-z]{2}-[A-Z]{2}$/',
            ],
            'configurazione.extras.wallpaper_url'            => 'sometimes|nullable|string|max:2048',
            'configurazione.extras.wifi'                     => 'sometimes|nullable|array',
            'configurazione.extras.wifi.ssid'                => 'required_with:configurazione.extras.wifi|string|min:1|max:32',
            'configurazione.extras.wifi.password'            => 'sometimes|string|min:1|max:128',
            'configurazione.extras.windows_update'           => 'sometimes|nullable|string|in:auto,download_only,manual',
        ];
    }

    public function messages(): array
    {
        return [
            // ── Campi radice ──────────────────────────────────────────────────
            'nome.required'        => 'Il nome del wizard è obbligatorio.',
            'nome.max'             => 'Il nome del wizard non può superare 150 caratteri.',
            'template_id.exists'   => 'Il template selezionato non esiste.',
            'wallpaper.image'      => 'Il wallpaper deve essere un\'immagine (JPG, PNG, GIF, ecc.).',
            'wallpaper.max'        => 'Il wallpaper non può superare 5 MB.',

            // ── Versione schema ───────────────────────────────────────────────
            'configurazione.required'        => 'La configurazione è obbligatoria.',
            'configurazione.version.required'=> 'La versione dello schema è obbligatoria.',
            'configurazione.version.in'      => 'Versione schema non supportata. Attesa: 1.0.',

            // ── Nome PC ───────────────────────────────────────────────────────
            'configurazione.pc_name.required' => 'Il nome PC è obbligatorio.',
            'configurazione.pc_name.max'      => 'Il nome PC non può superare 15 caratteri (limite Windows).',
            'configurazione.pc_name.regex'    => 'Il nome PC può contenere solo lettere, numeri e trattini, e non può iniziare o finire con un trattino.',

            // ── Utente Admin ──────────────────────────────────────────────────
            'configurazione.admin_user.required'             => 'La sezione utente admin è obbligatoria.',
            'configurazione.admin_user.username.required'    => 'Lo username dell\'admin è obbligatorio.',
            'configurazione.admin_user.username.max'         => 'Lo username non può superare 50 caratteri.',
            'configurazione.admin_user.username.regex'       => 'Lo username può contenere solo lettere, numeri, punti, underscore e trattini (nessuno spazio).',
            'configurazione.admin_user.password.required'    => 'La password è obbligatoria.',
            'configurazione.admin_user.password.min'         => 'La password deve contenere almeno 6 caratteri.',
            'configurazione.admin_user.password.max'         => 'La password non può superare 128 caratteri.',

            // ── Software ──────────────────────────────────────────────────────
            'configurazione.software.*.id.required'       => 'Ogni software deve avere un ID valido.',
            'configurazione.software.*.id.exists'         => 'Il software con ID :input non esiste nella libreria.',
            'configurazione.software.*.winget_id.required'=> 'Ogni software deve avere un identificatore winget.',
            'configurazione.software.*.type.in'           => 'Il tipo di installazione deve essere: winget, exe o msi.',
            'configurazione.software.*.download_url.url'  => 'L\'URL di download del software non è un URL valido.',

            // ── Bloatware ─────────────────────────────────────────────────────
            'configurazione.bloatware.*.package_name.required' => 'Ogni voce bloatware deve avere un nome pacchetto.',
            'configurazione.bloatware.*.selected.required'     => 'Lo stato di selezione del bloatware è obbligatorio.',

            // ── Power Plan ────────────────────────────────────────────────────
            'configurazione.power_plan.required'               => 'La configurazione del power plan è obbligatoria.',
            'configurazione.power_plan.type.required'          => 'Il tipo di power plan è obbligatorio.',
            'configurazione.power_plan.type.in'                => 'Il tipo di power plan deve essere: balanced, high_performance, power_saver o custom.',
            'configurazione.power_plan.screen_timeout_ac.min'  => 'Il timeout schermo deve essere almeno 1 minuto.',
            'configurazione.power_plan.screen_timeout_ac.max'  => 'Il timeout schermo non può superare 60 minuti.',
            'configurazione.power_plan.sleep_timeout_ac.min'   => 'Il timeout sospensione deve essere almeno 1 minuto.',
            'configurazione.power_plan.sleep_timeout_ac.max'   => 'Il timeout sospensione non può superare 120 minuti.',
            'configurazione.power_plan.cpu_min_percent.min'    => 'La percentuale CPU minima non può essere inferiore a 0.',
            'configurazione.power_plan.cpu_min_percent.max'    => 'La percentuale CPU minima non può superare 100.',
            'configurazione.power_plan.cpu_max_percent.max'    => 'La percentuale CPU massima non può superare 100.',

            // ── Extras ────────────────────────────────────────────────────────
            'configurazione.extras.language.regex'             => 'La lingua deve essere nel formato es. it-IT.',
            'configurazione.extras.keyboard_layout.regex'      => 'Il layout tastiera deve essere nel formato es. it-IT.',
            'configurazione.extras.wifi.ssid.required_with'    => 'L\'SSID è obbligatorio quando si configura il WiFi.',
            'configurazione.extras.wifi.ssid.max'              => 'L\'SSID non può superare 32 caratteri.',
            'configurazione.extras.windows_update.in'          => 'La policy Windows Update deve essere: auto, download_only o manual.',
        ];
    }

    /**
     * Validazione custom post-rules: cpu_min_percent non deve superare cpu_max_percent.
     * Aggiunge un errore manuale se la regola non è rispettata.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $config = $this->input('configurazione', []);
            $powerPlan = $config['power_plan'] ?? [];

            if (
                isset($powerPlan['cpu_min_percent'], $powerPlan['cpu_max_percent']) &&
                $powerPlan['cpu_min_percent'] > $powerPlan['cpu_max_percent']
            ) {
                $validator->errors()->add(
                    'configurazione.power_plan.cpu_min_percent',
                    'La percentuale CPU minima non può superare quella massima.'
                );
            }
        });
    }
}
