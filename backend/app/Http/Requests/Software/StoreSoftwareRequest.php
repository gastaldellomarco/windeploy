<?php
// backend/app/Http/Requests/Software/StoreSoftwareRequest.php

namespace App\Http\Requests\Software;

use Illuminate\Foundation\Http\FormRequest;

class StoreSoftwareRequest extends FormRequest
{
    /**
     * Autorizzazione: solo admin può aggiungere software alla libreria.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->hasRole('admin');
    }

    /**
     * Regole di validazione per la creazione di un software.
     * I campi seguono la tabella software_library definita in 0105-schema DB.md.
     */
    public function rules(): array
    {
        return [
            'nome'           => ['required', 'string', 'max:255'],
            'versione'       => ['nullable', 'string', 'max:50'],
            'publisher'      => ['nullable', 'string', 'max:255'],
            'tipo'           => ['required', 'string', 'in:winget,exe,msi'],
            'identificatore' => ['required', 'string', 'max:500'],
            'categoria'      => ['nullable', 'string', 'max:100'],
            'icona_url'      => ['nullable', 'url', 'max:500'],
            'attivo'         => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Messaggi di errore personalizzati in italiano.
     */
    public function messages(): array
    {
        return [
            'nome.required'    => 'Il nome del software è obbligatorio.',
            'nome.max'         => 'Il nome non può superare 255 caratteri.',
            'versione.max'     => 'La versione non può superare 50 caratteri.',
            'publisher.max'    => 'Il publisher non può superare 255 caratteri.',
            'tipo.required'    => 'Il tipo è obbligatorio.',
            'tipo.in'          => 'Il tipo deve essere uno di: winget, exe, msi.',
            'identificatore.required' => 'L\'identificatore (winget ID o path) è obbligatorio.',
            'identificatore.max'      => 'L\'identificatore non può superare 500 caratteri.',
            'categoria.max'    => 'La categoria non può superare 100 caratteri.',
            'icona_url.url'    => 'L\'URL dell\'icona non è valido.',
            'icona_url.max'    => 'L\'URL dell\'icona non può superare 500 caratteri.',
        ];
    }
}