<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SoftwareLibraryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'versione' => $this->versione,
            'publisher' => $this->publisher,
            'tipo' => $this->tipo,
            'identificatore' => $this->identificatore,
            'categoria' => $this->categoria,
            'iconaurl' => $this->iconaurl ?? $this->icona_url ?? null,
            'attivo' => (bool) ($this->attivo ?? false),

            'createdat' => $this->created_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
