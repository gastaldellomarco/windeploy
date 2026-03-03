<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentAbortRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'execution_log_id' => 'required|integer|exists:execution_logs,id',
            'motivo'           => 'required|string|max:1000',
        ];
    }
}