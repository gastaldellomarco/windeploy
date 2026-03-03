<?php

namespace App\Http\Requests\Wizard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WizardStoreRequest extends FormRequest
{
    public function authorize()
    {
        // Autorizzazione già gestita nel controller, qui solo validazione
        return true;
    }

    public function rules()
    {
        return [
            'nome' => 'required|string|max:150',
            'template_id' => 'nullable|exists:templates,id',
            'configurazione' => 'required|array',
            'configurazione.nome_pc' => 'required|string|max:100',
            'configurazione.utente_admin' => 'required|array',
            'configurazione.utente_admin.username' => 'required|string|max:50',
            // password_encrypted non viene inviata, ma password in chiaro se presente
            'configurazione.utente_admin.password' => 'sometimes|string|min:6|max:128',
            'configurazione.software_installa' => 'array',
            'configurazione.software_installa.*' => 'integer|exists:software_library,id',
            'configurazione.bloatware_default' => 'array',
            'configurazione.bloatware_default.*' => 'string|max:255',
            'configurazione.power_plan' => 'array',
            'configurazione.extras' => 'array',
        ];
    }
}