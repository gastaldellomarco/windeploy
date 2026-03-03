<?php

namespace App\Http\Requests\Template;

use Illuminate\Foundation\Http\FormRequest;

class TemplateUpdateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            // For updates, fields are optional but must be valid when present
            'nome' => 'sometimes|string|max:150',
            'descrizione' => 'nullable|string',
            'scope' => 'sometimes|in:globale,personale',
            'configurazione' => 'sometimes|array',
            // other fields may be present depending on UI
        ];
    }
}
