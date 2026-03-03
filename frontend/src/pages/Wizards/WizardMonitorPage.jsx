import React from 'react';
import { useParams, NavLink } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, Activity } from 'lucide-react';
import client from '../../api/client';

async function fetchMonitor(wizardId) {
  const res = await client.get(`/wizards/${wizardId}/monitor`);
  return res.data;
}

export default function WizardMonitorPage() {
  const { id } = useParams();

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['wizardMonitor', id],
    queryFn: () => fetchMonitor(id),
    refetchInterval: (query) => {
      const d = query.state.data;
      if (!d) return 5000;

      // If no execution exists yet, poll less frequently (pending)
      if (d.pending) return 30000; // 30s

      // Determine execution status, prefer executionstato but fallback to stato
      const executionStatus = String(d.executionstato ?? d.stato ?? '').toLowerCase();
      if (['completato', 'errore', 'abortito'].includes(executionStatus)) return false;

      // Default fast polling while in progress
      return 5000;
    },
    staleTime: 0,
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold text-slate-100">Monitor wizard</h1>
          <p className="mt-1 text-sm text-slate-400">
            Aggiornamento automatico ogni 5s.
          </p>
        </div>

        <NavLink
          to="/wizards"
          className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm text-slate-200 hover:bg-slate-900"
        >
          <ArrowLeft className="h-4 w-4" />
          Torna alla lista
        </NavLink>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
        {isLoading && <div className="text-sm text-slate-400">Caricamento…</div>}

        {isError && (
          <div className="text-sm text-rose-300">
            Errore: {String(error?.message || 'richiesta fallita')}

            <div className="mt-2 text-xs text-slate-300">
              Status: {String(error?.response?.status ?? '—')}
            </div>

            <pre className="mt-2 max-h-48 overflow-auto rounded bg-slate-950 p-3 text-xs text-slate-200">
              {JSON.stringify(error?.response?.data ?? null, null, 2)}
            </pre>
          </div>
        )}

        {!isLoading && !isError && (
          <div className="space-y-4">
            {data?.pending === true && (
              <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                <div className="text-sm text-slate-300">In attesa di avvio</div>
                <div className="mt-1 text-xs text-slate-400">{data?.message ?? 'Nessuna esecuzione avviata.'}</div>
              </div>
            )}
            <div className="flex items-center gap-2 text-slate-200">
              <Activity className="h-4 w-4 text-sky-400" />
              <span className="text-sm font-medium">Stato esecuzione</span>
            </div>

            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
              <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                <div className="text-xs text-slate-400">Wizard ID</div>
                <div className="mt-1 font-mono text-sm text-slate-100">{data?.wizardid ?? '—'}</div>
              </div>

              <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                <div className="text-xs text-slate-400">Execution Log ID</div>
                <div className="mt-1 font-mono text-sm text-slate-100">{data?.executionlogid ?? '—'}</div>
              </div>

              <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                <div className="text-xs text-slate-400">Wizard stato</div>
                <div className="mt-1 text-sm text-slate-100">{data?.wizardstato ?? data?.stato ?? '—'}</div>
              </div>

              <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                <div className="text-xs text-slate-400">Execution stato</div>
                <div className="mt-1 text-sm text-slate-100">{data?.executionstato ?? data?.stato ?? '—'}</div>
              </div>
            </div>

            <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
              <div className="text-xs text-slate-400">Step corrente</div>
              <div className="mt-1 text-sm text-slate-100">{data?.stepcorrente ?? '—'}</div>
            </div>

            <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
              <div className="text-xs text-slate-400">Log dettagliato (JSON)</div>
              <pre className="mt-2 max-h-96 overflow-auto rounded bg-slate-950 p-3 text-xs text-slate-200">
                {JSON.stringify(data?.logdettagliato ?? null, null, 2)}
              </pre>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
