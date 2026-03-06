<?php
// backend/app/Http/Requests/Auth/LoginRequest.php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Autorizzazione: la route è pubblica.
     * Il controller si occuperà dell'autenticazione effettiva.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalizza l'email in minuscolo prima della validazione.
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
     * Regole di validazione per il login.
     */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ];
    }

    /**
     * Messaggi di errore personalizzati in italiano.
     */
    public function messages(): array
    {
        return [
            'email.required'    => 'L\'email è obbligatoria.',
            'email.email'       => 'Inserire un indirizzo email valido.',
            'email.max'         => 'L\'email non può superare 255 caratteri.',
            'password.required' => 'La password è obbligatoria.',
            'password.min'      => 'La password deve contenere almeno 8 caratteri.',
            'password.max'      => 'La password non può superare 128 caratteri.',
        ];
    }
}