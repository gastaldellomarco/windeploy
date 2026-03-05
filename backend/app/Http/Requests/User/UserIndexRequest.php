<?php
// File: app/Http/Requests/User/UserIndexRequest.php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role:admin è già nel middleware di rotta
    }

    public function rules(): array
    {
        return [
            'ruolo'  => ['sometimes', 'string', 'in:admin,tecnico,viewer'],
            'attivo' => ['sometimes', 'boolean'],
        ];
    }
}
