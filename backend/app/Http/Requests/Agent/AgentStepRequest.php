<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'execution_log_id' => 'required|integer|exists:execution_logs,id',
            'step.nome'        => 'required|string|max:100',
            'step.stato'       => 'nullable|string|max:50', // es. "ok", "errore", "warning"
            'step.messaggio'   => 'nullable|string',
        ];
    }
}