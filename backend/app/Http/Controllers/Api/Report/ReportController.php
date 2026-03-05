<?php
// File: app/Http/Controllers/Api/Report/ReportController.php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportIndexRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /**
     * Lista report con filtri e join su execution_logs + users.
     * - Admin: vede tutti.
     * - Tecnico: vede solo i propri (execution_logs.tecnico_user_id = user.id).
     */
    public function index(ReportIndexRequest $request)
    {
        $user = $request->user();

        $query = Report::with(['executionLog.tecnico', 'executionLog.wizard']);

        // Restrizione per ruolo tecnico
        if ($user->ruolo !== 'admin') {
            $query->whereHas('executionLog', function ($q) use ($user) {
                $q->where('tecnico_user_id', $user->id);
            });
        }

        // Filtro data_da / data_a sul created_at del report
        if ($request->filled('data_da')) {
            $query->whereDate('created_at', '>=', $request->input('data_da'));
        }

        if ($request->filled('data_a')) {
            $query->whereDate('created_at', '<=', $request->input('data_a'));
        }

        // Filtro per tecnico (solo admin)
        if ($request->filled('tecnico_id') && $user->ruolo === 'admin') {
            $tecnicoId = (int) $request->input('tecnico_id');

            $query->whereHas('executionLog', function ($q) use ($tecnicoId) {
                $q->where('tecnico_user_id', $tecnicoId);
            });
        }

        // Filtro per stato dell'execution_log
        if ($request->filled('stato')) {
            $stato = $request->input('stato');

            $query->whereHas('executionLog', function ($q) use ($stato) {
                $q->where('stato', $stato);
            });
        }

        $reports = $query->latest()->paginate(20);

        return ReportResource::collection($reports);
    }

    /**
     * Dettaglio report con html_content completo.
     * Applica le stesse regole di visibilità di index().
     */
    public function show(Request $request, Report $report)
    {
        $user = $request->user();
        $report->load('executionLog.tecnico', 'executionLog.wizard.user');

        if (! $this->canViewReport($user->ruolo, $user->id, $report)) {
            return response()->json(
                ['message' => 'Accesso negato.'],
                Response::HTTP_FORBIDDEN
            );
        }

        return response()->json([
            'report'       => new ReportResource($report),
            'html_content' => $report->html_content,
        ]);
    }

    /**
     * Download del report come file HTML.
     * Content-Disposition: attachment; filename="report-{pc_nome}-{data}.html"
     */
    public function download(Request $request, Report $report)
    {
        $user = $request->user();
        $report->load('executionLog');

        if (! $this->canViewReport($user->ruolo, $user->id, $report)) {
            return response()->json(
                ['message' => 'Accesso negato.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $executionLog = $report->executionLog;
        $pcName = $executionLog?->pc_nome_nuovo
            ?? $executionLog?->pc_nome_originale
            ?? 'pc';

        $datePart = $report->created_at
            ? $report->created_at->format('Ymd-His')
            : now()->format('Ymd-His');

        $filename = sprintf('report-%s-%s.html', $pcName, $datePart);

        return response(
            $report->html_content,
            Response::HTTP_OK,
            [
                'Content-Type'        => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    /**
     * Elimina (soft delete se abilitato sul modello) un report.
     * Accesso: solo admin.
     */
    public function destroy(Request $request, Report $report)
    {
        $user = $request->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(
                ['message' => 'Solo gli admin possono eliminare report.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $report->delete();

        return response()->json(['message' => 'Report eliminato.'], Response::HTTP_OK);
    }

    /**
     * Regole di visibilità report basate su ruolo + tecnico.
     */
    private function canViewReport(string $ruolo, int $userId, Report $report): bool
    {
        if ($ruolo === 'admin') {
            return true;
        }

        $executionLog = $report->executionLog;

        if (! $executionLog) {
            return false;
        }

        return (int) $executionLog->tecnico_user_id === $userId;
    }
}
