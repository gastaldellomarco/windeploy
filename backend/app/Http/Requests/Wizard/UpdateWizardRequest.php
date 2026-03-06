<?php
// backend/app/Http/Requests/Wizard/UpdateWizardRequest.php

namespace App\Http\Requests\Wizard;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWizardRequest extends FormRequest
{
    /**
     * Autorizzazione: solo admin e tecnico possono aggiornare wizard.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->hasAnyRole(['admin', 'tecnico']);
    }

    /**
     * Regole di validazione per aggiornamento (PATCH).
     * Tutti i campi sono opzionali ('sometimes') per supportare modifiche parziali.
     */
    public function rules(): array
    {
        return [
            'nome'         => ['sometimes', 'string', 'max:255'],
            'template_id'  => ['sometimes', 'nullable', 'exists:templates,id'],

            'configurazione'                        => ['sometimes', 'array'],

            'configurazione.pc_name'                => [
                'sometimes',
                'string',
                'max:15',
                'regex:/^[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?$/'
            ],

            'configurazione.admin_user'             => ['sometimes', 'array'],
            'configurazione.admin_user.username'    => ['sometimes', 'string', 'max:20'],
            'configurazione.admin_user.password'    => ['sometimes', 'string', 'min:8'],

            'configurazione.software_list'          => ['sometimes', 'nullable', 'array'],
            'configurazione.software_list.*.id'     => [
                'required_with:configurazione.software_list',
                'exists:software_library,id'
            ],

            'configurazione.power_plan'              => [
                'sometimes',
                'nullable',
                'string',
                'in:balanced,high_performance,power_saver,ultimate'
            ],

            'configurazione.bloatware_to_remove'     => ['sometimes', 'nullable', 'array'],
            'configurazione.bloatware_to_remove.*'   => ['string', 'max:255'],

            'configurazione.extras'                  => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * Messaggi di errore personalizzati in italiano.
     */
    public function messages(): array
    {
        return [
            'nome.max'             => 'Il nome del wizard non può superare 255 caratteri.',
            'template_id.exists'    => 'Il template selezionato non esiste.',

            'configurazione.pc_name.max'      => 'Il nome PC non può superare 15 caratteri (limite NetBIOS).',
            'configurazione.pc_name.regex'    => 'Il nome PC può contenere solo lettere, numeri e trattini; non può iniziare o finire con un trattino.',

            'configurazione.admin_user.username.max' => 'Lo username non può superare 20 caratteri.',
            'configurazione.admin_user.password.min' => 'La password deve contenere almeno 8 caratteri.',

            'configurazione.power_plan.in' => 'Il piano di alimentazione deve essere uno di: balanced, high_performance, power_saver, ultimate.',
        ];
    }
}