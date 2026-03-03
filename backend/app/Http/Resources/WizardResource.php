<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WizardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Names
            'nome' => $this->nome,
            'name' => $this->nome, // optional fallback

            // Code: support both DB naming styles
            'codiceunivoco' => $this->codiceunivoco ?? $this->codice_univoco ?? null,

            // Status
            'stato' => $this->stato,

            // Timestamps: expose both, frontend reads createdat OR createdAt
            'createdat' => $this->created_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),

            // Useful fields (don’t leak secrets!)
            'templateid' => $this->templateid ?? $this->template_id ?? null,
            'userid' => $this->userid ?? $this->user_id ?? null,

            // IMPORTANT: do NOT return plain passwords; ideally don’t return configurazione in listings
            'configurazione' => $this->when(
                $request->routeIs('wizards.show'),
                $this->configurazione
            ),
        ];
    }
}
