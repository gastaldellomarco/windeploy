<?php
// backend/app/Http/Requests/User/UpdateUserRequest.php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Autorizzazione: solo admin può modificare utenti.
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
     * Regole di validazione per aggiornamento (PATCH).
     * Tutti i campi sono opzionali.
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'nome'     => ['sometimes', 'string', 'max:255'],
            'email'    => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'password' => ['sometimes', 'string', 'min:8', 'max:128', 'confirmed'],
            'ruolo'    => ['sometimes', 'in:admin,tecnico,viewer'],
            'attivo'   => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Validazione custom per prevenire auto‑demotion.
     * Un admin non può modificare il proprio ruolo (protezione da errori umani).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->route('user');
            if (!$user) {
                return;
            }

            // Se l'utente sta modificando se stesso e sta cambiando ruolo
            if ($this->user()->id === $user->id && $this->has('ruolo')) {
                $validator->errors()->add(
                    'ruolo',
                    'Non puoi modificare il tuo stesso ruolo.'
                );
            }
        });
    }

    /**
     * Messaggi di errore personalizzati in italiano.
     */
    public function messages(): array
    {
        return [
            'nome.max'          => 'Il nome non può superare 255 caratteri.',
            'email.email'       => 'Inserire un indirizzo email valido.',
            'email.unique'      => 'Questa email è già utilizzata.',
            'password.min'      => 'La password deve contenere almeno 8 caratteri.',
            'password.confirmed'=> 'La conferma password non corrisponde.',
            'ruolo.in'          => 'Il ruolo deve essere uno di: admin, tecnico, viewer.',
        ];
    }
}