<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descrizione' => $this->descrizione,
            'scope' => $this->scope,
            'configurazione' => $this->configurazione,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'nome' => $this->user?->nome ?? $this->user?->name ?? null,
                    'email' => $this->user?->email ?? null,
                ];
            }),
            'createdAt' => optional($this->created_at)->toISOString(),
        ];
    }
}
