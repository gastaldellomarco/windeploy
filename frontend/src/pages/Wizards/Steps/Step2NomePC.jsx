import React, { useMemo } from 'react';

const DEPARTMENTS = [
  { id: 'IT', label: 'IT' },
  { id: 'AMM', label: 'AMM' },
  { id: 'COM', label: 'COM' },
  { id: 'DEV', label: 'DEV' },
];

export default function Step2NomePC({ wizard, dispatch, validatePcName }) {
  const validation = useMemo(() => validatePcName(wizard.pcName.raw), [wizard.pcName.raw, validatePcName]);

  return (
    <div className="space-y-5">
      <div>
        <div className="text-sm font-semibold text-slate-100">STEP 2 — Nome PC</div>
        <div className="mt-1 text-sm text-slate-400">
          Il nome PC è validato secondo regole compatibili con Windows (max 15, solo alfanumerico e trattini).
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <label className="block text-xs font-medium text-slate-300" htmlFor="pcName">
          Nome PC
        </label>
        <input
          id="pcName"
          type="text"
          value={wizard.pcName.raw}
          onChange={(e) =>
            dispatch({ type: 'PATCH_PATH', payload: { path: ['pcName', 'raw'], value: e.target.value } })
          }
          placeholder="Es. PC-CONT-01"
          className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
        />
        {!validation.ok && (
          <div className="mt-2 text-sm text-rose-300">{validation.message}</div>
        )}
        {validation.ok && wizard.pcName.raw && (
          <div className="mt-2 text-sm text-emerald-300">Nome valido.</div>
        )}

        <div className="mt-4 flex items-center gap-2">
          <input
            id="useDeptVar"
            type="checkbox"
            checked={wizard.pcName.useDepartmentVariable}
            onChange={(e) =>
              dispatch({
                type: 'PATCH_PATH',
                payload: { path: ['pcName', 'useDepartmentVariable'], value: e.target.checked },
              })
            }
            className="h-4 w-4 rounded border-slate-700 bg-slate-950 text-sky-500"
          />
          <label htmlFor="useDeptVar" className="text-sm text-slate-200">
            Usa variabile reparto
          </label>
        </div>

        {wizard.pcName.useDepartmentVariable && (
          <div className="mt-3">
            <label className="block text-xs font-medium text-slate-300" htmlFor="dept">
              Reparto
            </label>
            <select
              id="dept"
              value={wizard.pcName.department}
              onChange={(e) =>
                dispatch({ type: 'PATCH_PATH', payload: { path: ['pcName', 'department'], value: e.target.value } })
              }
              className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
            >
              {DEPARTMENTS.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.label}
                </option>
              ))}
            </select>

            <div className="mt-2 text-xs text-slate-400">
              Il reparto verrà pre-posto al nome (es. IT-PC-CONT-01).
            </div>
          </div>
        )}
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <div className="text-xs font-medium text-slate-300">Anteprima in tempo reale</div>
        <div className="mt-2 font-mono text-lg text-slate-100">
          {wizard.pcName.formattedPreview || '—'}
        </div>
        <div className="mt-2 text-xs text-slate-400">
          L’agent userà questo valore per rinominare la macchina.
        </div>
      </div>
    </div>
  );
}
