<?php
// File: app/Http/Requests/User/UserUpdateRequest.php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // solo admin tramite middleware
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'nome'     => ['sometimes', 'string', 'max:100'],
            'email'    => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $userId],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:128'],
            'ruolo'    => ['sometimes', 'string', 'in:admin,tecnico,viewer'],
            'attivo'   => ['sometimes', 'boolean'],
        ];
    }
}
