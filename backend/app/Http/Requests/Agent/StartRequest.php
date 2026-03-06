<?php
// backend/app/Http/Requests/Agent/StartRequest.php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class StartRequest extends FormRequest
{
    /**
     * Autorizzazione: endpoint pubblico (il middleware throttle e la logica
     * del controller gestiscono l'accesso). Nessun utente autenticato qui.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regole di validazione per l'avvio dell'agent.
     * Il wizard_code deve rispettare il formato WD-XXXX (4 caratteri alfanumerici).
     * Il MAC address viene normalizzato nel controller, qui si valida la forma base.
     */
    public function rules(): array
    {
        return [
            'wizard_code' => [
                'required',
                'string',
                'regex:/^WD-[A-Z0-9]{4}$/',  // formato WD-ABCD (4 caratteri)
            ],
            'mac_address' => [
                'required',
                'string',
                'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', // es. AA:BB:CC:DD:EE:FF
            ],
        ];
    }

    /**
     * Messaggi di errore personalizzati in italiano.
     */
    public function messages(): array
    {
        return [
            'wizard_code.required' => 'Il codice wizard è obbligatorio.',
            'wizard_code.regex'    => 'Il codice wizard deve essere nel formato WD-XXXX (es. WD-ABCD).',
            'mac_address.required' => 'L\'indirizzo MAC è obbligatorio.',
            'mac_address.regex'    => 'Il formato del MAC address non è valido (es. AA:BB:CC:DD:EE:FF).',
        ];
    }
}