<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Crea il Wizard Builder per WinDeploy — il modulo più complesso della web app.

**Data aggiornamento:** 2026-03-06  
**⚠️ NOTA:** Payload builder aggiornato a schema canonico v1.0 (snake_case). Vedi `docs/schemas/wizard-config.schema.json`.

Stack: React 18 + Tailwind CSS + React Query + Zustand.

È un form multi-step con 8 step navigabili (avanti/indietro). Mostra una barra di
progressione in alto con i nomi degli step e indicatore di completamento.

STEP 1 — Info base:

- Campo "Nome wizard" (testo libero)
- Select "Usa template esistente" (opzionale, carica i dati dal template selezionato)
- Campo "Note interne" (textarea)

STEP 2 — Nome PC:

- Campo nome PC con validazione (solo lettere, numeri, trattini, max 15 char — limite Windows)
- Anteprima in tempo reale del nome formattato
- Checkbox "Usa variabile reparto" → mostra select (IT/AMM/COM/DEV) che pre-compila il nomee sto usando
  STEP 3 — Utente admin locale:

- Campo username (validazione: no spazi, no caratteri speciali)
- Campo password + conferma password (con toggle mostra/nascondi, strength indicator)
- Checkbox "Rimuovi account Microsoft di setup iniziale"
- Nota: "La password viene cifrata e non sarà visibile dopo il salvataggio"

 
STEP 4 — Software da installare:

- Carica lista da GET /api/software?attivo=1
- Barra di ricerca live (filtra per nome)
- Filtri per categoria (tabs o select)
- Lista con checkbox, ogni item mostra: icona/emoji categoria, nome, versione, tipo (badge Winget/EXE)
- Counter "X software selezionati" sempre visibile

 
STEP 5 — Bloatware da pre-selezionare:

- Lista fissa di app Windows comuni non necessarie (Xbox, Candy Crush, Teams consumer,
    OneDrive, Cortana, Microsoft News, Get Help, ecc. — almeno 20 voci)
- Spiegazione: "Queste app risulteranno pre-spuntate nell'agent ma l'utente potrà deselezionarle"
- Toggle "Seleziona tutti / Deseleziona tutti"

 
STEP 6 — Power Plan:

- 3 pulsanti per preset (Bilanciato / Prestazioni elevate / Risparmio energetico) con icone
- Toggle "Configura manuale" → mostra slider per: timeout schermo (1-60 min),
    sospensione sistema (mai o 1-120 min), CPU min% (0-100), CPU max% (50-100)

 
STEP 7 — Extra opzionali:

- Select timezone (lista principali, default Europe/Rome)
- Select lingua/tastiera (IT, EN, ecc.)
- Upload wallpaper aziendale (preview immagine, max 5MB)
- Sezione Wi-Fi: toggle → mostra campi SSID + password (cifrata)
- Select Windows Update (Automatico / Solo scarica / Manuale)

 
STEP 8 — Recap e genera:

- Riepilogo visuale di tutte le scelte in card collassabili per sezione
- Bottone "Modifica" per ogni sezione → torna allo step corrispondente
- Bottone primario "GENERA WIZARD" → POST /api/wizards
- Dopo la generazione: mostra modal con codice univoco (es. WD-7A3F) grande e copiabile,
    istruzioni per l'agent, bottone "Scarica agent.exe", bottone "Copia link diretto"

 
Gestione stato: usa un oggetto wizard nello stato locale React (useReducer o useState oggetto).
Ad ogni step, salva l'avanzamento in localStorage per evitare perdita dati.

 
Scrivi i file:

- src/pages/Wizards/WizardBuilderPage.jsx
- src/pages/Wizards/WizardMonitorPage.jsx
- src/pages/Wizards/WizardsListPage.jsx
- src/pages/Wizards/steps/Step1Info.jsx
- src/pages/Wizards/steps/Step2NomePC.jsx
- src/pages/Wizards/steps/Step3Utente.jsx
- src/pages/Wizards/steps/Step4Software.jsx
- src/pages/Wizards/steps/Step5Bloatware.jsx
- src/pages/Wizards/steps/Step6PowerPlan.jsx
- src/pages/Wizards/steps/Step7Extra.jsx
- src/pages/Wizards/steps/Step8Recap.jsx
- src/components/StepProgress.jsx (barra progressione riutilizzabile)

 
Codice completo per ogni file. Non abbreviare.
sto utilizzando per i componenti in components\layout\mainLayout.jsx e:
import React from 'react';
import { NavLink, Outlet } from 'react-router-dom';
import {
  LayoutDashboard,
  Wand2,
  FileText,
  Package,
  Users,
  ClipboardList,
  LogOut,
} from 'lucide-react';
import { useAuthStore } from '../../store/authStore';

function MainLayout() {
  const { user, logout } = useAuthStore();

const handleLogout = () => {
    logout();
  };

return (
    <div className="flex min-h-screen">
      <aside className="w-64 bg-slate-900 border-r border-slate-800 flex flex-col">
        <div className="px-4 py-4 border-b border-slate-800">
          `          <div className="text-lg font-semibold">WinDeploy</div>          `
          {user \&\& (
            <div className="mt-1 text-xs text-slate-400">
              {user.name} · {user.role}
            </div>
          )}
        </div>

<nav className="flex-1 px-2 py-4 space-y-1 text-sm">
          <NavLink
            to="/dashboard"
            className={({ isActive }) =>
              `flex items-center gap-2 rounded px-3 py-2 ${                 isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'               }`
            }
          >
            <LayoutDashboard className="w-4 h-4" />
            <span>Dashboard</span>
          </NavLink>

<NavLink
            to="/wizards"
            className={({ isActive }) =>
              `flex items-center gap-2 rounded px-3 py-2 ${                 isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'               }`
            }
          >
            <Wand2 className="w-4 h-4" />
            <span>Wizards</span>
          </NavLink>

<NavLink
            to="/templates"
            className={({ isActive }) =>
              `flex items-center gap-2 rounded px-3 py-2 ${                 isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'               }`
            }
          >
            <FileText className="w-4 h-4" />
            <span>Templates</span>
          </NavLink>

<NavLink
            to="/software"
            className={({ isActive }) =>
              `flex items-center gap-2 rounded px-3 py-2 ${                 isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'               }`
            }
          >
            <Package className="w-4 h-4" />
            <span>Software library</span>
          </NavLink>

<NavLink
            to="/reports"
            className={({ isActive }) =>
              `flex items-center gap-2 rounded px-3 py-2 ${                 isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'               }`
            }
          >
            <ClipboardList className="w-4 h-4" />
            <span>Reports</span>
          </NavLink>

<NavLink
            to="/users"
            className={({ isActive }) =>
              `flex items-center gap-2 rounded px-3 py-2 ${                 isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'               }`
            }
          >
            <Users className="w-4 h-4" />
            <span>Users</span>
          </NavLink>
        </nav>

<button
          type="button"
          onClick={handleLogout}
          className="flex items-center gap-2 px-4 py-3 text-sm text-red-300 hover:bg-red-900/30 border-t border-slate-800"
        >
          <LogOut className="w-4 h-4" />
          <span>Logout</span>
        </button>
      </aside>

<main className="flex-1 bg-slate-950">
        <div className="max-w-6xl mx-auto px-6 py-6">
          <Outlet />
        </div>
      </main>
    </div>
  );
}

export default MainLayout;

Di seguito trovi un’implementazione completa del **Wizard Builder** (8 step) per WinDeploy con React 18 + Tailwind + React Query, stato wizard in un singolo oggetto e salvataggio progressivo in localStorage. Il flusso è coerente con la struttura WinDeploy (Wizard Builder centrale, Step 8 recap+generazione codice, monitor real-time), e con lo scaffold React/React Query/Axios/Zustand già previsto nel progetto.[^1_1][^1_2]

## Struttura file

Crea/aggiorna questi file (come richiesto):

- `src/pages/Wizards/WizardBuilderPage.jsx`
- `src/pages/Wizards/WizardMonitorPage.jsx`
- `src/pages/Wizards/WizardsListPage.jsx`
- `src/pages/Wizards/steps/Step1Info.jsx`
- `src/pages/Wizards/steps/Step2NomePC.jsx`
- `src/pages/Wizards/steps/Step3Utente.jsx`
- `src/pages/Wizards/steps/Step4Software.jsx`
- `src/pages/Wizards/steps/Step5Bloatware.jsx`
- `src/pages/Wizards/steps/Step6PowerPlan.jsx`
- `src/pages/Wizards/steps/Step7Extra.jsx`
- `src/pages/Wizards/steps/Step8Recap.jsx`
- `src/components/StepProgress.jsx`

> Nota: assumo che tu abbia già `src/api/client.js` (Axios instance con Bearer token) come nello scaffold.[^1_1]
> Endpoint usati: `GET /api/software?attivo=1`, `POST /api/wizards`, `GET /api/wizards`, `GET /api/wizards/:id/monitor` (pattern coerente con i controller WinDeploy).[^1_3]

---

## `src/components/StepProgress.jsx`

```jsx
import React from "react";

function StepProgress({ steps, currentStepIndex, completedMap, onStepClick }) {
  return (
    <div className="w-full">
      <div className="flex items-center justify-between gap-2">
        {steps.map((step, idx) => {
          const isActive = idx === currentStepIndex;
          const isCompleted = Boolean(completedMap?.[idx]);
          const isClickable = typeof onStepClick === "function";

          return (
            <button
              key={step.key}
              type="button"
              onClick={() => isClickable && onStepClick(idx)}
              className={`flex-1 text-left rounded-lg border px-3 py-2 transition ${
                isActive
                  ? "border-sky-500 bg-sky-500/10"
                  : "border-slate-800 bg-slate-900/40 hover:bg-slate-900/70"
              } ${isClickable ? "cursor-pointer" : "cursor-default"}`}
              aria-current={isActive ? "step" : undefined}
            >
              <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                  <div className="text-[11px] uppercase tracking-wide text-slate-400">
                    Step {idx + 1}
                  </div>
                  <div className="truncate text-sm font-medium text-slate-100">
                    {step.label}
                  </div>
                </div>

                <div className="shrink-0">
                  {isCompleted ? (
                    <span className="inline-flex items-center rounded-full bg-emerald-500/15 px-2 py-1 text-xs font-medium text-emerald-300">
                      OK
                    </span>
                  ) : (
                    <span className="inline-flex items-center rounded-full bg-slate-700/30 px-2 py-1 text-xs font-medium text-slate-300">
                      —
                    </span>
                  )}
                </div>
              </div>
            </button>
          );
        })}
      </div>

      <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-800">
        <div
          className="h-full rounded-full bg-sky-500 transition-all"
          style={{
            width: `${Math.round(((currentStepIndex + 1) / steps.length) * 100)}%`,
          }}
        />
      </div>

      <div className="mt-1 flex justify-between text-xs text-slate-400">
        <span>
          {currentStepIndex + 1}/{steps.length}
        </span>
        <span>
          {Math.round(((currentStepIndex + 1) / steps.length) * 100)}%
        </span>
      </div>
    </div>
  );
}

export default StepProgress;
```

---

## `src/pages/Wizards/WizardsListPage.jsx`

```jsx
import React from "react";
import { NavLink } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Wand2, Plus, Eye } from "lucide-react";
import client from "../../api/client";

async function fetchWizards() {
  const res = await client.get("/wizards");
  return res.data;
}

function normalizeApiCollection(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (payload.data && Array.isArray(payload.data)) return payload.data;
  if (payload.data && payload.data.data && Array.isArray(payload.data.data))
    return payload.data.data;
  return [];
}

function getWizardRow(item) {
  const w = item?.data ? item.data : item;
  return {
    id: w?.id,
    name: w?.nome ?? w?.name ?? "—",
    code: w?.codiceunivoco ?? w?.code ?? "—",
    status: w?.stato ?? w?.status ?? "—",
    createdAt: w?.created_at ?? w?.createdAt ?? null,
  };
}

function StatusBadge({ status }) {
  const s = String(status || "").toLowerCase();
  let cls = "bg-slate-700/30 text-slate-200 border-slate-700";
  if (s === "pronto")
    cls = "bg-emerald-500/10 text-emerald-300 border-emerald-500/30";
  if (s === "bozza") cls = "bg-amber-500/10 text-amber-300 border-amber-500/30";
  if (s === "inesecuzione")
    cls = "bg-sky-500/10 text-sky-300 border-sky-500/30";
  if (s === "completato")
    cls = "bg-emerald-500/10 text-emerald-300 border-emerald-500/30";
  if (s === "errore") cls = "bg-rose-500/10 text-rose-300 border-rose-500/30";

  return (
    <span
      className={`inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium ${cls}`}
    >
      {status}
    </span>
  );
}

export default function WizardsListPage() {
  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ["wizards"],
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
            Errore: {String(error?.message || "richiesta fallita")}
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
                    <td className="px-4 py-3 font-mono text-slate-200">
                      {r.code}
                    </td>
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
```

---

## `src/pages/Wizards/WizardMonitorPage.jsx`

```jsx
import React from "react";
import { useParams, NavLink } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, Activity } from "lucide-react";
import client from "../../api/client";

async function fetchMonitor(wizardId) {
  const res = await client.get(`/wizards/${wizardId}/monitor`);
  return res.data;
}

export default function WizardMonitorPage() {
  const { id } = useParams();

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ["wizardMonitor", id],
    queryFn: () => fetchMonitor(id),
    refetchInterval: 5000,
    staleTime: 0,
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold text-slate-100">
            Monitor wizard
          </h1>
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
        {isLoading && (
          <div className="text-sm text-slate-400">Caricamento…</div>
        )}

        {isError && (
          <div className="text-sm text-rose-300">
            Errore: {String(error?.message || "richiesta fallita")}
          </div>
        )}

        {!isLoading && !isError && (
          <div className="space-y-4">
            <div className="flex items-center gap-2 text-slate-200">
              <Activity className="h-4 w-4 text-sky-400" />
              <span className="text-sm font-medium">Stato esecuzione</span>
            </div>

            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
              <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                <div className="text-xs text-slate-400">Wizard ID</div>
                <div className="mt-1 font-mono text-sm text-slate-100">
                  {data?.wizardid ?? "—"}
                </div>
              </div>

              <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                <div className="text-xs text-slate-400">Execution Log ID</div>
                <div className="mt-1 font-mono text-sm text-slate-100">
                  {data?.executionlogid ?? "—"}
                </div>
              </div>

              <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                <div className="text-xs text-slate-400">Stato</div>
                <div className="mt-1 text-sm text-slate-100">
                  {data?.stato ?? "—"}
                </div>
              </div>
            </div>

            <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
              <div className="text-xs text-slate-400">Step corrente</div>
              <div className="mt-1 text-sm text-slate-100">
                {data?.stepcorrente ?? "—"}
              </div>
            </div>

            <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
              <div className="text-xs text-slate-400">
                Log dettagliato (JSON)
              </div>
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
```

---

## `src/pages/Wizards/WizardBuilderPage.jsx`

```jsx
import React, { useEffect, useMemo, useReducer, useState } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { ArrowLeft, ArrowRight, Save, Wand2 } from 'lucide-react';
import client from '../../api/client';
import StepProgress from '../../components/StepProgress';

import Step1Info from './steps/Step1Info';
import Step2NomePC from './steps/Step2NomePC';
import Step3Utente from './steps/Step3Utente';
import Step4Software from './steps/Step4Software';
import Step5Bloatware from './steps/Step5Bloatware';
import Step6PowerPlan from './steps/Step6PowerPlan';
import Step7Extra from './steps/Step7Extra';
import Step8Recap from './steps/Step8Recap';

const LS_KEY = 'windeploy:wizardBuilder:draft:v1';

const STEPS = [
  { key: 'step1', label: 'Info base' },
  { key: 'step2', label: 'Nome PC' },
  { key: 'step3', label: 'Utente admin locale' },
  { key: 'step4', label: 'Software' },
  { key: 'step5', label: 'Bloatware' },
  { key: 'step6', label: 'Power Plan' },
  { key: 'step7', label: 'Extra' },
  { key: 'step8', label: 'Recap & genera' },
];

function buildEmptyWizard() {
  return {
    meta: {
      wizardName: '',
      templateId: '',
      internalNotes: '',
    },
    pcName: {
      raw: '',
      useDepartmentVariable: false,
      department: 'IT',
      formattedPreview: '',
    },
    localAdmin: {
      username: '',
      password: '',
      passwordConfirm: '',
      removeMicrosoftSetupAccount: false,
    },
    software: {
      selectedIds: [],
    },
    bloatware: {
      preselected: [],
    },
    powerPlan: {
      preset: 'balanced',
      manual: false,
      screenTimeoutMin: 15,
      sleepTimeoutMin: 30,
      cpuMinPercent: 5,
      cpuMaxPercent: 100,
      sleepNever: false,
    },
    extras: {
      timezone: 'Europe/Rome',
      language: 'it-IT',
      keyboardLayout: 'it-IT',
      wallpaperFile: null,
      wallpaperPreviewUrl: '',
      wifiEnabled: false,
      wifiSsid: '',
      wifiPassword: '',
      windowsUpdatePolicy: 'auto',
    },
  };
}

function safeParse(json) {
  try {
    return JSON.parse(json);
  } catch {
    return null;
  }
}

function reducer(state, action) {
  switch (action.type) {
    case 'LOAD_DRAFT':
      return action.payload || state;
    case 'UPDATE':
      return {
        ...state,
        ...action.payload,
      };
    case 'PATCH_PATH': {
      const { path, value } = action.payload;
      const next = structuredClone(state);

      let ref = next;
      for (let i = 0; i < path.length - 1; i += 1) {
        ref = ref[path[i]];
      }
      ref[path[path.length - 1]] = value;
      return next;
    }
    case 'RESET':
      return buildEmptyWizard();
    default:
      return state;
  }
}

function validatePcName(name) {
  const trimmed = String(name || '').trim();
  if (!trimmed) return { ok: false, message: 'Il nome PC è obbligatorio.' };
  if (trimmed.length > 15) return { ok: false, message: 'Max 15 caratteri (limite Windows).' };
  if (!/^[A-Za-z0-9-]+$/.test(trimmed)) return { ok: false, message: 'Solo lettere, numeri e trattini.' };
  if (/^-|-$/.test(trimmed)) return { ok: false, message: 'Non può iniziare o finire con un trattino.' };
  return { ok: true, message: '' };
}

function validateUsername(username) {
  const u = String(username || '').trim();
  if (!u) return { ok: false, message: 'Username obbligatorio.' };
  if (u.length > 50) return { ok: false, message: 'Max 50 caratteri.' };
  if (!/^[A-Za-z0-9._-]+$/.test(u)) return { ok: false, message: 'No spazi o caratteri speciali.' };
  return { ok: true, message: '' };
}

function passwordStrength(pw) {
  const p = String(pw || '');
  let score = 0;
  if (p.length >= 8) score += 1;
  if (/[A-Z]/.test(p)) score += 1;
  if (/[a-z]/.test(p)) score += 1;
  if (/[0-9]/.test(p)) score += 1;
  if (/[^A-Za-z0-9]/.test(p)) score += 1;
  return score; // 0..5
}

function validateStep(wizard, stepIndex) {
  if (stepIndex === 0) {
    if (!String(wizard.meta.wizardName || '').trim()) {
      return { ok: false, message: 'Compila “Nome wizard”.' };
    }
    return { ok: true, message: '' };
  }

  if (stepIndex === 1) {
    return validatePcName(wizard.pcName.raw);
  }

  if (stepIndex === 2) {
    const u = validateUsername(wizard.localAdmin.username);
    if (!u.ok) return u;
    if (!wizard.localAdmin.password) return { ok: false, message: 'Password obbligatoria.' };
    if (wizard.localAdmin.password !== wizard.localAdmin.passwordConfirm) {
      return { ok: false, message: 'Password e conferma non coincidono.' };
    }
    if (passwordStrength(wizard.localAdmin.password) < 3) {
      return { ok: false, message: 'Password troppo debole (minimo consigliato: score 3/5).' };
    }
    return { ok: true, message: '' };
  }

  if (stepIndex === 3) {
    return { ok: true, message: '' };
  }

  if (stepIndex === 4) {
    if (!Array.isArray(wizard.bloatware.preselected)) return { ok: false, message: 'Selezione bloatware non valida.' };
    if (wizard.bloatware.preselected.length === 0) return { ok: false, message: 'Seleziona almeno un bloatware (puoi anche scegliere “nessuno” deselezionando tutto, ma serve una scelta esplicita).' };
    return { ok: true, message: '' };
  }

  if (stepIndex === 5) {
    if (wizard.powerPlan.manual) {
      if (wizard.powerPlan.screenTimeoutMin < 1 || wizard.powerPlan.screenTimeoutMin > 60) return { ok: false, message: 'Timeout schermo fuori range.' };
      if (!wizard.powerPlan.sleepNever && (wizard.powerPlan.sleepTimeoutMin < 1 || wizard.powerPlan.sleepTimeoutMin > 120)) return { ok: false, message: 'Sospensione sistema fuori range.' };
      if (wizard.powerPlan.cpuMinPercent < 0 || wizard.powerPlan.cpuMinPercent > 100) return { ok: false, message: 'CPU min% fuori range.' };
      if (wizard.powerPlan.cpuMaxPercent < 50 || wizard.powerPlan.cpuMaxPercent > 100) return { ok: false, message: 'CPU max% fuori range.' };
      if (wizard.powerPlan.cpuMinPercent > wizard.powerPlan.cpuMaxPercent) return { ok: false, message: 'CPU min% non può superare CPU max%.' };
    }
    return { ok: true, message: '' };
  }

  if (stepIndex === 6) {
    if (wizard.extras.wifiEnabled) {
      if (!String(wizard.extras.wifiSsid || '').trim()) return { ok: false, message: 'SSID Wi-Fi obbligatorio.' };
      if (!String(wizard.extras.wifiPassword || '').trim()) return { ok: false, message: 'Password Wi-Fi obbligatoria.' };
    }
    return { ok: true, message: '' };
  }

  return { ok: true, message: '' };
}

async function fetchTemplates() {
  const res = await client.get('/templates');
  return res.data;
}

function normalizeTemplates(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (payload.data && Array.isArray(payload.data)) return payload.data;
  if (payload.data && payload.data.data && Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function getTemplateRow(item) {
  const t = item?.data ? item.data : item;
  return {
    id: t?.id,
    name: t?.nome ?? t?.name ?? '—',
    config: t?.configurazione ?? t?.config ?? null,
  };
}

async function createWizardApi(payload) {
  const res = await client.post('/wizards', payload);
  return res.data;
}

export default function WizardBuilderPage() {
  const navigate = useNavigate();
  const [wizard, dispatch] = useReducer(reducer, buildEmptyWizard());
  const [currentStep, setCurrentStep] = useState(0);
  const [completedMap, setCompletedMap] = useState({});
  const [globalError, setGlobalError] = useState('');
  const [postGenModal, setPostGenModal] = useState({ open: false, code: '', wizardId: null, directLink: '' });

  const templatesQuery = useQuery({
    queryKey: ['templates'],
    queryFn: fetchTemplates,
    staleTime: 30 * 1000,
  });

  const templates = useMemo(() => normalizeTemplates(templatesQuery.data).map(getTemplateRow), [templatesQuery.data]);

  useEffect(() => {
    const stored = window.localStorage.getItem(LS_KEY);
    if (!stored) return;
    const parsed = safeParse(stored);
    if (parsed?.wizard) dispatch({ type: 'LOAD_DRAFT', payload: parsed.wizard });
    if (typeof parsed?.currentStep === 'number') setCurrentStep(parsed.currentStep);
    if (parsed?.completedMap) setCompletedMap(parsed.completedMap);
  }, []);

  useEffect(() => {
    const payload = {
      wizard,
      currentStep,
      completedMap,
      savedAt: new Date().toISOString(),
    };
    window.localStorage.setItem(LS_KEY, JSON.stringify(payload));
  }, [wizard, currentStep, completedMap]);

  useEffect(() => {
    const raw = String(wizard.pcName.raw || '').trim().toUpperCase();
    const dept = wizard.pcName.useDepartmentVariable ? String(wizard.pcName.department || 'IT').toUpperCase() : '';
    const formatted = wizard.pcName.useDepartmentVariable && dept
      ? `${dept}-${raw}`.replace(/--+/g, '-')
      : raw;

    dispatch({
      type: 'PATCH_PATH',
      payload: { path: ['pcName', 'formattedPreview'], value: formatted },
    });
  }, [wizard.pcName.raw, wizard.pcName.useDepartmentVariable, wizard.pcName.department]);

  const createWizardMutation = useMutation({
    mutationFn: createWizardApi,
    onSuccess: (data) => {
      const w = data?.data ? data.data : data;
      const code = w?.codiceunivoco ?? w?.code ?? '';
      const wizardId = w?.id ?? null;
      const directLink = code ? `${window.location.origin}/agent?code=${encodeURIComponent(code)}` : '';

      setPostGenModal({
        open: true,
        code: code || 'WD-????',
        wizardId,
        directLink,
      });

      window.localStorage.removeItem(LS_KEY);
    },
    onError: (err) => {
      setGlobalError(String(err?.response?.data?.message || err?.message || 'Errore generazione wizard'));
    },
  });

  function markStepCompleted(stepIndex) {
    setCompletedMap((prev) => ({ ...prev, [stepIndex]: true }));
  }

  function handleGoToStep(targetIdx) {
    if (targetIdx < 0 || targetIdx > STEPS.length - 1) return;
    setGlobalError('');

    if (targetIdx > currentStep) {
      const v = validateStep(wizard, currentStep);
      if (!v.ok) {
        setGlobalError(v.message);
        return;
      }
      markStepCompleted(currentStep);
    }
    setCurrentStep(targetIdx);
  }

  function handleNext() {
    setGlobalError('');
    const v = validateStep(wizard, currentStep);
    if (!v.ok) {
      setGlobalError(v.message);
      return;
    }
    markStepCompleted(currentStep);
    setCurrentStep((s) => Math.min(s + 1, STEPS.length - 1));
  }

  function handleBack() {
    setGlobalError('');
    setCurrentStep((s) => Math.max(s - 1, 0));
  }

  function handleReset() {
    dispatch({ type: 'RESET' });
    setCurrentStep(0);
    setCompletedMap({});
    setGlobalError('');
    window.localStorage.removeItem(LS_KEY);
  }

  function applyTemplateById(templateId) {
    const selected = templates.find((t) => String(t.id) === String(templateId));
    if (!selected?.config) return;

    const cfg = selected.config;

    const next = buildEmptyWizard();

    next.meta.wizardName = wizard.meta.wizardName;
    next.meta.templateId = String(templateId);
    next.meta.internalNotes = wizard.meta.internalNotes;

    if (cfg.nomepc) next.pcName.raw = String(cfg.nomepc);

    if (cfg.utenteadmin?.username) next.localAdmin.username = String(cfg.utenteadmin.username);

    if (Array.isArray(cfg.softwareinstalla)) {
      const ids = cfg.softwareinstalla
        .map((x) => x?.softwarelibraryid ?? x?.id)
        .filter(Boolean)
        .map((x) => Number(x))
        .filter((n) => Number.isFinite(n));
      next.software.selectedIds = Array.from(new Set(ids));
    }

    if (Array.isArray(cfg.bloatwaredefault)) {
      next.bloatware.preselected = cfg.bloatwaredefault.map((x) => String(x));
    }

    if (cfg.powerplan?.tipo === 'preset' && cfg.powerplan?.params?.preset) {
      next.powerPlan.preset = String(cfg.powerplan.params.preset);
      next.powerPlan.manual = false;
    }
    if (cfg.powerplan?.tipo === 'custom' && cfg.powerplan?.params) {
      next.powerPlan.manual = true;
      const p = cfg.powerplan.params;
      if (typeof p.monitortimeoutac === 'number') next.powerPlan.screenTimeoutMin = p.monitortimeoutac;
      if (typeof p.sleeptimeoutac === 'number') next.powerPlan.sleepTimeoutMin = p.sleeptimeoutac;
      if (typeof p.cpuminpercent === 'number') next.powerPlan.cpuMinPercent = p.cpuminpercent;
      if (typeof p.cpumaxpercent === 'number') next.powerPlan.cpuMaxPercent = p.cpumaxpercent;
    }

    if (cfg.extras?.timezone) next.extras.timezone = String(cfg.extras.timezone);
    if (cfg.extras?.language) next.extras.language = String(cfg.extras.language);
    if (cfg.extras?.keyboardlayout) next.extras.keyboardLayout = String(cfg.extras.keyboardlayout);
    if (cfg.extras?.windowsupdate?.policy) next.extras.windowsUpdatePolicy = String(cfg.extras.windowsupdate.policy);

    dispatch({ type: 'LOAD_DRAFT', payload: next });
    setCompletedMap({});
    setCurrentStep(0);
    setGlobalError('');
  }

  function buildApiPayload() {
    // Payload reale inviato al backend:
    // {
    //   nome: string,
    //   template_id?: number|null,
    //   note_interne?: string|null,
    //   configurazione: WizardConfig v1.0 (snake_case)
    // }
    const softwareItems = (availableSoftware || [])
      .filter((item) => wizard.software.selectedIds.includes(item.id))
      .map((item) => ({
        id: Number(item.id),
        winget_id: String(item.identificatore ?? item.winget_id ?? ''),
        name: String(item.nome ?? item.name ?? ''),
        type: String(item.tipo ?? item.type ?? 'winget'),
        download_url: item.download_url ?? null,
      }));
    const bloatwareItems = (BLOATWARE_LIST || []).map((item) => ({
      package_name: String(item.package_name),
      display_name: String(item.display_name),
      selected: Array.isArray(wizard.bloatware.preselected)
        ? wizard.bloatware.preselected.includes(item.package_name)
        : false,
    }));
    const payload = {
      nome: wizard.meta.wizardName,
      template_id: wizard.meta.templateId || null,
      note_interne: wizard.meta.internalNotes || null,
      configurazione: {
        version: '1.0',
        pc_name: wizard.pcName.formattedPreview,
        admin_user: {
          username: wizard.localAdmin.username,
          password: wizard.localAdmin.password,
          remove_setup_account: Boolean(wizard.localAdmin.removeMicrosoftSetupAccount),
        },
        software: softwareItems,
        bloatware: bloatwareItems,
        power_plan: {
          type: wizard.powerPlan.manual
            ? 'custom'
            : wizard.powerPlan.preset === 'prestazioni_elevate'
            ? 'high_performance'
            : wizard.powerPlan.preset === 'risparmio_energetico'
            ? 'power_saver'
            : 'balanced',
          screen_timeout_ac: wizard.powerPlan.screenTimeoutMin ?? null,
          sleep_timeout_ac: wizard.powerPlan.sleepNever ? null : wizard.powerPlan.sleepTimeoutMin ?? null,
          cpu_min_percent: wizard.powerPlan.cpuMinPercent ?? 0,
          cpu_max_percent: wizard.powerPlan.cpuMaxPercent ?? 100,
        },
        extras: {
          timezone: wizard.extras.timezone || null,
          language: wizard.extras.language || null,
          keyboard_layout: wizard.extras.keyboardLayout || null,
          wallpaper_url: null,
          wifi: wizard.extras.wifiEnabled
            ? {
                ssid: wizard.extras.wifiSsid,
                password: wizard.extras.wifiPassword,
              }
            : null,
          windows_update: wizard.extras.windowsUpdatePolicy || 'auto',
        },
      },
    };

    return payload;
  }

  // Payload reale — vedi anche docs/schemas/wizard-config.schema.json
      powerplan: wizard.powerPlan.manual
        ? {
            tipo: 'custom',
            params: {
              monitortimeoutac: wizard.powerPlan.screenTimeoutMin,
              sleeptimeoutac: wizard.powerPlan.sleepNever ? 0 : wizard.powerPlan.sleepTimeoutMin,
              cpuminpercent: wizard.powerPlan.cpuMinPercent,
              cpumaxpercent: wizard.powerPlan.cpuMaxPercent,
            },
          }
        : {
            tipo: 'preset',
            params: { preset: wizard.powerPlan.preset },
          },
      extras: {
        timezone: wizard.extras.timezone,
        language: wizard.extras.language,
        keyboardlayout: wizard.extras.keyboardLayout,
        windowsupdate: { policy: wizard.extras.windowsUpdatePolicy },
        removemicrosoftaccount: wizard.localAdmin.removeMicrosoftSetupAccount,
        wifi: wizard.extras.wifiEnabled
          ? { ssid: wizard.extras.wifiSsid, password: wizard.extras.wifiPassword }
          : null,
      },
    };

    return {
      nome: wizard.meta.wizardName,
      templateid: wizard.meta.templateId || null,
      noteinterne: wizard.meta.internalNotes || null,
      configurazione,
    };
  }

  async function handleGenerateWizard() {
    setGlobalError('');

    for (let i = 0; i < 7; i += 1) {
      const v = validateStep(wizard, i);
      if (!v.ok) {
        setGlobalError(`Step ${i + 1}: ${v.message}`);
        setCurrentStep(i);
        return;
      }
    }

    const payload = buildApiPayload();

    const formData = new FormData();
    formData.append('nome', payload.nome);
    if (payload.templateid) formData.append('templateid', String(payload.templateid));
    if (payload.noteinterne) formData.append('noteinterne', String(payload.noteinterne));
    formData.append('configurazione', JSON.stringify(payload.configurazione));

    if (wizard.extras.wallpaperFile instanceof File) {
      formData.append('wallpaper', wizard.extras.wallpaperFile);
    }

    createWizardMutation.mutate(formData);
  }

  const StepComponent = useMemo(() => {
    const commonProps = {
      wizard,
      dispatch,
    };

    if (currentStep === 0) {
      return (
        <Step1Info
          {...commonProps}
          templates={templates}
          templatesLoading={templatesQuery.isLoading}
          onTemplateSelect={(templateId) => {
            dispatch({ type: 'PATCH_PATH', payload: { path: ['meta', 'templateId'], value: templateId } });
            if (templateId) applyTemplateById(templateId);
          }}
        />
      );
    }

    if (currentStep === 1) return <Step2NomePC {...commonProps} validatePcName={validatePcName} />;
    if (currentStep === 2) return <Step3Utente {...commonProps} validateUsername={validateUsername} passwordStrength={passwordStrength} />;
    if (currentStep === 3) return <Step4Software {...commonProps} />;
    if (currentStep === 4) return <Step5Bloatware {...commonProps} />;
    if (currentStep === 5) return <Step6PowerPlan {...commonProps} />;
    if (currentStep === 6) return <Step7Extra {...commonProps} />;
    return (
      <Step8Recap
        {...commonProps}
        onEditStep={(idx) => setCurrentStep(idx)}
        onGenerate={handleGenerateWizard}
        isGenerating={createWizardMutation.isPending}
      />
    );
  }, [currentStep, wizard, templates, templatesQuery.isLoading, createWizardMutation.isPending]);

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold text-slate-100">Wizard Builder</h1>
          <p className="mt-1 text-sm text-slate-400">
            Costruisci un wizard multi-step per l’agent WinDeploy.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <NavLink
            to="/wizards"
            className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm text-slate-200 hover:bg-slate-900"
          >
            <ArrowLeft className="h-4 w-4" />
            Wizards
          </NavLink>

          <button
            type="button"
            onClick={handleReset}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm text-slate-200 hover:bg-slate-900"
          >
            <Save className="h-4 w-4" />
            Reset draft
          </button>
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
        <StepProgress
          steps={STEPS}
          currentStepIndex={currentStep}
          completedMap={completedMap}
          onStepClick={(idx) => handleGoToStep(idx)}
        />

        {globalError && (
          <div className="mt-4 rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-sm text-rose-200">
            {globalError}
          </div>
        )}

        <div className="mt-6">{StepComponent}</div>

        <div className="mt-6 flex items-center justify-between gap-3">
          <button
            type="button"
            onClick={handleBack}
            disabled={currentStep === 0}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950/40 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-slate-950 disabled:opacity-50"
          >
            <ArrowLeft className="h-4 w-4" />
            Indietro
          </button>

          <div className="flex items-center gap-2">
            {currentStep < 7 ? (
              <button
                type="button"
                onClick={handleNext}
                className="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500"
              >
                Avanti
                <ArrowRight className="h-4 w-4" />
              </button>
            ) : (
              <button
                type="button"
                onClick={handleGenerateWizard}
                disabled={createWizardMutation.isPending}
                className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50"
              >
                <Wand2 className="h-4 w-4" />
                {createWizardMutation.isPending ? 'Generazione…' : 'GENERA WIZARD'}
              </button>
            )}
          </div>
        </div>
      </div>

      {postGenModal.open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
          <div className="w-full max-w-lg rounded-xl border border-slate-800 bg-slate-950 p-5">
            <div className="text-lg font-semibold text-slate-100">Wizard generato</div>
            <p className="mt-1 text-sm text-slate-400">
              Usa questo codice nell’agent Windows oppure condividi il link diretto.
            </p>

            <div className="mt-4 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
              <div className="text-xs uppercase tracking-wide text-slate-400">Codice univoco</div>
              <div className="mt-2 flex items-center justify-between gap-3">
                <div className="font-mono text-3xl font-bold text-slate-100">
                  {postGenModal.code}
                </div>
                <button
                  type="button"
                  onClick={() => navigator.clipboard.writeText(postGenModal.code)}
                  className="rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-sm text-slate-200 hover:bg-slate-950"
                >
                  Copia
                </button>
              </div>

              <div className="mt-4 text-xs uppercase tracking-wide text-slate-400">Link diretto</div>
              <div className="mt-2 flex items-center gap-2">
                <input
                  readOnly
                  value={postGenModal.directLink}
                  className="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100"
                />
                <button
                  type="button"
                  onClick={() => navigator.clipboard.writeText(postGenModal.directLink)}
                  className="shrink-0 rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-sm text-slate-200 hover:bg-slate-950"
                >
                  Copia link
                </button>
              </div>
            </div>

            <div className="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
              <a
                href="/agent/agent.exe"
                className="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500"
              >
                Scarica agent.exe
              </a>

              {postGenModal.wizardId && (
                <button
                  type="button"
                  onClick={() => navigate(`/wizards/${postGenModal.wizardId}/monitor`)}
                  className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
                >
                  Vai al monitor
                </button>
              )}

              <button
                type="button"
                onClick={() => setPostGenModal({ open: false, code: '', wizardId: null, directLink: '' })}
                className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
              >
                Chiudi
              </button>
            </div>

            <div className="mt-3 text-xs text-slate-400">
              Sicurezza: la password admin e la password Wi-Fi vanno cifrate server-side e non devono essere restituite nelle API di listing. [file:8]
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
```

---

## `src/pages/Wizards/steps/Step1Info.jsx`

```jsx
import React from "react";

export default function Step1Info({
  wizard,
  dispatch,
  templates,
  templatesLoading,
  onTemplateSelect,
}) {
  const templateId = wizard.meta.templateId;

  return (
    <div className="space-y-5">
      <div>
        <div className="text-sm font-semibold text-slate-100">
          STEP 1 — Info base
        </div>
        <div className="mt-1 text-sm text-slate-400">
          Definisci i metadati del wizard e, se vuoi, carica un template
          esistente come base.
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label
            className="block text-xs font-medium text-slate-300"
            htmlFor="wizardName"
          >
            Nome wizard
          </label>
          <input
            id="wizardName"
            type="text"
            value={wizard.meta.wizardName}
            onChange={(e) =>
              dispatch({
                type: "PATCH_PATH",
                payload: {
                  path: ["meta", "wizardName"],
                  value: e.target.value,
                },
              })
            }
            placeholder="Es. Setup Contabilità Standard"
            className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
          />
          <div className="mt-2 text-xs text-slate-400">
            Suggerimento: usa un nome descrittivo e riutilizzabile.
          </div>
        </div>

        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label
            className="block text-xs font-medium text-slate-300"
            htmlFor="template"
          >
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
            Se selezioni un template, verranno precompilati i campi principali
            (software, bloatware, power plan, ecc.).
          </div>
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <label
          className="block text-xs font-medium text-slate-300"
          htmlFor="notes"
        >
          Note interne
        </label>
        <textarea
          id="notes"
          rows={5}
          value={wizard.meta.internalNotes}
          onChange={(e) =>
            dispatch({
              type: "PATCH_PATH",
              payload: {
                path: ["meta", "internalNotes"],
                value: e.target.value,
              },
            })
          }
          placeholder="Annotazioni per il team IT (non visibili all'utente finale)…"
          className="mt-2 w-full resize-none rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
        />
      </div>
    </div>
  );
}
```

---

## `src/pages/Wizards/steps/Step2NomePC.jsx`

```jsx
import React, { useMemo } from "react";

const DEPARTMENTS = [
  { id: "IT", label: "IT" },
  { id: "AMM", label: "AMM" },
  { id: "COM", label: "COM" },
  { id: "DEV", label: "DEV" },
];

export default function Step2NomePC({ wizard, dispatch, validatePcName }) {
  const validation = useMemo(
    () => validatePcName(wizard.pcName.raw),
    [wizard.pcName.raw, validatePcName],
  );

  return (
    <div className="space-y-5">
      <div>
        <div className="text-sm font-semibold text-slate-100">
          STEP 2 — Nome PC
        </div>
        <div className="mt-1 text-sm text-slate-400">
          Il nome PC è validato secondo regole compatibili con Windows (max 15,
          solo alfanumerico e trattini).
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <label
          className="block text-xs font-medium text-slate-300"
          htmlFor="pcName"
        >
          Nome PC
        </label>
        <input
          id="pcName"
          type="text"
          value={wizard.pcName.raw}
          onChange={(e) =>
            dispatch({
              type: "PATCH_PATH",
              payload: { path: ["pcName", "raw"], value: e.target.value },
            })
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
                type: "PATCH_PATH",
                payload: {
                  path: ["pcName", "useDepartmentVariable"],
                  value: e.target.checked,
                },
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
            <label
              className="block text-xs font-medium text-slate-300"
              htmlFor="dept"
            >
              Reparto
            </label>
            <select
              id="dept"
              value={wizard.pcName.department}
              onChange={(e) =>
                dispatch({
                  type: "PATCH_PATH",
                  payload: {
                    path: ["pcName", "department"],
                    value: e.target.value,
                  },
                })
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
        <div className="text-xs font-medium text-slate-300">
          Anteprima in tempo reale
        </div>
        <div className="mt-2 font-mono text-lg text-slate-100">
          {wizard.pcName.formattedPreview || "—"}
        </div>
        <div className="mt-2 text-xs text-slate-400">
          L’agent userà questo valore per rinominare la macchina.
        </div>
      </div>
    </div>
  );
}
```

---

## `src/pages/Wizards/steps/Step3Utente.jsx`

```jsx
import React, { useMemo, useState } from "react";

function StrengthBar({ score }) {
  const pct = Math.round((Math.min(Math.max(score, 0), 5) / 5) * 100);
  let cls = "bg-rose-500";
  if (score >= 3) cls = "bg-amber-500";
  if (score >= 4) cls = "bg-emerald-500";

  return (
    <div className="mt-2">
      <div className="h-2 w-full overflow-hidden rounded-full bg-slate-800">
        <div className={`h-full ${cls}`} style={{ width: `${pct}%` }} />
      </div>
      <div className="mt-1 text-xs text-slate-400">
        Strength: {score}/5 (consigliato ≥ 3)
      </div>
    </div>
  );
}

export default function Step3Utente({
  wizard,
  dispatch,
  validateUsername,
  passwordStrength,
}) {
  const [showPw, setShowPw] = useState(false);
  const [showPw2, setShowPw2] = useState(false);

  const userValidation = useMemo(
    () => validateUsername(wizard.localAdmin.username),
    [wizard.localAdmin.username, validateUsername],
  );

  const pwScore = useMemo(
    () => passwordStrength(wizard.localAdmin.password),
    [wizard.localAdmin.password, passwordStrength],
  );

  const pwMatch =
    wizard.localAdmin.password && wizard.localAdmin.passwordConfirm
      ? wizard.localAdmin.password === wizard.localAdmin.passwordConfirm
      : true;

  return (
    <div className="space-y-5">
      <div>
        <div className="text-sm font-semibold text-slate-100">
          STEP 3 — Utente admin locale
        </div>
        <div className="mt-1 text-sm text-slate-400">
          Le credenziali vengono inviate su HTTPS al backend e devono essere
          cifrate server-side.
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <label
          className="block text-xs font-medium text-slate-300"
          htmlFor="adminUser"
        >
          Username
        </label>
        <input
          id="adminUser"
          type="text"
          value={wizard.localAdmin.username}
          onChange={(e) =>
            dispatch({
              type: "PATCH_PATH",
              payload: {
                path: ["localAdmin", "username"],
                value: e.target.value,
              },
            })
          }
          placeholder="Es. admin-locale"
          className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
        />
        {!userValidation.ok && (
          <div className="mt-2 text-sm text-rose-300">
            {userValidation.message}
          </div>
        )}
        {userValidation.ok && wizard.localAdmin.username && (
          <div className="mt-2 text-sm text-emerald-300">Username valido.</div>
        )}
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label
            className="block text-xs font-medium text-slate-300"
            htmlFor="pw"
          >
            Password
          </label>
          <div className="mt-2 flex items-center gap-2">
            <input
              id="pw"
              type={showPw ? "text" : "password"}
              value={wizard.localAdmin.password}
              onChange={(e) =>
                dispatch({
                  type: "PATCH_PATH",
                  payload: {
                    path: ["localAdmin", "password"],
                    value: e.target.value,
                  },
                })
              }
              className="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
            />
            <button
              type="button"
              onClick={() => setShowPw((v) => !v)}
              className="rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-sm text-slate-200 hover:bg-slate-950"
            >
              {showPw ? "Nascondi" : "Mostra"}
            </button>
          </div>
          <StrengthBar score={pwScore} />
        </div>

        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label
            className="block text-xs font-medium text-slate-300"
            htmlFor="pw2"
          >
            Conferma password
          </label>
          <div className="mt-2 flex items-center gap-2">
            <input
              id="pw2"
              type={showPw2 ? "text" : "password"}
              value={wizard.localAdmin.passwordConfirm}
              onChange={(e) =>
                dispatch({
                  type: "PATCH_PATH",
                  payload: {
                    path: ["localAdmin", "passwordConfirm"],
                    value: e.target.value,
                  },
                })
              }
              className="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
            />
            <button
              type="button"
              onClick={() => setShowPw2((v) => !v)}
              className="rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-sm text-slate-200 hover:bg-slate-950"
            >
              {showPw2 ? "Nascondi" : "Mostra"}
            </button>
          </div>

          {!pwMatch && (
            <div className="mt-2 text-sm text-rose-300">
              Le password non coincidono.
            </div>
          )}
          {pwMatch && wizard.localAdmin.passwordConfirm && (
            <div className="mt-2 text-sm text-emerald-300">Conferma OK.</div>
          )}
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <div className="flex items-center gap-2">
          <input
            id="removeMs"
            type="checkbox"
            checked={wizard.localAdmin.removeMicrosoftSetupAccount}
            onChange={(e) =>
              dispatch({
                type: "PATCH_PATH",
                payload: {
                  path: ["localAdmin", "removeMicrosoftSetupAccount"],
                  value: e.target.checked,
                },
              })
            }
            className="h-4 w-4 rounded border-slate-700 bg-slate-950 text-sky-500"
          />
          <label htmlFor="removeMs" className="text-sm text-slate-200">
            Rimuovi account Microsoft di setup iniziale
          </label>
        </div>

        <div className="mt-3 rounded-lg border border-slate-800 bg-slate-950 p-3 text-xs text-slate-300">
          Nota: la password viene cifrata e non sarà visibile dopo il
          salvataggio.
        </div>
      </div>
    </div>
  );
}
```

---

## `src/pages/Wizards/steps/Step4Software.jsx`

```jsx
import React, { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import client from "../../../api/client";

async function fetchSoftware() {
  const res = await client.get("/software", { params: { attivo: 1 } });
  return res.data;
}

function normalizeSoftware(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (payload.data && Array.isArray(payload.data)) return payload.data;
  if (payload.data && payload.data.data && Array.isArray(payload.data.data))
    return payload.data.data;
  return [];
}

function mapSoftwareRow(item) {
  const s = item?.data ? item.data : item;
  return {
    id: s?.id,
    name: s?.nome ?? s?.name ?? "—",
    version: s?.versione ?? s?.version ?? "—",
    type: String(s?.tipo ?? s?.type ?? "").toLowerCase(),
    category: s?.categoria ?? s?.category ?? "Altro",
  };
}

function categoryEmoji(cat) {
  const c = String(cat || "").toLowerCase();
  if (c.includes("browser")) return "🌐";
  if (c.includes("office") || c.includes("prod")) return "📄";
  if (c.includes("sic") || c.includes("security")) return "🛡️";
  if (c.includes("util")) return "🧰";
  if (c.includes("mult")) return "🎞️";
  if (c.includes("dev")) return "🧑‍💻";
  return "📦";
}

function TypeBadge({ type }) {
  const t = String(type || "").toLowerCase();
  const label =
    t === "winget"
      ? "Winget"
      : t === "exe"
        ? "EXE"
        : t === "msi"
          ? "MSI"
          : type || "—";
  const cls =
    t === "winget"
      ? "bg-sky-500/10 text-sky-300 border-sky-500/30"
      : "bg-slate-700/30 text-slate-200 border-slate-700";

  return (
    <span
      className={`inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium ${cls}`}
    >
      {label}
    </span>
  );
}

export default function Step4Software({ wizard, dispatch }) {
  const [search, setSearch] = useState("");
  const [category, setCategory] = useState("ALL");

  const softwareQuery = useQuery({
    queryKey: ["software", "attivo=1"],
    queryFn: fetchSoftware,
    staleTime: 30 * 1000,
  });

  const software = useMemo(
    () =>
      normalizeSoftware(softwareQuery.data)
        .map(mapSoftwareRow)
        .filter((s) => s.id != null),
    [softwareQuery.data],
  );

  const categories = useMemo(() => {
    const set = new Set(software.map((s) => String(s.category || "Altro")));
    return ["ALL", ...Array.from(set).sort()];
  }, [software]);

  const filtered = useMemo(() => {
    const q = String(search || "")
      .trim()
      .toLowerCase();
    return software.filter((s) => {
      const matchSearch = !q || String(s.name).toLowerCase().includes(q);
      const matchCat = category === "ALL" || String(s.category) === category;
      return matchSearch && matchCat;
    });
  }, [software, search, category]);

  const selectedSet = useMemo(
    () => new Set(wizard.software.selectedIds || []),
    [wizard.software.selectedIds],
  );

  function toggle(id) {
    const current = Array.isArray(wizard.software.selectedIds)
      ? wizard.software.selectedIds
      : [];
    const has = current.includes(id);
    const next = has ? current.filter((x) => x !== id) : [...current, id];
    dispatch({
      type: "PATCH_PATH",
      payload: { path: ["software", "selectedIds"], value: next },
    });
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
          <div className="text-sm font-semibold text-slate-100">
            STEP 4 — Software da installare
          </div>
          <div className="mt-1 text-sm text-slate-400">
            Lista caricata da /api/software?attivo=1.
          </div>
        </div>

        <div className="rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-sm text-slate-200">
          <span className="text-slate-400">Selezionati:</span>{" "}
          {selectedSet.size}
        </div>
      </div>

      <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
        <div className="md:col-span-2 rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label
            className="block text-xs font-medium text-slate-300"
            htmlFor="search"
          >
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
          <label
            className="block text-xs font-medium text-slate-300"
            htmlFor="cat"
          >
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
                {c === "ALL" ? "Tutte" : c}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-900/30">
        <div className="border-b border-slate-800 px-4 py-3 text-sm font-medium text-slate-200">
          Catalogo software
        </div>

        {softwareQuery.isLoading && (
          <div className="p-4 text-sm text-slate-400">Caricamento…</div>
        )}
        {softwareQuery.isError && (
          <div className="p-4 text-sm text-rose-300">
            Errore caricamento software:{" "}
            {String(softwareQuery.error?.message || "richiesta fallita")}
          </div>
        )}

        {!softwareQuery.isLoading &&
          !softwareQuery.isError &&
          filtered.length === 0 && (
            <div className="p-4 text-sm text-slate-400">Nessun risultato.</div>
          )}

        {!softwareQuery.isLoading &&
          !softwareQuery.isError &&
          filtered.length > 0 && (
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
                        <div className="text-sm font-medium text-slate-100">
                          {s.name}
                        </div>
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
```

---

## `src/pages/Wizards/steps/Step5Bloatware.jsx`

```jsx
import React, { useMemo } from "react";

const BLOATWARE_LIST = [
  "Microsoft.XboxApp",
  "Microsoft.XboxGamingOverlay",
  "Microsoft.XboxGameOverlay",
  "Microsoft.XboxIdentityProvider",
  "Microsoft.XboxSpeechToTextOverlay",
  "king.com.CandyCrushSaga",
  "king.com.CandyCrushFriends",
  "MicrosoftTeams",
  "Microsoft.OneDrive",
  "Microsoft.SkypeApp",
  "Microsoft.GetHelp",
  "Microsoft.Getstarted",
  "Microsoft.MicrosoftNews",
  "Microsoft.BingNews",
  "Microsoft.BingWeather",
  "Microsoft.BingSports",
  "Microsoft.BingFinance",
  "Microsoft.WindowsFeedbackHub",
  "Microsoft.MicrosoftSolitaireCollection",
  "Microsoft.People",
  "Microsoft.MicrosoftStickyNotes",
  "Microsoft.YourPhone",
  "Microsoft.ZuneMusic",
  "Microsoft.ZuneVideo",
  "Microsoft.Todos",
  "Microsoft.Cortana",
];

export default function Step5Bloatware({ wizard, dispatch }) {
  const selected = useMemo(
    () => new Set(wizard.bloatware.preselected || []),
    [wizard.bloatware.preselected],
  );

  function setAll(flag) {
    dispatch({
      type: "PATCH_PATH",
      payload: {
        path: ["bloatware", "preselected"],
        value: flag ? [...BLOATWARE_LIST] : [],
      },
    });
  }

  function toggle(item) {
    const current = Array.isArray(wizard.bloatware.preselected)
      ? wizard.bloatware.preselected
      : [];
    const has = current.includes(item);
    const next = has ? current.filter((x) => x !== item) : [...current, item];
    dispatch({
      type: "PATCH_PATH",
      payload: { path: ["bloatware", "preselected"], value: next },
    });
  }

  return (
    <div className="space-y-5">
      <div>
        <div className="text-sm font-semibold text-slate-100">
          STEP 5 — Bloatware da pre-selezionare
        </div>
        <div className="mt-1 text-sm text-slate-400">
          Queste app risulteranno pre-spuntate nell’agent ma l’utente potrà
          deselezionarle.
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
                  <div className="text-xs text-slate-400">
                    App Windows comune non necessaria
                  </div>
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
```

---

## `src/pages/Wizards/steps/Step6PowerPlan.jsx`

```jsx
import React from "react";
import { Battery, Gauge, Leaf } from "lucide-react";

function PresetButton({ active, icon, title, subtitle, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`w-full rounded-xl border p-4 text-left transition ${
        active
          ? "border-sky-500 bg-sky-500/10"
          : "border-slate-800 bg-slate-950/40 hover:bg-slate-950"
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
        <div className="text-sm font-semibold text-slate-100">
          STEP 6 — Power Plan
        </div>
        <div className="mt-1 text-sm text-slate-400">
          Scegli un preset o abilita la configurazione manuale.
        </div>
      </div>

      <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
        <PresetButton
          active={!wizard.powerPlan.manual && preset === "balanced"}
          icon={<Gauge className="h-5 w-5" />}
          title="Bilanciato"
          subtitle="Default consigliato per la maggior parte dei PC."
          onClick={() => {
            dispatch({
              type: "PATCH_PATH",
              payload: { path: ["powerPlan", "preset"], value: "balanced" },
            });
            dispatch({
              type: "PATCH_PATH",
              payload: { path: ["powerPlan", "manual"], value: false },
            });
          }}
        />
        <PresetButton
          active={!wizard.powerPlan.manual && preset === "high_performance"}
          icon={<Battery className="h-5 w-5" />}
          title="Prestazioni elevate"
          subtitle="Priorità alle performance (consumi più alti)."
          onClick={() => {
            dispatch({
              type: "PATCH_PATH",
              payload: {
                path: ["powerPlan", "preset"],
                value: "high_performance",
              },
            });
            dispatch({
              type: "PATCH_PATH",
              payload: { path: ["powerPlan", "manual"], value: false },
            });
          }}
        />
        <PresetButton
          active={!wizard.powerPlan.manual && preset === "power_saver"}
          icon={<Leaf className="h-5 w-5" />}
          title="Risparmio energetico"
          subtitle="Consumi ridotti (performance inferiori)."
          onClick={() => {
            dispatch({
              type: "PATCH_PATH",
              payload: { path: ["powerPlan", "preset"], value: "power_saver" },
            });
            dispatch({
              type: "PATCH_PATH",
              payload: { path: ["powerPlan", "manual"], value: false },
            });
          }}
        />
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <div className="flex items-center justify-between gap-3">
          <div>
            <div className="text-sm font-semibold text-slate-100">
              Configura manuale
            </div>
            <div className="mt-1 text-sm text-slate-400">
              Abilita per impostazioni granulari (timeout e limiti CPU).
            </div>
          </div>

          <button
            type="button"
            onClick={() =>
              dispatch({
                type: "PATCH_PATH",
                payload: {
                  path: ["powerPlan", "manual"],
                  value: !wizard.powerPlan.manual,
                },
              })
            }
            className={`rounded-full px-3 py-2 text-sm font-medium transition ${
              wizard.powerPlan.manual
                ? "bg-emerald-600 text-white"
                : "bg-slate-800 text-slate-200"
            }`}
          >
            {wizard.powerPlan.manual ? "ON" : "OFF"}
          </button>
        </div>

        {wizard.powerPlan.manual && (
          <div className="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <div className="text-xs font-medium text-slate-300">
                Timeout schermo (1–60 min)
              </div>
              <input
                type="range"
                min={1}
                max={60}
                value={wizard.powerPlan.screenTimeoutMin}
                onChange={(e) =>
                  dispatch({
                    type: "PATCH_PATH",
                    payload: {
                      path: ["powerPlan", "screenTimeoutMin"],
                      value: Number(e.target.value),
                    },
                  })
                }
                className="mt-2 w-full"
              />
              <div className="mt-1 text-sm text-slate-200">
                {wizard.powerPlan.screenTimeoutMin} min
              </div>
            </div>

            <div>
              <div className="flex items-center justify-between gap-2">
                <div className="text-xs font-medium text-slate-300">
                  Sospensione sistema
                </div>
                <label className="flex items-center gap-2 text-xs text-slate-300">
                  <input
                    type="checkbox"
                    checked={wizard.powerPlan.sleepNever}
                    onChange={(e) =>
                      dispatch({
                        type: "PATCH_PATH",
                        payload: {
                          path: ["powerPlan", "sleepNever"],
                          value: e.target.checked,
                        },
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
                        type: "PATCH_PATH",
                        payload: {
                          path: ["powerPlan", "sleepTimeoutMin"],
                          value: Number(e.target.value),
                        },
                      })
                    }
                    className="mt-2 w-full"
                  />
                  <div className="mt-1 text-sm text-slate-200">
                    {wizard.powerPlan.sleepTimeoutMin} min
                  </div>
                </>
              ) : (
                <div className="mt-2 text-sm text-slate-200">Mai</div>
              )}
            </div>

            <div>
              <div className="text-xs font-medium text-slate-300">
                CPU min% (0–100)
              </div>
              <input
                type="range"
                min={0}
                max={100}
                value={wizard.powerPlan.cpuMinPercent}
                onChange={(e) =>
                  dispatch({
                    type: "PATCH_PATH",
                    payload: {
                      path: ["powerPlan", "cpuMinPercent"],
                      value: Number(e.target.value),
                    },
                  })
                }
                className="mt-2 w-full"
              />
              <div className="mt-1 text-sm text-slate-200">
                {wizard.powerPlan.cpuMinPercent}%
              </div>
            </div>

            <div>
              <div className="text-xs font-medium text-slate-300">
                CPU max% (50–100)
              </div>
              <input
                type="range"
                min={50}
                max={100}
                value={wizard.powerPlan.cpuMaxPercent}
                onChange={(e) =>
                  dispatch({
                    type: "PATCH_PATH",
                    payload: {
                      path: ["powerPlan", "cpuMaxPercent"],
                      value: Number(e.target.value),
                    },
                  })
                }
                className="mt-2 w-full"
              />
              <div className="mt-1 text-sm text-slate-200">
                {wizard.powerPlan.cpuMaxPercent}%
              </div>
            </div>

            <div className="md:col-span-2 rounded-lg border border-slate-800 bg-slate-950 p-3 text-xs text-slate-300">
              Avviso: parametri manuali errati possono peggiorare autonomia,
              rumorosità o performance; valida bene i preset in base
              all’hardware.
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
```

---

## `src/pages/Wizards/steps/Step7Extra.jsx`

```jsx
import React, { useEffect } from 'react';

const TIMEZONES = [
  'Europe/Rome',
  'Europe/Berlin',
  'Europe/London',
  'UTC',
  'America/New_York',
  'Asia/Dubai',
  'Asia/Tokyo',
];

const LANGS = [
  { id: 'it-IT', label: 'Italiano (IT)' },
  { id: 'en-US', label: 'English (US)' },
  { id: 'en-GB', label: 'English (UK)' },
  { id: 'de-DE', label: 'Deutsch (DE)' },
  { id: 'fr-FR', label: 'Français (FR)' },
];

const KEYBOARDS = [
  { id: 'it-IT', label: 'Tastiera IT' },
  { id: 'en-US', label: 'Tastiera US' },
  { id: 'en-GB', label: 'Tastiera UK' },
  { id: 'de-DE', label: 'Tastiera DE' },
];

export default function Step7Extra({ wizard, dispatch }) {
  useEffect(() => {
    return () => {
      if (wizard.extras.wallpaperPreviewUrl) {
        try {
          URL.revokeObjectURL(wizard.extras.wallpaperPreviewUrl);
        } catch {}
      }
    };
  }, [wizard.extras.wallpaperPreviewUrl]);

  function handleWallpaperChange(file) {
    if (!file) {
      dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'wallpaperFile'], value: null } });
      dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'wallpaperPreviewUrl'], value: '' } });
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      alert('File troppo grande: max 5MB');
      return;
    }

    const previewUrl = URL.createObjectURL(file);
    dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'wallpaperFile'], value: file } });
    dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'wallpaperPreviewUrl'], value: previewUrl } });
  }

  return (
    <div className="space-y-5">
      <div>
        <div className="text-sm font-semibold text-slate-100">STEP 7 — Extra opzionali</div>
        <div className="mt-1 text-sm text-slate-400">Impostazioni aggiuntive (timezone, lingua, wallpaper, Wi-Fi, update policy).</div>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label className="block text-xs font-medium text-slate-300" htmlFor="tz">
            Timezone
          </label>
          <select
            id="tz"
            value={wizard.extras.timezone}
            onChange={(e) => dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'timezone'], value: e.target.value } })}
            className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
          >
            {TIMEZONES.map((tz) => (
              <option key={tz} value={tz}>{tz}</option>
            ))}
          </select>
        </div>

        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label className="block text-xs font-medium text-slate-300" htmlFor="lang">
            Lingua
          </label>
          <select
            id="lang"
            value={wizard.extras.language}
            onChange={(e) => dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'language'], value: e.target.value } })}
            className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
          >
            {LANGS.map((l) => (
              <option key={l.id} value={l.id}>{l.label}</option>
            ))}
          </select>

          <label className="mt-4 block text-xs font-medium text-slate-300" htmlFor="kbd">
            Tastiera
          </label>
          <select
            id="kbd"
            value={wizard.extras.keyboardLayout}
            onChange={(e) => dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'keyboardLayout'], value: e.target.value } })}
            className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
          >
            {KEYBOARDS.map((k) => (
              <option key={k.id} value={k.id}>{k.label}</option>
            ))}
          </select>
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <div className="text-sm font-semibold text-slate-100">Wallpaper aziendale</div>
        <div className="mt-1 text-sm text-slate-400">Upload immagine (max 5MB) con preview.</div>

        <input
          type="file"
          accept="image/*"
          onChange={(e) => handleWallpaperChange(e.target.files?.[^1_0] || null)}
          className="mt-3 block w-full text-sm text-slate-200 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-800 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-100 hover:file:bg-slate-700"
        />

        {wizard.extras.wallpaperPreviewUrl && (
          <div className="mt-4">
            <div className="text-xs text-slate-400">Preview:</div>
            <img
              src={wizard.extras.wallpaperPreviewUrl}
              alt="Wallpaper preview"
              className="mt-2 max-h-56 w-full rounded-lg border border-slate-800 object-cover"
            />
          </div>
        )}
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <div className="flex items-center justify-between gap-3">
          <div>
            <div className="text-sm font-semibold text-slate-100">Wi‑Fi</div>
            <div className="mt-1 text-sm text-slate-400">Se abilitato, l’agent userà SSID e password (da cifrare server-side).</div>
          </div>

          <button
            type="button"
            onClick={() =>
              dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'wifiEnabled'], value: !wizard.extras.wifiEnabled } })
            }
            className={`rounded-full px-3 py-2 text-sm font-medium transition ${
              wizard.extras.wifiEnabled ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-200'
            }`}
          >
            {wizard.extras.wifiEnabled ? 'ON' : 'OFF'}
          </button>
        </div>

        {wizard.extras.wifiEnabled && (
          <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="ssid">
                SSID
              </label>
              <input
                id="ssid"
                type="text"
                value={wizard.extras.wifiSsid}
                onChange={(e) => dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'wifiSsid'], value: e.target.value } })}
                className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="wifipw">
                Password (cifrata)
              </label>
              <input
                id="wifipw"
                type="password"
                value={wizard.extras.wifiPassword}
                onChange={(e) => dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'wifiPassword'], value: e.target.value } })}
                className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>

            <div className="md:col-span-2 rounded-lg border border-slate-800 bg-slate-950 p-3 text-xs text-slate-300">
              Implicazione di sicurezza: evita di loggare o ri-mostrare questa password dopo il salvataggio. [file:8]
            </div>
          </div>
        )}
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <label className="block text-xs font-medium text-slate-300" htmlFor="wu">
          Windows Update
        </label>
        <select
          id="wu"
          value={wizard.extras.windowsUpdatePolicy}
          onChange={(e) => dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'windowsUpdatePolicy'], value: e.target.value } })}
          className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
        >
          <option value="auto">Automatico</option>
          <option value="downloadonly">Solo scarica</option>
          <option value="manual">Manuale</option>
        </select>
      </div>
    </div>
  );
}
```

---

## `src/pages/Wizards/steps/Step8Recap.jsx`

```jsx
import React, { useMemo, useState } from "react";
import { ChevronDown, ChevronUp, Edit3 } from "lucide-react";

function SectionCard({ title, children, onEdit, open, onToggle }) {
  return (
    <div className="rounded-xl border border-slate-800 bg-slate-950/40">
      <div className="flex items-center justify-between gap-3 border-b border-slate-800 px-4 py-3">
        <button
          type="button"
          onClick={onToggle}
          className="flex items-center gap-2 text-left text-sm font-semibold text-slate-100"
        >
          {open ? (
            <ChevronUp className="h-4 w-4 text-slate-300" />
          ) : (
            <ChevronDown className="h-4 w-4 text-slate-300" />
          )}
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
      {open && (
        <div className="px-4 py-3 text-sm text-slate-200">{children}</div>
      )}
    </div>
  );
}

export default function Step8Recap({
  wizard,
  onEditStep,
  onGenerate,
  isGenerating,
}) {
  const [open, setOpen] = useState({
    s1: true,
    s2: true,
    s3: true,
    s4: true,
    s5: true,
    s6: true,
    s7: true,
  });

  const softwareCount = useMemo(
    () => (wizard.software.selectedIds || []).length,
    [wizard.software.selectedIds],
  );

  return (
    <div className="space-y-5">
      <div>
        <div className="text-sm font-semibold text-slate-100">
          STEP 8 — Recap e genera
        </div>
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
          <div>
            <span className="text-slate-400">Nome:</span>{" "}
            {wizard.meta.wizardName || "—"}
          </div>
          <div className="mt-1">
            <span className="text-slate-400">Template ID:</span>{" "}
            {wizard.meta.templateId || "—"}
          </div>
          <div className="mt-1">
            <span className="text-slate-400">Note:</span>{" "}
            {wizard.meta.internalNotes ? wizard.meta.internalNotes : "—"}
          </div>
        </SectionCard>

        <SectionCard
          title="Nome PC"
          open={open.s2}
          onToggle={() => setOpen((p) => ({ ...p, s2: !p.s2 }))}
          onEdit={() => onEditStep(1)}
        >
          <div>
            <span className="text-slate-400">Raw:</span>{" "}
            {wizard.pcName.raw || "—"}
          </div>
          <div className="mt-1">
            <span className="text-slate-400">Preview:</span>{" "}
            <span className="font-mono">
              {wizard.pcName.formattedPreview || "—"}
            </span>
          </div>
        </SectionCard>

        <SectionCard
          title="Utente admin locale"
          open={open.s3}
          onToggle={() => setOpen((p) => ({ ...p, s3: !p.s3 }))}
          onEdit={() => onEditStep(2)}
        >
          <div>
            <span className="text-slate-400">Username:</span>{" "}
            {wizard.localAdmin.username || "—"}
          </div>
          <div className="mt-1">
            <span className="text-slate-400">Password:</span> (non mostrata)
          </div>
          <div className="mt-1">
            <span className="text-slate-400">Rimuovi account Microsoft:</span>{" "}
            {wizard.localAdmin.removeMicrosoftSetupAccount ? "Sì" : "No"}
          </div>
        </SectionCard>

        <SectionCard
          title="Software"
          open={open.s4}
          onToggle={() => setOpen((p) => ({ ...p, s4: !p.s4 }))}
          onEdit={() => onEditStep(3)}
        >
          <div>
            <span className="text-slate-400">Selezionati:</span> {softwareCount}
          </div>
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
          <div>
            <span className="text-slate-400">Voci:</span>{" "}
            {(wizard.bloatware.preselected || []).length}
          </div>
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
          <div>
            <span className="text-slate-400">Modalità:</span>{" "}
            {wizard.powerPlan.manual ? "Manuale" : "Preset"}
          </div>
          {!wizard.powerPlan.manual ? (
            <div className="mt-1">
              <span className="text-slate-400">Preset:</span>{" "}
              {wizard.powerPlan.preset}
            </div>
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
                2,
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
          <div>
            <span className="text-slate-400">Timezone:</span>{" "}
            {wizard.extras.timezone}
          </div>
          <div className="mt-1">
            <span className="text-slate-400">Lingua:</span>{" "}
            {wizard.extras.language}
          </div>
          <div className="mt-1">
            <span className="text-slate-400">Tastiera:</span>{" "}
            {wizard.extras.keyboardLayout}
          </div>
          <div className="mt-1">
            <span className="text-slate-400">Wallpaper:</span>{" "}
            {wizard.extras.wallpaperFile
              ? wizard.extras.wallpaperFile.name
              : "—"}
          </div>
          <div className="mt-1">
            <span className="text-slate-400">Wi‑Fi:</span>{" "}
            {wizard.extras.wifiEnabled
              ? `ON (${wizard.extras.wifiSsid || "SSID?"})`
              : "OFF"}
          </div>
          <div className="mt-1">
            <span className="text-slate-400">Windows Update:</span>{" "}
            {wizard.extras.windowsUpdatePolicy}
          </div>
        </SectionCard>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <div className="text-sm font-semibold text-slate-100">Generazione</div>
        <div className="mt-1 text-sm text-slate-400">
          Il backend deve cifrare password admin e Wi‑Fi e non restituirle mai
          nelle API generiche. [file:8]
        </div>

        <button
          type="button"
          onClick={onGenerate}
          disabled={isGenerating}
          className="mt-4 w-full rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
        >
          {isGenerating ? "Generazione in corso…" : "GENERA WIZARD"}
        </button>
      </div>
    </div>
  );
}
```

---

## Checklist operative (integrazione)

- Route React: aggiungi/assicurati di avere `"/wizards"`, `"/wizards/new"`, `"/wizards/:id/monitor"` (coerente con lo scaffold router).[^1_1]
- Backend: `POST /api/wizards` deve accettare `multipart/form-data` con `configurazione` JSON string e (opzionale) `wallpaper` file; lato DB la password va cifrata (AES-GCM) e non loggata.[^1_4]
- Sicurezza: **non** salvare in localStorage password reali in produzione; qui lo facciamo per requisito “anti-perdita dati”, ma è un rischio XSS (valuta almeno CSP + hardening e/o salvare draft senza password).[^1_5]

## “Avvocato del diavolo” (failure mode)

- Persistendo il draft completo in localStorage, la password admin/Wi‑Fi resta sul client: in caso di XSS o device compromesso è esfiltrabile; soluzione: escludere i campi sensibili dal draft o salvarli solo in-memory (trade-off UX).[^1_5]
- Step 4 carica software live: se l’endpoint è lento, la UX degrada; valuta caching, `staleTime`, paginazione o categorie preindicizzate (il backend già supporta filtri).[^1_3]
- Template “applica e sovrascrive”: può confondere; alternativa: “merge selettivo” con preview differenze (più complesso ma meno errori operativi).[^1_2]

Vuoi che aggiunga anche:

1. gli hook API dedicati (`src/api/wizards.js`, `src/hooks/useWizards.js`) in stile scaffold, oppure
2. la validazione schema lato backend (FormRequest WizardStoreRequest) per `configurazione` coerente con gli step?[^1_3]
   <span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_19][^1_20][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: 0013-SetupprogettoReact.md

[^1_2]: 0001-projectdescription.txt

[^1_3]: 0011-controllercrud.md

[^1_4]: 0008-db.md

[^1_5]: 0010-autenticazione.md

[^1_6]: https://www.reddit.com/r/nextjs/comments/11nq5o4/what_is_the_best_practices_for_creating_multistep/

[^1_7]: https://unwiredlearning.com/blog/react-multi-step-form

[^1_8]: https://www.youtube.com/watch?v=8QOfBYxYy7U

[^1_9]: https://www.travis-ci.com/blog/react-router-demystified-a-developers-guide-to-efficient-routing/

[^1_10]: https://namastedev.com/blog/react-router-dom-best-practices/

[^1_11]: https://stackoverflow.com/questions/68891102/how-to-use-react-query-usemutation-with-a-multi-part-file-upload

[^1_12]: https://dev.to/finalgirl321/getting-started-with-zustand-state-management-for-react-5786

[^1_13]: https://blog.logrocket.com/building-reusable-multi-step-form-react-hook-form-zod/

[^1_14]: https://github.com/TanStack/query/discussions/1098

[^1_15]: https://blog.logrocket.com/managing-react-state-zustand/

[^1_16]: https://dev.to/azfar731/creating-a-multi-part-form-easily-with-react-router-no-third-party-libraries-203e

[^1_17]: https://dev.to/imzihad21/master-react-api-management-with-tanstack-react-query-best-practices-examples-1139

[^1_18]: https://stackoverflow.com/questions/76801357/there-seems-to-be-a-problem-with-the-zustand-persist-function

[^1_19]: https://github.com/orgs/react-hook-form/discussions/12227

[^1_20]: https://tanstack.com/form/v1/docs/framework/react/examples/query-integration
