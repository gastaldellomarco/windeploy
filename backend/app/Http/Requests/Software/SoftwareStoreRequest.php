<?php
// File: app/Http/Requests/Software/SoftwareStoreRequest.php

namespace App\Http\Requests\Software;

use Illuminate\Foundation\Http\FormRequest;

class SoftwareStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Il controllo di ruolo è nel controller; qui solo validazione.
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'          => ['required', 'string', 'max:150'],
            'versione'      => ['nullable', 'string', 'max:50'],
            'publisher'     => ['nullable', 'string', 'max:150'],
            'tipo'          => ['required', 'string', 'in:winget,exe,msi'],
            'identificatore'=> ['required', 'string', 'max:255'],
            'categoria'     => ['nullable', 'string', 'max:100'],
            'icona_url'     => ['nullable', 'url', 'max:500'],
            'attivo'        => ['sometimes', 'boolean'],
        ];
    }
}
