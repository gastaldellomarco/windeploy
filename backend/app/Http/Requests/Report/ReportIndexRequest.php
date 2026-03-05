<?php
// File: app/Http/Requests/Report/ReportIndexRequest.php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class ReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data_da'    => ['sometimes', 'date'],
            'data_a'     => ['sometimes', 'date', 'after_or_equal:data_da'],
            'tecnico_id' => ['sometimes', 'integer', 'exists:users,id'],
            'stato'      => ['sometimes', 'string', 'in:avviato,incorso,completato,errore,abortito'],
        ];
    }
}
