<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentAuthRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'codice_wizard' => 'required|string|max:10',
            'mac_address'   => [
                'required',
                'string',
                'max:17',
                'regex:/^([0-9A-Fa-f]{2}([:-])){5}([0-9A-Fa-f]{2})$/',
            ],
        ];
    }

    public function messages()
    {
        return [
            'codice_wizard.required' => 'Il codice wizard è obbligatorio.',
            'mac_address.required'   => 'L\'indirizzo MAC è obbligatorio.',
            'mac_address.regex'      => 'Formato MAC non valido (es. 00:11:22:33:44:55).',
        ];
    }
}
