<?php
// File: app/Http/Resources/UserResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Trasforma il modello User in array JSON.
     * Non espone mai la password o campi sensibili.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name ?? $this->nome,
            'nome'       => $this->nome,
            'email'      => $this->email,
            // Provide both `ruolo` (DB enum) and `role` for compatibility
            'ruolo'      => $this->ruolo,
            'role'       => $this->role ?? mb_strtolower((string) $this->ruolo),
            'normalizedRole' => mb_strtolower((string) ($this->role ?? $this->ruolo ?? 'viewer')),
            'last_login' => $this->last_login?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
