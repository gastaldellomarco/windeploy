<?php
// backend/app/Http/Requests/Agent/StepRequest.php
// (già implementato in 0110, nessuna modifica richiesta)

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class StepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wizard_code' => [
                'required',
                'string',
                'regex:/^WD-[A-Z0-9]{5}$/',
            ],
            'step'        => 'required|string|max:100',
            'status'      => 'required|in:in_progress,completed,error,skipped',
            'message'     => 'required|string|max:500',
            'progress'    => 'required|integer|min:0|max:100',
            'timestamp'   => 'required|date_format:Y-m-d\TH:i:s\Z',
        ];
    }

    public function messages(): array
    {
        return [
            'wizard_code.required'   => 'Il codice wizard è obbligatorio.',
            'wizard_code.regex'      => 'Il formato del codice wizard non è valido (es: WD-ABC12).',
            'step.required'          => 'Il nome dello step è obbligatorio.',
            'step.max'               => 'Il nome dello step non può superare i 100 caratteri.',
            'status.required'        => 'Lo stato dello step è obbligatorio.',
            'status.in'              => 'Lo stato deve essere uno di: in_progress, completed, error, skipped.',
            'message.required'       => 'Il messaggio di dettaglio è obbligatorio.',
            'message.max'            => 'Il messaggio non può superare i 500 caratteri.',
            'progress.required'      => 'Il progresso percentuale è obbligatorio.',
            'progress.integer'       => 'Il progresso deve essere un numero intero.',
            'progress.min'           => 'Il progresso non può essere inferiore a 0.',
            'progress.max'           => 'Il progresso non può superare 100.',
            'timestamp.required'     => 'Il timestamp è obbligatorio.',
            'timestamp.date_format'  => 'Il timestamp deve essere in formato ISO8601 UTC (es: 2026-03-06T14:30:00Z).',
        ];
    }
}