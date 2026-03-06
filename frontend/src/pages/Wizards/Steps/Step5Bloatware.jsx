import React, { useMemo } from 'react';
import { BLOATWARE_LIST } from '../../../data/bloatware';

export default function Step5Bloatware({ wizard, dispatch }) {
  const selected = useMemo(() => new Set(wizard.bloatware.preselected || []), [wizard.bloatware.preselected]);

  function setAll(flag) {
    dispatch({
      type: 'PATCH_PATH',
      payload: { path: ['bloatware', 'preselected'], value: flag ? [...BLOATWARE_LIST] : [] },
    });
  }

  function toggle(item) {
    const current = Array.isArray(wizard.bloatware.preselected) ? wizard.bloatware.preselected : [];
    const has = current.includes(item);
    const next = has ? current.filter((x) => x !== item) : [...current, item];
    dispatch({ type: 'PATCH_PATH', payload: { path: ['bloatware', 'preselected'], value: next } });
  }

  return (
    <div className="space-y-5">
      <div>
        <div className="text-sm font-semibold text-slate-100">STEP 5 — Bloatware da pre-selezionare</div>
        <div className="mt-1 text-sm text-slate-400">
          Queste app risulteranno pre-spuntate nell’agent ma l’utente potrà deselezionarle.
        </div>
      </div>

      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div className="rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-sm text-slate-200">
          Selezionati: <span className="font-mono">{selected.size}</span>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => setAll(true)}
            className="rounded-lg bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-500"
          >
            Seleziona tutti
          </button>
          <button
            type="button"
            onClick={() => setAll(false)}
            className="rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
          >
            Deseleziona tutti
          </button>
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-900/30">
        <div className="border-b border-slate-800 px-4 py-3 text-sm font-medium text-slate-200">
          Lista bloatware (fissa)
        </div>

        <div className="max-h-[520px] overflow-auto">
          {BLOATWARE_LIST.map((app) => (
            <label
              key={app}
              className="flex cursor-pointer items-center justify-between gap-3 border-t border-slate-800 px-4 py-3 hover:bg-slate-950/40"
            >
              <div className="flex items-center gap-3">
                <input
                  type="checkbox"
                  checked={selected.has(app)}
                  onChange={() => toggle(app)}
                  className="h-4 w-4 rounded border-slate-700 bg-slate-950 text-sky-500"
                />
                <div>
                  <div className="font-mono text-sm text-slate-100">{app}</div>
                  <div className="text-xs text-slate-400">App Windows comune non necessaria</div>
                </div>
              </div>

              <span className="text-xs text-slate-400">preselect</span>
            </label>
          ))}
        </div>
      </div>
    </div>
  );
}
