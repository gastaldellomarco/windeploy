<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentAbortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'execution_log_id' => 'required|integer|exists:execution_logs,id',
            'motivo'           => 'nullable|string|max:1000',
        ];
    }
}