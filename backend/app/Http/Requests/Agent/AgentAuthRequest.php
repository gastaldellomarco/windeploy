<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codice_wizard' => 'required|string|size:7|regex:/^WD-[A-Z0-9]{4}$/',
            'mac_address'   => 'required|string|regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/',
        ];
    }

    public function messages(): array
    {
        return [
            'codice_wizard.regex' => 'Il codice wizard deve essere nel formato WD-XXXX (es. WD-7A3F).',
            'mac_address.regex'   => 'Indirizzo MAC non valido.',
        ];
    }
}