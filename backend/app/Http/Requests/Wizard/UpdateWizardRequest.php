<?php

namespace App\Http\Requests\Wizard;

use Illuminate\Foundation\Http\FormRequest;

class WizardUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'                         => 'sometimes|string|max:150',
            'template_id'                   => 'nullable|integer|exists:templates,id',
            'configurazione'                 => 'sometimes|array',
            'configurazione.nuovo_nome_pc'   => 'nullable|string|max:15',
            'configurazione.utente_admin'    => 'sometimes|array',
            'configurazione.utente_admin.nome' => 'sometimes|string|max:50',
            'configurazione.utente_admin.password' => 'sometimes|string|min:6',
            'configurazione.extras'          => 'nullable|array',
            'configurazione.software_installazione' => 'nullable|array',
        ];
    }
}