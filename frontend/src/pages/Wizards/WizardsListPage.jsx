import React from 'react';
import { NavLink } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Wand2, Plus, Eye } from 'lucide-react';
import client from '../../api/client';

async function fetchWizards() {
  const res = await client.get('/wizards');
  return res.data;
}

function normalizeApiCollection(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (payload.data && Array.isArray(payload.data)) return payload.data;
  if (payload.data && payload.data.data && Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function getWizardRow(item) {
  const w = item?.data ? item.data : item;
  return {
    id: w?.id,
    name: w?.nome ?? w?.name ?? '—',
    code: w?.codiceunivoco ?? w?.code ?? '—',
    status: w?.stato ?? w?.status ?? '—',
    createdAt: w?.created_at ?? w?.createdAt ?? null,
  };
}

function StatusBadge({ status }) {
  const s = String(status || '').toLowerCase();
  let cls = 'bg-slate-700/30 text-slate-200 border-slate-700';
  if (s === 'pronto') cls = 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30';
  if (s === 'bozza') cls = 'bg-amber-500/10 text-amber-300 border-amber-500/30';
  if (s === 'inesecuzione') cls = 'bg-sky-500/10 text-sky-300 border-sky-500/30';
  if (s === 'completato') cls = 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30';
  if (s === 'errore') cls = 'bg-rose-500/10 text-rose-300 border-rose-500/30';

  return (
    <span className={`inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium ${cls}`}>
      {status}
    </span>
  );
}

export default function WizardsListPage() {
  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['wizards'],
    queryFn: fetchWizards,
    staleTime: 15 * 1000,
  });

  const rows = normalizeApiCollection(data).map(getWizardRow);

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold text-slate-100">Wizards</h1>
          <p className="mt-1 text-sm text-slate-400">
            Crea e gestisci i wizard di provisioning per l’agent Windows.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => refetch()}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/50 px-3 py-2 text-sm text-slate-200 hover:bg-slate-900"
          >
            <Wand2 className="h-4 w-4" />
            Aggiorna
          </button>

          <NavLink
            to="/wizards/new"
            className="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-500"
          >
            <Plus className="h-4 w-4" />
            Nuovo wizard
          </NavLink>
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-900/40">
        <div className="border-b border-slate-800 px-4 py-3 text-sm font-medium text-slate-200">
          Lista wizard
        </div>

        {isLoading && (
          <div className="p-4 text-sm text-slate-400">Caricamento…</div>
        )}

        {isError && (
          <div className="p-4 text-sm text-rose-300">
            Errore: {String(error?.message || 'richiesta fallita')}
          </div>
        )}

        {!isLoading && !isError && rows.length === 0 && (
          <div className="p-4 text-sm text-slate-400">
            Nessun wizard trovato. Crea il primo con “Nuovo wizard”.
          </div>
        )}

        {!isLoading && !isError && rows.length > 0 && (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-slate-950/40 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th className="px-4 py-3">Nome</th>
                  <th className="px-4 py-3">Codice</th>
                  <th className="px-4 py-3">Stato</th>
                  <th className="px-4 py-3 text-right">Azioni</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.id} className="border-t border-slate-800">
                    <td className="px-4 py-3 text-slate-100">{r.name}</td>
                    <td className="px-4 py-3 font-mono text-slate-200">{r.code}</td>
                    <td className="px-4 py-3">
                      <StatusBadge status={r.status} />
                    </td>
                    <td className="px-4 py-3 text-right">
                      <NavLink
                        to={`/wizards/${r.id}/monitor`}
                        className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-xs font-medium text-slate-200 hover:bg-slate-950"
                      >
                        <Eye className="h-4 w-4" />
                        Monitor
                      </NavLink>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
