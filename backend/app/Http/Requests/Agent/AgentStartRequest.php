<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentStartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // il middleware auth:api ha già validato il token
    }

    public function rules(): array
    {
        return [
            'pc_info.nome_originale'  => 'required|string|max:100',
            'pc_info.cpu'             => 'nullable|string|max:255',
            'pc_info.ram'             => 'nullable|integer|min:1',
            'pc_info.disco'           => 'nullable|integer|min:1',
            'pc_info.windows_version' => 'nullable|string|max:50',
        ];
    }
}