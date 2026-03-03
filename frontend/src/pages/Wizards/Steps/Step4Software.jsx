import React, { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import client from '../../../api/client';

async function fetchSoftware() {
  const res = await client.get('/software', { params: { attivo: 1 } });
  return res.data;
}

function normalizeSoftware(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (payload.data && Array.isArray(payload.data)) return payload.data;
  if (payload.data && payload.data.data && Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function mapSoftwareRow(item) {
  const s = item?.data ? item.data : item;
  return {
    id: s?.id,
    name: s?.nome ?? s?.name ?? '—',
    version: s?.versione ?? s?.version ?? '—',
    type: String(s?.tipo ?? s?.type ?? '').toLowerCase(),
    category: s?.categoria ?? s?.category ?? 'Altro',
  };
}

function categoryEmoji(cat) {
  const c = String(cat || '').toLowerCase();
  if (c.includes('browser')) return '🌐';
  if (c.includes('office') || c.includes('prod')) return '📄';
  if (c.includes('sic') || c.includes('security')) return '🛡️';
  if (c.includes('util')) return '🧰';
  if (c.includes('mult')) return '🎞️';
  if (c.includes('dev')) return '🧑‍💻';
  return '📦';
}

function TypeBadge({ type }) {
  const t = String(type || '').toLowerCase();
  const label = t === 'winget' ? 'Winget' : t === 'exe' ? 'EXE' : t === 'msi' ? 'MSI' : (type || '—');
  const cls = t === 'winget'
    ? 'bg-sky-500/10 text-sky-300 border-sky-500/30'
    : 'bg-slate-700/30 text-slate-200 border-slate-700';

  return (
    <span className={`inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium ${cls}`}>
      {label}
    </span>
  );
}

export default function Step4Software({ wizard, dispatch }) {
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('ALL');

  const softwareQuery = useQuery({
    queryKey: ['software', 'attivo=1'],
    queryFn: fetchSoftware,
    staleTime: 30 * 1000,
  });

  const software = useMemo(
    () => normalizeSoftware(softwareQuery.data).map(mapSoftwareRow).filter((s) => s.id != null),
    [softwareQuery.data]
  );

  const categories = useMemo(() => {
    const set = new Set(software.map((s) => String(s.category || 'Altro')));
    return ['ALL', ...Array.from(set).sort()];
  }, [software]);

  const filtered = useMemo(() => {
    const q = String(search || '').trim().toLowerCase();
    return software.filter((s) => {
      const matchSearch = !q || String(s.name).toLowerCase().includes(q);
      const matchCat = category === 'ALL' || String(s.category) === category;
      return matchSearch && matchCat;
    });
  }, [software, search, category]);

  const selectedSet = useMemo(() => new Set(wizard.software.selectedIds || []), [wizard.software.selectedIds]);

  function toggle(id) {
    const current = Array.isArray(wizard.software.selectedIds) ? wizard.software.selectedIds : [];
    const has = current.includes(id);
    const next = has ? current.filter((x) => x !== id) : [...current, id];
    dispatch({ type: 'PATCH_PATH', payload: { path: ['software', 'selectedIds'], value: next } });
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
          <div className="text-sm font-semibold text-slate-100">STEP 4 — Software da installare</div>
          <div className="mt-1 text-sm text-slate-400">Lista caricata da /api/software?attivo=1.</div>
        </div>

        <div className="rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-sm text-slate-200">
          <span className="text-slate-400">Selezionati:</span> {selectedSet.size}
        </div>
      </div>

      <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
        <div className="md:col-span-2 rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label className="block text-xs font-medium text-slate-300" htmlFor="search">
            Ricerca
          </label>
          <input
            id="search"
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Filtra per nome…"
            className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
          />
        </div>

        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label className="block text-xs font-medium text-slate-300" htmlFor="cat">
            Categoria
          </label>
          <select
            id="cat"
            value={category}
            onChange={(e) => setCategory(e.target.value)}
            className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
          >
            {categories.map((c) => (
              <option key={c} value={c}>
                {c === 'ALL' ? 'Tutte' : c}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-900/30">
        <div className="border-b border-slate-800 px-4 py-3 text-sm font-medium text-slate-200">
          Catalogo software
        </div>

        {softwareQuery.isLoading && <div className="p-4 text-sm text-slate-400">Caricamento…</div>}
        {softwareQuery.isError && (
          <div className="p-4 text-sm text-rose-300">
            Errore caricamento software: {String(softwareQuery.error?.message || 'richiesta fallita')}
          </div>
        )}

        {!softwareQuery.isLoading && !softwareQuery.isError && filtered.length === 0 && (
          <div className="p-4 text-sm text-slate-400">Nessun risultato.</div>
        )}

        {!softwareQuery.isLoading && !softwareQuery.isError && filtered.length > 0 && (
          <div className="max-h-[520px] overflow-auto">
            {filtered.map((s) => {
              const checked = selectedSet.has(s.id);
              return (
                <label
                  key={s.id}
                  className="flex cursor-pointer items-center justify-between gap-3 border-t border-slate-800 px-4 py-3 hover:bg-slate-950/40"
                >
                  <div className="flex items-center gap-3">
                    <input
                      type="checkbox"
                      checked={checked}
                      onChange={() => toggle(s.id)}
                      className="h-4 w-4 rounded border-slate-700 bg-slate-950 text-sky-500"
                    />
                    <div className="text-xl">{categoryEmoji(s.category)}</div>
                    <div>
                      <div className="text-sm font-medium text-slate-100">{s.name}</div>
                      <div className="text-xs text-slate-400">
                        {s.category} · v{s.version}
                      </div>
                    </div>
                  </div>

                  <TypeBadge type={s.type} />
                </label>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
