<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Crea la pagina Monitor per WinDeploy in React 18 + Tailwind CSS.

Questa pagina mostra in tempo reale l'avanzamento di una configurazione
in corso su un PC remoto.
 
URL: /wizards/:id/monitor
 
LAYOUT:

- Header con: nome wizard, codice univoco, nome PC in configurazione (se già disponibile)
- Badge stato (grande, colorato): In attesa / In esecuzione / Completato / Errore
- Info hardware PC (se già inviata dall'agent): CPU, RAM, Disco, versione Windows (card grigia)
 
SEZIONE STEP (lista verticale):
Ogni step viene mostrato in ordine cronologico con:
- Icona stato animata: ⏳ in attesa / 🔄 in corso (animazione pulse) / ✅ ok / ⚠️ avviso / ❌ errore
- Nome step e messaggio dettagliato
- Timestamp
Quando arriva un nuovo step, si aggiunge alla lista con animazione slide-in.
 
AGGIORNAMENTO: polling ogni 5 secondi a GET /api/wizards/:id/monitor
Se stato = completato o errore → smette di fare polling e mostra il pulsante "Vedi Report"
 
PROGRESS BAR:
Calcola la percentuale basandosi su quanti step sono stati completati vs totale atteso (8 step).
 
SEZIONE INFERIORE (se completato):
- Card verde "Configurazione completata" con statistiche: tot installati, tot rimossi, tot errori
- Bottone "Scarica Report HTML"
- Bottone "Configura un altro PC" → /wizards/new
 
Scrivi il file completo: src/pages/Monitor/index.jsx
Con custom hook: src/hooks/useMonitor.js (gestisce il polling e lo stato)

Di seguito trovi l’implementazione completa della pagina **Monitor** (React 18 + Tailwind) con polling ogni 5s su `GET /api/wizards/:id/monitor`, stop automatico su `completato`/`errore`, progress bar su 8 step attesi e animazione “slide-in” quando arriva un nuovo step. Il “pulse” per lo step in corso usa `animate-pulse` di Tailwind.[^1_1]

## Struttura file coinvolti

- `src/hooks/useMonitor.js`
- `src/pages/Monitor/index.jsx`

> Nota: l’animazione slide-in è implementata “senza dipendenze” (no react-transition-group) usando una classe Tailwind dinamica con `transition` + `transform`. Tailwind documenta le utility di animazione/transition (`animate-pulse`, ecc.).[^1_1]

***

## File: `src/hooks/useMonitor.js`

```jsx
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import client from "../api/client";

/**
 * Expected API (tolerant parsing):
 * GET /api/wizards/:id/monitor
 * {
 *   wizard: { id, nome|name, codice_univoco|codiceunivoco|code, stato|status },
 *   pc: { nome|name }, // optional
 *   hardware: { cpu, ram_gb|ramGb|ram, disco_gb|discoGb|disco, windows_version|windowsVersion|windows },
 *   steps: [{ nome|name, stato|status, messaggio|message, timestamp|created_at|time }],
 *   summary: { installati, rimossi, errori } // optional, shown only when completed
 * }
 */

const EXPECTED_TOTAL_STEPS = 8;
const POLL_INTERVAL_MS = 5000;

function normalizeStatus(raw) {
  const s = String(raw ?? "").trim().toLowerCase();
  if (["in attesa", "attesa", "waiting", "queued"].includes(s)) return "in_attesa";
  if (["in esecuzione", "esecuzione", "running", "in_corso", "incorso"].includes(s)) return "in_esecuzione";
  if (["completato", "completed", "done"].includes(s)) return "completato";
  if (["errore", "error", "failed", "abortito", "aborted"].includes(s)) return "errore";
  return s || "in_attesa";
}

function normalizeStepStatus(raw) {
  const s = String(raw ?? "").trim().toLowerCase();
  if (["in attesa", "attesa", "waiting", "queued"].includes(s)) return "pending";
  if (["in corso", "incorso", "running", "processing"].includes(s)) return "running";
  if (["ok", "success", "completato", "completed", "done"].includes(s)) return "ok";
  if (["avviso", "warning", "warn"].includes(s)) return "warning";
  if (["errore", "error", "failed", "abortito", "aborted"].includes(s)) return "error";
  return s || "pending";
}

function toIsoOrNull(value) {
  if (!value) return null;
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return null;
  return d.toISOString();
}

function safeArray(value) {
  return Array.isArray(value) ? value : [];
}

function normalizeMonitorPayload(payload) {
  const p = payload?.data ?? payload ?? {};

  const wizardRaw = p.wizard ?? p.data?.wizard ?? p.wizardData ?? {};
  const wizard = {
    id: wizardRaw.id ?? p.wizard_id ?? p.wizardId ?? null,
    name: wizardRaw.nome ?? wizardRaw.name ?? p.nome ?? p.name ?? "Wizard",
    code:
      wizardRaw.codice_univoco ??
      wizardRaw.codiceunivoco ??
      wizardRaw.code ??
      p.codice_univoco ??
      p.codiceunivoco ??
      p.code ??
      "",
    status: normalizeStatus(wizardRaw.stato ?? wizardRaw.status ?? p.stato ?? p.status),
  };

  const pcRaw = p.pc ?? p.machine ?? p.client ?? {};
  const pc = {
    name: pcRaw.nome ?? pcRaw.name ?? p.pc_nome ?? p.pcName ?? null,
  };

  const hwRaw = p.hardware ?? p.hardwareinfo ?? p.hw ?? p.pcinfo ?? {};
  const hardware = {
    cpu: hwRaw.cpu ?? null,
    ramGb: hwRaw.ram_gb ?? hwRaw.ramGb ?? hwRaw.ram ?? null,
    diskGb: hwRaw.disco_gb ?? hwRaw.discoGb ?? hwRaw.disco ?? hwRaw.disk_gb ?? hwRaw.diskGb ?? null,
    windowsVersion: hwRaw.windows_version ?? hwRaw.windowsVersion ?? hwRaw.windows ?? null,
  };

  const stepsRaw = safeArray(p.steps ?? p.log ?? p.logdettagliato ?? p.execution?.logdettagliato ?? []);
  const steps = stepsRaw
    .map((s, idx) => {
      const name = s.nome ?? s.name ?? s.step ?? s.titolo ?? `Step ${idx + 1}`;
      const status = normalizeStepStatus(s.stato ?? s.status ?? s.esito ?? s.result);
      const message = s.messaggio ?? s.message ?? s.dettaglio ?? s.detail ?? "";
      const ts = toIsoOrNull(s.timestamp ?? s.created_at ?? s.time ?? s.at ?? s.updated_at);
      return {
        id: s.id ?? `${name}-${ts ?? idx}`,
        name: String(name),
        status,
        message: message ? String(message) : "",
        timestamp: ts,
      };
    })
    .filter(Boolean);

  const summaryRaw = p.summary ?? p.sommario ?? p.sommariofinale ?? null;
  const summary = summaryRaw
    ? {
        installed: Array.isArray(summaryRaw.installati) ? summaryRaw.installati.length : Number(summaryRaw.installati ?? 0) || 0,
        removed: Array.isArray(summaryRaw.rimossi) ? summaryRaw.rimossi.length : Number(summaryRaw.rimossi ?? 0) || 0,
        errors: Array.isArray(summaryRaw.errori) ? summaryRaw.errori.length : Number(summaryRaw.errori ?? 0) || 0,
      }
    : null;

  return { wizard, pc, hardware, steps, summary };
}

function computeCompletedCount(steps) {
  return safeArray(steps).filter((s) => s?.status === "ok" || s?.status === "warning" || s?.status === "error").length;
}

export default function useMonitor(wizardId) {
  const [data, setData] = useState({
    wizard: { id: wizardId ?? null, name: "Wizard", code: "", status: "in_attesa" },
    pc: { name: null },
    hardware: { cpu: null, ramGb: null, diskGb: null, windowsVersion: null },
    steps: [],
    summary: null,
  });

  const [ui, setUi] = useState({
    isLoading: true,
    isPolling: true,
    isError: false,
    errorMessage: "",
    lastUpdatedAt: null,
    newStepIds: [],
  });

  const timerRef = useRef(null);
  const prevStepIdsRef = useRef(new Set());

  const shouldPoll = useMemo(() => {
    const status = data?.wizard?.status;
    return ui.isPolling && status !== "completato" && status !== "errore";
  }, [data?.wizard?.status, ui.isPolling]);

  const fetchOnce = useCallback(async () => {
    if (!wizardId) return;

    try {
      setUi((prev) => ({
        ...prev,
        isError: false,
        errorMessage: "",
      }));

      const res = await client.get(`/wizards/${wizardId}/monitor`);
      const normalized = normalizeMonitorPayload(res?.data);

      const nextSteps = normalized.steps ?? [];
      const prevIds = prevStepIdsRef.current;
      const nextIds = new Set(nextSteps.map((s) => s.id));

      const newIds = [];
      for (const s of nextSteps) {
        if (!prevIds.has(s.id)) newIds.push(s.id);
      }

      prevStepIdsRef.current = nextIds;

      setData((prev) => ({
        ...prev,
        ...normalized,
      }));

      setUi((prev) => ({
        ...prev,
        isLoading: false,
        lastUpdatedAt: new Date().toISOString(),
        newStepIds: newIds,
      }));

      // Remove "new" highlight after animation window
      if (newIds.length > 0) {
        window.setTimeout(() => {
          setUi((prev) => {
            const remaining = prev.newStepIds.filter((id) => !newIds.includes(id));
            return { ...prev, newStepIds: remaining };
          });
        }, 650);
      }

      const status = normalized?.wizard?.status;
      if (status === "completato" || status === "errore") {
        setUi((prev) => ({ ...prev, isPolling: false }));
      }
    } catch (err) {
      const msg =
        err?.response?.data?.message ??
        err?.message ??
        "Errore durante il recupero dello stato monitor.";
      setUi((prev) => ({
        ...prev,
        isLoading: false,
        isError: true,
        errorMessage: String(msg),
      }));
    }
  }, [wizardId]);

  useEffect(() => {
    if (!wizardId) return;
    fetchOnce();
  }, [wizardId, fetchOnce]);

  useEffect(() => {
    if (!shouldPoll) {
      if (timerRef.current) window.clearInterval(timerRef.current);
      timerRef.current = null;
      return;
    }

    timerRef.current = window.setInterval(() => {
      fetchOnce();
    }, POLL_INTERVAL_MS);

    return () => {
      if (timerRef.current) window.clearInterval(timerRef.current);
      timerRef.current = null;
    };
  }, [shouldPoll, fetchOnce]);

  const completedCount = useMemo(() => computeCompletedCount(data.steps), [data.steps]);

  const progressPercent = useMemo(() => {
    const pct = Math.round((Math.min(completedCount, EXPECTED_TOTAL_STEPS) / EXPECTED_TOTAL_STEPS) * 100);
    return Math.max(0, Math.min(100, pct));
  }, [completedCount]);

  const actions = useMemo(() => {
    return {
      refetch: fetchOnce,
      stopPolling: () => setUi((prev) => ({ ...prev, isPolling: false })),
      startPolling: () => setUi((prev) => ({ ...prev, isPolling: true })),
    };
  }, [fetchOnce]);

  return {
    data,
    ui,
    progress: {
      expectedTotal: EXPECTED_TOTAL_STEPS,
      completedCount,
      percent: progressPercent,
    },
    actions,
  };
}
```


***

## File: `src/pages/Monitor/index.jsx`

```jsx
import React, { useMemo } from "react";
import { NavLink, useNavigate, useParams } from "react-router-dom";
import useMonitor from "../../hooks/useMonitor";

function formatTs(iso) {
  if (!iso) return "—";
  try {
    const d = new Date(iso);
    return d.toLocaleString();
  } catch {
    return "—";
  }
}

function statusBadge(status) {
  const s = String(status ?? "");
  if (s === "in_attesa") return { label: "In attesa", cls: "bg-slate-700/40 text-slate-100 border-slate-600/60" };
  if (s === "in_esecuzione") return { label: "In esecuzione", cls: "bg-sky-500/20 text-sky-200 border-sky-500/40" };
  if (s === "completato") return { label: "Completato", cls: "bg-emerald-500/20 text-emerald-200 border-emerald-500/40" };
  if (s === "errore") return { label: "Errore", cls: "bg-rose-500/20 text-rose-200 border-rose-500/40" };
  return { label: s || "—", cls: "bg-slate-700/40 text-slate-100 border-slate-600/60" };
}

function stepIcon(stepStatus) {
  if (stepStatus === "pending") return { icon: "⏳", cls: "text-slate-300" };
  if (stepStatus === "running") return { icon: "🔄", cls: "text-sky-300 motion-safe:animate-pulse" };
  if (stepStatus === "ok") return { icon: "✅", cls: "text-emerald-300" };
  if (stepStatus === "warning") return { icon: "⚠️", cls: "text-amber-300" };
  if (stepStatus === "error") return { icon: "❌", cls: "text-rose-300" };
  return { icon: "⏳", cls: "text-slate-300" };
}

function ProgressBar({ percent, labelLeft, labelRight }) {
  return (
    <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
      <div className="flex items-center justify-between gap-3 text-xs text-slate-400">
        <div className="truncate">{labelLeft}</div>
        <div className="shrink-0 font-mono">{labelRight}</div>
      </div>

      <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-800">
        <div
          className="h-full rounded-full bg-sky-500 transition-all duration-500"
          style={{ width: `${percent}%` }}
          aria-label={`Progresso ${percent}%`}
        />
      </div>
    </div>
  );
}

function HardwareCard({ hardware }) {
  const hasAny =
    Boolean(hardware?.cpu) ||
    hardware?.ramGb !== null ||
    hardware?.diskGb !== null ||
    Boolean(hardware?.windowsVersion);

  if (!hasAny) return null;

  return (
    <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
      <div className="text-sm font-semibold text-slate-100">Hardware PC</div>
      <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
        <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
          <div className="text-xs text-slate-400">CPU</div>
          <div className="mt-1 text-sm text-slate-100">{hardware?.cpu ?? "—"}</div>
        </div>
        <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
          <div className="text-xs text-slate-400">RAM</div>
          <div className="mt-1 text-sm text-slate-100">
            {hardware?.ramGb !== null && hardware?.ramGb !== undefined ? `${hardware.ramGb} GB` : "—"}
          </div>
        </div>
        <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
          <div className="text-xs text-slate-400">Disco</div>
          <div className="mt-1 text-sm text-slate-100">
            {hardware?.diskGb !== null && hardware?.diskGb !== undefined ? `${hardware.diskGb} GB` : "—"}
          </div>
        </div>
        <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
          <div className="text-xs text-slate-400">Windows</div>
          <div className="mt-1 text-sm text-slate-100">{hardware?.windowsVersion ?? "—"}</div>
        </div>
      </div>
    </div>
  );
}

function StepRow({ step, isNew }) {
  const meta = stepIcon(step.status);

  // Slide-in: new items start slightly translated + transparent, then go to normal
  const base =
    "rounded-xl border border-slate-800 bg-slate-900/40 p-4 transition-all duration-500";
  const enter = isNew ? "opacity-0 -translate-y-2" : "opacity-100 translate-y-0";

  return (
    <div className={`${base} ${enter}`}>
      <div className="flex items-start gap-3">
        <div className={`shrink-0 text-xl leading-none ${meta.cls}`} aria-hidden="true">
          {meta.icon}
        </div>

        <div className="min-w-0 flex-1">
          <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div className="truncate text-sm font-semibold text-slate-100">{step.name}</div>
            <div className="shrink-0 text-xs text-slate-400">{formatTs(step.timestamp)}</div>
          </div>

          {step.message ? (
            <div className="mt-2 whitespace-pre-wrap text-sm text-slate-200">{step.message}</div>
          ) : (
            <div className="mt-2 text-sm text-slate-400">Nessun dettaglio disponibile.</div>
          )}
        </div>
      </div>
    </div>
  );
}

export default function MonitorPage() {
  const { id } = useParams();
  const navigate = useNavigate();

  const { data, ui, progress, actions } = useMonitor(id);

  const badge = useMemo(() => statusBadge(data?.wizard?.status), [data?.wizard?.status]);

  const showReportCta = data?.wizard?.status === "completato" || data?.wizard?.status === "errore";

  const headerPcName = data?.pc?.name ? String(data.pc.name) : null;

  const completedCardVisible = data?.wizard?.status === "completato";

  const summary = data?.summary ?? { installed: 0, removed: 0, errors: 0 };

  // For slide-in: force reflow on mount by rendering "new" class first; hook already clears newStepIds after ~650ms
  const newIdsSet = useMemo(() => new Set(ui.newStepIds ?? []), [ui.newStepIds]);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div className="min-w-0">
          <div className="text-xs uppercase tracking-wide text-slate-400">Monitor</div>
          <h1 className="mt-1 truncate text-xl font-semibold text-slate-100">
            {data?.wizard?.name ?? "Wizard"}
          </h1>

          <div className="mt-2 flex flex-col gap-1 text-sm text-slate-300">
            <div className="flex items-center gap-2">
              <span className="text-slate-400">Codice:</span>
              <span className="font-mono text-slate-100">{data?.wizard?.code || "—"}</span>
            </div>
            <div className="flex items-center gap-2">
              <span className="text-slate-400">PC:</span>
              <span className="text-slate-100">{headerPcName ?? "In attesa info dal PC..."}</span>
            </div>
          </div>
        </div>

        <div className="flex flex-col items-start gap-3 sm:flex-row sm:items-center">
          <div className={`rounded-2xl border px-5 py-3 text-base font-semibold ${badge.cls}`}>
            {badge.label}
          </div>

          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={actions.refetch}
              className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
            >
              Aggiorna
            </button>

            <NavLink
              to="/wizards"
              className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
            >
              Torna ai wizard
            </NavLink>
          </div>
        </div>
      </div>

      {/* Errors */}
      {ui.isError ? (
        <div className="rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-200">
          {ui.errorMessage || "Errore non specificato."}
        </div>
      ) : null}

      {/* Top cards */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <ProgressBar
            percent={progress.percent}
            labelLeft={`Progresso (${progress.completedCount}/${progress.expectedTotal} step completati)`}
            labelRight={`${progress.percent}%`}
          />
          <div className="mt-3 text-xs text-slate-500">
            {ui.lastUpdatedAt ? `Ultimo aggiornamento: ${formatTs(ui.lastUpdatedAt)}` : "—"}
            {ui.isPolling && !showReportCta ? " • Polling ogni 5s" : " • Polling fermo"}
          </div>
        </div>

        <HardwareCard hardware={data?.hardware} />
      </div>

      {/* Steps */}
      <div className="space-y-3">
        <div className="flex items-end justify-between gap-3">
          <div>
            <div className="text-sm font-semibold text-slate-100">Step</div>
            <div className="mt-1 text-sm text-slate-400">Avanzamento in ordine cronologico.</div>
          </div>

          {showReportCta ? (
            <button
              type="button"
              onClick={() => navigate(`/reports/${id}`)}
              className="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500"
            >
              Vedi Report
            </button>
          ) : null}
        </div>

        {ui.isLoading ? (
          <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4 text-sm text-slate-400">
            Caricamento monitor...
          </div>
        ) : null}

        {!ui.isLoading && (data?.steps?.length ?? 0) === 0 ? (
          <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4 text-sm text-slate-400">
            Nessuno step ancora ricevuto.
          </div>
        ) : null}

        <div className="space-y-3">
          {(data?.steps ?? []).map((s) => (
            <StepRow key={s.id} step={s} isNew={newIdsSet.has(s.id)} />
          ))}
        </div>
      </div>

      {/* Completed section */}
      {completedCardVisible ? (
        <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-5">
          <div className="text-lg font-semibold text-emerald-100">Configurazione completata</div>
          <div className="mt-1 text-sm text-emerald-200/80">
            Il PC ha terminato l’esecuzione del wizard. Puoi scaricare il report o avviare una nuova configurazione.
          </div>

          <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div className="rounded-lg border border-emerald-500/20 bg-slate-950/30 p-3">
              <div className="text-xs text-emerald-200/80">Tot installati</div>
              <div className="mt-1 text-2xl font-semibold text-emerald-100">{summary.installed}</div>
            </div>
            <div className="rounded-lg border border-emerald-500/20 bg-slate-950/30 p-3">
              <div className="text-xs text-emerald-200/80">Tot rimossi</div>
              <div className="mt-1 text-2xl font-semibold text-emerald-100">{summary.removed}</div>
            </div>
            <div className="rounded-lg border border-emerald-500/20 bg-slate-950/30 p-3">
              <div className="text-xs text-emerald-200/80">Tot errori</div>
              <div className="mt-1 text-2xl font-semibold text-emerald-100">{summary.errors}</div>
            </div>
          </div>

          <div className="mt-5 flex flex-col gap-2 sm:flex-row">
            <a
              href={`/api/wizards/${id}/report.html`}
              className="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
            >
              Scarica Report HTML
            </a>

            <NavLink
              to="/wizards/new"
              className="inline-flex items-center justify-center rounded-lg border border-emerald-500/30 bg-slate-950/30 px-4 py-2 text-sm font-semibold text-emerald-100 hover:bg-slate-950/50"
            >
              Configura un altro PC
            </NavLink>
          </div>

          <div className="mt-3 text-xs text-emerald-200/70">
            Suggerimento: se vuoi evitare link diretti non autenticati al report, esponi il download solo tramite endpoint protetto (token utente).
          </div>
        </div>
      ) : null}
    </div>
  );
}
```


## Note operative e trade-off (importanti)

- Animazione “slide-in”: qui è volutamente semplice e senza librerie; se vuoi animazioni enter/exit più robuste (anche su rimozione/riordino), valuta `react-transition-group`, ma è una dipendenza extra.[^1_2]
- Polling: per ora è “sempre in foreground”; se ti serve polling anche a tab inattiva, potresti valutare una soluzione realtime (Laravel broadcasting/WebSocket) oppure gestire `visibilitychange` lato client. (Nel progetto è citabile l’approccio realtime via eventi, ma qui rimaniamo sul requisito polling.)[^1_3]
- Sicurezza report: `href="/api/wizards/:id/report.html"` è un placeholder; in produzione evita URL scaricabili senza controllo ACL, meglio endpoint autenticato (Sanctum) o link firmati a scadenza.[^1_4]

Vuoi che l’endpoint `GET /api/wizards/:id/monitor` lo normalizziamo a uno schema definitivo (così puoi semplificare parecchio il parsing in `useMonitor.js`)?
<span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_19][^1_20][^1_21][^1_22][^1_23][^1_24][^1_25][^1_26][^1_27][^1_28][^1_29][^1_30][^1_31][^1_32][^1_33][^1_34][^1_35][^1_36][^1_37][^1_38][^1_39][^1_40][^1_41][^1_42][^1_43][^1_44][^1_5][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: https://tailwindcss.com/docs/animation

[^1_2]: https://coursework.vschool.io/react-transitions-with-react-transition-group/

[^1_3]: 0012-apiendpointwindows.md

[^1_4]: 0001-projectdescription.txt

[^1_5]: 0016-wizards.md

[^1_6]: 0015-dashboard.md

[^1_7]: 0014-Login.md

[^1_8]: 0013-SetupprogettoReact.md

[^1_9]: 0011-controllercrud.md

[^1_10]: 0010-autenticazione.md

[^1_11]: 0009-scaffoldprogetto.md

[^1_12]: 0008-db.md

[^1_13]: 0007-cheatsheet.md

[^1_14]: 0006-viteconfigjs.md

[^1_15]: 0005-filedotenv.md

[^1_16]: 0004-Strutturacartelle.md

[^1_17]: 0003-setupiniziale.md

[^1_18]: 0002-ricerca-iniziale.md

[^1_19]: https://react.dev/reference/react/ViewTransition

[^1_20]: https://react.dev/blog/2025/04/23/react-labs-view-transitions-activity-and-more

[^1_21]: https://id.react.dev/reference/react/ViewTransition

[^1_22]: https://tailwindcss.com/docs/transition-duration

[^1_23]: https://v3.tailwindcss.com/docs/animation

[^1_24]: https://tailwindcss.com/docs/display

[^1_25]: https://tailwindcss.com/docs/transition-timing-function

[^1_26]: https://tailwindcss.com/docs/scale

[^1_27]: https://tailwindcss.com/docs/visibility

[^1_28]: https://tailwindcss.com/docs/scroll-snap-type

[^1_29]: https://tailwindcss.com/docs/transition-behavior

[^1_30]: https://tailwindcss.com/docs/overflow

[^1_31]: https://stackoverflow.com/questions/63406003/how-to-slide-in-and-out-of-items-of-a-list-with-react-transition-group

[^1_32]: https://blog.bitsrc.io/animating-reactjs-with-react-transition-group-2af6c87cab0c

[^1_33]: https://mui.com/material-ui/transitions/

[^1_34]: https://www.youtube.com/watch?v=BZRyIOrWfHU

[^1_35]: https://refine.dev/blog/tailwind-animations/

[^1_36]: https://github.com/TanStack/query/issues/8353

[^1_37]: https://devrecipes.net/animations-with-react-transition-group-in-practical-examples/

[^1_38]: https://www.geeksforgeeks.org/css/how-to-add-keyframes-to-a-tailwind-css-config-file/

[^1_39]: https://github.com/TanStack/query/discussions/713

[^1_40]: https://codesandbox.io/examples/package/react-transition-group

[^1_41]: https://v2.tailwindcss.com/docs/animation

[^1_42]: https://stackoverflow.com/questions/66263924/how-can-i-stop-a-react-query-usequery-running-on-a-refetchinterval-when-a-condit

[^1_43]: https://www.dhiwise.com/post/ways-to-enhance-your-react-apps-using-react-transition-group

[^1_44]: https://tailkits.com/blog/tailwind-animate-guide/

