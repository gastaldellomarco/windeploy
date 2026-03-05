<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExecutionLogResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'wizard_id'         => $this->wizard_id,
            'pc_nome_originale' => $this->pc_nome_originale,
            'pc_nome_nuovo'     => $this->pc_nome_nuovo,
            'hardware_info'     => $this->hardware_info,
            'stato'             => $this->stato,
            'step_corrente'     => $this->step_corrente,
            'log_dettagliato'   => $this->log_dettagliato,
            'started_at'        => $this->started_at?->toIso8601String(),
            'completed_at'      => $this->completed_at?->toIso8601String(),
            'wizard'            => new WizardResource($this->whenLoaded('wizard')),
            'tecnico'           => new UserResource($this->whenLoaded('tecnico')),
        ];
    }
}