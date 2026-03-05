<?php
// File: app/Http/Requests/User/UserStoreRequest.php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // solo admin tramite middleware
    }

    public function rules(): array
    {
        return [
            'nome'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['sometimes', 'string', 'min:8', 'max:128'],
            'ruolo'    => ['required', 'string', 'in:admin,tecnico,viewer'],
            'attivo'   => ['sometimes', 'boolean'],
        ];
    }
}
