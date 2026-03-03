import React, { useMemo, useState } from 'react';
import { ChevronDown, ChevronUp, Edit3 } from 'lucide-react';

function SectionCard({ title, children, onEdit, open, onToggle }) {
  return (
    <div className="rounded-xl border border-slate-800 bg-slate-950/40">
      <div className="flex items-center justify-between gap-3 border-b border-slate-800 px-4 py-3">
        <button
          type="button"
          onClick={onToggle}
          className="flex items-center gap-2 text-left text-sm font-semibold text-slate-100"
        >
          {open ? <ChevronUp className="h-4 w-4 text-slate-300" /> : <ChevronDown className="h-4 w-4 text-slate-300" />}
          {title}
        </button>

        <button
          type="button"
          onClick={onEdit}
          className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-xs font-medium text-slate-200 hover:bg-slate-900"
        >
          <Edit3 className="h-4 w-4" />
          Modifica
        </button>
      </div>
      {open && <div className="px-4 py-3 text-sm text-slate-200">{children}</div>}
    </div>
  );
}

export default function Step8Recap({ wizard, onEditStep, onGenerate, isGenerating }) {
  const [open, setOpen] = useState({
    s1: true,
    s2: true,
    s3: true,
    s4: true,
    s5: true,
    s6: true,
    s7: true,
  });

  const softwareCount = useMemo(() => (wizard.software.selectedIds || []).length, [wizard.software.selectedIds]);

  return (
    <div className="space-y-5">
      <div>
        <div className="text-sm font-semibold text-slate-100">STEP 8 — Recap e genera</div>
        <div className="mt-1 text-sm text-slate-400">
          Verifica tutte le scelte, poi genera il wizard (POST /api/wizards).
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4">
        <SectionCard
          title="Info base"
          open={open.s1}
          onToggle={() => setOpen((p) => ({ ...p, s1: !p.s1 }))}
          onEdit={() => onEditStep(0)}
        >
          <div><span className="text-slate-400">Nome:</span> {wizard.meta.wizardName || '—'}</div>
          <div className="mt-1"><span className="text-slate-400">Template ID:</span> {wizard.meta.templateId || '—'}</div>
          <div className="mt-1"><span className="text-slate-400">Note:</span> {wizard.meta.internalNotes ? wizard.meta.internalNotes : '—'}</div>
        </SectionCard>

        <SectionCard
          title="Nome PC"
          open={open.s2}
          onToggle={() => setOpen((p) => ({ ...p, s2: !p.s2 }))}
          onEdit={() => onEditStep(1)}
        >
          <div><span className="text-slate-400">Raw:</span> {wizard.pcName.raw || '—'}</div>
          <div className="mt-1"><span className="text-slate-400">Preview:</span> <span className="font-mono">{wizard.pcName.formattedPreview || '—'}</span></div>
        </SectionCard>

        <SectionCard
          title="Utente admin locale"
          open={open.s3}
          onToggle={() => setOpen((p) => ({ ...p, s3: !p.s3 }))}
          onEdit={() => onEditStep(2)}
        >
          <div><span className="text-slate-400">Username:</span> {wizard.localAdmin.username || '—'}</div>
          <div className="mt-1"><span className="text-slate-400">Password:</span> (non mostrata)</div>
          <div className="mt-1"><span className="text-slate-400">Rimuovi account Microsoft:</span> {wizard.localAdmin.removeMicrosoftSetupAccount ? 'Sì' : 'No'}</div>
        </SectionCard>

        <SectionCard
          title="Software"
          open={open.s4}
          onToggle={() => setOpen((p) => ({ ...p, s4: !p.s4 }))}
          onEdit={() => onEditStep(3)}
        >
          <div><span className="text-slate-400">Selezionati:</span> {softwareCount}</div>
          <div className="mt-2 font-mono text-xs text-slate-300">
            {JSON.stringify(wizard.software.selectedIds || [], null, 2)}
          </div>
        </SectionCard>

        <SectionCard
          title="Bloatware pre-selezionato"
          open={open.s5}
          onToggle={() => setOpen((p) => ({ ...p, s5: !p.s5 }))}
          onEdit={() => onEditStep(4)}
        >
          <div><span className="text-slate-400">Voci:</span> {(wizard.bloatware.preselected || []).length}</div>
          <div className="mt-2 font-mono text-xs text-slate-300">
            {JSON.stringify(wizard.bloatware.preselected || [], null, 2)}
          </div>
        </SectionCard>

        <SectionCard
          title="Power Plan"
          open={open.s6}
          onToggle={() => setOpen((p) => ({ ...p, s6: !p.s6 }))}
          onEdit={() => onEditStep(5)}
        >
          <div><span className="text-slate-400">Modalità:</span> {wizard.powerPlan.manual ? 'Manuale' : 'Preset'}</div>
          {!wizard.powerPlan.manual ? (
            <div className="mt-1"><span className="text-slate-400">Preset:</span> {wizard.powerPlan.preset}</div>
          ) : (
            <div className="mt-2 font-mono text-xs text-slate-300">
              {JSON.stringify(
                {
                  screenTimeoutMin: wizard.powerPlan.screenTimeoutMin,
                  sleepNever: wizard.powerPlan.sleepNever,
                  sleepTimeoutMin: wizard.powerPlan.sleepTimeoutMin,
                  cpuMinPercent: wizard.powerPlan.cpuMinPercent,
                  cpuMaxPercent: wizard.powerPlan.cpuMaxPercent,
                },
                null,
                2
              )}
            </div>
          )}
        </SectionCard>

        <SectionCard
          title="Extra opzionali"
          open={open.s7}
          onToggle={() => setOpen((p) => ({ ...p, s7: !p.s7 }))}
          onEdit={() => onEditStep(6)}
        >
          <div><span className="text-slate-400">Timezone:</span> {wizard.extras.timezone}</div>
          <div className="mt-1"><span className="text-slate-400">Lingua:</span> {wizard.extras.language}</div>
          <div className="mt-1"><span className="text-slate-400">Tastiera:</span> {wizard.extras.keyboardLayout}</div>
          <div className="mt-1"><span className="text-slate-400">Wallpaper:</span> {wizard.extras.wallpaperFile ? wizard.extras.wallpaperFile.name : '—'}</div>
          <div className="mt-1"><span className="text-slate-400">Wi‑Fi:</span> {wizard.extras.wifiEnabled ? `ON (${wizard.extras.wifiSsid || 'SSID?'})` : 'OFF'}</div>
          <div className="mt-1"><span className="text-slate-400">Windows Update:</span> {wizard.extras.windowsUpdatePolicy}</div>
        </SectionCard>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <div className="text-sm font-semibold text-slate-100">Generazione</div>
        <div className="mt-1 text-sm text-slate-400">
          Il backend deve cifrare password admin e Wi‑Fi e non restituirle mai nelle API generiche. [file:8]
        </div>

        <button
          type="button"
          onClick={onGenerate}
          disabled={isGenerating}
          className="mt-4 w-full rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
        >
          {isGenerating ? 'Generazione in corso…' : 'GENERA WIZARD'}
        </button>
      </div>
    </div>
  );
}
