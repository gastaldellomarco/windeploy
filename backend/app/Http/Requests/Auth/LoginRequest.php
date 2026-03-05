<?php
// File: app/Http/Requests/Auth/LoginRequest.php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * L'endpoint di login è pubblico.
     */
    public function authorize(): bool
    {
        return true;
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
     * Messaggi di errore personalizzati.
     */
    public function messages(): array
    {
        return [
            'email.required'    => 'L\'email è obbligatoria.',
            'email.email'       => 'Formato email non valido.',
            'password.required' => 'La password è obbligatoria.',
            'password.min'      => 'La password deve essere di almeno 8 caratteri.',
        ];
    }
}
