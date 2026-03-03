<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        $report = $this->resource;

        $executionLog = $report->executionLog ?? null;
        $wizard = $executionLog->wizard ?? null;
        $user = $wizard->user ?? null;

        return [
            'id' => $report->id,
            'created_at' => $report->created_at ? $report->created_at->toDateTimeString() : null,
            'execution_log' => $executionLog ? [
                'id' => $executionLog->id ?? null,
                'started_at' => $executionLog->started_at ?? null,
                'completed_at' => $executionLog->completed_at ?? null,
                'stato' => $executionLog->stato ?? $executionLog->status ?? null,
                'tecnico_nome' => $executionLog->tecnico_nome ?? $executionLog->technicianName ?? null,
                'pc_nome_nuovo' => $executionLog->pc_nome_nuovo ?? $executionLog->pcnome_nuovo ?? $executionLog->pcnomeNuovo ?? null,
            ] : null,
            'wizard' => $wizard ? [
                'id' => $wizard->id ?? null,
                'nome' => $wizard->nome ?? $wizard->name ?? null,
                'codice_univoco' => $wizard->codice_univoco ?? null,
            ] : null,
            'user' => $user ? [
                'id' => $user->id ?? null,
                'nome' => $user->nome ?? $user->name ?? null,
            ] : null,
            // Include a download URL for convenience
            'download_url' => url('/api/reports/' . ($report->id ?? '') . '/download'),
        ];
    }
}
