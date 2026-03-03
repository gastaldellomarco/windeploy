<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /**
     * Elenco report con filtri (data, tecnico).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Report::with('executionLog.wizard.user');

        // Viewer e tecnico vedono solo i report dei wizard che possono vedere?
        // Per semplicità, admin vede tutto, gli altri vedono solo report associati a wizard di loro proprietà o eseguiti da loro?
        // Dalla descrizione, viewer può vedere dashboard e report, quindi probabilmente tutti i report.
        // Qui seguiamo: admin tutto, tecnico vede i report dei wizard che ha creato, viewer tutti (sola lettura).
        // Adattabile.

        if ($user->ruolo === 'tecnico') {
            // Tecnico vede report dei wizard di sua proprietà
            $query->whereHas('executionLog.wizard', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif ($user->ruolo === 'viewer') {
            // Viewer vede tutti i report (sola lettura)
            // nessun filtro aggiuntivo
        } // admin nessun filtro

        // Filtri
        if ($request->has('da_data')) {
            $query->whereDate('created_at', '>=', $request->da_data);
        }
        if ($request->has('a_data')) {
            $query->whereDate('created_at', '<=', $request->a_data);
        }
        if ($request->has('tecnico_id') && $user->ruolo === 'admin') {
            $query->whereHas('executionLog.wizard', function ($q) use ($request) {
                $q->where('user_id', $request->tecnico_id);
            });
        }

        $reports = $query->latest()->paginate(20);

        return ReportResource::collection($reports);
    }

    /**
     * Mostra dettaglio report con contenuto HTML.
     */
    public function show(Report $report)
    {
        // Autorizzazione: simile a index
        if (Gate::denies('view', $report)) {
            return response()->json(['message' => 'Accesso negato.'], Response::HTTP_FORBIDDEN);
        }

        return new ReportResource($report);
    }

    /**
     * Download del file HTML.
     */
    public function download(Report $report)
    {
        if (Gate::denies('view', $report)) {
            return response()->json(['message' => 'Accesso negato.'], Response::HTTP_FORBIDDEN);
        }

        // Imposta nome file
        $filename = 'report_' . $report->executionLog->wizard->codice_univoco . '_' . $report->created_at->format('Ymd_His') . '.html';

        return response()->streamDownload(function () use ($report) {
            echo $report->html_content;
        }, $filename, ['Content-Type' => 'text/html']);
    }

    /**
     * Elimina report (solo admin).
     */
    public function destroy(Report $report)
    {
        $user = request()->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(['message' => 'Solo gli admin possono eliminare report.'], Response::HTTP_FORBIDDEN);
        }

        $report->delete();

        return response()->json(['message' => 'Report eliminato.']);
    }
}