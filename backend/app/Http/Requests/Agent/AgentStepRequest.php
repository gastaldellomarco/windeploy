<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentStepRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'execution_log_id'   => 'required|integer|exists:execution_logs,id',
            'step'               => 'required|array',
            'step.nome'          => 'required|string|max:100',
            'step.stato'         => 'required|in:completato,errore,avviso',
            'step.messaggio'     => 'nullable|string|max:1000',
        ];
    }
}