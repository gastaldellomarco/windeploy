<?php
// backend/app/Http/Requests/User/StoreUserRequest.php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Autorizzazione: solo admin può creare utenti.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('admin');
    }

    /**
     * Normalizza l'email in minuscolo.
     */
    public function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email))
            ]);
        }
    }

    /**
     * Regole di validazione.
     * I nomi dei campi seguono le colonne della tabella `users` (0105-schema DB.md).
     */
    public function rules(): array
    {
        return [
            'nome'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
            'ruolo'    => ['required', 'in:admin,tecnico,viewer'],
            'attivo'   => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Messaggi di errore personalizzati in italiano.
     */
    public function messages(): array
    {
        return [
            'nome.required'     => 'Il nome è obbligatorio.',
            'nome.max'          => 'Il nome non può superare 255 caratteri.',
            'email.required'    => 'L\'email è obbligatoria.',
            'email.email'       => 'Inserire un indirizzo email valido.',
            'email.unique'      => 'Questa email è già utilizzata.',
            'password.required' => 'La password è obbligatoria.',
            'password.min'      => 'La password deve contenere almeno 8 caratteri.',
            'password.confirmed'=> 'La conferma password non corrisponde.',
            'ruolo.required'    => 'Il ruolo è obbligatorio.',
            'ruolo.in'          => 'Il ruolo deve essere uno di: admin, tecnico, viewer.',
        ];
    }
}