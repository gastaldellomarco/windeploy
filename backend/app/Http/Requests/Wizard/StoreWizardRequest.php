<?php
// backend/app/Http/Requests/Wizard/StoreWizardRequest.php

namespace App\Http\Requests\Wizard;

use Illuminate\Foundation\Http\FormRequest;

class StoreWizardRequest extends FormRequest
{
    /**
     * Autorizzazione: solo admin e tecnico possono creare wizard.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->hasAnyRole(['admin', 'tecnico']);
    }

    /**
     * Regole di validazione.
     *
     * Nota: il campo 'configurazione' verrà salvato come JSON nel DB.
     * Il modello Wizard ha il cast 'configurazione' => 'array',
     * quindi nel controller si può passare direttamente l'array.
     */
    public function rules(): array
    {
        return [
            // Campi principali del wizard
            'nome'         => ['required', 'string', 'max:255'],
            'template_id'  => ['nullable', 'exists:templates,id'],

            // Configurazione JSON (struttura canonica definita in 0106)
            'configurazione'                        => ['required', 'array'],
            'configurazione.pc_name'                => [
                'required',
                'string',
                'max:15',
                'regex:/^[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?$/'
            ],
            'configurazione.admin_user'             => ['required', 'array'],
            'configurazione.admin_user.username'    => ['required', 'string', 'max:20'],
            'configurazione.admin_user.password'    => ['required', 'string', 'min:8'],

            'configurazione.software_list'          => ['nullable', 'array'],
            'configurazione.software_list.*.id'     => [
                'required_with:configurazione.software_list',
                'exists:software_library,id'
            ],

            'configurazione.power_plan'              => [
                'nullable',
                'string',
                'in:balanced,high_performance,power_saver,ultimate'
            ],

            'configurazione.bloatware_to_remove'     => ['nullable', 'array'],
            'configurazione.bloatware_to_remove.*'   => ['string', 'max:255'],

            'configurazione.extras'                  => ['nullable', 'array'],
        ];
    }

    /**
     * Messaggi di errore personalizzati in italiano.
     */
    public function messages(): array
    {
        return [
            'nome.required'        => 'Il nome del wizard è obbligatorio.',
            'nome.max'             => 'Il nome del wizard non può superare 255 caratteri.',
            'template_id.exists'    => 'Il template selezionato non esiste.',

            'configurazione.required' => 'La configurazione è obbligatoria.',

            'configurazione.pc_name.required' => 'Il nome del PC è obbligatorio.',
            'configurazione.pc_name.max'      => 'Il nome PC non può superare 15 caratteri (limite NetBIOS).',
            'configurazione.pc_name.regex'    => 'Il nome PC può contenere solo lettere, numeri e trattini; non può iniziare o finire con un trattino.',

            'configurazione.admin_user.required'         => 'I dati dell\'utente admin sono obbligatori.',
            'configurazione.admin_user.username.required'=> 'Lo username dell\'admin è obbligatorio.',
            'configurazione.admin_user.username.max'     => 'Lo username non può superare 20 caratteri.',
            'configurazione.admin_user.password.required'=> 'La password dell\'admin è obbligatoria.',
            'configurazione.admin_user.password.min'     => 'La password deve contenere almeno 8 caratteri.',

            'configurazione.power_plan.in' => 'Il piano di alimentazione deve essere uno di: balanced, high_performance, power_saver, ultimate.',
        ];
    }
}