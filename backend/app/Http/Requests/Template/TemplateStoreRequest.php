<?php

namespace App\Http\Requests\Template;

use Illuminate\Foundation\Http\FormRequest;

class TemplateStoreRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nome' => 'required|string|max:150',
            'descrizione' => 'nullable|string',
            'scope' => 'sometimes|in:globale,personale',
            'configurazione' => 'required|array',
            // ... validazione struttura configurazione (simile a wizard)
        ];
    }
}