<?php

namespace App\Http\Requests\Software;

use Illuminate\Foundation\Http\FormRequest;

class SoftwareLibraryUpdateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nome' => 'required|string|max:150',
            'versione' => 'nullable|string|max:50',
            'publisher' => 'nullable|string|max:150',
            'tipo' => 'required|in:winget,exe,msi',
            'identificatore' => 'required|string|max:255',
            'categoria' => 'nullable|string|max:100',
            'icona_url' => 'nullable|url|max:500',
            'attivo' => 'boolean',
        ];
    }
}
