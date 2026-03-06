<?php
// backend/app/Http/Requests/Software/UpdateSoftwareRequest.php

namespace App\Http\Requests\Software;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSoftwareRequest extends FormRequest
{
    /**
     * Autorizzazione: solo admin può modificare software.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->hasRole('admin');
    }

    /**
     * Regole di validazione per l'aggiornamento (PATCH).
     * Tutti i campi sono opzionali.
     */
    public function rules(): array
    {
        return [
            'nome'           => ['sometimes', 'string', 'max:255'],
            'versione'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'publisher'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'tipo'           => ['sometimes', 'string', 'in:winget,exe,msi'],
            'identificatore' => ['sometimes', 'string', 'max:500'],
            'categoria'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'icona_url'      => ['sometimes', 'nullable', 'url', 'max:500'],
            'attivo'         => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Messaggi di errore personalizzati in italiano.
     */
    public function messages(): array
    {
        return [
            'nome.max'         => 'Il nome non può superare 255 caratteri.',
            'versione.max'     => 'La versione non può superare 50 caratteri.',
            'publisher.max'    => 'Il publisher non può superare 255 caratteri.',
            'tipo.in'          => 'Il tipo deve essere uno di: winget, exe, msi.',
            'identificatore.max' => 'L\'identificatore non può superare 500 caratteri.',
            'categoria.max'    => 'La categoria non può superare 100 caratteri.',
            'icona_url.url'    => 'L\'URL dell\'icona non è valido.',
            'icona_url.max'    => 'L\'URL dell\'icona non può superare 500 caratteri.',
        ];
    }
}