<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'execution_log_id'   => 'required|integer|exists:execution_logs,id',
            'report_html'        => 'required|string',
            'pc_nome_nuovo'      => 'nullable|string|max:100',
            'sommario.installati' => 'nullable|array',
            'sommario.rimossi'    => 'nullable|array',
            'sommario.errori'     => 'nullable|array',
        ];
    }
}