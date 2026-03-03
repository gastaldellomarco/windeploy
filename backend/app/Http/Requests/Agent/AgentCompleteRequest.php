<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentCompleteRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'execution_log_id'   => 'required|integer|exists:execution_logs,id',
            'report_html'        => 'required|string',
            'pc_nome_nuovo'      => 'required|string|max:100',
            'sommario'           => 'sometimes|array',
            'sommario.installati' => 'sometimes|array',
            'sommario.rimossi'    => 'sometimes|array',
            'sommario.errori'     => 'sometimes|array',
        ];
    }
}