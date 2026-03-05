<?php
// File: app/Http/Requests/Template/TemplateUpdateRequest.php

namespace App\Http\Requests\Template;

use Illuminate\Foundation\Http\FormRequest;

class TemplateUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'           => ['required', 'string', 'max:150'],
            'descrizione'    => ['nullable', 'string'],
            'scope'          => ['sometimes', 'string', 'in:globale,personale'],
            'configurazione' => ['required', 'array'],
            'configurazione.nome_pc'                 => ['sometimes', 'string', 'max:100'],
            'configurazione.utente_admin'            => ['sometimes', 'array'],
            'configurazione.utente_admin.username'   => ['sometimes', 'string', 'max:50'],
            'configurazione.utente_admin.password'   => ['sometimes', 'string', 'min:6', 'max:128'],
            'configurazione.software_installa'       => ['sometimes', 'array'],
            'configurazione.software_installa.*.id'  => ['sometimes', 'integer'],
            'configurazione.bloatware_default'       => ['sometimes', 'array'],
            'configurazione.bloatware_default.*'     => ['sometimes', 'string', 'max:255'],
            'configurazione.power_plan'              => ['sometimes', 'array'],
            'configurazione.extras'                  => ['sometimes', 'array'],
        ];
    }
}
