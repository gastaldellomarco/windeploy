import React from 'react';

export default function Step1Info({ wizard, dispatch, templates, templatesLoading, onTemplateSelect }) {
  const templateId = wizard.meta.templateId;

  return (
    <div className="space-y-5">
      <div>
        <div className="text-sm font-semibold text-slate-100">STEP 1 — Info base</div>
        <div className="mt-1 text-sm text-slate-400">
          Definisci i metadati del wizard e, se vuoi, carica un template esistente come base.
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label className="block text-xs font-medium text-slate-300" htmlFor="wizardName">
            Nome wizard
          </label>
          <input
            id="wizardName"
            type="text"
            value={wizard.meta.wizardName}
            onChange={(e) =>
              dispatch({ type: 'PATCH_PATH', payload: { path: ['meta', 'wizardName'], value: e.target.value } })
            }
            placeholder="Es. Setup Contabilità Standard"
            className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
          />
          <div className="mt-2 text-xs text-slate-400">Suggerimento: usa un nome descrittivo e riutilizzabile.</div>
        </div>

        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label className="block text-xs font-medium text-slate-300" htmlFor="template">
            Usa template esistente (opzionale)
          </label>
          <select
            id="template"
            value={templateId}
            onChange={(e) => onTemplateSelect(e.target.value)}
            className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
          >
            <option value="">— Nessun template —</option>
            {templatesLoading ? (
              <option value="" disabled>
                Caricamento template…
              </option>
            ) : (
              templates.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.name}
                </option>
              ))
            )}
          </select>
          <div className="mt-2 text-xs text-slate-400">
            Se selezioni un template, verranno precompilati i campi principali (software, bloatware, power plan, ecc.).
          </div>
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <label className="block text-xs font-medium text-slate-300" htmlFor="notes">
          Note interne
        </label>
        <textarea
          id="notes"
          rows={5}
          value={wizard.meta.internalNotes}
          onChange={(e) =>
            dispatch({ type: 'PATCH_PATH', payload: { path: ['meta', 'internalNotes'], value: e.target.value } })
          }
          placeholder="Annotazioni per il team IT (non visibili all'utente finale)…"
          className="mt-2 w-full resize-none rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
        />
      </div>
    </div>
  );
}
