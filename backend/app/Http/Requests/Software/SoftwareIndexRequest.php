<?php
// File: app/Http/Requests/Software/SoftwareIndexRequest.php

namespace App\Http\Requests\Software;

use Illuminate\Foundation\Http\FormRequest;

class SoftwareIndexRequest extends FormRequest
{
    /**
     * Autorizzazione già gestita da Sanctum; qui sempre true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validazione filtri lista software.
     */
    public function rules(): array
    {
        return [
            'attivo'    => ['sometimes', 'boolean'],
            'categoria' => ['sometimes', 'string', 'max:100'],
            'tipo'      => ['sometimes', 'string', 'in:winget,exe,msi'],
            'search'    => ['sometimes', 'string', 'max:150'],
        ];
    }
}
