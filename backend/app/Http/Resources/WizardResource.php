<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WizardResource extends JsonResource
{
    public function toArray($request)
    {
        $config = $this->configurazione;
        // Rimuovi eventuali campi cifrati dalla risposta pubblica
        if (isset($config['utente_admin']['password_encrypted'])) {
            unset($config['utente_admin']['password_encrypted']);
        }
        if (isset($config['extras']['wifi']['password_encrypted'])) {
            unset($config['extras']['wifi']['password_encrypted']);
        }

        return [
            'id'               => $this->id,
            'nome'             => $this->nome,
            'codice_univoco'   => $this->codice_univoco,
            'stato'            => $this->stato,
            'configurazione'   => $config,
            'expires_at'       => $this->expires_at?->toIso8601String(),
            'used_at'          => $this->used_at?->toIso8601String(),
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
            'user'             => new UserResource($this->whenLoaded('user')),
            'template'         => new TemplateResource($this->whenLoaded('template')),
        ];
    }
}