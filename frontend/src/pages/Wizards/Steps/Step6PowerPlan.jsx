import React from 'react';
import { Battery, Gauge, Leaf } from 'lucide-react';

function PresetButton({ active, icon, title, subtitle, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`w-full rounded-xl border p-4 text-left transition ${
        active ? 'border-sky-500 bg-sky-500/10' : 'border-slate-800 bg-slate-950/40 hover:bg-slate-950'
      }`}
    >
      <div className="flex items-start gap-3">
        <div className="mt-0.5 text-sky-300">{icon}</div>
        <div>
          <div className="text-sm font-semibold text-slate-100">{title}</div>
          <div className="mt-1 text-sm text-slate-400">{subtitle}</div>
        </div>
      </div>
    </button>
  );
}

export default function Step6PowerPlan({ wizard, dispatch }) {
  const preset = wizard.powerPlan.preset;

  return (
    <div className="space-y-5">
      <div>
        <div className="text-sm font-semibold text-slate-100">STEP 6 — Power Plan</div>
        <div className="mt-1 text-sm text-slate-400">
          Scegli un preset o abilita la configurazione manuale.
        </div>
      </div>

      <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
        <PresetButton
          active={!wizard.powerPlan.manual && preset === 'balanced'}
          icon={<Gauge className="h-5 w-5" />}
          title="Bilanciato"
          subtitle="Default consigliato per la maggior parte dei PC."
          onClick={() => {
            dispatch({ type: 'PATCH_PATH', payload: { path: ['powerPlan', 'preset'], value: 'balanced' } });
            dispatch({ type: 'PATCH_PATH', payload: { path: ['powerPlan', 'manual'], value: false } });
          }}
        />
        <PresetButton
          active={!wizard.powerPlan.manual && preset === 'high_performance'}
          icon={<Battery className="h-5 w-5" />}
          title="Prestazioni elevate"
          subtitle="Priorità alle performance (consumi più alti)."
          onClick={() => {
            dispatch({ type: 'PATCH_PATH', payload: { path: ['powerPlan', 'preset'], value: 'high_performance' } });
            dispatch({ type: 'PATCH_PATH', payload: { path: ['powerPlan', 'manual'], value: false } });
          }}
        />
        <PresetButton
          active={!wizard.powerPlan.manual && preset === 'power_saver'}
          icon={<Leaf className="h-5 w-5" />}
          title="Risparmio energetico"
          subtitle="Consumi ridotti (performance inferiori)."
          onClick={() => {
            dispatch({ type: 'PATCH_PATH', payload: { path: ['powerPlan', 'preset'], value: 'power_saver' } });
            dispatch({ type: 'PATCH_PATH', payload: { path: ['powerPlan', 'manual'], value: false } });
          }}
        />
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <div className="flex items-center justify-between gap-3">
          <div>
            <div className="text-sm font-semibold text-slate-100">Configura manuale</div>
            <div className="mt-1 text-sm text-slate-400">
              Abilita per impostazioni granulari (timeout e limiti CPU).
            </div>
          </div>

          <button
            type="button"
            onClick={() =>
              dispatch({ type: 'PATCH_PATH', payload: { path: ['powerPlan', 'manual'], value: !wizard.powerPlan.manual } })
            }
            className={`rounded-full px-3 py-2 text-sm font-medium transition ${
              wizard.powerPlan.manual ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-200'
            }`}
          >
            {wizard.powerPlan.manual ? 'ON' : 'OFF'}
          </button>
        </div>

        {wizard.powerPlan.manual && (
          <div className="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <div className="text-xs font-medium text-slate-300">Timeout schermo (1–60 min)</div>
              <input
                type="range"
                min={1}
                max={60}
                value={wizard.powerPlan.screenTimeoutMin}
                onChange={(e) =>
                  dispatch({
                    type: 'PATCH_PATH',
                    payload: { path: ['powerPlan', 'screenTimeoutMin'], value: Number(e.target.value) },
                  })
                }
                className="mt-2 w-full"
              />
              <div className="mt-1 text-sm text-slate-200">{wizard.powerPlan.screenTimeoutMin} min</div>
            </div>

            <div>
              <div className="flex items-center justify-between gap-2">
                <div className="text-xs font-medium text-slate-300">Sospensione sistema</div>
                <label className="flex items-center gap-2 text-xs text-slate-300">
                  <input
                    type="checkbox"
                    checked={wizard.powerPlan.sleepNever}
                    onChange={(e) =>
                      dispatch({
                        type: 'PATCH_PATH',
                        payload: { path: ['powerPlan', 'sleepNever'], value: e.target.checked },
                      })
                    }
                    className="h-4 w-4 rounded border-slate-700 bg-slate-950 text-sky-500"
                  />
                  Mai
                </label>
              </div>

              {!wizard.powerPlan.sleepNever ? (
                <>
                  <input
                    type="range"
                    min={1}
                    max={120}
                    value={wizard.powerPlan.sleepTimeoutMin}
                    onChange={(e) =>
                      dispatch({
                        type: 'PATCH_PATH',
                        payload: { path: ['powerPlan', 'sleepTimeoutMin'], value: Number(e.target.value) },
                      })
                    }
                    className="mt-2 w-full"
                  />
                  <div className="mt-1 text-sm text-slate-200">{wizard.powerPlan.sleepTimeoutMin} min</div>
                </>
              ) : (
                <div className="mt-2 text-sm text-slate-200">Mai</div>
              )}
            </div>

            <div>
              <div className="text-xs font-medium text-slate-300">CPU min% (0–100)</div>
              <input
                type="range"
                min={0}
                max={100}
                value={wizard.powerPlan.cpuMinPercent}
                onChange={(e) =>
                  dispatch({
                    type: 'PATCH_PATH',
                    payload: { path: ['powerPlan', 'cpuMinPercent'], value: Number(e.target.value) },
                  })
                }
                className="mt-2 w-full"
              />
              <div className="mt-1 text-sm text-slate-200">{wizard.powerPlan.cpuMinPercent}%</div>
            </div>

            <div>
              <div className="text-xs font-medium text-slate-300">CPU max% (50–100)</div>
              <input
                type="range"
                min={50}
                max={100}
                value={wizard.powerPlan.cpuMaxPercent}
                onChange={(e) =>
                  dispatch({
                    type: 'PATCH_PATH',
                    payload: { path: ['powerPlan', 'cpuMaxPercent'], value: Number(e.target.value) },
                  })
                }
                className="mt-2 w-full"
              />
              <div className="mt-1 text-sm text-slate-200">{wizard.powerPlan.cpuMaxPercent}%</div>
            </div>

            <div className="md:col-span-2 rounded-lg border border-slate-800 bg-slate-950 p-3 text-xs text-slate-300">
              Avviso: parametri manuali errati possono peggiorare autonomia, rumorosità o performance; valida bene i preset in base all’hardware.
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
