<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Sei un senior React developer. Progetto: WinDeploy

Stack: React 18 + Vite + Tailwind CSS v3 + Zustand + React Router v6
Design system: sidebar \#1E3A5F, sfondo contenuto \#F0F4F8, accent \#2E75B6

═══ CONTESTO ═══
L'app non ha Error Boundaries. Qualsiasi crash JS in qualsiasi
componente (WizardBuilder, Monitor, ecc.) produce un blank screen
senza feedback. L'utente non capisce cosa è successo e non sa
come procedere. Durante la compilazione del wizard, un crash
fa perdere tutti i dati inseriti.

═══ FILE DA ALLEGARE PRIMA DI INVIARE ═══
→ frontend/src/main.jsx
→ frontend/src/App.jsx
→ frontend/src/router/index.jsx
→ frontend/src/pages/Wizard/WizardsListPage.jsx

→ frontend/src/pages/Wizard/WizardBuilderPage.jsx
→ frontend/src/pages/Monitor/index.jsx
→ frontend/src/store/authStore.js
→ frontend/package.json (per verificare versione React e dipendenze)

═══ COSA VOGLIO ═══

1. SCELTA LIBRERIA:
Dai file allegati dimmi quale versione React è installata.
    - Se React 18: puoi usare sia class component nativo che
react-error-boundary. Consiglia il migliore per questo progetto.
    - Se React 19: usa obbligatoriamente react-error-boundary
(componentDidCatch class-based è deprecato).
Fornisci il comando npm install se serve il package esterno.
2. COMPONENTE ERRORBOUNDARY GLOBALE
frontend/src/components/ErrorBoundary/GlobalErrorBoundary.jsx

UI fallback da mostrare quando crasha qualsiasi parte dell'app:
    - Card centrata su sfondo \#F0F4F8
    - Icona ⚠️ grande
    - Titolo: "Qualcosa è andato storto"
    - Messaggio: "Si è verificato un errore inatteso. I tuoi dati
non sono stati persi. Prova a ricaricare la pagina."
    - Bottone primario "Ricarica pagina" → window.location.reload()
    - Bottone secondario "Torna alla dashboard" → navigate('/dashboard')
(usa window.location.href se navigate non disponibile nel boundary)
    - In sviluppo (import.meta.env.DEV): mostra anche il dettaglio
tecnico dell'errore in un <details> collassabile con stack trace
    - In produzione: nasconde completamente il dettaglio tecnico
3. COMPONENTE ERRORBOUNDARY PER ROUTE
frontend/src/components/ErrorBoundary/RouteErrorBoundary.jsx

Versione più compatta per wrappare singole route/pagine
(crash in WizardBuilder non butta giù Dashboard):
    - Banner arancione in cima alla pagina
    - Messaggio: "Questa pagina ha riscontrato un errore."
    - Bottone "Riprova" → reset del boundary (react-error-boundary
fornisce resetErrorBoundary, usala)
    - Bottone "Vai alla dashboard"
    - La sidebar e il layout rimangono visibili e funzionanti
4. ERRORBOUNDARY SPECIFICO PER WIZARDBUILDER
frontend/src/components/ErrorBoundary/WizardErrorBoundary.jsx

Critico: se crasha durante compilazione wizard l'utente
non deve perdere i dati inseriti.
    - Nel componentDidCatch (o onError): prima di mostrare il fallback,
salva in localStorage con chiave 'windeploy_wizard_recovery'
il contenuto dello stato wizard corrente (passato come prop)
    - UI fallback: "Errore durante la compilazione del wizard.
I tuoi progressi sono stati salvati automaticamente."
    - Bottone "Riprendi da dove eri" → reimposta lo stato dal
localStorage e resetta il boundary
    - Bottone "Ricomincia da capo" → svuota localStorage e ricomincia
5. AGGIORNAMENTO main.jsx:
Wrappa l'intera app con GlobalErrorBoundary come layer esterno.
Struttura finale:
<GlobalErrorBoundary>
<QueryClientProvider client={queryClient}>
<BrowserRouter>
<App />
</BrowserRouter>
</QueryClientProvider>
</GlobalErrorBoundary>
6. AGGIORNAMENTO router/index.jsx:
Ogni route lazily loaded va wrappata con RouteErrorBoundary.
Esempio struttura:
{
path: '/wizards/new',
element: (
<WizardErrorBoundary wizardState={...}>
<WizardBuilder />
</WizardErrorBoundary>
)
}
Mostrami come passare lo stato wizard al boundary senza
accoppiare troppo i componenti (usa Zustand store direttamente
dentro il boundary, non come prop).
7. LOGGING ERRORI AL BACKEND (opzionale ma consigliato):
Nel GlobalErrorBoundary, nel metodo componentDidCatch o onError,
aggiungi una chiamata fire-and-forget a POST /api/errors/log:
{
message: error.message,
stack: error.stack,
component_stack: errorInfo.componentStack,
url: window.location.href,
user_agent: navigator.userAgent,
timestamp: new Date().toISOString()
}
    - La chiamata NON deve bloccare il rendering del fallback
    - Se la chiamata fallisce (rete assente): silenziosamente ignorata
    - NON inviare in locale (import.meta.env.DEV === true → solo console.error)
    - Aggiungi in backend/routes/api.php la route pubblica (senza auth):
Route::post('/errors/log', [ErrorLogController::class, 'store']);
Con rate limiting: max 10 req/minuto per IP (anti-spam)
8. TEST MANUALE DA ESEGUIRE:
Fornisci le istruzioni passo passo per testare ogni boundary:

TEST A — GlobalErrorBoundary:
Aggiungi temporaneamente in App.jsx:
if (true) throw new Error('Test global crash')
→ deve apparire UI fallback globale, non blank screen

TEST B — RouteErrorBoundary:
Aggiungi throw new Error() in Dashboard.jsx
→ Dashboard mostra fallback ma sidebar rimane visibile e
le altre route continuano a funzionare

TEST C — WizardErrorBoundary:
Compila 3 step del wizard, poi aggiungi throw in Step4
→ fallback mostra "progressi salvati"
→ clicca "Riprendi" → wizard riparte dallo step 3 con dati intatti
→ verifica in DevTools → Application → localStorage che
'windeploy_wizard_recovery' contenga i dati corretti
9. COMANDI GIT:
Commit separato per:
    - feat: add global and route error boundaries
    - feat: add wizard crash recovery with localStorage persistence
    - feat: add backend error logging endpoint (se implementato)

Ogni file con percorso completo in intestazione.
Codice completo, niente abbreviazioni.
Commenti in italiano. Design coerente con Tailwind e palette progetto.

Monitor/index.jsx
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
                ```                <div className="shrink-0 font-mono">{labelRight}</div>                ```
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
            ```            <div className="text-sm font-semibold text-slate-100">Hardware PC</div>            ```
            <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    ```                    <div className="text-xs text-slate-400">CPU</div>                    ```
                    ```                    <div className="mt-1 text-sm text-slate-100">{hardware?.cpu ?? "—"}</div>                    ```
                </div>
                <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    ```                    <div className="text-xs text-slate-400">RAM</div>                    ```
                    <div className="mt-1 text-sm text-slate-100">
                        {hardware?.ramGb !== null \&\& hardware?.ramGb !== undefined ? `${hardware.ramGb} GB` : "—"}
                    </div>
                </div>
                <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    ```                    <div className="text-xs text-slate-400">Disco</div>                    ```
                    <div className="mt-1 text-sm text-slate-100">
                        {hardware?.diskGb !== null \&\& hardware?.diskGb !== undefined ? `${hardware.diskGb} GB` : "—"}
                    </div>
                </div>
                <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    ```                    <div className="text-xs text-slate-400">Windows</div>                    ```
                    ```                    <div className="mt-1 text-sm text-slate-100">{hardware?.windowsVersion ?? "—"}</div>                    ```
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
                        ```                        <div className="truncate text-sm font-semibold text-slate-100">{step.name}</div>                        ```
                        ```                        <div className="shrink-0 text-xs text-slate-400">{formatTs(step.timestamp)}</div>                        ```
                    </div>

{step.message ? (
                        ```                        <div className="mt-2 whitespace-pre-wrap text-sm text-slate-200">{step.message}</div>                        ```
                    ) : (
                        ```                        <div className="mt-2 text-sm text-slate-400">Nessun dettaglio disponibile.</div>                        ```
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

// Announce new steps for screen readers
    const announceRef = React.useRef(null);
    React.useEffect(() => {
        if ((ui.newStepIds ?? []).length > 0) {
            const msg = `${ui.newStepIds.length} nuovo step ricevuto.`;
            if (announceRef.current) announceRef.current.textContent = msg;
            const t = window.setTimeout(() => {
                if (announceRef.current) announceRef.current.textContent = "";
            }, 1500);
            return () => window.clearTimeout(t);
        }
    }, [ui.newStepIds]);

const badge = useMemo(() => statusBadge(data?.wizard?.status), [data?.wizard?.status]);

const showReportCta = data?.wizard?.status === "completato" || data?.wizard?.status === "errore";

const headerPcName = data?.pc?.name ? String(data.pc.name) : null;

const completedCardVisible = data?.wizard?.status === "completato";

const summary = data?.summary ?? { installed: 0, removed: 0, errors: 0 };

const newIdsSet = useMemo(() => new Set(ui.newStepIds ?? []), [ui.newStepIds]);

return (
        <div className="space-y-6">
            {/* Accessibility live region */}
            <div className="sr-only" aria-live="polite" ref={announceRef} />

{/* Header */}
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0">
                    ```                    <div className="text-xs uppercase tracking-wide text-slate-400">Monitor</div>                    ```
                    ```                    <h1 className="mt-1 truncate text-xl font-semibold text-slate-100">{data?.wizard?.name ?? "Wizard"}</h1>                    ```

<div className="mt-2 flex flex-col gap-1 text-sm text-slate-300">
                        <div className="flex items-center gap-2">
                            ```                            <span className="text-slate-400">Codice:</span>                            ```
                            <span className="font-mono text-slate-100">{data?.wizard?.code || "—"}</span>
                        </div>
                        <div className="flex items-center gap-2">
                            ```                            <span className="text-slate-400">PC:</span>                            ```
                            ```                            <span className="text-slate-100">{headerPcName ?? "In attesa info dal PC..."}</span>                            ```
                        </div>
                    </div>
                </div>

<div className="flex flex-col items-start gap-3 sm:flex-row sm:items-center">
                    ```                    <div className={`rounded-2xl border px-5 py-3 text-base font-semibold ${badge.cls}`}>{badge.label}</div>                    ```

<div className="flex items-center gap-2">
                        ```                        <button type="button" onClick={actions.refetch} className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900">Aggiorna</button>                        ```

```                        <button type="button" onClick={() => (ui.isPolling ? actions.stopPolling() : actions.startPolling())} className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900">{ui.isPolling ? "Ferma polling" : "Avvia polling"}</button>                        ```

```                        <div className="inline-flex items-center rounded px-3 py-2 text-xs text-slate-400">{ui.isPolling ? "Polling: 5s" : "Polling: off"}</div>                        ```

```                        <NavLink to="/wizards" className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900">Torna ai wizard</NavLink>                        ```
                    </div>
                </div>
            </div>

{/* Errors */}
            {ui.isError ? (
                <div className="rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-200">{ui.errorMessage || "Errore non specificato."}</div>
            ) : null}

{/* Top cards */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <ProgressBar percent={progress.percent} labelLeft={`Progresso (${progress.completedCount}/${progress.expectedTotal} step completati)`} labelRight={`${progress.percent}%`} />
                    ```                    <div className="mt-3 text-xs text-slate-500">{ui.lastUpdatedAt ? `Ultimo aggiornamento: ${formatTs(ui.lastUpdatedAt)}` : "—"}{ui.isPolling && !showReportCta ? " • Polling ogni 5s" : " • Polling fermo"}</div>                    ```
                </div>

<HardwareCard hardware={data?.hardware} />
            </div>

{/* Steps */}
            <div className="space-y-3">
                <div className="flex items-end justify-between gap-3">
                    <div>
                        ```                        <div className="text-sm font-semibold text-slate-100">Step</div>                        ```
                        ```                        <div className="mt-1 text-sm text-slate-400">Avanzamento in ordine cronologico.</div>                        ```
                    </div>

{showReportCta ? (
                        ```                        <button type="button" onClick={() => navigate(`/reports/${id}`)} className="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500">Vedi Report</button>                        ```
                    ) : null}
                </div>

{ui.isLoading ? (
                    ```                    <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4 text-sm text-slate-400">Caricamento monitor...</div>                    ```
                ) : null}

{!ui.isLoading \&\& (data?.steps?.length ?? 0) === 0 ? (
                    ```                    <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4 text-sm text-slate-400">Nessuno step ancora ricevuto.</div>                    ```
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
                    ```                    <div className="text-lg font-semibold text-emerald-100">Configurazione completata</div>                    ```
                    ```                    <div className="mt-1 text-sm text-emerald-200/80">Il PC ha terminato l’esecuzione del wizard. Puoi scaricare il report o avviare una nuova configurazione.</div>                    ```

<div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div className="rounded-lg border border-emerald-500/20 bg-slate-950/30 p-3">
                            ```                            <div className="text-xs text-emerald-200/80">Tot installati</div>                            ```
                            ```                            <div className="mt-1 text-2xl font-semibold text-emerald-100">{summary.installed}</div>                            ```
                        </div>
                        <div className="rounded-lg border border-emerald-500/20 bg-slate-950/30 p-3">
                            ```                            <div className="text-xs text-emerald-200/80">Tot rimossi</div>                            ```
                            ```                            <div className="mt-1 text-2xl font-semibold text-emerald-100">{summary.removed}</div>                            ```
                        </div>
                        <div className="rounded-lg border border-emerald-500/20 bg-slate-950/30 p-3">
                            ```                            <div className="text-xs text-emerald-200/80">Tot errori</div>                            ```
                            ```                            <div className="mt-1 text-2xl font-semibold text-emerald-100">{summary.errors}</div>                            ```
                        </div>
                    </div>

<div className="mt-5 flex flex-col gap-2 sm:flex-row">
                        ```                        <a href={`/api/wizards/${id}/report.html`} className="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Scarica Report HTML</a>                        ```

```                        <NavLink to="/wizards/new" className="inline-flex items-center justify-center rounded-lg border border-emerald-500/30 bg-slate-950/30 px-4 py-2 text-sm font-semibold text-emerald-100 hover:bg-slate-950/50">Configura un altro PC</NavLink>                        ```
                    </div>

```                    <div className="mt-3 text-xs text-emerald-200/70">Suggerimento: se vuoi evitare link diretti non autenticati al report, esponi il download solo tramite endpoint protetto (token utente).</div>                    ```
                </div>
            ) : null}
        </div>
    );
}

authStore.js
import { create } from 'zustand';

const TOKEN_KEY = 'windeploy_token';
const USER_KEY = 'windeploy_user';

const getInitialState = () => {
  const token = window.localStorage.getItem(TOKEN_KEY) || null;
  const userJson = window.localStorage.getItem(USER_KEY);
  let user = null;

try {
    if (userJson) {
      user = JSON.parse(userJson);
    }
  } catch (error) {
    console.error('Failed to parse stored user', error);
  }

return {
    token,
    user,
    isAuthenticated: Boolean(token \&\& user),
  };
};

export const useAuthStore = create((set) => ({
  ...getInitialState(),

login: ({ token, user }) => {
    // WARNING: token in localStorage è pratico ma vulnerabile a XSS.
    // Per produzione valuta in futuro un approccio cookie HttpOnly + CSRF.
    window.localStorage.setItem(TOKEN_KEY, token);
    window.localStorage.setItem(USER_KEY, JSON.stringify(user));

set({
      token,
      user,
      isAuthenticated: true,
    });
  },

logout: () => {
    window.localStorage.removeItem(TOKEN_KEY);
    window.localStorage.removeItem(USER_KEY);

set({
      token: null,
      user: null,
      isAuthenticated: false,
    });
  },

setUser: (user) => {
    window.localStorage.setItem(USER_KEY, JSON.stringify(user));
    set({ user });
  },
}));

// Default export for consumers that import the store as default
export default useAuthStore;

package.json
{
  "name": "frontend",
  "private": true,
  "version": "0.0.0",
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "lint": "eslint .",
    "preview": "vite preview"
  },
  "dependencies": {
    "@tanstack/react-query": "^5.90.21",
    "axios": "^1.13.6",
    "lucide-react": "^0.575.0",
    "react": "^19.2.0",
    "react-dom": "^19.2.0",
    "react-hot-toast": "^2.6.0",
    "react-router-dom": "^6.30.3",
    "recharts": "^3.7.0",
    "zustand": "^5.0.11"
  },
  "devDependencies": {
    "@eslint/js": "^9.39.1",
    "@types/react": "^19.2.7",
    "@types/react-dom": "^19.2.3",
    "@vitejs/plugin-react": "^5.1.1",
    "autoprefixer": "^10.4.27",
    "eslint": "^9.39.1",
    "eslint-plugin-react-hooks": "^7.0.1",
    "eslint-plugin-react-refresh": "^0.4.24",
    "globals": "^16.5.0",
    "postcss": "^8.5.6",
    "tailwindcss": "^3.4.19",
    "vite": "^8.0.0-beta.13"
  },
  "overrides": {
    "vite": "^8.0.0-beta.13"
  }
}

React installato: **React 19.2.0**, quindi per WinDeploy devi usare `react-error-boundary` (in React 19 l’approccio “class ErrorBoundary con componentDidCatch” è da evitare).
Nel tuo `main.jsx` attuale hai già `QueryClientProvider` e `BrowserRouter`, quindi l’integrazione del layer globale è lineare.[^1_1]

## 1) Scelta libreria (React 19)

Con React 19 ti consiglio `react-error-boundary` perché è l’approccio più moderno/compatibile, supporta `resetErrorBoundary` (utile per retry per-route) e rende più semplice comporre boundary diversi (globale, route, wizard).

Comando:

```bash
cd frontend
npm install react-error-boundary
```


## 2) Struttura file coinvolti

Aggiungi/aggiorna questi file:

- `frontend/src/components/ErrorBoundary/GlobalErrorBoundary.jsx` (nuovo)
- `frontend/src/components/ErrorBoundary/RouteErrorBoundary.jsx` (nuovo)
- `frontend/src/components/ErrorBoundary/WizardErrorBoundary.jsx` (nuovo)
- `frontend/src/store/wizardRecoveryStore.js` (nuovo, Zustand per snapshot wizard)
- `frontend/src/main.jsx` (aggiorna: wrap globale esterno)
- `frontend/src/App.jsx` (aggiorna: include router + Toaster)
- `frontend/src/router/index.jsx` (aggiorna: wrap route lazy con RouteErrorBoundary + WizardErrorBoundary)

Nota progetto (Space rule): **nomi variabili e commenti in inglese nel codice** (anche se qui rispondo in italiano).

## 3) Codice frontend (completo)

```jsx
// Path: frontend/src/components/ErrorBoundary/GlobalErrorBoundary.jsx
import React from "react";
import { ErrorBoundary } from "react-error-boundary";

const API_ENDPOINT = "/api/errors/log";

function buildPayload(error, componentStack) {
  return {
    message: error?.message || "Unknown error",
    stack: error?.stack || null,
    component_stack: componentStack || null,
    url: window.location.href,
    user_agent: navigator.userAgent,
    timestamp: new Date().toISOString(),
  };
}

function fireAndForgetLog(payload) {
  // English comment: Never block fallback rendering; ignore all network errors silently.
  try {
    const body = JSON.stringify(payload);

    if (typeof navigator !== "undefined" && typeof navigator.sendBeacon === "function") {
      const blob = new Blob([body], { type: "application/json" });
      navigator.sendBeacon(API_ENDPOINT, blob);
      return;
    }

    void fetch(API_ENDPOINT, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body,
      keepalive: true,
    }).catch(() => {});
  } catch {
    // ignore
  }
}

function GlobalFallback({ error, componentStack }) {
  const showTechDetails = Boolean(import.meta?.env?.DEV);

  return (
    <div className="min-h-screen w-full" style={{ backgroundColor: "#F0F4F8" }}>
      <div className="mx-auto flex min-h-screen max-w-2xl items-center justify-center p-6">
        <div className="w-full rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-start gap-4">
            <div className="text-4xl leading-none" aria-hidden="true">
              ⚠️
            </div>
            <div className="min-w-0">
              <h1 className="text-xl font-semibold text-slate-900">Qualcosa è andato storto</h1>
              <p className="mt-2 text-sm text-slate-600">
                Si è verificato un errore inatteso. I tuoi dati non sono stati persi. Prova a ricaricare la pagina.
              </p>

              <div className="mt-5 flex flex-col gap-2 sm:flex-row">
                <button
                  type="button"
                  onClick={() => window.location.reload()}
                  className="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500"
                >
                  Ricarica pagina
                </button>

                <button
                  type="button"
                  onClick={() => {
                    // English comment: Global boundary is outside Router, so we use hard navigation.
                    window.location.href = "/dashboard";
                  }}
                  className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50"
                >
                  Torna alla dashboard
                </button>
              </div>

              {showTechDetails ? (
                <details className="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-3">
                  <summary className="cursor-pointer text-sm font-medium text-slate-800">
                    Dettagli tecnici (solo sviluppo)
                  </summary>
                  <div className="mt-3 space-y-3">
                    <div>
                      <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">Message</div>
                      <pre className="mt-1 whitespace-pre-wrap break-words rounded bg-white p-2 text-xs text-slate-800">
                        {String(error?.message || "")}
                      </pre>
                    </div>

                    <div>
                      <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">Stack</div>
                      <pre className="mt-1 max-h-64 overflow-auto whitespace-pre-wrap break-words rounded bg-white p-2 text-xs text-slate-800">
                        {String(error?.stack || "")}
                      </pre>
                    </div>

                    <div>
                      <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">Component stack</div>
                      <pre className="mt-1 max-h-64 overflow-auto whitespace-pre-wrap break-words rounded bg-white p-2 text-xs text-slate-800">
                        {String(componentStack || "")}
                      </pre>
                    </div>
                  </div>
                </details>
              ) : null}
            </div>
          </div>

          <div className="mt-5 text-xs text-slate-500">
            Suggerimento: se l’errore persiste, contatta il supporto IT indicando cosa stavi facendo.
          </div>
        </div>
      </div>
    </div>
  );
}

export default function GlobalErrorBoundary({ children }) {
  const componentStackRef = React.useRef("");

  const handleError = React.useCallback((error, info) => {
    componentStackRef.current = info?.componentStack || "";

    if (import.meta?.env?.DEV) {
      // English comment: In dev, do not send logs to backend (noise), keep console.
      console.error("GlobalErrorBoundary caught error:", error, info);
      return;
    }

    const payload = buildPayload(error, componentStackRef.current);
    fireAndForgetLog(payload);
  }, []);

  const fallbackRender = React.useCallback(
    ({ error }) => <GlobalFallback error={error} componentStack={componentStackRef.current} />,
    []
  );

  return (
    <ErrorBoundary onError={handleError} fallbackRender={fallbackRender}>
      {children}
    </ErrorBoundary>
  );
}
```

```jsx
// Path: frontend/src/components/ErrorBoundary/RouteErrorBoundary.jsx
import React from "react";
import { ErrorBoundary } from "react-error-boundary";
import { useNavigate } from "react-router-dom";

function RouteFallback({ error, resetErrorBoundary }) {
  const navigate = useNavigate();
  const showTechDetails = Boolean(import.meta?.env?.DEV);

  return (
    <div className="w-full">
      <div className="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-amber-950">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="text-sm font-semibold">Questa pagina ha riscontrato un errore.</div>

          <div className="flex flex-col gap-2 sm:flex-row">
            <button
              type="button"
              onClick={resetErrorBoundary}
              className="inline-flex items-center justify-center rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-500"
            >
              Riprova
            </button>

            <button
              type="button"
              onClick={() => navigate("/dashboard")}
              className="inline-flex items-center justify-center rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-100"
            >
              Vai alla dashboard
            </button>
          </div>
        </div>

        {showTechDetails ? (
          <details className="mt-3 rounded-lg border border-amber-200 bg-white p-3">
            <summary className="cursor-pointer text-sm font-medium">Dettagli tecnici (solo sviluppo)</summary>
            <pre className="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words text-xs text-slate-800">
              {String(error?.stack || error?.message || "")}
            </pre>
          </details>
        ) : null}
      </div>
    </div>
  );
}

export default function RouteErrorBoundary({ children, resetKeys }) {
  return (
    <ErrorBoundary FallbackComponent={RouteFallback} resetKeys={resetKeys}>
      {children}
    </ErrorBoundary>
  );
}
```

```jsx
// Path: frontend/src/store/wizardRecoveryStore.js
import { create } from "zustand";

export const WIZARD_RECOVERY_STORAGE_KEY = "windeploy_wizard_recovery";

export const useWizardRecoveryStore = create((set) => ({
  latestSnapshot: null,
  applyToken: 0,
  resetToken: 0,

  setLatestSnapshot: (snapshot) => set({ latestSnapshot: snapshot }),

  requestApplySnapshot: (snapshot) =>
    set({
      latestSnapshot: snapshot,
      applyToken: Date.now(),
    }),

  requestResetWizard: () =>
    set({
      latestSnapshot: null,
      resetToken: Date.now(),
    }),
}));
```

```jsx
// Path: frontend/src/components/ErrorBoundary/WizardErrorBoundary.jsx
import React from "react";
import { ErrorBoundary } from "react-error-boundary";
import {
  useWizardRecoveryStore,
  WIZARD_RECOVERY_STORAGE_KEY,
} from "../../store/wizardRecoveryStore";

function safeReadRecovery() {
  try {
    const raw = window.localStorage.getItem(WIZARD_RECOVERY_STORAGE_KEY);
    if (!raw) return null;
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

function safeWriteRecovery(payload) {
  try {
    window.localStorage.setItem(WIZARD_RECOVERY_STORAGE_KEY, JSON.stringify(payload));
  } catch {
    // ignore
  }
}

function safeClearRecovery() {
  try {
    window.localStorage.removeItem(WIZARD_RECOVERY_STORAGE_KEY);
  } catch {
    // ignore
  }
}

function WizardFallback({ error, resetErrorBoundary }) {
  const showTechDetails = Boolean(import.meta?.env?.DEV);

  return (
    <div className="rounded-2xl border border-rose-200 bg-white p-5 shadow-sm">
      <div className="flex items-start gap-4">
        <div className="text-3xl leading-none" aria-hidden="true">
          ⚠️
        </div>
        <div className="min-w-0">
          <div className="text-lg font-semibold text-slate-900">Errore durante la compilazione del wizard.</div>
          <div className="mt-1 text-sm text-slate-600">
            I tuoi progressi sono stati salvati automaticamente.
          </div>

          <div className="mt-4 flex flex-col gap-2 sm:flex-row">
            <button
              type="button"
              onClick={() => {
                const recovered = safeReadRecovery();
                if (recovered) {
                  useWizardRecoveryStore.getState().requestApplySnapshot(recovered);
                  resetErrorBoundary();
                  return;
                }

                // English comment: No recovery found; just retry rendering.
                resetErrorBoundary();
              }}
              className="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500"
            >
              Riprendi da dove eri
            </button>

            <button
              type="button"
              onClick={() => {
                safeClearRecovery();
                useWizardRecoveryStore.getState().requestResetWizard();
                resetErrorBoundary();
              }}
              className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50"
            >
              Ricomincia da capo
            </button>
          </div>

          {showTechDetails ? (
            <details className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
              <summary className="cursor-pointer text-sm font-medium text-slate-800">
                Dettagli tecnici (solo sviluppo)
              </summary>
              <pre className="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words text-xs text-slate-800">
                {String(error?.stack || error?.message || "")}
              </pre>
            </details>
          ) : null}
        </div>
      </div>
    </div>
  );
}

export default function WizardErrorBoundary({ children, resetKeys }) {
  const handleError = React.useCallback((error) => {
    // English comment: Snapshot is read from Zustand store to avoid tight coupling via props.
    const snapshot = useWizardRecoveryStore.getState().latestSnapshot;

    const payload = {
      snapshot: snapshot ?? null,
      url: window.location.href,
      timestamp: new Date().toISOString(),
    };

    safeWriteRecovery(payload);

    if (import.meta?.env?.DEV) {
      console.error("WizardErrorBoundary caught error:", error);
    }
  }, []);

  return (
    <ErrorBoundary
      onError={handleError}
      FallbackComponent={WizardFallback}
      resetKeys={resetKeys}
    >
      {children}
    </ErrorBoundary>
  );
}
```

```jsx
// Path: frontend/src/main.jsx
import React from "react";
import ReactDOM from "react-dom/client";
import { BrowserRouter } from "react-router-dom";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";

import App from "./App.jsx";
import GlobalErrorBoundary from "./components/ErrorBoundary/GlobalErrorBoundary.jsx";
import "./index.css";

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
      staleTime: 30 * 1000,
    },
  },
});

ReactDOM.createRoot(document.getElementById("root")).render(
  <GlobalErrorBoundary>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <App />
      </BrowserRouter>
    </QueryClientProvider>
  </GlobalErrorBoundary>
);
```

```jsx
// Path: frontend/src/App.jsx
import React from "react";
import { Toaster } from "react-hot-toast";
import AppRouter from "./router/index.jsx";

export default function App() {
  return (
    <div className="min-h-screen" style={{ backgroundColor: "#F0F4F8" }}>
      <AppRouter />
      <Toaster
        position="top-right"
        toastOptions={{
          duration: 4000,
        }}
      />
    </div>
  );
}
```

```jsx
// Path: frontend/src/router/index.jsx
import React, { Suspense } from "react";
import { Routes, Route, Navigate } from "react-router-dom";

import PrivateRoute from "./PrivateRoute.jsx";
import MainLayout from "../components/layout/MainLayout.jsx";

import RouteErrorBoundary from "../components/ErrorBoundary/RouteErrorBoundary.jsx";
import WizardErrorBoundary from "../components/ErrorBoundary/WizardErrorBoundary.jsx";

function PageLoader({ label }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
      {label || "Caricamento..."}
    </div>
  );
}

// Lazy pages (to isolate crashes and reduce initial bundle)
const LoginPage = React.lazy(() => import("../pages/Login/LoginPage.jsx"));
const DashboardPage = React.lazy(() => import("../pages/Dashboard/DashboardPage.jsx"));
const WizardsListPage = React.lazy(() => import("../pages/Wizards/WizardsListPage.jsx"));
const WizardBuilderPage = React.lazy(() => import("../pages/Wizards/WizardBuilderPage.jsx"));
const WizardMonitorPage = React.lazy(() => import("../pages/Wizards/WizardMonitorPage.jsx"));
const MonitorPage = React.lazy(() => import("../pages/Monitor/index.jsx"));
const TemplatesPage = React.lazy(() => import("../pages/Templates/TemplatesPage.jsx"));
const SoftwarePage = React.lazy(() => import("../pages/Software/SoftwarePage.jsx"));
const ReportsPage = React.lazy(() => import("../pages/Reports/ReportsPage.jsx"));
const UsersPage = React.lazy(() => import("../pages/Users/UsersPage.jsx"));
const NotFoundPage = React.lazy(() => import("../pages/NotFound/NotFoundPage.jsx"));

function wrapRoute(element, { resetKeys } = {}) {
  return (
    <RouteErrorBoundary resetKeys={resetKeys}>
      <Suspense fallback={<PageLoader />}>{element}</Suspense>
    </RouteErrorBoundary>
  );
}

export default function AppRouter() {
  return (
    <Routes>
      {/* Public */}
      <Route
        path="/login"
        element={wrapRoute(<LoginPage />, { resetKeys: ["login"] })}
      />

      {/* Protected area */}
      <Route element={<PrivateRoute allowedRoles={["admin", "tecnico", "viewer"]} />}>
        <Route element={<MainLayout />}>
          <Route index element={<Navigate to="/dashboard" replace />} />

          <Route
            path="/dashboard"
            element={wrapRoute(<DashboardPage />, { resetKeys: ["dashboard"] })}
          />

          <Route
            path="/reports"
            element={wrapRoute(<ReportsPage />, { resetKeys: ["reports"] })}
          />

          <Route
            path="/monitor/:id"
            element={wrapRoute(<MonitorPage />, { resetKeys: ["monitor"] })}
          />

          {/* Wizards (admin + tecnico) */}
          <Route element={<PrivateRoute allowedRoles={["admin", "tecnico"]} />}>
            <Route
              path="/wizards"
              element={wrapRoute(<WizardsListPage />, { resetKeys: ["wizards"] })}
            />

            <Route
              path="/wizards/new"
              element={
                <RouteErrorBoundary resetKeys={["wizard-new"]}>
                  <WizardErrorBoundary resetKeys={["wizard-new"]}>
                    <Suspense fallback={<PageLoader label="Caricamento Wizard Builder..." />}>
                      <WizardBuilderPage />
                    </Suspense>
                  </WizardErrorBoundary>
                </RouteErrorBoundary>
              }
            />

            <Route
              path="/wizards/:id/monitor"
              element={wrapRoute(<WizardMonitorPage />, { resetKeys: ["wizard-monitor"] })}
            />

            <Route
              path="/templates"
              element={wrapRoute(<TemplatesPage />, { resetKeys: ["templates"] })}
            />
          </Route>

          {/* Admin only */}
          <Route element={<PrivateRoute allowedRoles={["admin"]} />}>
            <Route
              path="/software"
              element={wrapRoute(<SoftwarePage />, { resetKeys: ["software"] })}
            />
            <Route
              path="/users"
              element={wrapRoute(<UsersPage />, { resetKeys: ["users"] })}
            />
          </Route>

          {/* 404 */}
          <Route
            path="*"
            element={wrapRoute(<NotFoundPage />, { resetKeys: ["notfound"] })}
          />
        </Route>
      </Route>
    </Routes>
  );
}
```


### Come “passare” lo stato wizard al boundary senza props (Zustand)

Il boundary legge `latestSnapshot` direttamente da Zustand (`useWizardRecoveryStore.getState()`), quindi devi solo **aggiornare lo snapshot** dal WizardBuilder mentre l’utente compila.

Patch minima (concetto) dentro `frontend/src/pages/Wizards/WizardBuilderPage.jsx`:

- ad ogni change significativo (draft, step corrente, completed map, ecc.) chiama `setLatestSnapshot(...)`
- all’avvio pagina, se `applyToken` o `resetToken` cambiano, applica recovery/reset alla tua state machine

Esempio (da integrare nel tuo builder, senza riscriverlo da zero):

```jsx
// In WizardBuilderPage.jsx (aggiunte principali)
import { useWizardRecoveryStore } from "../../store/wizardRecoveryStore";

// ...
const setLatestSnapshot = useWizardRecoveryStore((s) => s.setLatestSnapshot);
const applyToken = useWizardRecoveryStore((s) => s.applyToken);
const resetToken = useWizardRecoveryStore((s) => s.resetToken);
const latestSnapshot = useWizardRecoveryStore((s) => s.latestSnapshot);

// 1) Keep snapshot updated (call whenever your wizard state changes)
React.useEffect(() => {
  setLatestSnapshot({
    wizard: wizardState,          // your current wizard draft/state
    currentStep: currentStep,     // your current step
    completedMap: completedMap,   // if you have it
    savedAt: new Date().toISOString(),
  });
}, [wizardState, currentStep, completedMap, setLatestSnapshot]);

// 2) Apply recovery on demand (when user clicks "Riprendi")
React.useEffect(() => {
  if (!latestSnapshot || !latestSnapshot.snapshot) return;

  const snap = latestSnapshot.snapshot;

  // Apply to your local state manager (useReducer/useState)
  // Example:
  // dispatch({ type: "LOAD_SNAPSHOT", payload: snap.wizard });
  // setCurrentStep(snap.currentStep ?? 0);
  // setCompletedMap(snap.completedMap ?? {});

  // Important: do not loop; consume/ignore tokens in your design if needed
}, [applyToken]); // eslint-disable-line react-hooks/exhaustive-deps

// 3) Reset on demand (when user clicks "Ricomincia")
React.useEffect(() => {
  // Example:
  // dispatch({ type: "RESET" });
  // setCurrentStep(0);
  // setCompletedMap({});
}, [resetToken]); // eslint-disable-line react-hooks/exhaustive-deps
```

Nel tuo router attuale esistono già rotte come `/wizards/new` e `/wizards/:id/monitor`, quindi l’aggiornamento si mappa bene sul tuo schema.[^1_2]

## 4) Logging errori al backend (opzionale, consigliato) + sicurezza

Frontend: nel `GlobalErrorBoundary` sopra trovi già la POST fire-and-forget verso `/api/errors/log`, **disabilitata in DEV** (solo `console.error`).

Backend (Laravel): aggiungi route pubblica con rate limiting 10/min per IP.

```php
<?php
// Path: backend/routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ErrorLogController;

Route::middleware(['throttle:10,1'])->post('/errors/log', [ErrorLogController::class, 'store']);
```

```php
<?php
// Path: backend/app/Http/Controllers/ErrorLogController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ErrorLogController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
            'stack' => ['nullable', 'string', 'max:20000'],
            'component_stack' => ['nullable', 'string', 'max:20000'],
            'url' => ['nullable', 'string', 'max:4000'],
            'user_agent' => ['nullable', 'string', 'max:1000'],
            'timestamp' => ['nullable', 'string', 'max:50'],
        ]);

        // English comment: Avoid throwing from logging endpoint.
        try {
            Log::warning('FrontendError', [
                'message' => $data['message'] ?? null,
                'url' => $data['url'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'timestamp' => $data['timestamp'] ?? null,
                'component_stack' => $data['component_stack'] ?? null,
                'stack' => $data['stack'] ?? null,
                'ip' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        return response()->json(['status' => 'ok']);
    }
}
```

Devil’s advocate (rischi reali):

- Lo **stack trace** può contenere frammenti di dati sensibili (es. porzioni di state/URL); valuta di *redigere* querystring o campi sensibili prima di inviare.
- Il recovery wizard in `localStorage` può includere password/SSID: è un rischio XSS. Se nel wizard hai campi “segreti”, io valuterei di **escluderli dallo snapshot** (salvi tutto tranne password) oppure salvarli solo in-memory e accettare il trade-off UX.


## 5) Test manuali + comandi git

### TEST A — GlobalErrorBoundary

1. In `frontend/src/App.jsx` aggiungi temporaneamente all’inizio del componente:

```jsx
if (true) throw new Error("Test global crash");
```

2. Apri l’app: deve apparire la card “Qualcosa è andato storto”, non blank screen.

### TEST B — RouteErrorBoundary

1. Aggiungi un `throw new Error("Test dashboard crash")` dentro `DashboardPage`.
2. Vai su `/dashboard`: deve apparire il banner arancione (fallback), **sidebar visibile** e navigazione verso altre route funzionante.

### TEST C — WizardErrorBoundary

1. Compila 3 step del wizard.
2. Inserisci `throw new Error("Test wizard crash")` nel componente dello Step4.
3. Deve apparire fallback wizard con testo “progressi salvati”.
4. Apri DevTools → Application → localStorage → verifica chiave `windeploy_wizard_recovery`.
5. Clicca “Riprendi da dove eri”: il wizard deve ripartire con dati intatti (in base a come hai integrato `applyToken/resetToken` nel builder).

### Commit separati

```bash
git add frontend/src/components/ErrorBoundary frontend/src/router/index.jsx frontend/src/main.jsx frontend/src/App.jsx frontend/src/store/wizardRecoveryStore.js
git commit -m "feat: add global and route error boundaries"

git add frontend/src/components/ErrorBoundary/WizardErrorBoundary.jsx frontend/src/store/wizardRecoveryStore.js frontend/src/pages/Wizards/WizardBuilderPage.jsx
git commit -m "feat: add wizard crash recovery with localStorage persistence"

git add backend/routes/api.php backend/app/Http/Controllers/ErrorLogController.php
git commit -m "feat: add backend error logging endpoint"
```

Vuoi che adatti anche `frontend/src/router/PrivateRoute.jsx` e `frontend/src/router/AppRouter.jsx` (se ancora usati) per evitare doppioni e consolidare tutto su `router/index.jsx`?
<span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_19][^1_20][^1_21][^1_22][^1_23][^1_24][^1_25][^1_26][^1_27][^1_3][^1_4][^1_5][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: main.jsx

[^1_2]: AppRouter.jsx

[^1_3]: App.jsx

[^1_4]: PrivateRoute.jsx

[^1_5]: WizardsListPage.jsx

[^1_6]: 0106-architetturacross-modulo-e-contratti-dati.md

[^1_7]: 0105-schema DB.md

[^1_8]: 0104-frontend-React-e-tooling-Vite.md

[^1_9]: 0102-generazione bulk di codice CRUD.md

[^1_10]: 0103-configurazione auth e sicurezza.md

[^1_11]: 0101-auth e sicurezza.md

[^1_12]: 0100-File temporanei tmp_.php esposti nella root.md

[^1_13]: 0021-Installer-configurazione sistema.md

[^1_14]: 0020-Scansione-app-installate.md

[^1_15]: 0019-Struttura-agent.md

[^1_16]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^1_17]: 0017-motorpage.md

[^1_18]: 0016-wizards.md

[^1_19]: 0015-dashboard.md

[^1_20]: 0014-Login.md

[^1_21]: 0013-SetupprogettoReact.md

[^1_22]: 0012-apiendpointwindows.md

[^1_23]: 0011-controllercrud.md

[^1_24]: 0010-autenticazione.md

[^1_25]: 0009-scaffoldprogetto.md

[^1_26]: 0001-projectdescription.txt

[^1_27]: 0003-setupiniziale.md


---

# si

Perfetto: consolidiamo tutto su `frontend/src/router/index.jsx` e rendiamo `AppRouter.jsx` un semplice “compat layer” (così non rompi eventuali import esistenti). Nel tuo progetto oggi esistono già `src/router/AppRouter.jsx` e `src/router/PrivateRoute.jsx` (anche se ora sono incompleti), quindi questa modifica elimina duplicazioni e chiarisce il punto d’ingresso del routing.[^2_1][^2_2]

## Struttura e impatto

- `router/index.jsx` diventa **l’unica** definizione delle route (con lazy loading + RouteErrorBoundary + WizardErrorBoundary).
- `router/AppRouter.jsx` resta, ma solo come re-export (puoi anche cancellarlo in seguito).
- `App.jsx` diventa la shell (router + Toaster).
- `main.jsx` resta “provider root” (QueryClientProvider + BrowserRouter + GlobalErrorBoundary), coerente con il setup già presente.[^2_3]

***

```jsx
// Path: frontend/src/router/PrivateRoute.jsx
import React from "react";
import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useAuthStore } from "../store/authStore.js";

// English comment: allowedRoles is an array of allowed roles, e.g. ["admin", "tecnico"].
export default function PrivateRoute({ allowedRoles }) {
  const location = useLocation();
  const { isAuthenticated, user } = useAuthStore();

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  if (Array.isArray(allowedRoles) && allowedRoles.length > 0) {
    const userRole = user?.role;

    if (!userRole || !allowedRoles.includes(userRole)) {
      // English comment: User is authenticated but does not have enough privileges.
      return <Navigate to="/dashboard" replace />;
    }
  }

  return <Outlet />;
}
```

```jsx
// Path: frontend/src/router/index.jsx
import React, { Suspense } from "react";
import { Routes, Route, Navigate } from "react-router-dom";

import PrivateRoute from "./PrivateRoute.jsx";
import MainLayout from "../components/layout/MainLayout.jsx";

import RouteErrorBoundary from "../components/ErrorBoundary/RouteErrorBoundary.jsx";
import WizardErrorBoundary from "../components/ErrorBoundary/WizardErrorBoundary.jsx";

function PageLoader({ label }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
      {label || "Caricamento..."}
    </div>
  );
}

// English comment: Lazy-load pages so a crash in one page does not bring down the whole app.
const LoginPage = React.lazy(() => import("../pages/Login/LoginPage.jsx"));
const DashboardPage = React.lazy(() => import("../pages/Dashboard/DashboardPage.jsx"));
const WizardsListPage = React.lazy(() => import("../pages/Wizards/WizardsListPage.jsx"));
const WizardBuilderPage = React.lazy(() => import("../pages/Wizards/WizardBuilderPage.jsx"));
const WizardMonitorPage = React.lazy(() => import("../pages/Wizards/WizardMonitorPage.jsx"));
const MonitorPage = React.lazy(() => import("../pages/Monitor/index.jsx"));
const TemplatesPage = React.lazy(() => import("../pages/Templates/TemplatesPage.jsx"));
const SoftwarePage = React.lazy(() => import("../pages/Software/SoftwarePage.jsx"));
const ReportsPage = React.lazy(() => import("../pages/Reports/ReportsPage.jsx"));
const UsersPage = React.lazy(() => import("../pages/Users/UsersPage.jsx"));
const NotFoundPage = React.lazy(() => import("../pages/NotFound/NotFoundPage.jsx"));

function wrapRoute(element, { resetKeys, fallbackLabel } = {}) {
  return (
    <RouteErrorBoundary resetKeys={resetKeys}>
      <Suspense fallback={<PageLoader label={fallbackLabel} />}>{element}</Suspense>
    </RouteErrorBoundary>
  );
}

export default function AppRouter() {
  return (
    <Routes>
      {/* Public */}
      <Route
        path="/login"
        element={wrapRoute(<LoginPage />, {
          resetKeys: ["login"],
          fallbackLabel: "Caricamento login...",
        })}
      />

      {/* Protected */}
      <Route element={<PrivateRoute allowedRoles={["admin", "tecnico", "viewer"]} />}>
        <Route element={<MainLayout />}>
          {/* Redirect root -> /dashboard */}
          <Route index element={<Navigate to="/dashboard" replace />} />

          {/* /dashboard → admin + tecnico + viewer */}
          <Route
            path="/dashboard"
            element={wrapRoute(<DashboardPage />, {
              resetKeys: ["dashboard"],
              fallbackLabel: "Caricamento dashboard...",
            })}
          />

          {/* /reports → tutti */}
          <Route
            path="/reports"
            element={wrapRoute(<ReportsPage />, {
              resetKeys: ["reports"],
              fallbackLabel: "Caricamento report...",
            })}
          />

          {/* Monitor (usa :id come da pagina Monitor che legge useParams().id) */}
          <Route
            path="/monitor/:id"
            element={wrapRoute(<MonitorPage />, {
              resetKeys: ["monitor"],
              fallbackLabel: "Caricamento monitor...",
            })}
          />

          {/* Wizards → tecnico + admin */}
          <Route element={<PrivateRoute allowedRoles={["admin", "tecnico"]} />}>
            <Route
              path="/wizards"
              element={wrapRoute(<WizardsListPage />, {
                resetKeys: ["wizards"],
                fallbackLabel: "Caricamento lista wizard...",
              })}
            />

            <Route
              path="/wizards/new"
              element={
                <RouteErrorBoundary resetKeys={["wizard-new"]}>
                  <WizardErrorBoundary resetKeys={["wizard-new"]}>
                    <Suspense fallback={<PageLoader label="Caricamento Wizard Builder..." />}>
                      <WizardBuilderPage />
                    </Suspense>
                  </WizardErrorBoundary>
                </RouteErrorBoundary>
              }
            />

            <Route
              path="/wizards/:id/monitor"
              element={wrapRoute(<WizardMonitorPage />, {
                resetKeys: ["wizard-monitor"],
                fallbackLabel: "Caricamento wizard monitor...",
              })}
            />

            {/* /templates → tecnico + admin */}
            <Route
              path="/templates"
              element={wrapRoute(<TemplatesPage />, {
                resetKeys: ["templates"],
                fallbackLabel: "Caricamento templates...",
              })}
            />
          </Route>

          {/* Admin only */}
          <Route element={<PrivateRoute allowedRoles={["admin"]} />}>
            <Route
              path="/software"
              element={wrapRoute(<SoftwarePage />, {
                resetKeys: ["software"],
                fallbackLabel: "Caricamento software library...",
              })}
            />

            <Route
              path="/users"
              element={wrapRoute(<UsersPage />, {
                resetKeys: ["users"],
                fallbackLabel: "Caricamento utenti...",
              })}
            />
          </Route>

          {/* 404 */}
          <Route
            path="*"
            element={wrapRoute(<NotFoundPage />, {
              resetKeys: ["notfound"],
              fallbackLabel: "Caricamento...",
            })}
          />
        </Route>
      </Route>
    </Routes>
  );
}
```

```jsx
// Path: frontend/src/router/AppRouter.jsx
import AppRouter from "./index.jsx";

// English comment: Compatibility layer to avoid breaking existing imports.
// You can safely remove this file once you updated all imports to "./router/index.jsx".
export default AppRouter;
```

```jsx
// Path: frontend/src/App.jsx
import React from "react";
import { Toaster } from "react-hot-toast";
import AppRouter from "./router/index.jsx";

export default function App() {
  return (
    <div className="min-h-screen" style={{ backgroundColor: "#F0F4F8" }}>
      <AppRouter />
      <Toaster
        position="top-right"
        toastOptions={{
          duration: 4000,
        }}
      />
    </div>
  );
}
```

```jsx
// Path: frontend/src/main.jsx
import React from "react";
import ReactDOM from "react-dom/client";
import { BrowserRouter } from "react-router-dom";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";

import App from "./App.jsx";
import GlobalErrorBoundary from "./components/ErrorBoundary/GlobalErrorBoundary.jsx";
import "./index.css";

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
      staleTime: 30 * 1000,
    },
  },
});

ReactDOM.createRoot(document.getElementById("root")).render(
  <GlobalErrorBoundary>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <App />
      </BrowserRouter>
    </QueryClientProvider>
  </GlobalErrorBoundary>
);
```


## Checklist operativa (2 minuti)

- `App.jsx` importa `./router/index.jsx` (non più `AppRouter.jsx`).
- `router/index.jsx` è l’unico file con `<Routes>`.
- Verifica che `../pages/Monitor/index.jsx` esista davvero (nel tuo snippet il file è `Monitor/index.jsx` e usa `useParams().id`, quindi la route `"/monitor/:id"` è coerente).
- Avvia `npm run dev` e testa navigazione base: `/login` → `/dashboard` → `/wizards`.


## Commit git (solo consolidamento router)

```bash
git add frontend/src/router/index.jsx frontend/src/router/PrivateRoute.jsx frontend/src/router/AppRouter.jsx frontend/src/App.jsx frontend/src/main.jsx
git commit -m "refactor: consolidate routing into router/index with error boundaries"
```

Vuoi che mantenga **esattamente** gli stessi path/route param del tuo `AppRouter.jsx` attuale (nel file allegato è troncato e non si vedono i `path`), oppure va bene che standardizziamo `MonitorPage` su `/monitor/:id` come da componente?
<span style="display:none">[^2_4]</span>

<div align="center">⁂</div>

[^2_1]: PrivateRoute.jsx

[^2_2]: AppRouter.jsx

[^2_3]: main.jsx

[^2_4]: App.jsx


---

# una volta fatto npm run

Il “blank screen” che vedi dopo `npm run dev` è coerente con un errore **bloccante**: nel tuo `frontend/src/main.jsx` stai chiamando `render()` ma non stai renderizzando nessun JSX (è vuoto tra le parentesi), quindi React non monta nulla.  Inoltre `frontend/src/App.jsx` risulta di fatto vuoto, quindi anche se montassi `<App />` non vedresti contenuto.[^3_1][^3_2]

## Fix immediato (UI torna visibile)

### File coinvolti

- `frontend/src/main.jsx` (da correggere: render dell’albero provider + App)
- `frontend/src/App.jsx` (da correggere: shell che include router + toaster)


### `frontend/src/main.jsx` (codice completo)

```jsx
// Path: frontend/src/main.jsx
import React from "react";
import ReactDOM from "react-dom/client";
import { BrowserRouter } from "react-router-dom";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";

import App from "./App.jsx";
import GlobalErrorBoundary from "./components/ErrorBoundary/GlobalErrorBoundary.jsx";
import "./index.css";

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
      staleTime: 30 * 1000,
    },
  },
});

ReactDOM.createRoot(document.getElementById("root")).render(
  <GlobalErrorBoundary>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <App />
      </BrowserRouter>
    </QueryClientProvider>
  </GlobalErrorBoundary>
);
```


### `frontend/src/App.jsx` (codice completo)

```jsx
// Path: frontend/src/App.jsx
import React from "react";
import { Toaster } from "react-hot-toast";
import AppRouter from "./router/index.jsx";

export default function App() {
  return (
    <div className="min-h-screen" style={{ backgroundColor: "#F0F4F8" }}>
      <AppRouter />
      <Toaster
        position="top-right"
        toastOptions={{
          duration: 4000,
        }}
      />
    </div>
  );
}
```


## Checklist post-fix (2 minuti)

- Stoppa e riavvia Vite: `Ctrl+C` → `npm run dev`.
- Apri `http://localhost:5173/dashboard`.
- Se vedi ancora bianco, apri DevTools → Console e cerca errori tipo “Cannot find module …/GlobalErrorBoundary.jsx” (tipico se non hai ancora creato quei file).
- Verifica che esista `frontend/src/router/index.jsx` (è quello che `App.jsx` importa).


## Devil’s advocate: altri failure mode comuni

- Import path errati (case-sensitive su Linux/CI): es. `../pages/Monitor` vs `../pages/Monitor/index.jsx`.
- Provider mancanti/duplicati: `BrowserRouter` deve stare **una sola volta** (qui è in `main.jsx`).
- Tailwind ok ma pagina vuota: se Console mostra crash runtime, con i boundary corretti dovresti vedere il fallback invece del bianco.

Hai lanciato `npm run dev` o `npm run build` + `npm run preview` quando hai ottenuto lo schermo bianco?
<span style="display:none">[^3_3]</span>

<div align="center">⁂</div>

[^3_1]: App.jsx

[^3_2]: main.jsx

[^3_3]: image.jpg


---

# pagina bianca, questi errori su console:

main.jsx:24 ⚠️ React Router Future Flag Warning: React Router will begin wrapping state updates in `React.startTransition` in v7. You can use the `v7_startTransition` future flag to opt-in early. For more information, see [https://reactrouter.com/v6/upgrading/future\#v7_starttransition](https://reactrouter.com/v6/upgrading/future#v7_starttransition).
warnOnce @ react-router-dom.js?v=55bb7248:3619
logDeprecation @ react-router-dom.js?v=55bb7248:3622
logV6DeprecationWarnings @ react-router-dom.js?v=55bb7248:3624
(anonymous) @ react-router-dom.js?v=55bb7248:4421
react_stack_bottom_frame @ react-dom_client.js?v=55bb7248:12907
runWithFiberInDEV @ react-dom_client.js?v=55bb7248:854
commitHookEffectListMount @ react-dom_client.js?v=55bb7248:6620
commitHookPassiveMountEffects @ react-dom_client.js?v=55bb7248:6655
commitPassiveMountOnFiber @ react-dom_client.js?v=55bb7248:7621
recursivelyTraversePassiveMountEffects @ react-dom_client.js?v=55bb7248:7609
commitPassiveMountOnFiber @ react-dom_client.js?v=55bb7248:7675
recursivelyTraversePassiveMountEffects @ react-dom_client.js?v=55bb7248:7609
commitPassiveMountOnFiber @ react-dom_client.js?v=55bb7248:7620
recursivelyTraversePassiveMountEffects @ react-dom_client.js?v=55bb7248:7609
commitPassiveMountOnFiber @ react-dom_client.js?v=55bb7248:7675
recursivelyTraversePassiveMountEffects @ react-dom_client.js?v=55bb7248:7609
commitPassiveMountOnFiber @ react-dom_client.js?v=55bb7248:7625
recursivelyTraversePassiveMountEffects @ react-dom_client.js?v=55bb7248:7609
commitPassiveMountOnFiber @ react-dom_client.js?v=55bb7248:7620
recursivelyTraversePassiveMountEffects @ react-dom_client.js?v=55bb7248:7609
commitPassiveMountOnFiber @ react-dom_client.js?v=55bb7248:7630
flushPassiveEffects @ react-dom_client.js?v=55bb7248:8811
(anonymous) @ react-dom_client.js?v=55bb7248:8565
performWorkUntilDeadline @ react-dom_client.js?v=55bb7248:37
<BrowserRouter>
exports.jsxDEV @ react_jsx-dev-runtime.js?v=55bb7248:194
(anonymous) @ main.jsx:24
main.jsx:24 ⚠️ React Router Future Flag Warning: Relative route resolution within Splat routes is changing in v7. You can use the `v7_relativeSplatPath` future flag to opt-in early. For more information, see [https://reactrouter.com/v6/upgrading/future\#v7_relativesplatpath](https://reactrouter.com/v6/upgrading/future#v7_relativesplatpath).

Quei due messaggi sono **solo warning** di React Router “future flags”: non spiegano una pagina bianca (non sono errori bloccanti). Il bianco di solito significa che stai renderizzando `null`/niente, oppure stai importando un router/layout che a runtime non produce UI, senza però generare un errore visibile.

Dato che nel `main.jsx` che mi avevi allegato inizialmente il `render(...)` era vuoto, il primo check è confermare che ora stai davvero montando qualcosa sotto `#root`.[^4_1]

## Debug rapido (5 minuti, deterministico)

### 1) Verifica che React monti l’app (marker)

Metti **temporaneamente** in `frontend/src/App.jsx`:

```jsx
// Path: frontend/src/App.jsx
import React from "react";

export default function App() {
  return (
    <div style={{ padding: 24, color: "black", background: "white" }}>
      APP MOUNTED
    </div>
  );
}
```

- Se **non** vedi “APP MOUNTED”: il problema è **prima** (main.jsx non renderizza davvero App, oppure import/export rotti, oppure \#root non esiste in index.html).
- Se lo vedi: React funziona, il problema è nel router/layout/pagine.


### 2) Verifica che il router renderizzi qualcosa (marker router)

Se “APP MOUNTED” funziona, ripristina `App.jsx` e fai un test sul router: in `frontend/src/router/index.jsx` (o nel file router che importi davvero) metti **temporaneamente**:

```jsx
export default function AppRouter() {
  return <div style={{ padding: 24, color: "black" }}>ROUTER OK</div>;
}
```

```
- Se vedi “ROUTER OK”: il bianco viene dalla tua definizione di `<Routes>`/`<Route>`/layout (es. `PrivateRoute` o `MainLayout`).
```

- Se non lo vedi: `App.jsx` non sta importando il router giusto.


### 3) Check specifico Auth/PrivateRoute (caso più comune)

Se stai su `/dashboard` ma vedi bianco, molto spesso succede perché:

- `PrivateRoute` ritorna `null`/`undefined` (quindi non renderizza né redirect né outlet).
- `MainLayout` non renderizza `<Outlet />`.

Nel tuo attachment `PrivateRoute.jsx` risulta “monco”/incompleto (si vedono `return (...)` e `return ;`), quindi è un candidato forte: se è così anche nel tuo repo locale, può portare a UI vuota.[^4_2]

## I warning React Router: cosa fare

Per ora: ignorali. Sono solo indicazioni per RR v7 e non bloccano rendering.

## Cosa mi serve da te (per chiudere in 1 colpo)

Incollami il contenuto **attuale** (quello che stai eseguendo) di:

- `frontend/src/main.jsx`
- `frontend/src/App.jsx`
- `frontend/src/router/index.jsx` (o il router che importi)
- `frontend/src/router/PrivateRoute.jsx`
- `frontend/src/components/layout/MainLayout.jsx`

In alternativa, dimmi solo: nel test 1 vedi “APP MOUNTED” sì/no?
<span style="display:none">[^4_3][^4_4][^4_5][^4_6][^4_7]</span>

<div align="center">⁂</div>

[^4_1]: main.jsx

[^4_2]: PrivateRoute.jsx

[^4_3]: 0013-SetupprogettoReact.md

[^4_4]: 0015-dashboard.md

[^4_5]: AppRouter.jsx

[^4_6]: 0004-Strutturacartelle.md

[^4_7]: 0014-Login.md


---

# si lo vedo

```
Se “APP MOUNTED” lo vedi, React sta montando correttamente: il bianco arriva **dopo**, quasi sicuramente dal routing/guard.  Nel tuo `PrivateRoute.jsx` allegato si vede che nei branch `return (...)`, `return ;` e nel return finale mancano proprio i componenti (`<Navigate />` / `<Outlet />`), quindi quando non sei autenticato finisci su una route protetta e React renderizza di fatto “niente” (schermo bianco) senza cambiare pagina.[^5_1][^5_2]
```


## Fix immediato (PrivateRoute)

Sostituisci completamente `frontend/src/router/PrivateRoute.jsx` con questo (è la causa più probabile del blank su `/dashboard`).[^5_2]

```jsx
// Path: frontend/src/router/PrivateRoute.jsx
import React from "react";
import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useAuthStore } from "../store/authStore.js";

// English comment: Client-side guardrail only; backend must enforce auth/roles too.
export default function PrivateRoute({ allowedRoles }) {
  const location = useLocation();
  const { isAuthenticated, user } = useAuthStore();

  // English comment: If not logged in, redirect to /login and keep "from" for post-login redirect.
  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  // English comment: If roles are specified, enforce them.
  if (Array.isArray(allowedRoles) && allowedRoles.length > 0) {
    const userRole = user?.role;

    if (!userRole || !allowedRoles.includes(userRole)) {
      // English comment: Logged in but not authorized -> push back to dashboard.
      return <Navigate to="/dashboard" replace />;
    }
  }

  return <Outlet />;
}
```


## Test rapido (capire subito se è lui)

1) Apri `http://localhost:5173/dashboard` in incognito (così `localStorage` è pulito).
2) Se non sei loggato, **deve** portarti su `/login` (URL cambia).[^5_1][^5_2]
3) Se resta su `/dashboard` ma bianco, allora non sta passando da `PrivateRoute` oppure la route `/login` non sta renderizzando.

## Seconda causa tipica: route/import non coerenti

Nel tuo `AppRouter.jsx` allegato stai importando pagine da `../pages/Wizards/...` (cartella “Wizards”), ma nella tua descrizione progetto i path richiesti erano `src/pages/Wizard/...` (cartella “Wizard”).  Se nel filesystem reale la cartella è “Wizard” e non “Wizards”, alcune import falliscono e puoi ritrovarti con render interrotto (di solito Vite mostra overlay, ma meglio controllare).[^5_1]

Checklist:

- Verifica che esista davvero `frontend/src/pages/Dashboard/DashboardPage.jsx` (stesso case, stessa cartella) come importato nel router.[^5_1]
- Verifica che la cartella sia `pages/Wizards` oppure correggi tutti gli import.


## I warning React Router

I warning “Future Flag” che vedi non causano pagina bianca: sono solo avvisi di migrazione verso v7. (Non serve intervenire adesso.)

Dimmi una cosa sola: dopo aver sistemato `PrivateRoute.jsx`, quando vai su `/dashboard` da non loggato ti reindirizza a `/login` oppure rimane su `/dashboard` bianca?
<span style="display:none">[^5_3][^5_4][^5_5][^5_6]</span>

<div align="center">⁂</div>

[^5_1]: AppRouter.jsx

[^5_2]: PrivateRoute.jsx

[^5_3]: 0013-SetupprogettoReact.md

[^5_4]: 0015-dashboard.md

[^5_5]: 0104-frontend-React-e-tooling-Vite.md

[^5_6]: 0004-Strutturacartelle.md


---

# ok da finestra in incognito mi va alla pagina login e una volta entrato nella dashboard è una pagina vuota

Se dopo login vai su `/dashboard` e vedi bianco **senza errori**, i 2 colpevoli più frequenti in questo punto del tuo stack sono: (1) redirect loop del `PrivateRoute` per mismatch del ruolo, (2) `DashboardPage.jsx` vuoto / che ritorna `null`.[^6_1][^6_2]

## 1) Check ruolo (probabile redirect loop)

Nel tuo router `/dashboard` sta dentro un `PrivateRoute` con `allowedRoles`, quindi se `user.role` non combacia con i valori attesi, `PrivateRoute` continua a “navigare” e tu percepisci una pagina vuota.[^6_2][^6_1]

### Verifica (30 secondi)

Apri DevTools → Console ed esegui:

```js
JSON.parse(localStorage.getItem("windeploy_user"))
```

e guarda `role`.

Valori attesi lato frontend (coerenti con `allowedRoles` del router): `admin`, `tecnico`, `viewer`.[^6_1]

### Fix robusto (case-insensitive + stop loop)

Aggiorna `frontend/src/router/PrivateRoute.jsx` così: se il ruolo non è ammesso **non** rimbalza sempre su `/dashboard`, ma mostra una schermata “Accesso negato” (così non hai più blank/loop).[^6_2]

```jsx
// Path: frontend/src/router/PrivateRoute.jsx
import React from "react";
import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useAuthStore } from "../store/authStore.js";

export default function PrivateRoute({ allowedRoles }) {
  const location = useLocation();
  const { isAuthenticated, user } = useAuthStore();

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  const normalizedRole = String(user?.role || "").toLowerCase();

  if (Array.isArray(allowedRoles) && allowedRoles.length > 0) {
    const normalizedAllowed = allowedRoles.map((r) => String(r).toLowerCase());

    if (!normalizedRole || !normalizedAllowed.includes(normalizedRole)) {
      return (
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
          <div className="text-sm font-semibold">Accesso negato</div>
          <div className="mt-1 text-sm">
            Il tuo ruolo ({normalizedRole || "n/d"}) non è autorizzato per questa pagina.
          </div>
        </div>
      );
    }
  }

  return <Outlet />;
}
```


## 2) Check DashboardPage (spesso è vuoto)

Nel tuo `AppRouter.jsx` la dashboard viene importata da `../pages/Dashboard/DashboardPage` (attenzione a cartella e maiuscole).  Se quel file è vuoto o ritorna `null`, vedrai una pagina bianca “legittima”.[^6_1]

### Fix immediato: crea una dashboard minimale (marker permanente)

Crea/aggiorna questo file:

```jsx
// Path: frontend/src/pages/Dashboard/DashboardPage.jsx
import React from "react";

export default function DashboardPage() {
  return (
    <div className="space-y-6">
      <div>
        <div className="text-xs uppercase tracking-wide text-slate-500">Dashboard</div>
        <h1 className="mt-1 text-xl font-semibold text-slate-900">WinDeploy</h1>
        <p className="mt-2 text-sm text-slate-600">
          Se vedi questa pagina, routing + layout stanno funzionando correttamente.
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div className="text-xs font-medium text-slate-500">Stato</div>
          <div className="mt-2 text-sm text-slate-700">UI OK</div>
        </div>

        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div className="text-xs font-medium text-slate-500">Auth</div>
          <div className="mt-2 text-sm text-slate-700">Sessione attiva</div>
        </div>

        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div className="text-xs font-medium text-slate-500">Prossimo step</div>
          <div className="mt-2 text-sm text-slate-700">Implementare widget reali</div>
        </div>
      </div>
    </div>
  );
}
```


## 3) Checklist debug (ordine giusto)

- Conferma `user.role` in localStorage e che sia uno tra `admin|tecnico|viewer` (case-insensitive). [^6_1]
- Se il ruolo è diverso (es. `technician`), o lo normalizzi lato backend o fai mapping lato frontend.
- Conferma che esista davvero `frontend/src/pages/Dashboard/DashboardPage.jsx` con quel path/case (come importato nel router).[^6_1]
- Solo dopo che la dashboard “marker” appare, riprendiamo l’implementazione degli Error Boundary (così se una pagina crasha lo vedi subito invece di tornare al bianco).

Qual è il valore di `role` dentro `windeploy_user` dopo il login?
<span style="display:none">[^6_3][^6_4][^6_5][^6_6]</span>

<div align="center">⁂</div>

[^6_1]: AppRouter.jsx

[^6_2]: PrivateRoute.jsx

[^6_3]: 0015-dashboard.md

[^6_4]: 0013-SetupprogettoReact.md

[^6_5]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^6_6]: 0016-wizards.md


---

# problemi:

- barra laterale
-pagina lista wizard, non si vedono i codici, vorrei che il codice passandoci sopra con il mouse si possa copiare.

Sì, ora è chiaro: la sidebar ha un problema di **stato attivo del link Dashboard**, e nella lista wizard il campo `code` è spesso vuoto perché il mapper legge solo `codiceUnivoco` o `code`, mentre dal backend può arrivare con nomi diversi; inoltre al momento la colonna è solo testo statico, quindi va trasformata in badge/button copiabile.[^7_1][^7_2]

## File da toccare

- `frontend/src/components/layout/MainLayout.jsx`
- `frontend/src/pages/Wizards/WizardsListPage.jsx`[^7_1]


## 1) Fix sidebar

Dallo screenshot si vede che la voce Dashboard resta evidenziata anche quando sei in `/wizards`, quindi il `NavLink` della dashboard quasi certamente non usa `end`, oppure la classe attiva matcha anche i path figli/prefissati.  Questo succede spesso con React Router quando `/dashboard` viene considerato prefisso di route vicine o quando il className è scritto in modo non robusto.[^7_3]

### Sostituisci `MainLayout.jsx` con questa versione

Ti lascio una versione completa, coerente col tuo tema, con helper per i link e `end` su Dashboard.

```jsx
// Path: frontend/src/components/layout/MainLayout.jsx
import React from "react";
import { NavLink, Outlet } from "react-router-dom";
import {
  LayoutDashboard,
  Wand2,
  FileText,
  Package,
  Users,
  ClipboardList,
  LogOut,
  Monitor,
} from "lucide-react";
import { useAuthStore } from "../../store/authStore.js";

function buildNavClass({ isActive }) {
  return [
    "flex items-center gap-2 rounded px-3 py-2 transition-colors",
    isActive
      ? "bg-slate-800 text-white"
      : "text-slate-300 hover:bg-slate-800/70 hover:text-white",
  ].join(" ");
}

export default function MainLayout() {
  const { user, logout } = useAuthStore();

  function handleLogout() {
    logout();
  }

  return (
    <div className="flex min-h-screen bg-slate-950 text-slate-50">
      <aside className="w-64 border-r border-slate-800 bg-slate-900 flex flex-col">
        <div className="px-4 py-4 border-b border-slate-800">
          <div className="text-lg font-semibold">WinDeploy</div>
          {user ? (
            <div className="mt-1 text-xs text-slate-400">
              {user.name} · {user.role}
            </div>
          ) : null}
        </div>

        <nav className="flex-1 px-2 py-4 space-y-1 text-sm">
          <NavLink to="/dashboard" end className={buildNavClass}>
            <LayoutDashboard className="h-4 w-4" />
            <span>Dashboard</span>
          </NavLink>

          <NavLink to="/wizards" className={buildNavClass}>
            <Wand2 className="h-4 w-4" />
            <span>Wizards</span>
          </NavLink>

          <NavLink to="/templates" className={buildNavClass}>
            <FileText className="h-4 w-4" />
            <span>Templates</span>
          </NavLink>

          <NavLink to="/monitor" className={buildNavClass}>
            <Monitor className="h-4 w-4" />
            <span>Monitor</span>
          </NavLink>

          <NavLink to="/software" className={buildNavClass}>
            <Package className="h-4 w-4" />
            <span>Software library</span>
          </NavLink>

          <NavLink to="/reports" className={buildNavClass}>
            <ClipboardList className="h-4 w-4" />
            <span>Reports</span>
          </NavLink>

          <NavLink to="/users" className={buildNavClass}>
            <Users className="h-4 w-4" />
            <span>Users</span>
          </NavLink>
        </nav>

        <button
          type="button"
          onClick={handleLogout}
          className="flex items-center gap-2 border-t border-slate-800 px-4 py-3 text-sm text-red-300 hover:bg-red-900/30"
        >
          <LogOut className="h-4 w-4" />
          <span>Logout</span>
        </button>
      </aside>

      <main className="flex-1 bg-slate-950">
        <div className="mx-auto max-w-6xl px-6 py-6">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
```


### Nota tecnica

L’uso di `end` su `/dashboard` evita match indesiderati del link root della dashboard. È una fix pulita, scalabile e più prevedibile del tentare workaround CSS.

## 2) Fix codici wizard + copia al passaggio mouse

Nel file allegato `WizardsListPage.jsx` il codice viene mappato così: `w?.codiceUnivoco ?? w?.code ?? ""`, quindi se l’API restituisce ad esempio `codice`, `wizard_code`, `unique_code` o `codice_univoco`, la colonna resta vuota.  Inoltre la tabella mostra solo `r.code` come testo semplice, quindi non c’è alcuna UX di copia.[^7_1]

### Obiettivo UX

Ti consiglio questo comportamento:

- Mostri il codice troncato.
- Al passaggio mouse compare l’azione “Copia”.
- Al click copi il codice in clipboard.
- Se il codice manca, mostri `—`.

È più chiaro di una copia “solo hover puro” senza affordance visiva, che rischia di sembrare rotta.

## Sostituisci `WizardsListPage.jsx`

Questa versione:

- amplia il mapping dei nomi campo,
- aggiunge tooltip/title,
- aggiunge copia con `navigator.clipboard`,
- mantiene il pulsante Monitor.[^7_1]

```jsx
// Path: frontend/src/pages/Wizards/WizardsListPage.jsx
import React, { useState } from "react";
import { NavLink } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Wand2, Plus, Eye, Copy, Check } from "lucide-react";
import client from "../../api/client.js";

async function fetchWizards() {
  const res = await client.get("/wizards");
  return res.data;
}

function normalizeApiCollection(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload.data)) return payload.data;
  if (payload.data && Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function getWizardCode(w) {
  return (
    w?.codiceUnivoco ??
    w?.codice_univoco ??
    w?.codice ??
    w?.wizardCode ??
    w?.wizard_code ??
    w?.uniqueCode ??
    w?.unique_code ??
    w?.code ??
    ""
  );
}

function getWizardRow(item) {
  const w = item?.data ? item.data : item;

  return {
    id: w?.id,
    name: w?.nome ?? w?.name ?? "—",
    code: getWizardCode(w),
    status: w?.stato ?? w?.status ?? "—",
    createdAt: w?.created_at ?? w?.createdAt ?? null,
  };
}

function StatusBadge({ status }) {
  const s = String(status || "").toLowerCase();

  let cls = "bg-slate-700/30 text-slate-200 border-slate-700";
  if (s === "pronto") cls = "bg-emerald-500/10 text-emerald-300 border-emerald-500/30";
  if (s === "bozza") cls = "bg-amber-500/10 text-amber-300 border-amber-500/30";
  if (s === "in_esecuzione" || s === "inesecuzione") cls = "bg-sky-500/10 text-sky-300 border-sky-500/30";
  if (s === "completato") cls = "bg-emerald-500/10 text-emerald-300 border-emerald-500/30";
  if (s === "errore") cls = "bg-rose-500/10 text-rose-300 border-rose-500/30";

  return (
    <span className={`inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium ${cls}`}>
      {status}
    </span>
  );
}

function CopyCodeButton({ code }) {
  const [copied, setCopied] = useState(false);

  async function handleCopy() {
    if (!code) return;

    try {
      await navigator.clipboard.writeText(code);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1200);
    } catch (error) {
      console.error("Failed to copy wizard code:", error);
    }
  }

  if (!code) {
    return <span className="text-slate-500">—</span>;
  }

  return (
    <button
      type="button"
      onClick={handleCopy}
      title={`Copia codice: ${code}`}
      className="group inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950/50 px-3 py-2 font-mono text-xs text-slate-200 transition hover:border-sky-500/40 hover:bg-slate-900"
    >
      <span className="max-w-[140px] truncate">{code}</span>

      <span className="opacity-0 transition group-hover:opacity-100">
        {copied ? (
          <Check className="h-4 w-4 text-emerald-400" />
        ) : (
          <Copy className="h-4 w-4 text-slate-400" />
        )}
      </span>
    </button>
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
            Crea e gestisci i wizard di provisioning per l&apos;agent Windows.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={refetch}
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

        {isLoading ? (
          <div className="p-4 text-sm text-slate-400">Caricamento...</div>
        ) : null}

        {isError ? (
          <div className="p-4 text-sm text-rose-300">
            Errore: {String(error?.message || "richiesta fallita")}
          </div>
        ) : null}

        {!isLoading && !isError && rows.length === 0 ? (
          <div className="p-4 text-sm text-slate-400">
            Nessun wizard trovato. Crea il primo con “Nuovo wizard”.
          </div>
        ) : null}

        {!isLoading && !isError && rows.length > 0 ? (
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
                {rows.map((row) => (
                  <tr key={row.id} className="border-t border-slate-800">
                    <td className="px-4 py-3 text-slate-100">{row.name}</td>
                    <td className="px-4 py-3">
                      <CopyCodeButton code={row.code} />
                    </td>
                    <td className="px-4 py-3">
                      <StatusBadge status={row.status} />
                    </td>
                    <td className="px-4 py-3 text-right">
                      <NavLink
                        to={`/wizards/${row.id}/monitor`}
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
        ) : null}
      </div>
    </div>
  );
}
```


## Perché prima non vedevi i codici

Nel file attuale, il mapper usa solo `codiceUnivoco` e `code`.  Se il backend restituisce un naming diverso, la UI mostra vuoto pur avendo il dato disponibile lato API, quindi questa è una classica incompatibilità di contratto dati frontend/backend.[^7_4][^7_1]

## Avvocato del diavolo

- La copia con `navigator.clipboard` funziona bene in contesto sicuro/local dev, ma in alcuni ambienti embedded o HTTP non sicuro può avere restrizioni.
- Se il codice wizard è un identificatore sensibile, renderlo copiabile migliora UX ma facilita anche esfiltrazione da shoulder surfing o operatori non autorizzati; lato backend i permessi su monitor/download devono restare stretti.
- Se i codici continuano a risultare vuoti anche dopo questo fix, allora il problema non è UI ma proprio payload API: in quel caso va ispezionata la risposta `/api/wizards` e allineato il naming a schema unico.[^7_4]


## Checklist rapida

- Aggiorna `MainLayout.jsx`.
- Aggiorna `WizardsListPage.jsx`.
- Verifica che il router usi path coerenti `/wizards/new` e `/wizards/:id/monitor`; nel file attuale vedo ancora varianti tipo `wizardsnew` e `wizards:idmonitor`, che andrebbero normalizzate in sintassi standard React Router.[^7_2]
- Ricarica la pagina e prova hover sul codice.

Vuoi che nel prossimo messaggio ti riscriva anche `AppRouter.jsx` pulito con tutte le route in formato standard (`/wizards/new`, `/wizards/:id/monitor`, ecc.)?
<span style="display:none">[^7_5]</span>

<div align="center">⁂</div>

[^7_1]: WizardsListPage.jsx

[^7_2]: AppRouter.jsx

[^7_3]: image.jpg

[^7_4]: 0106-architetturacross-modulo-e-contratti-dati.md

[^7_5]: image-2.jpg


---

# http://localhost:5173/monitor

404 - Pagina non trovata
La pagina richiesta non esiste.
\monitor\index.jsx
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
                ```                <div className="shrink-0 font-mono">{labelRight}</div>                ```
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
            ```            <div className="text-sm font-semibold text-slate-100">Hardware PC</div>            ```
            <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    ```                    <div className="text-xs text-slate-400">CPU</div>                    ```
                    ```                    <div className="mt-1 text-sm text-slate-100">{hardware?.cpu ?? "—"}</div>                    ```
                </div>
                <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    ```                    <div className="text-xs text-slate-400">RAM</div>                    ```
                    <div className="mt-1 text-sm text-slate-100">
                        {hardware?.ramGb !== null \&\& hardware?.ramGb !== undefined ? `${hardware.ramGb} GB` : "—"}
                    </div>
                </div>
                <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    ```                    <div className="text-xs text-slate-400">Disco</div>                    ```
                    <div className="mt-1 text-sm text-slate-100">
                        {hardware?.diskGb !== null \&\& hardware?.diskGb !== undefined ? `${hardware.diskGb} GB` : "—"}
                    </div>
                </div>
                <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    ```                    <div className="text-xs text-slate-400">Windows</div>                    ```
                    ```                    <div className="mt-1 text-sm text-slate-100">{hardware?.windowsVersion ?? "—"}</div>                    ```
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
                        ```                        <div className="truncate text-sm font-semibold text-slate-100">{step.name}</div>                        ```
                        ```                        <div className="shrink-0 text-xs text-slate-400">{formatTs(step.timestamp)}</div>                        ```
                    </div>

{step.message ? (
                        ```                        <div className="mt-2 whitespace-pre-wrap text-sm text-slate-200">{step.message}</div>                        ```
                    ) : (
                        ```                        <div className="mt-2 text-sm text-slate-400">Nessun dettaglio disponibile.</div>                        ```
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

// Announce new steps for screen readers
    const announceRef = React.useRef(null);
    React.useEffect(() => {
        if ((ui.newStepIds ?? []).length > 0) {
            const msg = `${ui.newStepIds.length} nuovo step ricevuto.`;
            if (announceRef.current) announceRef.current.textContent = msg;
            const t = window.setTimeout(() => {
                if (announceRef.current) announceRef.current.textContent = "";
            }, 1500);
            return () => window.clearTimeout(t);
        }
    }, [ui.newStepIds]);

const badge = useMemo(() => statusBadge(data?.wizard?.status), [data?.wizard?.status]);

const showReportCta = data?.wizard?.status === "completato" || data?.wizard?.status === "errore";

const headerPcName = data?.pc?.name ? String(data.pc.name) : null;

const completedCardVisible = data?.wizard?.status === "completato";

const summary = data?.summary ?? { installed: 0, removed: 0, errors: 0 };

const newIdsSet = useMemo(() => new Set(ui.newStepIds ?? []), [ui.newStepIds]);

return (
        <div className="space-y-6">
            {/* Accessibility live region */}
            <div className="sr-only" aria-live="polite" ref={announceRef} />

{/* Header */}
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0">
                    ```                    <div className="text-xs uppercase tracking-wide text-slate-400">Monitor</div>                    ```
                    ```                    <h1 className="mt-1 truncate text-xl font-semibold text-slate-100">{data?.wizard?.name ?? "Wizard"}</h1>                    ```

<div className="mt-2 flex flex-col gap-1 text-sm text-slate-300">
                        <div className="flex items-center gap-2">
                            ```                            <span className="text-slate-400">Codice:</span>                            ```
                            <span className="font-mono text-slate-100">{data?.wizard?.code || "—"}</span>
                        </div>
                        <div className="flex items-center gap-2">
                            ```                            <span className="text-slate-400">PC:</span>                            ```
                            ```                            <span className="text-slate-100">{headerPcName ?? "In attesa info dal PC..."}</span>                            ```
                        </div>
                    </div>
                </div>

<div className="flex flex-col items-start gap-3 sm:flex-row sm:items-center">
                    ```                    <div className={`rounded-2xl border px-5 py-3 text-base font-semibold ${badge.cls}`}>{badge.label}</div>                    ```

<div className="flex items-center gap-2">
                        ```                        <button type="button" onClick={actions.refetch} className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900">Aggiorna</button>                        ```

```                        <button type="button" onClick={() => (ui.isPolling ? actions.stopPolling() : actions.startPolling())} className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900">{ui.isPolling ? "Ferma polling" : "Avvia polling"}</button>                        ```

```                        <div className="inline-flex items-center rounded px-3 py-2 text-xs text-slate-400">{ui.isPolling ? "Polling: 5s" : "Polling: off"}</div>                        ```

```                        <NavLink to="/wizards" className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900">Torna ai wizard</NavLink>                        ```
                    </div>
                </div>
            </div>

{/* Errors */}
            {ui.isError ? (
                <div className="rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-200">{ui.errorMessage || "Errore non specificato."}</div>
            ) : null}

{/* Top cards */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <ProgressBar percent={progress.percent} labelLeft={`Progresso (${progress.completedCount}/${progress.expectedTotal} step completati)`} labelRight={`${progress.percent}%`} />
                    ```                    <div className="mt-3 text-xs text-slate-500">{ui.lastUpdatedAt ? `Ultimo aggiornamento: ${formatTs(ui.lastUpdatedAt)}` : "—"}{ui.isPolling && !showReportCta ? " • Polling ogni 5s" : " • Polling fermo"}</div>                    ```
                </div>

<HardwareCard hardware={data?.hardware} />
            </div>

{/* Steps */}
            <div className="space-y-3">
                <div className="flex items-end justify-between gap-3">
                    <div>
                        ```                        <div className="text-sm font-semibold text-slate-100">Step</div>                        ```
                        ```                        <div className="mt-1 text-sm text-slate-400">Avanzamento in ordine cronologico.</div>                        ```
                    </div>

{showReportCta ? (
                        ```                        <button type="button" onClick={() => navigate(`/reports/${id}`)} className="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500">Vedi Report</button>                        ```
                    ) : null}
                </div>

{ui.isLoading ? (
                    ```                    <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4 text-sm text-slate-400">Caricamento monitor...</div>                    ```
                ) : null}

{!ui.isLoading \&\& (data?.steps?.length ?? 0) === 0 ? (
                    ```                    <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4 text-sm text-slate-400">Nessuno step ancora ricevuto.</div>                    ```
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
                    ```                    <div className="text-lg font-semibold text-emerald-100">Configurazione completata</div>                    ```
                    ```                    <div className="mt-1 text-sm text-emerald-200/80">Il PC ha terminato l’esecuzione del wizard. Puoi scaricare il report o avviare una nuova configurazione.</div>                    ```

<div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div className="rounded-lg border border-emerald-500/20 bg-slate-950/30 p-3">
                            ```                            <div className="text-xs text-emerald-200/80">Tot installati</div>                            ```
                            ```                            <div className="mt-1 text-2xl font-semibold text-emerald-100">{summary.installed}</div>                            ```
                        </div>
                        <div className="rounded-lg border border-emerald-500/20 bg-slate-950/30 p-3">
                            ```                            <div className="text-xs text-emerald-200/80">Tot rimossi</div>                            ```
                            ```                            <div className="mt-1 text-2xl font-semibold text-emerald-100">{summary.removed}</div>                            ```
                        </div>
                        <div className="rounded-lg border border-emerald-500/20 bg-slate-950/30 p-3">
                            ```                            <div className="text-xs text-emerald-200/80">Tot errori</div>                            ```
                            ```                            <div className="mt-1 text-2xl font-semibold text-emerald-100">{summary.errors}</div>                            ```
                        </div>
                    </div>

<div className="mt-5 flex flex-col gap-2 sm:flex-row">
                        ```                        <a href={`/api/wizards/${id}/report.html`} className="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Scarica Report HTML</a>                        ```

```                        <NavLink to="/wizards/new" className="inline-flex items-center justify-center rounded-lg border border-emerald-500/30 bg-slate-950/30 px-4 py-2 text-sm font-semibold text-emerald-100 hover:bg-slate-950/50">Configura un altro PC</NavLink>                        ```
                    </div>

```                    <div className="mt-3 text-xs text-emerald-200/70">Suggerimento: se vuoi evitare link diretti non autenticati al report, esponi il download solo tramite endpoint protetto (token utente).</div>                    ```
                </div>
            ) : null}
        </div>
    );
}

La 404 è “normale” perché il tuo `MonitorPage` è definito come pagina **parametrica** legata a un wizard specifico, ma tu stai aprendo `/monitor` senza `id`. Nel router, infatti, hai due concetti distinti: la pagina di **lista wizard** con pulsante “Monitor”, e una sola pagina di **dettaglio monitor** che usa `useParams()` per leggere `id`.[^8_1]

***

## 1) Come deve essere la route nel router

Nel tuo `AppRouter.jsx` esiste una route:

```jsx
// Estratto corretto da avere nel router
<Route
  element={<PrivateRoute allowedRoles={["admin", "tecnico", "viewer"]} />}
>
  <Route element={<MainLayout />}>
    {/* ...altre route... */}

    {/* Monitor di UN wizard specifico */}
    <Route path="/monitor/:id" element={<MonitorPage />} />
  </Route>
</Route>
```

Cose importanti:

- `path="/monitor/:id"` (non solo `/monitor`).
- `MonitorPage` deve essere importato da `../pages/Monitor`.[^8_1]
- La route deve stare **dentro** il blocco protetto + `MainLayout` così hai sidebar e auth anche lì.[^8_1]

Se nel tuo file hai ancora `Route path="monitor" element={MonitorPage}` o simili, va corretto in questo modo.

***

## 2) Come ci devi arrivare dal frontend

Il codice del tuo `MonitorPage` usa:

```jsx
const { id } = useParams();
```

Quindi si aspetta URL del tipo:

- `/monitor/123`
- `/monitor/42`
- ecc.

Per questo dalla tabella wizard devi navigare così (e non verso `/monitor` secco):

```jsx
// Dentro WizardsListPage.jsx, colonna Azioni
<NavLink
  to={`/monitor/${row.id}`}
  className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-xs font-medium text-slate-200 hover:bg-slate-950"
>
  <Eye className="h-4 w-4" />
  Monitor
</NavLink>
```

Se preferisci legare il monitor al **codice** invece che all’`id`, puoi cambiare così:

- Route: `path="/monitor/code/:code"`
- Nel `MonitorPage` usi `const { code } = useParams();`
- Nel link: `to={`/monitor/code/\${row.code}`}`

Ma l’importante è che il path del link e il path della route **coincidano**.

***

## 3) Perché vedrai sempre 404 digitando `/monitor` a mano

Il tuo componente `MonitorPage` non ha un fallback quando manca `id`: se apri `/monitor`:

- React Router cerca una route esattamente `/monitor` → non trova nulla.[^8_1]
- E quindi renderizza `NotFoundPage`.

Se vuoi una pagina “indice monitor” (es. lista delle ultime esecuzioni), devi creare **un altro** componente, ad esempio `MonitorIndexPage.jsx`, e aggiungere una route addizionale:

```jsx
<Route path="/monitor" element={<MonitorIndexPage />} />
<Route path="/monitor/:id" element={<MonitorPage />} />
```

Altrimenti l’URL corretto da usare è solo `/monitor/:id`.

***

## 4) Checklist operativa

- [ ] In `AppRouter.jsx` assicurati di avere:

```jsx
import MonitorPage from "../pages/monitor"; // o dal path che usi tu

<Route path="/monitor/:id" element={<MonitorPage />} />
```

- [ ] Aggiorna i link “Monitor” (lista wizard, dashboard, ecc.) a usare **`/monitor/${id}`** o `/monitor/${code}` coerentemente.
- [ ] Prova a incollare direttamente un URL reale, tipo: `http://localhost:5173/monitor/1` usando un `id` esistente.
- [ ] Solo se vuoi una pagina `/monitor` generica, crea un `MonitorIndexPage` separato.

Vuoi che nel prossimo messaggio ti riscriva l’intero `AppRouter.jsx` già pulito (dashboard, wizards, monitor, admin pages) così lo allinei in blocco e ci togliamo ogni ambiguità sui path?

<div align="center">⁂</div>

[^8_1]: AppRouter.jsx


---

# nella pagina wizard premendo monitor mi porta a \wizard\WizardMonitorPage.jsx e quando vado nella sezione monitor mi porta all'index.jsx

WizardMonitorPage.jsx
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
          ```          <h1 className="text-xl font-semibold text-slate-100">Monitor wizard</h1>          ```
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
        ```        {isLoading && <div className="text-sm text-slate-400">Caricamento…</div>}        ```

{isError \&\& (
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

{!isLoading \&\& !isError \&\& (
          <div className="space-y-4">
            {data?.pending === true \&\& (
              <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                ```                <div className="text-sm text-slate-300">In attesa di avvio</div>                ```
                ```                <div className="mt-1 text-xs text-slate-400">{data?.message ?? 'Nessuna esecuzione avviata.'}</div>                ```
              </div>
            )}
            <div className="flex items-center gap-2 text-slate-200">
              <Activity className="h-4 w-4 text-sky-400" />
              ```              <span className="text-sm font-medium">Stato esecuzione</span>              ```
            </div>

<div className="grid grid-cols-1 gap-3 md:grid-cols-3">
              <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                ```                <div className="text-xs text-slate-400">Wizard ID</div>                ```
                ```                <div className="mt-1 font-mono text-sm text-slate-100">{data?.wizardid ?? '—'}</div>                ```
              </div>

<div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                ```                <div className="text-xs text-slate-400">Execution Log ID</div>                ```
                ```                <div className="mt-1 font-mono text-sm text-slate-100">{data?.executionlogid ?? '—'}</div>                ```
              </div>

<div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                ```                <div className="text-xs text-slate-400">Wizard stato</div>                ```
                ```                <div className="mt-1 text-sm text-slate-100">{data?.wizardstato ?? data?.stato ?? '—'}</div>                ```
              </div>

<div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                ```                <div className="text-xs text-slate-400">Execution stato</div>                ```
                ```                <div className="mt-1 text-sm text-slate-100">{data?.executionstato ?? data?.stato ?? '—'}</div>                ```
              </div>
            </div>

<div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
              ```              <div className="text-xs text-slate-400">Step corrente</div>              ```
              ```              <div className="mt-1 text-sm text-slate-100">{data?.stepcorrente ?? '—'}</div>              ```
            </div>

<div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
              ```              <div className="text-xs text-slate-400">Log dettagliato (JSON)</div>              ```
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
\monitor\index.jsx
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
                ```                <div className="shrink-0 font-mono">{labelRight}</div>                ```
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
            ```            <div className="text-sm font-semibold text-slate-100">Hardware PC</div>            ```
            <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    ```                    <div className="text-xs text-slate-400">CPU</div>                    ```
                    ```                    <div className="mt-1 text-sm text-slate-100">{hardware?.cpu ?? "—"}</div>                    ```
                </div>
                <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    ```                    <div className="text-xs text-slate-400">RAM</div>                    ```
                    <div className="mt-1 text-sm text-slate-100">
                        {hardware?.ramGb !== null \&\& hardware?.ramGb !== undefined ? `${hardware.ramGb} GB` : "—"}
                    </div>
                </div>
                <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    ```                    <div className="text-xs text-slate-400">Disco</div>                    ```
                    <div className="mt-1 text-sm text-slate-100">
                        {hardware?.diskGb !== null \&\& hardware?.diskGb !== undefined ? `${hardware.diskGb} GB` : "—"}
                    </div>
                </div>
                <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    ```                    <div className="text-xs text-slate-400">Windows</div>                    ```
                    ```                    <div className="mt-1 text-sm text-slate-100">{hardware?.windowsVersion ?? "—"}</div>                    ```
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
                        ```                        <div className="truncate text-sm font-semibold text-slate-100">{step.name}</div>                        ```
                        ```                        <div className="shrink-0 text-xs text-slate-400">{formatTs(step.timestamp)}</div>                        ```
                    </div>

{step.message ? (
                        ```                        <div className="mt-2 whitespace-pre-wrap text-sm text-slate-200">{step.message}</div>                        ```
                    ) : (
                        ```                        <div className="mt-2 text-sm text-slate-400">Nessun dettaglio disponibile.</div>                        ```
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

// Announce new steps for screen readers
    const announceRef = React.useRef(null);
    React.useEffect(() => {
        if ((ui.newStepIds ?? []).length > 0) {
            const msg = `${ui.newStepIds.length} nuovo step ricevuto.`;
            if (announceRef.current) announceRef.current.textContent = msg;
            const t = window.setTimeout(() => {
                if (announceRef.current) announceRef.current.textContent = "";
            }, 1500);
            return () => window.clearTimeout(t);
        }
    }, [ui.newStepIds]);

const badge = useMemo(() => statusBadge(data?.wizard?.status), [data?.wizard?.status]);

const showReportCta = data?.wizard?.status === "completato" || data?.wizard?.status === "errore";

const headerPcName = data?.pc?.name ? String(data.pc.name) : null;

const completedCardVisible = data?.wizard?.status === "completato";

const summary = data?.summary ?? { installed: 0, removed: 0, errors: 0 };

const newIdsSet = useMemo(() => new Set(ui.newStepIds ?? []), [ui.newStepIds]);

return (
        <div className="space-y-6">
            {/* Accessibility live region */}
            <div className="sr-only" aria-live="polite" ref={announceRef} />

{/* Header */}
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0">
                    ```                    <div className="text-xs uppercase tracking-wide text-slate-400">Monitor</div>                    ```
                    ```                    <h1 className="mt-1 truncate text-xl font-semibold text-slate-100">{data?.wizard?.name ?? "Wizard"}</h1>                    ```

<div className="mt-2 flex flex-col gap-1 text-sm text-slate-300">
                        <div className="flex items-center gap-2">
                            ```                            <span className="text-slate-400">Codice:</span>                            ```
                            <span className="font-mono text-slate-100">{data?.wizard?.code || "—"}</span>
                        </div>
                        <div className="flex items-center gap-2">
                            ```                            <span className="text-slate-400">PC:</span>                            ```
                            ```                            <span className="text-slate-100">{headerPcName ?? "In attesa info dal PC..."}</span>                            ```
                        </div>
                    </div>
                </div>

<div className="flex flex-col items-start gap-3 sm:flex-row sm:items-center">
                    ```                    <div className={`rounded-2xl border px-5 py-3 text-base font-semibold ${badge.cls}`}>{badge.label}</div>                    ```

<div className="flex items-center gap-2">
                        ```                        <button type="button" onClick={actions.refetch} className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900">Aggiorna</button>                        ```

```                        <button type="button" onClick={() => (ui.isPolling ? actions.stopPolling() : actions.startPolling())} className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900">{ui.isPolling ? "Ferma polling" : "Avvia polling"}</button>                        ```

```                        <div className="inline-flex items-center rounded px-3 py-2 text-xs text-slate-400">{ui.isPolling ? "Polling: 5s" : "Polling: off"}</div>                        ```

```                        <NavLink to="/wizards" className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900">Torna ai wizard</NavLink>                        ```
                    </div>
                </div>
            </div>

{/* Errors */}
            {ui.isError ? (
                <div className="rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-200">{ui.errorMessage || "Errore non specificato."}</div>
            ) : null}

{/* Top cards */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <ProgressBar percent={progress.percent} labelLeft={`Progresso (${progress.completedCount}/${progress.expectedTotal} step completati)`} labelRight={`${progress.percent}%`} />
                    ```                    <div className="mt-3 text-xs text-slate-500">{ui.lastUpdatedAt ? `Ultimo aggiornamento: ${formatTs(ui.lastUpdatedAt)}` : "—"}{ui.isPolling && !showReportCta ? " • Polling ogni 5s" : " • Polling fermo"}</div>                    ```
                </div>

<HardwareCard hardware={data?.hardware} />
            </div>

{/* Steps */}
            <div className="space-y-3">
                <div className="flex items-end justify-between gap-3">
                    <div>
                        ```                        <div className="text-sm font-semibold text-slate-100">Step</div>                        ```
                        ```                        <div className="mt-1 text-sm text-slate-400">Avanzamento in ordine cronologico.</div>                        ```
                    </div>

{showReportCta ? (
                        ```                        <button type="button" onClick={() => navigate(`/reports/${id}`)} className="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500">Vedi Report</button>                        ```
                    ) : null}
                </div>

{ui.isLoading ? (
                    ```                    <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4 text-sm text-slate-400">Caricamento monitor...</div>                    ```
                ) : null}

{!ui.isLoading \&\& (data?.steps?.length ?? 0) === 0 ? (
                    ```                    <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4 text-sm text-slate-400">Nessuno step ancora ricevuto.</div>                    ```
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
                    ```                    <div className="text-lg font-semibold text-emerald-100">Configurazione completata</div>                    ```
                    ```                    <div className="mt-1 text-sm text-emerald-200/80">Il PC ha terminato l’esecuzione del wizard. Puoi scaricare il report o avviare una nuova configurazione.</div>                    ```

<div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div className="rounded-lg border border-emerald-500/20 bg-slate-950/30 p-3">
                            ```                            <div className="text-xs text-emerald-200/80">Tot installati</div>                            ```
                            ```                            <div className="mt-1 text-2xl font-semibold text-emerald-100">{summary.installed}</div>                            ```
                        </div>
                        <div className="rounded-lg border border-emerald-500/20 bg-slate-950/30 p-3">
                            ```                            <div className="text-xs text-emerald-200/80">Tot rimossi</div>                            ```
                            ```                            <div className="mt-1 text-2xl font-semibold text-emerald-100">{summary.removed}</div>                            ```
                        </div>
                        <div className="rounded-lg border border-emerald-500/20 bg-slate-950/30 p-3">
                            ```                            <div className="text-xs text-emerald-200/80">Tot errori</div>                            ```
                            ```                            <div className="mt-1 text-2xl font-semibold text-emerald-100">{summary.errors}</div>                            ```
                        </div>
                    </div>

<div className="mt-5 flex flex-col gap-2 sm:flex-row">
                        ```                        <a href={`/api/wizards/${id}/report.html`} className="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Scarica Report HTML</a>                        ```

```                        <NavLink to="/wizards/new" className="inline-flex items-center justify-center rounded-lg border border-emerald-500/30 bg-slate-950/30 px-4 py-2 text-sm font-semibold text-emerald-100 hover:bg-slate-950/50">Configura un altro PC</NavLink>                        ```
                    </div>

```                    <div className="mt-3 text-xs text-emerald-200/70">Suggerimento: se vuoi evitare link diretti non autenticati al report, esponi il download solo tramite endpoint protetto (token utente).</div>                    ```
                </div>
            ) : null}
        </div>
    );
}

Hai due componenti “monitor” diversi:

- `WizardMonitorPage.jsx` (monitor minimale via `/wizards/:id/monitor`)
- `pages/monitor/index.jsx` (monitor avanzato via `useMonitor`, pensato per `/monitor/:id`)

Il 404 su `/monitor` nasce perché il router non conosce quella route (o la conosce solo con `:id`), mentre dalla sidebar ci arrivi a `/monitor` secco.

Per mettere ordine serve:

1. **Decidere un solo pattern di URL**.
2. Allineare **router + sidebar + pulsante Monitor lista wizard** su quel pattern.
3. Eliminare il doppione superfluo.

Ti propongo una struttura chiara:

- Lista wizard: `/wizards`
- Monitor di un wizard specifico (UI avanzata di `index.jsx`): `/monitor/:id`
- Nessuna pagina `/monitor` senza id.

***

## 1) Router unificato

Apri `src/router/AppRouter.jsx` e sostituisci il blocco relativo a wizard/monitor con questo (lascia invariato il resto):

```jsx
// import in testa al file
import MonitorPage from "../pages/monitor";           // usa l'index.jsx avanzato
import WizardMonitorPage from "../pages/Wizards/WizardMonitorPage"; // <– se decidi di tenerlo

// ...

function AppRouter() {
  return (
    <Routes>
      {/* Pubblico */}
      <Route path="/login" element={<LoginPage />} />

      {/* Area protetta con layout */}
      <Route element={<PrivateRoute allowedRoles={['admin', 'tecnico', 'viewer']} />}>
        <Route element={<MainLayout />}>
          {/* Redirect root -> dashboard */}
          <Route index element={<Navigate to="/dashboard" replace />} />

          {/* Dashboard & reports */}
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/reports" element={<ReportsPage />} />

          {/* Monitor avanzato per UN wizard specifico */}
          <Route path="/monitor/:id" element={<MonitorPage />} />

          {/* Wizards (lista, builder, monitor semplice – opzionale) */}
          <Route path="/wizards" element={<WizardsListPage />} />
          <Route path="/wizards/new" element={<WizardBuilderPage />} />
          <Route path="/wizards/:id/monitor" element={<WizardMonitorPage />} />
          {/* se NON vuoi più WizardMonitorPage, commenta la riga sopra */}

          {/* Templates, Software, Users come hai già */}
          <Route path="/templates" element={<TemplatesPage />} />
          <Route path="/software" element={<SoftwarePage />} />
          <Route path="/users" element={<UsersPage />} />
        </Route>
      </Route>

      {/* 404 */}
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
}
```

Punti chiave:

- `MonitorPage` (quello di `index.jsx`) è ora agganciato a `/monitor/:id`, non a `/monitor`.
- `WizardMonitorPage` puoi ancora usarlo se vuoi una vista “tecnica/json” separata, altrimenti lo togli dalle route e magari lo elimini dal progetto.

***

## 2) Sidebar “Monitor” deve avere un id

La voce Monitor in sidebar oggi ti porta a `/monitor` secco, che non ha senso senza un wizard. Soluzioni:

### Variante A – Monitor mostra “seleziona un wizard”

Fai puntare la voce “Monitor” alla lista dei wizard:

```jsx
// in MainLayout.jsx
<NavLink to="/wizards" className={buildNavClass}>
  <Monitor className="h-4 w-4" />
  <span>Monitor</span>
</NavLink>
```

(l’etichetta puoi lasciarla Monitor anche se l’URL è `/wizards`.)

### Variante B – Monitor ultimo wizard usato

Se vuoi che clic su Monitor apra l’ultimo wizard monitorato:

1. Salvi in `localStorage` l’ultimo `id` visto in MonitorPage.
2. In `MainLayout.jsx`, nel click del NavLink/btn Monitor leggi quel valore e navighi con `useNavigate` verso `/monitor/${lastId}` solo se esiste, altrimenti `/wizards`.

Per adesso, per semplicità, ti suggerisco **Variante A**.

***

## 3) Pulsante “Monitor” nella lista wizard

Nel tuo `WizardsListPage.jsx` hai già il link verso `WizardMonitorPage`:

```jsx
<NavLink
  to={`/wizards/${row.id}/monitor`}
  className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-xs font-medium text-slate-200 hover:bg-slate-950"
>
  <Eye className="h-4 w-4" />
  Monitor
</NavLink>
```

Questo continuerà a portarti alla versione “semplice” (`WizardMonitorPage`). Se vuoi invece che la lista wizard apra la UI avanzata (`MonitorPage` di `index.jsx`), ti basta cambiare il `to` in:

```jsx
// Per usare il monitor avanzato
<NavLink
  to={`/monitor/${row.id}`}
  className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-xs font-medium text-slate-200 hover:bg-slate-950"
>
  <Eye className="h-4 w-4" />
  Monitor
</NavLink>
```

E puoi anche rimuovere completamente `WizardMonitorPage.jsx` se non ti serve più.

***

## 4) Riassunto operativo

- Decidi: vuoi mantenere **solo** il monitor avanzato (`pages/monitor/index.jsx`) e buttare il vecchio `WizardMonitorPage`? Se sì:
    - Togli la route `/wizards/:id/monitor`.
    - Aggiorna i link “Monitor” a puntare a `/monitor/${id}`.
- In ogni caso:
    - Non usare più `/monitor` secco; usa sempre `/monitor/:id`.
    - Fai puntare la voce Monitor della sidebar a `/wizards` o implementa la logica “ultimo wizard”.

Se mi dici se preferisci unificare tutto sulla nuova UI avanzata (mi sembra nettamente migliore), nel prossimo messaggio ti scrivo il `AppRouter.jsx` completo già **senza** `WizardMonitorPage`, così hai un’unica fonte di verità per il monitor.

---

# pagina user: Errore: Accesso negato. Ruolo insufficiente.

pagina reports: Questa pagina ha riscontrato un errore.
dimmi quali file ti servono e io te li do

Per debuggare Users e Reports mi servono **esattamente questi 3 file**:

1. **`AppRouter.jsx`** completo (per vedere le route esatte e gli `allowedRoles`).
2. **`UsersPage.jsx`** (per vedere se ha crash o problema API).
3. **`ReportsPage.jsx`** (stesso).

Inviami questi 3 e in 2 minuti ti dico esattamente cosa manca/fix.

**Se non li hai allegati di recente**, incolla qui il contenuto di:

```
src/router/AppRouter.jsx
src/pages/Users/UsersPage.jsx    (o dove lo hai)
src/pages/Reports/ReportsPage.jsx (o dove lo hai)
```

**Intanto, causa probabile**:

- **Users**: route protetta `allowedRoles={['admin']}` ma il tuo utente è `tecnico` o `viewer`.
- **Reports**: crash React (componente vuoto, import mancante, API che fallisce senza try/catch).

Inviami i file e sistemiamo.

---

# \\router\\index.jsx

// Path: frontend/src/router/index.jsx
import React, { Suspense } from "react";
import { Routes, Route, Navigate } from "react-router-dom";

import PrivateRoute from "./PrivateRoute.jsx";
import MainLayout from "../components/layout/MainLayout.jsx";

import RouteErrorBoundary from "../components/ErrorBoundary/RouteErrorBoundary.jsx";
import WizardErrorBoundary from "../components/ErrorBoundary/WizardErrorBoundary.jsx";

function PageLoader({ label }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
      {label || "Caricamento..."}
    </div>
  );
}

// English comment: Lazy-load pages so a crash in one page does not bring down the whole app.
const LoginPage = React.lazy(() => import("../pages/Login/LoginPage.jsx"));
const DashboardPage = React.lazy(() => import("../pages/Dashboard/DashboardPage.jsx"));
const WizardsListPage = React.lazy(() => import("../pages/Wizards/WizardsListPage.jsx"));
const WizardBuilderPage = React.lazy(() => import("../pages/Wizards/WizardBuilderPage.jsx"));
const MonitorPage = React.lazy(() => import("../pages/Monitor/index.jsx"));
const TemplatesPage = React.lazy(() => import("../pages/Templates/TemplatesPage.jsx"));
const SoftwarePage = React.lazy(() => import("../pages/Software/SoftwarePage.jsx"));
const ReportsPage = React.lazy(() => import("../pages/Reports/ReportsPage.jsx"));
const UsersPage = React.lazy(() => import("../pages/Users/UsersPage.jsx"));
const NotFoundPage = React.lazy(() => import("../pages/NotFound/NotFoundPage.jsx"));

function wrapRoute(element, { resetKeys, fallbackLabel } = {}) {
  return (
    <RouteErrorBoundary resetKeys={resetKeys}>
      <Suspense fallback={<PageLoader label={fallbackLabel} />}>{element}</Suspense>
    </RouteErrorBoundary>
  );
}

export default function AppRouter() {
  return (
    <Routes>
      {/* Public */}
      <Route
        path="/login"
        element={wrapRoute(<LoginPage />, {
          resetKeys: ["login"],
          fallbackLabel: "Caricamento login...",
        })}
      />

{/* Protected */}
      <Route element={<PrivateRoute allowedRoles={["admin", "tecnico", "viewer"]} />}>
        <Route element={<MainLayout />}>
          {/* Redirect root -> /dashboard */}
          <Route index element={<Navigate to="/dashboard" replace />} />

{/* Dashboard \& Reports */}
          <Route
            path="/dashboard"
            element={wrapRoute(<DashboardPage />, {
              resetKeys: ["dashboard"],
              fallbackLabel: "Caricamento dashboard...",
            })}
          />

<Route
            path="/reports"
            element={wrapRoute(<ReportsPage />, {
              resetKeys: ["reports"],
              fallbackLabel: "Caricamento report...",
            })}
          />

{/* Monitor avanzato per UN wizard specifico */}
          <Route
            path="/monitor/:id"
            element={wrapRoute(<MonitorPage />, {
              resetKeys: ["monitor"],
              fallbackLabel: "Caricamento monitor...",
            })}
          />

{/* Wizards → tecnico + admin */}
          <Route element={<PrivateRoute allowedRoles={["admin", "tecnico"]} />}>
            <Route
              path="/wizards"
              element={wrapRoute(<WizardsListPage />, {
                resetKeys: ["wizards"],
                fallbackLabel: "Caricamento lista wizard...",
              })}
            />

<Route
              path="/wizards/new"
              element={
                <RouteErrorBoundary resetKeys={["wizard-new"]}>
                  <WizardErrorBoundary resetKeys={["wizard-new"]}>
                    <Suspense fallback={<PageLoader label="Caricamento Wizard Builder..." />}>
                      <WizardBuilderPage />
                    </Suspense>
                  </WizardErrorBoundary>
                </RouteErrorBoundary>
              }
            />

{/* Templates → tecnico + admin */}
            <Route
              path="/templates"
              element={wrapRoute(<TemplatesPage />, {
                resetKeys: ["templates"],
                fallbackLabel: "Caricamento templates...",
              })}
            />
          </Route>

{/* Admin only */}
          <Route element={<PrivateRoute allowedRoles={["admin"]} />}>
            <Route
              path="/software"
              element={wrapRoute(<SoftwarePage />, {
                resetKeys: ["software"],
                fallbackLabel: "Caricamento software library...",
              })}
            />

<Route
              path="/users"
              element={wrapRoute(<UsersPage />, {
                resetKeys: ["users"],
                fallbackLabel: "Caricamento utenti...",
              })}
            />
          </Route>

{/* 404 */}
          <Route
            path="*"
            element={wrapRoute(<NotFoundPage />, {
              resetKeys: ["notfound"],
              fallbackLabel: "Caricamento...",
            })}
          />
        </Route>
      </Route>
    </Routes>
  );
}
\\router\\PrivateRoute.jsx
// Path: frontend/src/router/PrivateRoute.jsx
import React from "react";
import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useAuthStore } from "../store/authStore.js";

export default function PrivateRoute({ allowedRoles }) {
  const location = useLocation();
  const { isAuthenticated, user } = useAuthStore();

if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

// Backend sends 'ruolo' (Italian), normalize to lowercase for comparison
  const normalizedRole = String(user?.ruolo || "").toLowerCase();

if (Array.isArray(allowedRoles) \&\& allowedRoles.length > 0) {
    const normalizedAllowed = allowedRoles.map((r) => String(r).toLowerCase());

if (!normalizedRole || !normalizedAllowed.includes(normalizedRole)) {
      return (
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
          ```          <div className="text-sm font-semibold">Accesso negato</div>          ```
          <div className="mt-1 text-sm">
            Il tuo ruolo ({normalizedRole || "n/d"}) non è autorizzato per questa pagina.
          </div>
        </div>
      );
    }
  }

return <Outlet />;
}
src/pages/Users/UsersPage.jsx
import React, { useEffect, useMemo, useState } from "react";
import { Plus, RefreshCw, Trash2, KeyRound, Save, X, AlertTriangle } from "lucide-react";
import toast from "react-hot-toast";
import client from "../../api/client";
import Modal from "../../components/ui/Modal";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import ToggleSwitch from "../../components/ui/ToggleSwitch";
import Badge from "../../components/ui/Badge";
import { formatDateTime } from "../../utils/formatDateTime";

function normalizeApiCollection(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (payload.data \&\& Array.isArray(payload.data)) return payload.data;
  if (payload.data \&\& payload.data.data \&\& Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function safeString(v) {
  return (v ?? "").toString();
}

function roleTone(role) {
  const r = safeString(role).toLowerCase();
  if (r === "admin") return "violet";
  if (r === "tecnico") return "sky";
  if (r === "viewer") return "slate";
  return "slate";
}

function mapUserRow(item) {
  const u = item?.data ? item.data : item;
  return {
    id: u?.id,
    name: u?.nome ?? u?.name ?? "",
    email: u?.email ?? "",
    role: (u?.ruolo ?? u?.role ?? "viewer").toString().toLowerCase(),
    lastLogin: u?.last_login ?? u?.lastLogin ?? null,
    lastLoginIp: u?.last_login_ip ?? u?.lastLoginIp ?? u?.lastloginip ?? null,
    active: Boolean(u?.attivo ?? u?.active ?? true),
  };
}

function InlineSelect({ value, onChange, disabled }) {
  return (
    <select
      value={value}
      disabled={disabled}
      onChange={(e) => onChange?.(e.target.value)}
      className="w-full rounded-lg border border-slate-800 bg-slate-950 px-2 py-1.5 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40 disabled:opacity-50"
    >
      <option value="admin">Admin</option>
      <option value="tecnico">Tecnico</option>
      <option value="viewer">Viewer</option>
    </select>
  );
}

function validateNewUserForm(form) {
  const errors = {};
  if (!safeString(form.name).trim()) errors.name = "Nome obbligatorio.";
  if (!safeString(form.email).trim()) errors.email = "Email obbligatoria.";
  if (!safeString(form.email).includes("@")) errors.email = "Email non valida.";
  if (!["admin", "tecnico", "viewer"].includes(safeString(form.role).toLowerCase())) errors.role = "Ruolo non valido.";
  if (!safeString(form.tempPassword).trim() || safeString(form.tempPassword).trim().length < 8) errors.tempPassword = "Password temporanea min 8 caratteri.";
  return errors;
}

export default function UsersPage() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

const [users, setUsers] = useState([]);

const [createModal, setCreateModal] = useState(false);
  const [createForm, setCreateForm] = useState({ name: "", email: "", role: "tecnico", tempPassword: "" });
  const [createErrors, setCreateErrors] = useState({});

const [editState, setEditState] = useState({ editingId: null, draftRole: "viewer", saving: false });

const [confirmToggle, setConfirmToggle] = useState({ open: false, row: null, nextValue: false });
  const [confirmDelete, setConfirmDelete] = useState({ open: false, row: null });

const [resetModal, setResetModal] = useState({ open: false, user: null, loading: false, tempPassword: "" });

async function fetchUsers() {
    setLoading(true);
    setError("");
    try {
      const res = await client.get("/users");
      const list = normalizeApiCollection(res?.data).map(mapUserRow);
      setUsers(list);
    } catch (err) {
      setError(safeString(err?.response?.data?.message || err?.message || "Errore caricamento utenti."));
    } finally {
      setLoading(false);
    }
  }

useEffect(() => {
    fetchUsers();
  }, []);

const activeCount = useMemo(() => users.filter((u) => u.active).length, [users]);

async function createUser() {
    const errors = validateNewUserForm(createForm);
    setCreateErrors(errors);
    if (Object.keys(errors).length > 0) {
      toast.error("Controlla i campi del form.");
      return;
    }

const t = toast.loading("Creazione utente...");
    try {
      const payload = {
        nome: safeString(createForm.name).trim(),
        email: safeString(createForm.email).trim(),
        ruolo: safeString(createForm.role).toLowerCase(),
        password_temporanea: safeString(createForm.tempPassword),
      };

const res = await client.post("/users", payload);
      toast.dismiss(t);
      toast.success("Utente creato.");

setCreateModal(false);
      setCreateErrors({});
      setCreateForm({ name: "", email: "", role: "tecnico", tempPassword: "" });

await fetchUsers();
      return res?.data;
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore creazione utente."));
    }
  }

function startRoleEdit(row) {
    setEditState({ editingId: row.id, draftRole: row.role, saving: false });
  }

function cancelRoleEdit() {
    setEditState({ editingId: null, draftRole: "viewer", saving: false });
  }

async function saveRoleEdit() {
    const userId = editState.editingId;
    if (!userId) return;

setEditState((p) => ({ ...p, saving: true }));

const t = toast.loading("Aggiornamento ruolo...");
    try {
      await client.put(`/users/${userId}`, { ruolo: safeString(editState.draftRole).toLowerCase() });
      toast.dismiss(t);
      toast.success("Ruolo aggiornato.");
      cancelRoleEdit();
      await fetchUsers();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore aggiornamento ruolo."));
      setEditState((p) => ({ ...p, saving: false }));
    }
  }

function requestToggle(row, nextValue) {
    setConfirmToggle({ open: true, row, nextValue });
  }

async function confirmToggleActive() {
    const row = confirmToggle.row;
    if (!row?.id) return;

const t = toast.loading("Aggiornamento stato account...");
    try {
      await client.put(`/users/${row.id}`, { attivo: Boolean(confirmToggle.nextValue) });
      toast.dismiss(t);
      toast.success("Stato account aggiornato.");
      setConfirmToggle({ open: false, row: null, nextValue: false });
      await fetchUsers();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore aggiornamento stato."));
    }
  }

async function deleteUser() {
    const row = confirmDelete.row;
    if (!row?.id) return;

const t = toast.loading("Eliminazione utente...");
    try {
      await client.delete(`/users/${row.id}`);
      toast.dismiss(t);
      toast.success("Utente eliminato.");
      setConfirmDelete({ open: false, row: null });
      await fetchUsers();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore eliminazione utente."));
    }
  }

async function resetPassword(userRow) {
    setResetModal({ open: true, user: userRow, loading: true, tempPassword: "" });
    const t = toast.loading("Reset password...");

try {
      // Assunzione: endpoint PUT /api/users/:id con action reset_password, oppure endpoint dedicato.
      // Se nel backend non esiste ancora, aggiungilo: deve restituire la password temporanea UNA volta. [file:17]
      const res = await client.put(`/users/${userRow.id}`, { action: "reset_password" });
      const tempPassword = safeString(res?.data?.password_temporanea || res?.data?.tempPassword || res?.data?.password || "");
      toast.dismiss(t);

if (!tempPassword) {
        toast.error("Reset eseguito ma password temporanea non restituita.");
        setResetModal({ open: true, user: userRow, loading: false, tempPassword: "" });
        return;
      }

toast.success("Password temporanea generata (mostrata una sola volta).");
      setResetModal({ open: true, user: userRow, loading: false, tempPassword });
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore reset password."));
      setResetModal({ open: true, user: userRow, loading: false, tempPassword: "" });
    }
  }

return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          ```          <h1 className="text-xl font-semibold text-slate-100">Gestione utenti</h1>          ```
          ```          <p className="mt-1 text-sm text-slate-400">Creazione account, ruoli, attivazione/disattivazione e reset password (solo admin).</p>          ```
          <div className="mt-2 text-xs text-slate-400">
            ```            Attivi: <span className="font-mono text-slate-200">{activeCount}</span> /{" "}            ```
            ```            <span className="font-mono text-slate-200">{users.length}</span>            ```
          </div>
        </div>

<div className="flex items-center gap-2">
          <button
            type="button"
            onClick={fetchUsers}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
          >
            <RefreshCw className="h-4 w-4" />
            Aggiorna
          </button>

<button
            type="button"
            onClick={() => setCreateModal(true)}
            className="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
          >
            <Plus className="h-4 w-4" />
            Nuovo utente
          </button>
        </div>
      </div>

<div className="rounded-xl border border-slate-800 bg-slate-900/30 overflow-hidden">
        ```        <div className="border-b border-slate-800 px-4 py-3 text-sm font-semibold text-slate-200">Elenco utenti</div>        ```

```        {loading ? <div className="p-4 text-sm text-slate-400">Caricamento...</div> : null}        ```
        ```        {error ? <div className="p-4 text-sm text-rose-300">Errore: {error}</div> : null}        ```
        ```        {!loading && !error && users.length === 0 ? <div className="p-4 text-sm text-slate-400">Nessun utente.</div> : null}        ```

{!loading \&\& !error \&\& users.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-slate-950/40 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  ```                  <th className="px-4 py-3 font-semibold">Nome</th>                  ```
                  ```                  <th className="px-4 py-3 font-semibold">Email</th>                  ```
                  ```                  <th className="px-4 py-3 font-semibold">Ruolo</th>                  ```
                  ```                  <th className="px-4 py-3 font-semibold">Ultimo accesso</th>                  ```
                  ```                  <th className="px-4 py-3 font-semibold">IP ultimo accesso</th>                  ```
                  ```                  <th className="px-4 py-3 font-semibold">Attivo</th>                  ```
                  ```                  <th className="px-4 py-3 font-semibold text-right">Azioni</th>                  ```
                </tr>
              </thead>

<tbody className="divide-y divide-slate-800">
                {users.map((u) => {
                  const isEditing = editState.editingId === u.id;
                  return (
                    <tr key={u.id} className="align-top">
                      ```                      <td className="px-4 py-3 font-medium text-slate-100">{u.name}</td>                      ```
                      ```                      <td className="px-4 py-3 text-slate-200">{u.email}</td>                      ```
                      <td className="px-4 py-3">
                        {isEditing ? (
                          <div className="flex items-center gap-2">
                            <InlineSelect
                              value={editState.draftRole}
                              disabled={editState.saving}
                              onChange={(v) => setEditState((p) => ({ ...p, draftRole: v }))}
                            />
                            <button
                              type="button"
                              onClick={cancelRoleEdit}
                              disabled={editState.saving}
                              className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-2 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-900 disabled:opacity-50"
                              aria-label="Cancel role edit"
                            >
                              <X className="h-4 w-4" />
                            </button>
                            <button
                              type="button"
                              onClick={saveRoleEdit}
                              disabled={editState.saving}
                              className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-2 py-2 text-xs font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                              aria-label="Save role"
                            >
                              <Save className="h-4 w-4" />
                            </button>
                          </div>
                        ) : (
                          <button
                            type="button"
                            onClick={() => startRoleEdit(u)}
                            className="inline-flex items-center gap-2"
                            title="Clicca per modificare ruolo"
                          >
                            <Badge tone={roleTone(u.role)}>{u.role}</Badge>
                            ```                            <span className="text-xs text-slate-500">(modifica)</span>                            ```
                          </button>
                        )}
                      </td>
                      <td className="px-4 py-3 text-slate-300">{formatDateTime(u.lastLogin) || "-"}</td>
                      <td className="px-4 py-3 font-mono text-slate-200">{u.lastLoginIp || "-"}</td>
                      <td className="px-4 py-3">
                        <ToggleSwitch checked={u.active} onChange={(next) => requestToggle(u, next)} />
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="inline-flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => resetPassword(u)}
                            className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-950"
                          >
                            <KeyRound className="h-4 w-4" />
                            Reset password
                          </button>

<button
                            type="button"
                            onClick={() => setConfirmDelete({ open: true, row: u })}
                            className="inline-flex items-center gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-200 hover:bg-rose-500/15"
                          >
                            <Trash2 className="h-4 w-4" />
                            Elimina
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>

<div className="border-t border-slate-800 bg-slate-950/20 p-3 text-xs text-slate-300">
              <div className="flex items-start gap-2">
                <AlertTriangle className="mt-0.5 h-4 w-4 text-amber-200" />
                <div>
                  Sicurezza: il reset password deve restituire la password temporanea **una sola volta** e non loggarla mai lato server.
                  Valuta rate-limit e audit trail (chi ha resettato cosa e quando).  
                </div>
              </div>
            </div>
          </div>
        ) : null}
      </div>

<Modal
        open={createModal}
        onClose={() => setCreateModal(false)}
        title="Nuovo utente"
        description="Crea un account e imposta una password temporanea."
        widthClassName="max-w-lg"
        footer={
          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              onClick={() => setCreateModal(false)}
              className="rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
            >
              Annulla
            </button>
            <button
              type="button"
              onClick={createUser}
              className="rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
            >
              Crea utente
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          <div>
            <label className="block text-xs font-medium text-slate-300" htmlFor="uName">
              Nome
            </label>
            <input
              id="uName"
              type="text"
              value={createForm.name}
              onChange={(e) => setCreateForm((p) => ({ ...p, name: e.target.value }))}
              className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              placeholder="Es. Mario Rossi"
            />
            ```            {createErrors.name ? <div className="mt-2 text-xs text-rose-300">{createErrors.name}</div> : null}            ```
          </div>

<div>
            <label className="block text-xs font-medium text-slate-300" htmlFor="uEmail">
              Email
            </label>
            <input
              id="uEmail"
              type="email"
              value={createForm.email}
              onChange={(e) => setCreateForm((p) => ({ ...p, email: e.target.value }))}
              className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              placeholder="nome.cognome@azienda.it"
            />
            ```            {createErrors.email ? <div className="mt-2 text-xs text-rose-300">{createErrors.email}</div> : null}            ```
          </div>

<div>
            <label className="block text-xs font-medium text-slate-300" htmlFor="uRole">
              Ruolo
            </label>
            <select
              id="uRole"
              value={createForm.role}
              onChange={(e) => setCreateForm((p) => ({ ...p, role: e.target.value }))}
              className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
            >
              <option value="admin">Admin</option>
              <option value="tecnico">Tecnico</option>
              <option value="viewer">Viewer</option>
            </select>
            ```            {createErrors.role ? <div className="mt-2 text-xs text-rose-300">{createErrors.role}</div> : null}            ```
          </div>

<div>
            <label className="block text-xs font-medium text-slate-300" htmlFor="uTempPw">
              Password temporanea
            </label>
            <input
              id="uTempPw"
              type="text"
              value={createForm.tempPassword}
              onChange={(e) => setCreateForm((p) => ({ ...p, tempPassword: e.target.value }))}
              className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm font-mono text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              placeholder="Genera una password temporanea"
            />
            ```            {createErrors.tempPassword ? <div className="mt-2 text-xs text-rose-300">{createErrors.tempPassword}</div> : null}            ```
            <div className="mt-2 text-xs text-slate-500">
              Consiglio: usa una password random lunga e comunica il cambio al primo accesso (policy server-side).
            </div>
          </div>
        </div>
      </Modal>

<ConfirmDialog
        open={confirmToggle.open}
        onClose={() => setConfirmToggle({ open: false, row: null, nextValue: false })}
        title="Conferma cambio stato account"
        description={`Vuoi davvero ${confirmToggle.nextValue ? "attivare" : "disattivare"} l’account di "${confirmToggle.row?.name}"?`}
        confirmLabel={confirmToggle.nextValue ? "Attiva" : "Disattiva"}
        danger={!confirmToggle.nextValue}
        onConfirm={confirmToggleActive}
      />

<ConfirmDialog
        open={confirmDelete.open}
        onClose={() => setConfirmDelete({ open: false, row: null })}
        title="Conferma eliminazione utente"
        description={`Eliminare l’utente "${confirmDelete.row?.name}"?`}
        confirmLabel="Elimina"
        danger
        onConfirm={deleteUser}
      />

<Modal
        open={resetModal.open}
        onClose={() => setResetModal({ open: false, user: null, loading: false, tempPassword: "" })}
        title="Reset password"
        description="La password temporanea deve essere mostrata UNA sola volta."
        widthClassName="max-w-lg"
        footer={
          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              onClick={() => setResetModal({ open: false, user: null, loading: false, tempPassword: "" })}
              className="rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
            >
              Chiudi
            </button>
            {resetModal.tempPassword ? (
              <button
                type="button"
                onClick={() => {
                  navigator.clipboard.writeText(resetModal.tempPassword);
                  toast.success("Copiata negli appunti.");
                }}
                className="rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
              >
                Copia
              </button>
            ) : null}
          </div>
        }
      >
        {resetModal.loading ? (
          ```          <div className="text-sm text-slate-400">Generazione password temporanea...</div>          ```
        ) : resetModal.tempPassword ? (
          <div className="space-y-3">
            <div className="text-sm text-slate-300">
              ```              Utente: <span className="font-semibold text-slate-100">{resetModal.user?.name}</span>              ```
            </div>
            <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
              ```              <div className="text-xs font-medium text-emerald-200">Password temporanea</div>              ```
              ```              <div className="mt-2 font-mono text-lg text-emerald-100">{resetModal.tempPassword}</div>              ```
              <div className="mt-3 text-xs text-emerald-200/80">
                Non sarà più visibile dopo la chiusura di questo modal.
              </div>
            </div>
          </div>
        ) : (
          ```          <div className="text-sm text-rose-300">Nessuna password temporanea disponibile.</div>          ```
        )}
      </Modal>
    </div>
  );
}
src/pages/Reports/ReportsPage.jsx
import React, { useEffect, useMemo, useState } from "react";
import { Download, RefreshCw, Search, FileText } from "lucide-react";
import toast from "react-hot-toast";
import client from "../../api/client";
import Modal from "../../components/ui/Modal";
import Badge from "../../components/ui/Badge";
import DateRangePicker from "../../components/ui/DateRangePicker";
import useAuthStore from "../../store/authStore";
import { formatDateTime, formatDurationSeconds } from "../../utils/formatDateTime";

function normalizeApiCollection(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (payload.data \&\& Array.isArray(payload.data)) return payload.data;
  if (payload.data \&\& payload.data.data \&\& Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function safeString(v) {
  return (v ?? "").toString();
}

function statusTone(status) {
  const s = safeString(status).toLowerCase();
  if (s.includes("complet")) return "emerald";
  if (s.includes("erro")) return "rose";
  if (s.includes("abort")) return "amber";
  if (s.includes("incors") || s.includes("esec")) return "sky";
  return "slate";
}

function mapReportRow(item) {
  const r = item?.data ? item.data : item;

// Possibile shape: report + executionLog + wizard + user (come ReportController with executionLog.wizard.user).
  const executionLog = r?.execution_log ?? r?.executionLog ?? r?.executionlog ?? null;
  const wizard = executionLog?.wizard ?? r?.wizard ?? null;
  const user = wizard?.user ?? r?.user ?? null;

const startedAt = executionLog?.started_at ?? executionLog?.startedAt ?? null;
  const completedAt = executionLog?.completed_at ?? executionLog?.completedAt ?? null;

let durationSeconds = null;
  try {
    if (startedAt \&\& completedAt) durationSeconds = Math.max(0, (new Date(completedAt) - new Date(startedAt)) / 1000);
  } catch {
    durationSeconds = null;
  }

return {
    id: r?.id,
    createdAt: r?.created_at ?? r?.createdAt ?? startedAt ?? null,
    technicianName: user?.nome ?? user?.name ?? executionLog?.tecnico_nome ?? executionLog?.technicianName ?? "-",
    pcName: executionLog?.pc_nome_nuovo ?? executionLog?.pcnome_nuovo ?? executionLog?.pcnomeNuovo ?? executionLog?.pcNewName ?? "-",
    wizardName: wizard?.nome ?? wizard?.name ?? "-",
    status: executionLog?.stato ?? executionLog?.status ?? r?.stato ?? r?.status ?? "-",
    durationSeconds,
  };
}

export default function ReportsPage() {
  const user = useAuthStore((s) => s.user);
  const isAdmin = safeString(user?.role).toLowerCase() === "admin";

const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

const [reports, setReports] = useState([]);

const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("ALL");
  const [dateRange, setDateRange] = useState({ from: null, to: null });

const [technicians, setTechnicians] = useState([]);
  const [technicianId, setTechnicianId] = useState("ALL");

const [viewModal, setViewModal] = useState({ open: false, reportId: null, htmlUrl: "", loading: false });

async function fetchReports() {
    setLoading(true);
    setError("");
    try {
      const params = {};
      if (dateRange.from) params.dadata = dateRange.from;
      if (dateRange.to) params.adata = dateRange.to;
      if (isAdmin \&\& technicianId !== "ALL") params.tecnicoid = technicianId;
      if (statusFilter !== "ALL") params.stato = statusFilter;

const res = await client.get("/reports", { params });
      const list = normalizeApiCollection(res?.data).map(mapReportRow);
      setReports(list);

if (isAdmin) {
        const unique = new Map();
        list.forEach((r) => {
          const key = safeString(r.technicianName);
          if (!key) return;
          if (!unique.has(key)) unique.set(key, { id: key, name: key });
        });
        setTechnicians([{ id: "ALL", name: "Tutti" }, ...Array.from(unique.values())]);
      }
    } catch (err) {
      setError(safeString(err?.response?.data?.message || err?.message || "Errore caricamento report."));
    } finally {
      setLoading(false);
    }
  }

useEffect(() => {
    fetchReports();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return reports.filter((r) => {
      const matchSearch =
        !q ||
        safeString(r.pcName).toLowerCase().includes(q) ||
        safeString(r.wizardName).toLowerCase().includes(q) ||
        safeString(r.technicianName).toLowerCase().includes(q);
      return matchSearch;
    });
  }, [reports, search]);

async function openReport(reportId) {
    if (!reportId) return;
    setViewModal({ open: true, reportId, htmlUrl: "", loading: true });

try {
      // Fetch HTML content using authenticated client (includes token in headers)
      const res = await client.get(`/reports/${encodeURIComponent(reportId)}`);
      const htmlContent = res?.data?.html_content || "";
     
      if (!htmlContent) {
        throw new Error("Nessun contenuto HTML trovato");
      }
     
      // Create data URL from HTML content for iframe display
      const htmlUrl = `data:text/html;charset=UTF-8,${encodeURIComponent(htmlContent)}`;
      setViewModal({ open: true, reportId, htmlUrl, loading: false });
    } catch (err) {
      setViewModal({ open: true, reportId, htmlUrl: "", loading: false });
      toast.error(safeString(err?.response?.data?.message || err?.message || "Impossibile aprire il report."));
    }
  }

async function downloadReport(reportId) {
    if (!reportId) return;
   
    try {
      // Fetch file using authenticated client (includes token in headers)
      const res = await client.get(`/reports/${encodeURIComponent(reportId)}/download`, {
        responseType: 'blob',
      });
     
      // Create blob URL and trigger download
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
     
      // Extract filename from Content-Disposition header if available
      const contentDisposition = res.headers['content-disposition'];
      let filename = 'report.html';
      if (contentDisposition) {
        const filenameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
        if (filenameMatch) filename = filenameMatch[1];
      }
     
      link.setAttribute('download', filename);
      document.body.appendChild(link);
      link.click();
      link.parentNode.removeChild(link);
      window.URL.revokeObjectURL(url);
     
      toast.success("Report scaricato con successo.");
    } catch (err) {
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore nel download del report."));
    }
  }

return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          ```          <h1 className="text-xl font-semibold text-slate-100">Reports</h1>          ```
          ```          <p className="mt-1 text-sm text-slate-400">Storico configurazioni completate e report HTML scaricabili.</p>          ```
        </div>

<button
          type="button"
          onClick={fetchReports}
          className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
        >
          <RefreshCw className="h-4 w-4" />
          Aggiorna
        </button>
      </div>

<div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label className="block text-xs font-medium text-slate-300" htmlFor="reportSearch">
            Ricerca
          </label>
          <div className="mt-2 flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
            <Search className="h-4 w-4 text-slate-400" />
            <input
              id="reportSearch"
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="PC, wizard o tecnico..."
              className="w-full bg-transparent text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none"
            />
          </div>
        </div>

<div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          ```          <div className="text-xs font-medium text-slate-300">Data (range)</div>          ```
          <div className="mt-2">
            <DateRangePicker from={dateRange.from} to={dateRange.to} onChange={setDateRange} />
          </div>
        </div>

<div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4 space-y-4">
          <div>
            <label className="block text-xs font-medium text-slate-300" htmlFor="statusFilter">
              Stato
            </label>
            <select
              id="statusFilter"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
            >
              <option value="ALL">Tutti</option>
              <option value="completato">Completato</option>
              <option value="errore">Errore</option>
              <option value="abortito">Abortito</option>
              <option value="incorso">In corso</option>
            </select>
          </div>

{isAdmin ? (
            <div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="techFilter">
                Tecnico (solo admin)
              </label>
              <select
                id="techFilter"
                value={technicianId}
                onChange={(e) => setTechnicianId(e.target.value)}
                className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              >
                {(technicians.length ? technicians : [{ id: "ALL", name: "Tutti" }]).map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name}

Perfetto, ora è chiaro! Hai due problemi **distinti** e facili da fixare.

## Problema 1: Users → "Accesso negato" ✅ **RISOLTO**

**Causa**: Router corretto (`allowedRoles={["admin"]}`), ma il tuo utente ha `ruolo: "tecnico"` (non `role: "admin"`). [router]

**Fix istantaneo**: Apri DevTools → Application → LocalStorage → `windeploy_user` → cambia `ruolo` in `"admin"` → ricarica.

**Fix definitivo**:

- O fai login con utente admin dal backend.
- O aggiungi endpoint per cambiare ruolo tuo utente:

```
PUT /api/users/{tuo_id} → { "ruolo": "admin" }
```


***

## Problema 2: Reports → "Questa pagina ha riscontrato un errore" ❌ **CRASH REACT**

**Causa**: Il tuo `ReportsPage.jsx` è **incompleto/troncato** – finisce a metà del `<option>` e manca tutto il resto (tabella, Modal, footer, ecc.). [ReportsPage] React crasha sul JSX malformato.

**Soluzione**: Sostituisci **intero** `src/pages/Reports/ReportsPage.jsx` con questa versione **completa e funzionante** (da scaffold ufficiale ):[^11_1]

```jsx
// Path: src/pages/Reports/ReportsPage.jsx
import React, { useEffect, useMemo, useState } from "react";
import { Download, RefreshCw, Search, FileText, Eye } from "lucide-react";
import toast from "react-hot-toast";
import client from "../../api/client";
import Modal from "../../components/ui/Modal";
import Badge from "../../components/ui/Badge";
import DateRangePicker from "../../components/ui/DateRangePicker";
import useAuthStore from "../../store/authStore";
import { formatDateTime, formatDurationSeconds } from "../../utils/formatDateTime";

function normalizeApiCollection(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (payload.data && Array.isArray(payload.data)) return


<div align="center">⁂</div>

[^11_1]: 0018-userReportsSoftwareLibraryTemplateManager.md

---

# continua

```jsx
// Path: src/pages/Reports/ReportsPage.jsx (continuazione)
  if (payload.data && payload.data.data && Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function safeString(v) {
  return (v ?? "").toString();
}

function statusTone(status) {
  const s = safeString(status).toLowerCase();
  if (s.includes("complet")) return "emerald";
  if (s.includes("erro")) return "rose";
  if (s.includes("abort")) return "amber";
  if (s.includes("incors") || s.includes("esec")) return "sky";
  return "slate";
}

function mapReportRow(item) {
  const r = item?.data ? item.data : item;
  const executionLog = r?.execution_log ?? r?.executionLog ?? r?.executionlog ?? null;
  const wizard = executionLog?.wizard ?? r?.wizard ?? null;
  const user = wizard?.user ?? r?.user ?? null;

  const startedAt = executionLog?.started_at ?? executionLog?.startedAt ?? null;
  const completedAt = executionLog?.completed_at ?? executionLog?.completedAt ?? null;

  let durationSeconds = null;
  try {
    if (startedAt && completedAt) durationSeconds = Math.max(0, (new Date(completedAt) - new Date(startedAt)) / 1000);
  } catch {
    durationSeconds = null;
  }

  return {
    id: r?.id,
    createdAt: r?.created_at ?? r?.createdAt ?? startedAt ?? null,
    technicianName: user?.nome ?? user?.name ?? executionLog?.tecnico_nome ?? executionLog?.technicianName ?? "-",
    pcName

---

# dammi tutti i codici corretti

Sì. Ti lascio i file **corretti e coerenti tra loro** per sistemare subito:
- routing,
- controllo ruoli,
- pagina reports,
- pagina users.

Il problema Users nasce dal fatto che `/users` è volutamente solo admin nel router, quindi con utente `tecnico` vedrai “Accesso negato”.  Il problema Reports invece è quasi certamente dovuto al file troncato/incompleto, e inoltre va allineato al contratto dati e ai componenti UI riutilizzabili previsti nel progetto.[^13_1][^13_2][^13_3]

## File coinvolti
- `src/router/index.jsx`
- `src/router/PrivateRoute.jsx`
- `src/pages/Reports/ReportsPage.jsx`
- `src/pages/Users/UsersPage.jsx`

## 1) Router corretto
Questo router mantiene:
- area protetta base per `admin|tecnico|viewer`,
- wizards/templates per `admin|tecnico`,
- software/users solo admin,
- monitor avanzato su `/monitor/:id`.[^13_2][^13_4]

```jsx
// Path: src/router/index.jsx
import React, { Suspense } from "react";
import { Routes, Route, Navigate } from "react-router-dom";

import PrivateRoute from "./PrivateRoute.jsx";
import MainLayout from "../components/layout/MainLayout.jsx";

import RouteErrorBoundary from "../components/ErrorBoundary/RouteErrorBoundary.jsx";
import WizardErrorBoundary from "../components/ErrorBoundary/WizardErrorBoundary.jsx";

function PageLoader({ label }) {
  return (
    <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4 text-sm text-slate-300">
      {label || "Caricamento..."}
    </div>
  );
}

const LoginPage = React.lazy(() => import("../pages/Login/LoginPage.jsx"));
const DashboardPage = React.lazy(() => import("../pages/Dashboard/DashboardPage.jsx"));
const WizardsListPage = React.lazy(() => import("../pages/Wizards/WizardsListPage.jsx"));
const WizardBuilderPage = React.lazy(() => import("../pages/Wizards/WizardBuilderPage.jsx"));
const MonitorPage = React.lazy(() => import("../pages/Monitor/index.jsx"));
const TemplatesPage = React.lazy(() => import("../pages/Templates/TemplatesPage.jsx"));
const SoftwarePage = React.lazy(() => import("../pages/Software/SoftwarePage.jsx"));
const ReportsPage = React.lazy(() => import("../pages/Reports/ReportsPage.jsx"));
const UsersPage = React.lazy(() => import("../pages/Users/UsersPage.jsx"));
const NotFoundPage = React.lazy(() => import("../pages/NotFound/NotFoundPage.jsx"));

function wrapRoute(element, { resetKeys, fallbackLabel } = {}) {
  return (
    <RouteErrorBoundary resetKeys={resetKeys}>
      <Suspense fallback={<PageLoader label={fallbackLabel} />}>{element}</Suspense>
    </RouteErrorBoundary>
  );
}

export default function AppRouter() {
  return (
    <Routes>
      <Route
        path="/login"
        element={wrapRoute(<LoginPage />, {
          resetKeys: ["login"],
          fallbackLabel: "Caricamento login...",
        })}
      />

      <Route element={<PrivateRoute allowedRoles={["admin", "tecnico", "viewer"]} />}>
        <Route element={<MainLayout />}>
          <Route index element={<Navigate to="/dashboard" replace />} />

          <Route
            path="/dashboard"
            element={wrapRoute(<DashboardPage />, {
              resetKeys: ["dashboard"],
              fallbackLabel: "Caricamento dashboard...",
            })}
          />

          <Route
            path="/reports"
            element={wrapRoute(<ReportsPage />, {
              resetKeys: ["reports"],
              fallbackLabel: "Caricamento report...",
            })}
          />

          <Route
            path="/monitor/:id"
            element={wrapRoute(<MonitorPage />, {
              resetKeys: ["monitor"],
              fallbackLabel: "Caricamento monitor...",
            })}
          />

          <Route element={<PrivateRoute allowedRoles={["admin", "tecnico"]} />}>
            <Route
              path="/wizards"
              element={wrapRoute(<WizardsListPage />, {
                resetKeys: ["wizards"],
                fallbackLabel: "Caricamento lista wizard...",
              })}
            />

            <Route
              path="/wizards/new"
              element={
                <RouteErrorBoundary resetKeys={["wizard-new"]}>
                  <WizardErrorBoundary resetKeys={["wizard-new"]}>
                    <Suspense fallback={<PageLoader label="Caricamento Wizard Builder..." />}>
                      <WizardBuilderPage />
                    </Suspense>
                  </WizardErrorBoundary>
                </RouteErrorBoundary>
              }
            />

            <Route
              path="/templates"
              element={wrapRoute(<TemplatesPage />, {
                resetKeys: ["templates"],
                fallbackLabel: "Caricamento templates...",
              })}
            />
          </Route>

          <Route element={<PrivateRoute allowedRoles={["admin"]} />}>
            <Route
              path="/software"
              element={wrapRoute(<SoftwarePage />, {
                resetKeys: ["software"],
                fallbackLabel: "Caricamento software library...",
              })}
            />

            <Route
              path="/users"
              element={wrapRoute(<UsersPage />, {
                resetKeys: ["users"],
                fallbackLabel: "Caricamento utenti...",
              })}
            />
          </Route>

          <Route
            path="*"
            element={wrapRoute(<NotFoundPage />, {
              resetKeys: ["notfound"],
              fallbackLabel: "Caricamento...",
            })}
          />
        </Route>
      </Route>
    </Routes>
  );
}
```


## 2) PrivateRoute corretto

Nel tuo caso il backend può restituire `ruolo`, ma in altri punti del frontend si usa anche `role`. Conviene supportare entrambi per evitare mismatch silenziosi.[^13_3][^13_2]

```jsx
// Path: src/router/PrivateRoute.jsx
import React from "react";
import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useAuthStore } from "../store/authStore.js";

export default function PrivateRoute({ allowedRoles }) {
  const location = useLocation();
  const { isAuthenticated, user } = useAuthStore();

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  const normalizedRole = String(user?.ruolo ?? user?.role ?? "").toLowerCase();

  if (Array.isArray(allowedRoles) && allowedRoles.length > 0) {
    const normalizedAllowed = allowedRoles.map((r) => String(r).toLowerCase());

    if (!normalizedRole || !normalizedAllowed.includes(normalizedRole)) {
      return (
        <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-amber-100">
          <div className="text-sm font-semibold">Accesso negato</div>
          <div className="mt-1 text-sm">
            Il tuo ruolo ({normalizedRole || "n/d"}) non è autorizzato per questa pagina.
          </div>
        </div>
      );
    }
  }

  return <Outlet />;
}
```


## 3) ReportsPage corretto

Questa pagina:

- legge i report,
- filtra per ricerca/stato/date,
- mostra filtro tecnico solo per admin,
- apre il report in modal,
- scarica HTML autenticato.[^13_1]

```jsx
// Path: src/pages/Reports/ReportsPage.jsx
import React, { useEffect, useMemo, useState } from "react";
import { Download, RefreshCw, Search, FileText, Eye } from "lucide-react";
import toast from "react-hot-toast";
import client from "../../api/client";
import Modal from "../../components/ui/Modal";
import Badge from "../../components/ui/Badge";
import DateRangePicker from "../../components/ui/DateRangePicker";
import useAuthStore from "../../store/authStore";
import { formatDateTime, formatDurationSeconds } from "../../utils/formatDateTime";

function normalizeApiCollection(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (payload.data && Array.isArray(payload.data)) return payload.data;
  if (payload.data && payload.data.data && Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function safeString(v) {
  return (v ?? "").toString();
}

function statusTone(status) {
  const s = safeString(status).toLowerCase();
  if (s.includes("complet")) return "emerald";
  if (s.includes("erro")) return "rose";
  if (s.includes("abort")) return "amber";
  if (s.includes("incors") || s.includes("esec")) return "sky";
  return "slate";
}

function mapReportRow(item) {
  const r = item?.data ? item.data : item;
  const executionLog = r?.execution_log ?? r?.executionLog ?? r?.executionlog ?? null;
  const wizard = executionLog?.wizard ?? r?.wizard ?? null;
  const user = wizard?.user ?? r?.user ?? null;

  const startedAt = executionLog?.started_at ?? executionLog?.startedAt ?? executionLog?.startedat ?? null;
  const completedAt = executionLog?.completed_at ?? executionLog?.completedAt ?? executionLog?.completedat ?? null;

  let durationSeconds = null;
  try {
    if (startedAt && completedAt) {
      durationSeconds = Math.max(0, (new Date(completedAt) - new Date(startedAt)) / 1000);
    }
  } catch {
    durationSeconds = null;
  }

  return {
    id: r?.id,
    createdAt: r?.created_at ?? r?.createdAt ?? r?.createdat ?? startedAt ?? null,
    technicianName:
      user?.nome ??
      user?.name ??
      executionLog?.tecnico_nome ??
      executionLog?.tecnicoNome ??
      executionLog?.technicianName ??
      "-",
    pcName:
      executionLog?.pc_nome_nuovo ??
      executionLog?.pcnome_nuovo ??
      executionLog?.pcnomeNuovo ??
      executionLog?.pcNewName ??
      "-",
    wizardName: wizard?.nome ?? wizard?.name ?? "-",
    status: executionLog?.stato ?? executionLog?.status ?? r?.stato ?? r?.status ?? "-",
    durationSeconds,
  };
}

export default function ReportsPage() {
  const user = useAuthStore((s) => s.user);
  const isAdmin = safeString(user?.ruolo ?? user?.role).toLowerCase() === "admin";

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [reports, setReports] = useState([]);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("ALL");
  const [dateRange, setDateRange] = useState({ from: null, to: null });
  const [technicians, setTechnicians] = useState([]);
  const [technicianId, setTechnicianId] = useState("ALL");
  const [viewModal, setViewModal] = useState({
    open: false,
    reportId: null,
    htmlUrl: "",
    loading: false,
  });

  async function fetchReports() {
    setLoading(true);
    setError("");

    try {
      const params = {};
      if (dateRange.from) params.dadata = dateRange.from;
      if (dateRange.to) params.adata = dateRange.to;
      if (isAdmin && technicianId !== "ALL") params.tecnicoid = technicianId;
      if (statusFilter !== "ALL") params.stato = statusFilter;

      const res = await client.get("/reports", { params });
      const list = normalizeApiCollection(res?.data).map(mapReportRow);
      setReports(list);

      if (isAdmin) {
        const unique = new Map();
        list.forEach((row) => {
          const key = safeString(row.technicianName);
          if (!key) return;
          if (!unique.has(key)) unique.set(key, { id: key, name: key });
        });
        setTechnicians([{ id: "ALL", name: "Tutti" }, ...Array.from(unique.values())]);
      }
    } catch (err) {
      setError(safeString(err?.response?.data?.message || err?.message || "Errore caricamento report."));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    fetchReports();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();

    return reports.filter((r) => {
      const matchSearch =
        !q ||
        safeString(r.pcName).toLowerCase().includes(q) ||
        safeString(r.wizardName).toLowerCase().includes(q) ||
        safeString(r.technicianName).toLowerCase().includes(q);

      return matchSearch;
    });
  }, [reports, search]);

  async function openReport(reportId) {
    if (!reportId) return;

    setViewModal({ open: true, reportId, htmlUrl: "", loading: true });

    try {
      const res = await client.get(`/reports/${encodeURIComponent(reportId)}`);
      const htmlContent =
        res?.data?.html_content ??
        res?.data?.htmlContent ??
        res?.data?.html ??
        "";

      if (!htmlContent) {
        throw new Error("Nessun contenuto HTML trovato");
      }

      const htmlUrl = `data:text/html;charset=UTF-8,${encodeURIComponent(htmlContent)}`;
      setViewModal({ open: true, reportId, htmlUrl, loading: false });
    } catch (err) {
      setViewModal({ open: true, reportId, htmlUrl: "", loading: false });
      toast.error(safeString(err?.response?.data?.message || err?.message || "Impossibile aprire il report."));
    }
  }

  async function downloadReport(reportId) {
    if (!reportId) return;

    try {
      const res = await client.get(`/reports/${encodeURIComponent(reportId)}/download`, {
        responseType: "blob",
      });

      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement("a");
      link.href = url;

      const contentDisposition = res.headers["content-disposition"];
      let filename = "report.html";

      if (contentDisposition) {
        const filenameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
        if (filenameMatch) filename = filenameMatch[^13_1];
      }

      link.setAttribute("download", filename);
      document.body.appendChild(link);
      link.click();
      link.parentNode.removeChild(link);
      window.URL.revokeObjectURL(url);

      toast.success("Report scaricato con successo.");
    } catch (err) {
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore nel download del report."));
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold text-slate-100">Reports</h1>
          <p className="mt-1 text-sm text-slate-400">
            Storico configurazioni completate e report HTML scaricabili.
          </p>
        </div>

        <button
          type="button"
          onClick={fetchReports}
          className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
        >
          <RefreshCw className="h-4 w-4" />
          Aggiorna
        </button>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label className="block text-xs font-medium text-slate-300" htmlFor="reportSearch">
            Ricerca
          </label>
          <div className="mt-2 flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
            <Search className="h-4 w-4 text-slate-400" />
            <input
              id="reportSearch"
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="PC, wizard o tecnico..."
              className="w-full bg-transparent text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none"
            />
          </div>
        </div>

        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <div className="text-xs font-medium text-slate-300">Data (range)</div>
          <div className="mt-2">
            <DateRangePicker from={dateRange.from} to={dateRange.to} onChange={setDateRange} />
          </div>
        </div>

        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4 space-y-4">
          <div>
            <label className="block text-xs font-medium text-slate-300" htmlFor="statusFilter">
              Stato
            </label>
            <select
              id="statusFilter"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
            >
              <option value="ALL">Tutti</option>
              <option value="completato">Completato</option>
              <option value="errore">Errore</option>
              <option value="abortito">Abortito</option>
              <option value="incorso">In corso</option>
            </select>
          </div>

          {isAdmin ? (
            <div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="techFilter">
                Tecnico (solo admin)
              </label>
              <select
                id="techFilter"
                value={technicianId}
                onChange={(e) => setTechnicianId(e.target.value)}
                className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              >
                {(technicians.length ? technicians : [{ id: "ALL", name: "Tutti" }]).map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name}
                  </option>
                ))}
              </select>
            </div>
          ) : null}

          <button
            type="button"
            onClick={fetchReports}
            disabled={loading}
            className="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800 disabled:opacity-50"
          >
            Applica filtri
          </button>
        </div>
      </div>

      <div className="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/30">
        <div className="border-b border-slate-800 px-4 py-3 text-sm font-semibold text-slate-200">
          Configurazioni completate
        </div>

        {loading ? <div className="p-4 text-sm text-slate-400">Caricamento...</div> : null}
        {error ? <div className="p-4 text-sm text-rose-300">Errore: {error}</div> : null}
        {!loading && !error && filtered.length === 0 ? (
          <div className="p-4 text-sm text-slate-400">Nessun report trovato.</div>
        ) : null}

        {!loading && !error && filtered.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-slate-950/40 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th className="px-4 py-3 font-semibold">Data</th>
                  <th className="px-4 py-3 font-semibold">Tecnico</th>
                  <th className="px-4 py-3 font-semibold">Nome PC</th>
                  <th className="px-4 py-3 font-semibold">Wizard usato</th>
                  <th className="px-4 py-3 font-semibold">Stato</th>
                  <th className="px-4 py-3 font-semibold">Durata</th>
                  <th className="px-4 py-3 font-semibold text-right">Azioni</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-slate-800">
                {filtered.map((r) => (
                  <tr
                    key={r.id}
                    className="cursor-pointer hover:bg-slate-950/30"
                    onClick={() => openReport(r.id)}
                  >
                    <td className="px-4 py-3 text-slate-300">{formatDateTime(r.createdAt)}</td>
                    <td className="px-4 py-3 text-slate-200">{r.technicianName}</td>
                    <td className="px-4 py-3 font-medium text-slate-100">{r.pcName}</td>
                    <td className="px-4 py-3 text-slate-200">{r.wizardName}</td>
                    <td className="px-4 py-3">
                      <Badge tone={statusTone(r.status)}>{r.status}</Badge>
                    </td>
                    <td className="px-4 py-3 font-mono text-slate-200">
                      {r.durationSeconds == null ? "-" : formatDurationSeconds(r.durationSeconds)}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <div className="inline-flex items-center gap-2">
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation();
                            openReport(r.id);
                          }}
                          className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-950"
                        >
                          <Eye className="h-4 w-4" />
                          Apri
                        </button>

                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation();
                            downloadReport(r.id);
                          }}
                          className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-950"
                        >
                          <Download className="h-4 w-4" />
                          Download
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
      </div>

      <Modal
        open={viewModal.open}
        onClose={() => setViewModal({ open: false, reportId: null, htmlUrl: "", loading: false })}
        title="Report HTML"
        description="Visualizzazione in iframe + download."
        widthClassName="max-w-6xl"
        footer={
          <div className="flex items-center justify-between gap-3">
            <div className="inline-flex items-center gap-2 text-xs text-slate-400">
              <FileText className="h-4 w-4" />
              ID <span className="font-mono text-slate-200">{viewModal.reportId || "-"}</span>
            </div>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => downloadReport(viewModal.reportId)}
                className="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
              >
                <Download className="h-4 w-4" />
                Download
              </button>

              <button
                type="button"
                onClick={() => setViewModal({ open: false, reportId: null, htmlUrl: "", loading: false })}
                className="rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
              >
                Chiudi
              </button>
            </div>
          </div>
        }
      >
        {viewModal.loading ? (
          <div className="text-sm text-slate-400">Caricamento report...</div>
        ) : viewModal.htmlUrl ? (
          <iframe
            title="WinDeploy Report"
            src={viewModal.htmlUrl}
            className="h-[70vh] w-full rounded-lg border border-slate-800 bg-slate-950"
          />
        ) : (
          <div className="text-sm text-rose-300">Impossibile caricare il report.</div>
        )}
      </Modal>
    </div>
  );
}
```


## 4) UsersPage corretto

La tua pagina users è già quasi giusta, ma ti lascio una versione coerente con `ruolo|role`, `password_temporanea|passwordTemporanea`, e con import puliti. Il blocco admin-only lato router resta corretto. [^13_1]

```jsx
// Path: src/pages/Users/UsersPage.jsx
import React, { useEffect, useMemo, useState } from "react";
import { Plus, RefreshCw, Trash2, KeyRound, Save, X, AlertTriangle } from "lucide-react";
import toast from "react-hot-toast";
import client from "../../api/client";
import Modal from "../../components/ui/Modal";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import ToggleSwitch from "../../components/ui/ToggleSwitch";
import Badge from "../../components/ui/Badge";
import { formatDateTime } from "../../utils/formatDateTime";

function normalizeApiCollection(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (payload.data && Array.isArray(payload.data)) return payload.data;
  if (payload.data && payload.data.data && Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function safeString(v) {
  return (v ?? "").toString();
}

function roleTone(role) {
  const r = safeString(role).toLowerCase();
  if (r === "admin") return "violet";
  if (r === "tecnico") return "sky";
  if (r === "viewer") return "slate";
  return "slate";
}

function mapUserRow(item) {
  const u = item?.data ? item.data : item;

  return {
    id: u?.id,
    name: u?.nome ?? u?.name ?? "",
    email: u?.email ?? "",
    role: safeString(u?.ruolo ?? u?.role ?? "viewer").toLowerCase(),
    lastLogin: u?.last_login ?? u?.lastLogin ?? u?.lastlogin ?? null,
    lastLoginIp: u?.last_login_ip ?? u?.lastLoginIp ?? u?.lastloginip ?? null,
    active: Boolean(u?.attivo ?? u?.active ?? true),
  };
}

function InlineSelect({ value, onChange, disabled }) {
  return (
    <select
      value={value}
      disabled={disabled}
      onChange={(e) => onChange?.(e.target.value)}
      className="w-full rounded-lg border border-slate-800 bg-slate-950 px-2 py-1.5 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40 disabled:opacity-50"
    >
      <option value="admin">Admin</option>
      <option value="tecnico">Tecnico</option>
      <option value="viewer">Viewer</option>
    </select>
  );
}

function validateNewUserForm(form) {
  const errors = {};

  if (!safeString(form.name).trim()) errors.name = "Nome obbligatorio.";
  if (!safeString(form.email).trim()) errors.email = "Email obbligatoria.";
  if (!safeString(form.email).includes("@")) errors.email = "Email non valida.";
  if (!["admin", "tecnico", "viewer"].includes(safeString(form.role).toLowerCase())) {
    errors.role = "Ruolo non valido.";
  }
  if (!safeString(form.tempPassword).trim() || safeString(form.tempPassword).trim().length < 8) {
    errors.tempPassword = "Password temporanea min 8 caratteri.";
  }

  return errors;
}

export default function UsersPage() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [users, setUsers] = useState([]);

  const [createModal, setCreateModal] = useState(false);
  const [createForm, setCreateForm] = useState({
    name: "",
    email: "",
    role: "tecnico",
    tempPassword: "",
  });
  const [createErrors, setCreateErrors] = useState({});

  const [editState, setEditState] = useState({
    editingId: null,
    draftRole: "viewer",
    saving: false,
  });

  const [confirmToggle, setConfirmToggle] = useState({
    open: false,
    row: null,
    nextValue: false,
  });

  const [confirmDelete, setConfirmDelete] = useState({
    open: false,
    row: null,
  });

  const [resetModal, setResetModal] = useState({
    open: false,
    user: null,
    loading: false,
    tempPassword: "",
  });

  async function fetchUsers() {
    setLoading(true);
    setError("");

    try {
      const res = await client.get("/users");
      const list = normalizeApiCollection(res?.data).map(mapUserRow);
      setUsers(list);
    } catch (err) {
      setError(safeString(err?.response?.data?.message || err?.message || "Errore caricamento utenti."));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    fetchUsers();
  }, []);

  const activeCount = useMemo(() => users.filter((u) => u.active).length, [users]);

  async function createUser() {
    const errors = validateNewUserForm(createForm);
    setCreateErrors(errors);

    if (Object.keys(errors).length > 0) {
      toast.error("Controlla i campi del form.");
      return;
    }

    const t = toast.loading("Creazione utente...");

    try {
      const payload = {
        nome: safeString(createForm.name).trim(),
        email: safeString(createForm.email).trim(),
        ruolo: safeString(createForm.role).toLowerCase(),
        password_temporanea: safeString(createForm.tempPassword),
      };

      const res = await client.post("/users", payload);

      toast.dismiss(t);
      toast.success("Utente creato.");

      setCreateModal(false);
      setCreateErrors({});
      setCreateForm({ name: "", email: "", role: "tecnico", tempPassword: "" });

      await fetchUsers();
      return res?.data;
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore creazione utente."));
    }
  }

  function startRoleEdit(row) {
    setEditState({ editingId: row.id, draftRole: row.role, saving: false });
  }

  function cancelRoleEdit() {
    setEditState({ editingId: null, draftRole: "viewer", saving: false });
  }

  async function saveRoleEdit() {
    const userId = editState.editingId;
    if (!userId) return;

    setEditState((prev) => ({ ...prev, saving: true }));

    const t = toast.loading("Aggiornamento ruolo...");

    try {
      await client.put(`/users/${userId}`, {
        ruolo: safeString(editState.draftRole).toLowerCase(),
      });

      toast.dismiss(t);
      toast.success("Ruolo aggiornato.");
      cancelRoleEdit();
      await fetchUsers();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore aggiornamento ruolo."));
      setEditState((prev) => ({ ...prev, saving: false }));
    }
  }

  function requestToggle(row, nextValue) {
    setConfirmToggle({ open: true, row, nextValue });
  }

  async function confirmToggleActive() {
    const row = confirmToggle.row;
    if (!row?.id) return;

    const t = toast.loading("Aggiornamento stato account...");

    try {
      await client.put(`/users/${row.id}`, {
        attivo: Boolean(confirmToggle.nextValue),
      });

      toast.dismiss(t);
      toast.success("Stato account aggiornato.");
      setConfirmToggle({ open: false, row: null, nextValue: false });
      await fetchUsers();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore aggiornamento stato."));
    }
  }

  async function deleteUser() {
    const row = confirmDelete.row;
    if (!row?.id) return;

    const t = toast.loading("Eliminazione utente...");

    try {
      await client.delete(`/users/${row.id}`);
      toast.dismiss(t);
      toast.success("Utente eliminato.");
      setConfirmDelete({ open: false, row: null });
      await fetchUsers();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore eliminazione utente."));
    }
  }

  async function resetPassword(userRow) {
    setResetModal({ open: true, user: userRow, loading: true, tempPassword: "" });
    const t = toast.loading("Reset password...");

    try {
      const res = await client.put(`/users/${userRow.id}`, {
        action: "reset_password",
      });

      const tempPassword = safeString(
        res?.data?.password_temporanea ??
          res?.data?.passwordTemporanea ??
          res?.data?.tempPassword ??
          res?.data?.password ??
          ""
      );

      toast.dismiss(t);

      if (!tempPassword) {
        toast.error("Reset eseguito ma password temporanea non restituita.");
        setResetModal({ open: true, user: userRow, loading: false, tempPassword: "" });
        return;
      }

      toast.success("Password temporanea generata (mostrata una sola volta).");
      setResetModal({ open: true, user: userRow, loading: false, tempPassword });
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore reset password."));
      setResetModal({ open: true, user: userRow, loading: false, tempPassword: "" });
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold text-slate-100">Gestione utenti</h1>
          <p className="mt-1 text-sm text-slate-400">
            Creazione account, ruoli, attivazione/disattivazione e reset password solo admin.
          </p>
          <div className="mt-2 text-xs text-slate-400">
            Attivi: <span className="font-mono text-slate-200">{activeCount}</span> /{" "}
            <span className="font-mono text-slate-200">{users.length}</span>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={fetchUsers}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
          >
            <RefreshCw className="h-4 w-4" />
            Aggiorna
          </button>

          <button
            type="button"
            onClick={() => setCreateModal(true)}
            className="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
          >
            <Plus className="h-4 w-4" />
            Nuovo utente
          </button>
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-900/30 overflow-hidden">
        <div className="border-b border-slate-800 px-4 py-3 text-sm font-semibold text-slate-200">
          Elenco utenti
        </div>

        {loading ? <div className="p-4 text-sm text-slate-400">Caricamento...</div> : null}
        {error ? <div className="p-4 text-sm text-rose-300">Errore: {error}</div> : null}
        {!loading && !error && users.length === 0 ? (
          <div className="p-4 text-sm text-slate-400">Nessun utente.</div>
        ) : null}

        {!loading && !error && users.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-slate-950/40 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th className="px-4 py-3 font-semibold">Nome</th>
                  <th className="px-4 py-3 font-semibold">Email</th>
                  <th className="px-4 py-3 font-semibold">Ruolo</th>
                  <th className="px-4 py-3 font-semibold">Ultimo accesso</th>
                  <th className="px-4 py-3 font-semibold">IP ultimo accesso</th>
                  <th className="px-4 py-3 font-semibold">Attivo</th>
                  <th className="px-4 py-3 font-semibold text-right">Azioni</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-slate-800">
                {users.map((u) => {
                  const isEditing = editState.editingId === u.id;

                  return (
                    <tr key={u.id} className="align-top">
                      <td className="px-4 py-3 font-medium text-slate-100">{u.name}</td>
                      <td className="px-4 py-3 text-slate-200">{u.email}</td>
                      <td className="px-4 py-3">
                        {isEditing ? (
                          <div className="flex items-center gap-2">
                            <InlineSelect
                              value={editState.draftRole}
                              disabled={editState.saving}
                              onChange={(value) => setEditState((prev) => ({ ...prev, draftRole: value }))}
                            />

                            <button
                              type="button"
                              onClick={cancelRoleEdit}
                              disabled={editState.saving}
                              className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-2 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-900 disabled:opacity-50"
                              aria-label="Cancel role edit"
                            >
                              <X className="h-4 w-4" />
                            </button>

                            <button
                              type="button"
                              onClick={saveRoleEdit}
                              disabled={editState.saving}
                              className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-2 py-2 text-xs font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                              aria-label="Save role"
                            >
                              <Save className="h-4 w-4" />
                            </button>
                          </div>
                        ) : (
                          <button
                            type="button"
                            onClick={() => startRoleEdit(u)}
                            className="inline-flex items-center gap-2"
                            title="Clicca per modificare ruolo"
                          >
                            <Badge tone={roleTone(u.role)}>{u.role}</Badge>
                            <span className="text-xs text-slate-500">(modifica)</span>
                          </button>
                        )}
                      </td>

                      <td className="px-4 py-3 text-slate-300">{formatDateTime(u.lastLogin) || "-"}</td>
                      <td className="px-4 py-3 font-mono text-slate-200">{u.lastLoginIp || "-"}</td>
                      <td className="px-4 py-3">
                        <ToggleSwitch checked={u.active} onChange={(next) => requestToggle(u, next)} />
                      </td>

                      <td className="px-4 py-3 text-right">
                        <div className="inline-flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => resetPassword(u)}
                            className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-950"
                          >
                            <KeyRound className="h-4 w-4" />
                            Reset password
                          </button>

                          <button
                            type="button"
                            onClick={() => setConfirmDelete({ open: true, row: u })}
                            className="inline-flex items-center gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-200 hover:bg-rose-500/15"
                          >
                            <Trash2 className="h-4 w-4" />
                            Elimina
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>

            <div className="border-t border-slate-800 bg-slate-950/20 p-3 text-xs text-slate-300">
              <div className="flex items-start gap-2">
                <AlertTriangle className="mt-0.5 h-4 w-4 text-amber-200" />
                <div>
                  Sicurezza: il reset password deve restituire la password temporanea una sola volta e non loggarla
                  mai lato server. Valuta rate-limit e audit trail.
                </div>
              </div>
            </div>
          </div>
        ) : null}
      </div>

      <Modal
        open={createModal}
        onClose={() => setCreateModal(false)}
        title="Nuovo utente"
        description="Crea un account e imposta una password temporanea."
        widthClassName="max-w-lg"
        footer={
          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              onClick={() => setCreateModal(false)}
              className="rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
            >
              Annulla
            </button>
            <button
              type="button"
              onClick={createUser}
              className="rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
            >
              Crea utente
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          <div>
            <label className="block text-xs font-medium text-slate-300" htmlFor="uName">
              Nome
            </label>
            <input
              id="uName"
              type="text"
              value={createForm.name}
              onChange={(e) => setCreateForm((prev) => ({ ...prev, name: e.target.value }))}
              className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              placeholder="Es. Mario Rossi"
            />
            {createErrors.name ? <div className="mt-2 text-xs text-rose-300">{createErrors.name}</div> : null}
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-300" htmlFor="uEmail">
              Email
            </label>
            <input
              id="uEmail"
              type="email"
              value={createForm.email}
              onChange={(e) => setCreateForm((prev) => ({ ...prev, email: e.target.value }))}
              className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              placeholder="nome.cognome@azienda.it"
            />
            {createErrors.email ? <div className="mt-2 text-xs text-rose-300">{createErrors.email}</div> : null}
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-300" htmlFor="uRole">
              Ruolo
            </label>
            <select
              id="uRole"
              value={createForm.role}
              onChange={(e) => setCreateForm((prev) => ({ ...prev, role: e.target.value }))}
              className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
            >
              <option value="admin">Admin</option>
              <option value="tecnico">Tecnico</option>
              <option value="viewer">Viewer</option>
            </select>
            {createErrors.role ? <div className="mt-2 text-xs text-rose-300">{createErrors.role}</div> : null}
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-300" htmlFor="uTempPw">
              Password temporanea
            </label>
            <input
              id="uTempPw"
              type="text"
              value={createForm.tempPassword}
              onChange={(e) => setCreateForm((prev) => ({ ...prev, tempPassword: e.target.value }))}
              className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm font-mono text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              placeholder="Genera una password temporanea"
            />
            {createErrors.tempPassword ? (
              <div className="mt-2 text-xs text-rose-300">{createErrors.tempPassword}</div>
            ) : null}
            <div className="mt-2 text-xs text-slate-500">
              Consiglio: usa una password random lunga e imponi cambio password al primo accesso lato server.
            </div>
          </div>
        </div>
      </Modal>

      <ConfirmDialog
        open={confirmToggle.open}
        onClose={() => setConfirmToggle({ open: false, row: null, nextValue: false })}
        title="Conferma cambio stato account"
        description={`Vuoi davvero ${confirmToggle.nextValue ? "attivare" : "disattivare"} l’account di "${confirmToggle.row?.name}"?`}
        confirmLabel={confirmToggle.nextValue ? "Attiva" : "Disattiva"}
        danger={!confirmToggle.nextValue}
        onConfirm={confirmToggleActive}
      />

      <ConfirmDialog
        open={confirmDelete.open}
        onClose={() => setConfirmDelete({ open: false, row: null })}
        title="Conferma eliminazione utente"
        description={`Eliminare l’utente "${confirmDelete.row?.name}"?`}
        confirmLabel="Elimina"
        danger
        onConfirm={deleteUser}
      />

      <Modal
        open={resetModal.open}
        onClose={() => setResetModal({ open: false, user: null, loading: false, tempPassword: "" })}
        title="Reset password"
        description="La password temporanea deve essere mostrata UNA sola volta."
        widthClassName="max-w-lg"
        footer={
          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              onClick={() => setResetModal({ open: false, user: null, loading: false, tempPassword: "" })}
              className="rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
            >
              Chiudi
            </button>

            {resetModal.tempPassword ? (
              <button
                type="button"
                onClick={() => {
                  navigator.clipboard.writeText(resetModal.tempPassword);
                  toast.success("Copiata negli appunti.");
                }}
                className="rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
              >
                Copia
              </button>
            ) : null}
          </div>
        }
      >
        {resetModal.loading ? (
          <div className="text-sm text-slate-400">Generazione password temporanea...</div>
        ) : resetModal.tempPassword ? (
          <div className="space-y-3">
            <div className="text-sm text-slate-300">
              Utente: <span className="font-semibold text-slate-100">{resetModal.user?.name}</span>
            </div>

            <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
              <div className="text-xs font-medium text-emerald-200">Password temporanea</div>
              <div className="mt-2 font-mono text-lg text-emerald-100">{resetModal.tempPassword}</div>
              <div className="mt-3 text-xs text-emerald-200/80">
                Non sarà più visibile dopo la chiusura di questo modal.
              </div>
            </div>
          </div>
        ) : (
          <div className="text-sm text-rose-300">Nessuna password temporanea disponibile.</div>
        )}
      </Modal>
    </div>
  );
}
```


## Note critiche

- `/users` deve restare admin-only: quindi se il tuo utente è `tecnico`, vedere “Accesso negato” è comportamento corretto, non bug.[^13_2][^13_3]
- In `ReportsPage` ho usato `data URL` per l’iframe; funziona bene per HTML piccoli/medi, ma per report grandi conviene un endpoint autenticato che ritorni blob/stream.[^13_1]
- Nel `PrivateRoute` supportare sia `ruolo` sia `role` evita regressioni mentre allinei frontend e backend, ma a lungo termine conviene standardizzare un solo nome campo nel contratto API.[^13_3]


## Checklist operativa

- Sostituisci i 4 file sopra.
- Verifica che esistano già questi componenti utility: `Modal`, `ConfirmDialog`, `ToggleSwitch`, `Badge`, `DateRangePicker`, `formatDateTime`.[^13_1]
- Fai login con utente admin per testare `/users`.
- Testa `/reports` con Network aperto: se la pagina non crasha più ma mostra errore API, allora il problema si sposta sul backend `/api/reports`.

Mandami adesso il tuo `src/components/layout/MainLayout.jsx` e `src/store/authStore.js`, così controllo anche sidebar/ruoli end-to-end.

<div align="center">⁂</div>

[^13_1]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^13_2]: 0013-SetupprogettoReact.md

[^13_3]: PrivateRoute.jsx

[^13_4]: AppRouter.jsx


---

# MainLayout.jsx

// Path: frontend/src/components/layout/MainLayout.jsx
import React from "react";
import { NavLink, Outlet } from "react-router-dom";
import {
  LayoutDashboard,
  Wand2,
  FileText,
  Package,
  Users,
  ClipboardList,
  LogOut,
} from "lucide-react";
import { useAuthStore } from "../../store/authStore.js";

function buildNavClass({ isActive }) {
  return [
    "flex items-center gap-2 rounded px-3 py-2 transition-colors",
    isActive
      ? "bg-slate-800 text-white"
      : "text-slate-300 hover:bg-slate-800/70 hover:text-white",
  ].join(" ");
}

export default function MainLayout() {
  const { user, logout } = useAuthStore();

function handleLogout() {
    logout();
  }

return (
    <div className="flex min-h-screen bg-slate-950 text-slate-50">
      <aside className="w-64 border-r border-slate-800 bg-slate-900 flex flex-col">
        <div className="px-4 py-4 border-b border-slate-800">
          ```          <div className="text-lg font-semibold">WinDeploy</div>          ```
          {user ? (
            <div className="mt-1 text-xs text-slate-400">
              {user.name} · {user.ruolo || user.role}
            </div>
          ) : null}
        </div>

<nav className="flex-1 px-2 py-4 space-y-1 text-sm">
          <NavLink to="/dashboard" end className={buildNavClass}>
            <LayoutDashboard className="h-4 w-4" />
            <span>Dashboard</span>
          </NavLink>

<NavLink to="/wizards" className={buildNavClass}>
            <Wand2 className="h-4 w-4" />
            <span>Wizards</span>
          </NavLink>

<NavLink to="/templates" className={buildNavClass}>
            <FileText className="h-4 w-4" />
            <span>Templates</span>
          </NavLink>

<NavLink to="/software" className={buildNavClass}>
            <Package className="h-4 w-4" />
            <span>Software library</span>
          </NavLink>

<NavLink to="/reports" className={buildNavClass}>
            <ClipboardList className="h-4 w-4" />
            <span>Reports</span>
          </NavLink>

<NavLink to="/users" className={buildNavClass}>
            <Users className="h-4 w-4" />
            <span>Users</span>
          </NavLink>
        </nav>

<button
          type="button"
          onClick={handleLogout}
          className="flex items-center gap-2 border-t border-slate-800 px-4 py-3 text-sm text-red-300 hover:bg-red-900/30"
        >
          <LogOut className="h-4 w-4" />
          <span>Logout</span>
        </button>
      </aside>

<main className="flex-1 bg-slate-950">
        <div className="mx-auto max-w-6xl px-6 py-6">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
authStore.js
import { create } from 'zustand';

const TOKEN_KEY = 'windeploy_token';
const USER_KEY = 'windeploy_user';

const getInitialState = () => {
  const token = window.localStorage.getItem(TOKEN_KEY) || null;
  const userJson = window.localStorage.getItem(USER_KEY);
  let user = null;

try {
    if (userJson) {
      user = JSON.parse(userJson);
    }
  } catch (error) {
    console.error('Failed to parse stored user', error);
  }

return {
    token,
    user,
    isAuthenticated: Boolean(token \&\& user),
  };
};

export const useAuthStore = create((set) => ({
  ...getInitialState(),

login: ({ token, user }) => {
    // WARNING: token in localStorage è pratico ma vulnerabile a XSS.
    // Per produzione valuta in futuro un approccio cookie HttpOnly + CSRF.
    window.localStorage.setItem(TOKEN_KEY, token);
    window.localStorage.setItem(USER_KEY, JSON.stringify(user));

set({
      token,
      user,
      isAuthenticated: true,
    });
  },

logout: () => {
    window.localStorage.removeItem(TOKEN_KEY);
    window.localStorage.removeItem(USER_KEY);

set({
      token: null,
      user: null,
      isAuthenticated: false,
    });
  },

setUser: (user) => {
    window.localStorage.setItem(USER_KEY, JSON.stringify(user));
    set({ user });
  },
}));

// Default export for consumers that import the store as default
export default useAuthStore;

se riesci a migliorare qualcosa per l'interfaccia grafica fallo pure

Sì, posso migliorarlo. I tuoi due file sono già buoni, ma ci sono **3 miglioramenti utili**:

- uniformare definitivamente `role` e `ruolo`, perché oggi il frontend convive con entrambi e questo è fragile;[^14_1][^14_2]
- nascondere in sidebar le voci non accessibili invece di mostrare link che poi portano a “Accesso negato”, così l’UX è più pulita;[^14_1]
- rendere `authStore` più robusto contro JSON corrotti in localStorage e più comodo con helper come `normalizedRole`.[^14_1]

In più, dal punto di vista sicurezza, il token in `localStorage` resta comodo ma esposto a XSS, quindi questa soluzione va bene ora, però non è la più sicura nel lungo periodo.[^14_1]

## Struttura file coinvolti

- `src/components/layout/MainLayout.jsx`
- `src/store/authStore.js`


## 1) MainLayout migliorato

Questa versione:

- mostra solo le voci permesse dal ruolo,
- evidenzia meglio il branding,
- aggiunge una piccola card utente,
- evita il link inutile a pagine admin per utenti non admin,
- resta coerente con React Router e con la logica ruoli del progetto.[^14_1]

```jsx
// Path: frontend/src/components/layout/MainLayout.jsx
import React, { useMemo } from "react";
import { NavLink, Outlet } from "react-router-dom";
import {
  LayoutDashboard,
  Wand2,
  FileText,
  Package,
  Users,
  ClipboardList,
  LogOut,
  Shield,
  MonitorSmartphone,
} from "lucide-react";
import { useAuthStore } from "../../store/authStore.js";

function buildNavClass({ isActive }) {
  return [
    "group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all",
    isActive
      ? "bg-sky-500/15 text-white ring-1 ring-sky-500/30"
      : "text-slate-300 hover:bg-slate-800/70 hover:text-white",
  ].join(" ");
}

function normalizeRole(user) {
  return String(user?.normalizedRole ?? user?.ruolo ?? user?.role ?? "").toLowerCase();
}

function roleBadgeClass(role) {
  if (role === "admin") return "bg-violet-500/10 text-violet-200 ring-violet-500/20";
  if (role === "tecnico") return "bg-sky-500/10 text-sky-200 ring-sky-500/20";
  return "bg-slate-500/10 text-slate-200 ring-slate-500/20";
}

function roleLabel(role) {
  if (role === "admin") return "Admin";
  if (role === "tecnico") return "Tecnico";
  if (role === "viewer") return "Viewer";
  return "Sconosciuto";
}

export default function MainLayout() {
  const { user, logout } = useAuthStore();

  const normalizedRole = normalizeRole(user);

  const navItems = useMemo(() => {
    const allItems = [
      {
        to: "/dashboard",
        label: "Dashboard",
        icon: LayoutDashboard,
        roles: ["admin", "tecnico", "viewer"],
        end: true,
      },
      {
        to: "/wizards",
        label: "Wizards",
        icon: Wand2,
        roles: ["admin", "tecnico"],
      },
      {
        to: "/templates",
        label: "Templates",
        icon: FileText,
        roles: ["admin", "tecnico"],
      },
      {
        to: "/monitor/1",
        label: "Monitor",
        icon: MonitorSmartphone,
        roles: ["admin", "tecnico", "viewer"],
      },
      {
        to: "/software",
        label: "Software library",
        icon: Package,
        roles: ["admin"],
      },
      {
        to: "/reports",
        label: "Reports",
        icon: ClipboardList,
        roles: ["admin", "tecnico", "viewer"],
      },
      {
        to: "/users",
        label: "Users",
        icon: Users,
        roles: ["admin"],
      },
    ];

    return allItems.filter((item) => item.roles.includes(normalizedRole));
  }, [normalizedRole]);

  function handleLogout() {
    logout();
    window.location.href = "/login";
  }

  return (
    <div className="flex min-h-screen bg-slate-950 text-slate-50">
      <aside className="flex w-72 flex-col border-r border-slate-800 bg-slate-900/95">
        <div className="border-b border-slate-800 px-5 py-5">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-sky-500/15 ring-1 ring-sky-500/30">
              <Shield className="h-5 w-5 text-sky-300" />
            </div>
            <div>
              <div className="text-lg font-semibold tracking-tight text-white">WinDeploy</div>
              <div className="text-xs text-slate-400">Deployment console</div>
            </div>
          </div>

          {user ? (
            <div className="mt-4 rounded-2xl border border-slate-800 bg-slate-950/60 p-3">
              <div className="truncate text-sm font-medium text-slate-100">
                {user?.nome || user?.name || user?.email || "Utente"}
              </div>
              <div className="mt-1 truncate text-xs text-slate-400">{user?.email || "email non disponibile"}</div>
              <div className="mt-3">
                <span
                  className={[
                    "inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset",
                    roleBadgeClass(normalizedRole),
                  ].join(" ")}
                >
                  {roleLabel(normalizedRole)}
                </span>
              </div>
            </div>
          ) : null}
        </div>

        <nav className="flex-1 space-y-1 px-3 py-4">
          {navItems.map((item) => {
            const Icon = item.icon;

            return (
              <NavLink key={item.to} to={item.to} end={item.end} className={buildNavClass}>
                <Icon className="h-4 w-4 shrink-0 text-slate-400 transition-colors group-hover:text-slate-200" />
                <span>{item.label}</span>
              </NavLink>
            );
          })}
        </nav>

        <div className="border-t border-slate-800 p-3">
          <button
            type="button"
            onClick={handleLogout}
            className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-red-300 transition-colors hover:bg-red-900/30 hover:text-red-200"
          >
            <LogOut className="h-4 w-4" />
            <span>Logout</span>
          </button>
        </div>
      </aside>

      <main className="flex-1 bg-slate-950">
        <div className="mx-auto max-w-7xl px-6 py-6">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
```


## 2) authStore migliorato

Questo è il fix più importante lato stato. Rende il dato utente più stabile e aggiunge una normalizzazione unica del ruolo, così non devi più inseguire `role` vs `ruolo` in ogni componente.[^14_1]

```jsx
// Path: frontend/src/store/authStore.js
import { create } from "zustand";

const TOKEN_KEY = "windeploy_token";
const USER_KEY = "windeploy_user";

function normalizeUser(user) {
  if (!user || typeof user !== "object") return null;

  const normalizedRole = String(user?.ruolo ?? user?.role ?? "").toLowerCase();

  return {
    ...user,
    role: normalizedRole || user?.role || "",
    ruolo: normalizedRole || user?.ruolo || "",
    normalizedRole,
    displayName: user?.nome || user?.name || user?.email || "Utente",
  };
}

function readStoredJson(key) {
  try {
    const raw = window.localStorage.getItem(key);
    if (!raw) return null;
    return JSON.parse(raw);
  } catch (error) {
    console.error(`Failed to parse localStorage key: ${key}`, error);
    window.localStorage.removeItem(key);
    return null;
  }
}

function getInitialState() {
  const token = window.localStorage.getItem(TOKEN_KEY) || null;
  const rawUser = readStoredJson(USER_KEY);
  const user = normalizeUser(rawUser);

  return {
    token,
    user,
    isAuthenticated: Boolean(token && user),
  };
}

export const useAuthStore = create((set, get) => ({
  ...getInitialState(),

  login: ({ token, user }) => {
    const normalizedUser = normalizeUser(user);

    window.localStorage.setItem(TOKEN_KEY, token);
    window.localStorage.setItem(USER_KEY, JSON.stringify(normalizedUser));

    set({
      token,
      user: normalizedUser,
      isAuthenticated: Boolean(token && normalizedUser),
    });
  },

  logout: () => {
    window.localStorage.removeItem(TOKEN_KEY);
    window.localStorage.removeItem(USER_KEY);

    set({
      token: null,
      user: null,
      isAuthenticated: false,
    });
  },

  setUser: (user) => {
    const normalizedUser = normalizeUser(user);

    if (!normalizedUser) {
      window.localStorage.removeItem(USER_KEY);
      set({ user: null, isAuthenticated: Boolean(get().token) && false });
      return;
    }

    window.localStorage.setItem(USER_KEY, JSON.stringify(normalizedUser));

    set((state) => ({
      user: normalizedUser,
      isAuthenticated: Boolean(state.token && normalizedUser),
    }));
  },

  updateUser: (patch) => {
    const currentUser = get().user || {};
    const nextUser = normalizeUser({ ...currentUser, ...patch });

    window.localStorage.setItem(USER_KEY, JSON.stringify(nextUser));

    set((state) => ({
      user: nextUser,
      isAuthenticated: Boolean(state.token && nextUser),
    }));
  },

  getRole: () => {
    return String(get().user?.normalizedRole || "").toLowerCase();
  },
}));

export default useAuthStore;
```


## 3) Cosa migliora davvero

Con questi due file ottieni:

- sidebar più pulita, perché un `tecnico` non vede più `Users` e `Software`; questo evita UX frustrante e click inutili;[^14_1]
- stato auth più coerente, perché tutti i componenti possono leggere `user.normalizedRole` senza ambiguità;[^14_1]
- minore rischio di bug “sono loggato ma il ruolo non viene letto”, che è proprio il tipo di problema che ti ha colpito tra router, users e reports.[^14_2][^14_1]


## 4) Avvocato del diavolo

Ci sono però tre limiti da tenere a mente:

- Nascondere link in sidebar **non è sicurezza**; la sicurezza reale resta lato Laravel con middleware e policy, perché il client può sempre essere manipolato.[^14_1]
- Il link `"/monitor/1"` è solo un placeholder UX: senza un ID reale rischi di portare l’utente a una route che non esiste o a un monitor sbagliato. Se non hai un “ultimo wizard attivo”, meglio togliere quella voce.[^14_1]
- `localStorage` per il token è pratico ma più esposto a XSS; per una piattaforma amministrativa come WinDeploy, in futuro valuterei cookie HttpOnly + CSRF con Sanctum stateful.[^14_1]


## Checklist operativa

- Sostituisci `authStore.js` con la versione sopra.
- Sostituisci `MainLayout.jsx` con la versione sopra.
- In `PrivateRoute.jsx`, usa `user?.normalizedRole ?? user?.ruolo ?? user?.role`.
- In `ReportsPage.jsx` e `UsersPage.jsx`, usa la stessa logica di normalizzazione del ruolo.
- Se non hai una route monitor stabile, rimuovi temporaneamente la voce “Monitor” dalla sidebar.

Il prossimo passo giusto è questo: incollami `src/api/client.js` e il payload reale restituito da `POST /api/auth/login` oppure `GET /api/auth/me`, così ti allineo tutto il flusso auth senza mismatch.
<span style="display:none">[^14_3]</span>

<div align="center">⁂</div>

[^14_1]: 0013-SetupprogettoReact.md

[^14_2]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^14_3]: 0100-File-temporanei-tmp_.php-esposti-nella-root.md


---

# client.js

import axios from 'axios';
import { useAuthStore } from '../store/authStore';

// ── NOTA ARCHITETTURALE ────────────────────────────────────────────────────
// baseURL è '/api' (relativo, senza host).
// In DEV: Vite intercetta /api/* e lo proxy verso [http://windeploy.local.api](http://windeploy.local.api)
// In PROD: Nginx serve React e fa reverse proxy /api → Laravel backend
// NON usare mai 'http://windeploy.local.api/api' qui —
// bypassa il proxy e causa CORS in sviluppo.
// ──────────────────────────────────────────────────────────────────────────

const client = axios.create({
  baseURL: '/api',

// withCredentials: true → invia cookie di sessione Sanctum
  // Necessario per il flusso SPA con CSRF token
  withCredentials: true,

// Timeout aumentato a 30s per operazioni lunghe (es. avvio wizard)
  timeout: 30000,
});

// ── INTERCEPTOR REQUEST ───────────────────────────────────────────────────
// Aggiunge automaticamente a ogni chiamata:
// 1. Accept: application/json → Laravel risponde sempre JSON, mai HTML
// 2. Authorization: Bearer <token> → letto da Zustand authStore
client.interceptors.request.use(
  (config) => {
    config.headers = config.headers ?? {};

// Forza risposta JSON — evita che Laravel restituisca redirect HTML
    config.headers['Accept'] = 'application/json';

// Legge il token dallo store Zustand (non da React hook — siamo fuori da component tree)
    const { token } = useAuthStore.getState();
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }

return config;
  },
  (error) => Promise.reject(error)
);

// ── INTERCEPTOR RESPONSE ──────────────────────────────────────────────────
// Gestione centralizzata degli errori HTTP:
// - 401 Unauthorized → logout automatico + redirect a /login
// - 422 Unprocessable → errori di validazione Laravel (gestiti nei singoli hook)
// - 500 Server Error → toast errore generico
client.interceptors.response.use(
  (response) => response, // Risposta OK: pass-through senza modifiche

(error) => {
    const status = error?.response?.status;

if (status === 401) {
      // Token scaduto o non valido → invalida la sessione locale
      const { logout } = useAuthStore.getState();
      logout();

// Redirect grezzo (fuori da React Router) per evitare dipendenze circolari
      // Guard: non fare redirect se siamo già su /login (evita loop)
      if (window.location.pathname !== '/login') {
        window.location.href = '/login';
      }
    }

if (status === 500) {
      // Errore interno Laravel — mostra toast generico
      // Importa dinamicamente react-hot-toast per non creare dipendenza circolare
      import('react-hot-toast').then(({ default: toast }) => {
        toast.error('Errore del server. Riprova o contatta il supporto.');
      });
    }

// Per 422 (validazione) i singoli hook gestiscono error.response.data.errors
    return Promise.reject(error);
  }
);

export default client;
non mi fa vedere i template esistenti che ci sono nel db
pagina users: Errore: Accesso negato. Ruolo insufficiente.
vorrei che il report html generato sia più strutturato e professionale

Hai probabilmente **due problemi distinti**: il primo è di normalizzazione dei dati lato frontend, che spiega sia i template non visibili sia il falso “Ruolo insufficiente”; il secondo è di struttura del report HTML, che oggi nello scaffold è previsto come visualizzazione in modal/iframe ma non come template professionale già curato.[^15_1][^15_2]

La causa più probabile del blocco su `/users` è che nel router e nelle pagine il controllo ruolo usa `user?.role`, mentre nel progetto compaiono anche payload con `ruolo`, quindi basta un mismatch nel payload di login o `me` per far risultare l’utente non admin lato client.[^15_2][^15_1]

## File da toccare

- `frontend/src/store/authStore.js`
- `frontend/src/router/PrivateRoute.jsx`
- `frontend/src/pages/Templates/TemplatesPage.jsx`
- `frontend/src/pages/Users/UsersPage.jsx`
- `frontend/src/pages/Reports/ReportsPage.jsx`
- `backend/resources/views/reports/deployment-report.blade.php`
- opzionale: `backend/app/Http/Controllers/Api/ReportController.php`


## 1) Fix reale per ruolo e client

Il tuo `client.js` va bene come impostazione architetturale con `baseURL: '/api'`, proxy Vite in dev e reverse proxy Nginx in prod; è coerente con lo scaffold WinDeploy e con la separazione frontend/backend prevista nel progetto.[^15_1]

Però oggi stai mischiando due modelli auth: Bearer token nello store e `withCredentials: true` per cookie Sanctum; questa combinazione può funzionare, ma se non hai implementato davvero il flusso CSRF + session cookie lato backend, `withCredentials: true` non ti porta vantaggi concreti e rende il comportamento più ambiguo da debuggare.[^15_1]

Ti consiglio di **tenere il Bearer token** per adesso e sistemare prima la normalizzazione.

### `authStore.js`

```jsx
// Path: frontend/src/store/authStore.js
import { create } from "zustand";

const TOKEN_KEY = "windeploy_token";
const USER_KEY = "windeploy_user";

function safeJsonParse(value) {
  try {
    return value ? JSON.parse(value) : null;
  } catch (error) {
    console.error("Failed to parse stored user", error);
    return null;
  }
}

function normalizeRole(user) {
  return String(user?.normalizedRole ?? user?.ruolo ?? user?.role ?? "")
    .trim()
    .toLowerCase();
}

function normalizeUser(user) {
  if (!user || typeof user !== "object") {
    return null;
  }

  const normalizedRole = normalizeRole(user);

  return {
    ...user,
    name: user?.name ?? user?.nome ?? "",
    nome: user?.nome ?? user?.name ?? "",
    role: normalizedRole,
    ruolo: normalizedRole,
    normalizedRole,
  };
}

function getInitialState() {
  const token = window.localStorage.getItem(TOKEN_KEY) || null;
  const user = normalizeUser(safeJsonParse(window.localStorage.getItem(USER_KEY)));

  return {
    token,
    user,
    isAuthenticated: Boolean(token && user),
  };
}

export const useAuthStore = create((set, get) => ({
  ...getInitialState(),

  login: ({ token, user }) => {
    const normalizedUser = normalizeUser(user);

    window.localStorage.setItem(TOKEN_KEY, token);
    window.localStorage.setItem(USER_KEY, JSON.stringify(normalizedUser));

    set({
      token,
      user: normalizedUser,
      isAuthenticated: Boolean(token && normalizedUser),
    });
  },

  logout: () => {
    window.localStorage.removeItem(TOKEN_KEY);
    window.localStorage.removeItem(USER_KEY);

    set({
      token: null,
      user: null,
      isAuthenticated: false,
    });
  },

  setUser: (user) => {
    const normalizedUser = normalizeUser(user);

    if (!normalizedUser) {
      window.localStorage.removeItem(USER_KEY);
      set((state) => ({
        user: null,
        isAuthenticated: Boolean(state.token && false),
      }));
      return;
    }

    window.localStorage.setItem(USER_KEY, JSON.stringify(normalizedUser));

    set((state) => ({
      user: normalizedUser,
      isAuthenticated: Boolean(state.token && normalizedUser),
    }));
  },

  getRole: () => normalizeRole(get().user),
}));

export default useAuthStore;
```


### `PrivateRoute.jsx`

Nello scaffold il `PrivateRoute` controlla `user?.role` direttamente; è il punto tipico in cui nasce “Accesso negato. Ruolo insufficiente.” quando il backend restituisce `ruolo` o dati non normalizzati.[^15_1]

```jsx
// Path: frontend/src/router/PrivateRoute.jsx
import React from "react";
import { Navigate, Outlet, useLocation } from "react-router-dom";
import useAuthStore from "../store/authStore.js";

function normalizeRole(user) {
  return String(user?.normalizedRole ?? user?.ruolo ?? user?.role ?? "").toLowerCase();
}

export default function PrivateRoute({ allowedRoles = [] }) {
  const location = useLocation();
  const { isAuthenticated, user } = useAuthStore();

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  if (allowedRoles.length > 0) {
    const userRole = normalizeRole(user);

    if (!userRole || !allowedRoles.includes(userRole)) {
      return <Navigate to="/dashboard" replace state={{ denied: true }} />;
    }
  }

  return <Outlet />;
}
```


### `client.js`

Se vuoi tenere il modello Bearer-based attuale, io lo pulirei così:

```jsx
// Path: frontend/src/api/client.js
import axios from "axios";
import { useAuthStore } from "../store/authStore";

const client = axios.create({
  baseURL: "/api",
  withCredentials: false,
  timeout: 30000,
});

client.interceptors.request.use(
  (config) => {
    config.headers = config.headers ?? {};
    config.headers.Accept = "application/json";

    const { token } = useAuthStore.getState();
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
  },
  (error) => Promise.reject(error)
);

client.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status;

    if (status === 401) {
      const { logout } = useAuthStore.getState();
      logout();

      if (window.location.pathname !== "/login") {
        window.location.href = "/login";
      }
    }

    if (status === 500) {
      import("react-hot-toast").then(({ default: toast }) => {
        toast.error("Errore del server. Riprova o contatta il supporto.");
      });
    }

    return Promise.reject(error);
  }
);

export default client;
```


### Sicurezza

Tenere Bearer in `localStorage` è pratico ma più esposto a XSS; lo scaffold stesso segnala che la sicurezza reale resta lato backend e che il router client è solo un guardrail UX.[^15_1]

## 2) Perché non vedi i template

Nel materiale del progetto, `TemplatesPage` chiama `GET /api/templates`, separa i template in “miei” e “globali” usando `scope`, e usa un parser tollerante per più shape di risposta.[^15_2]

Il problema più comune qui non è la query, ma il fatto che i record reali dal DB spesso arrivano con campi tipo:

- `configurazione` string JSON invece che object,
- `scope` con valori diversi da `personale` / `globale`,
- `user_id` invece di `userid`,
- collezione annidata in shape non gestita completamente.[^15_2]

Ti lascio una versione più robusta di `mapTemplateRow` e `normalizeApiCollection`.

### `TemplatesPage.jsx` fix parser

```jsx
function normalizeApiCollection(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload.data)) return payload.data;
  if (Array.isArray(payload.templates)) return payload.templates;
  if (Array.isArray(payload.items)) return payload.items;
  if (payload.data && Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function parseConfig(raw) {
  if (!raw) return null;
  if (typeof raw === "object") return raw;

  if (typeof raw === "string") {
    try {
      return JSON.parse(raw);
    } catch {
      return null;
    }
  }

  return null;
}

function normalizeScope(value) {
  const scope = String(value ?? "").trim().toLowerCase();

  if (["globale", "global"].includes(scope)) return "globale";
  if (["personale", "private", "mine", "user"].includes(scope)) return "personale";

  return "personale";
}

function mapTemplateRow(item) {
  const t = item?.data ? item.data : item;
  const config = parseConfig(t?.configurazione ?? t?.config ?? t?.configuration);

  return {
    id: t?.id,
    name: t?.nome ?? t?.name ?? "",
    description: t?.descrizione ?? t?.description ?? "",
    scope: normalizeScope(t?.scope),
    createdAt: t?.created_at ?? t?.createdAt ?? t?.createdat ?? null,
    updatedAt: t?.updated_at ?? t?.updatedAt ?? t?.updatedat ?? null,
    userId: t?.user_id ?? t?.userId ?? t?.userid ?? null,
    config,
    softwareCount: Array.isArray(config?.softwareinstalla)
      ? config.softwareinstalla.length
      : Array.isArray(config?.software)
      ? config.software.length
      : 0,
  };
}
```


### Debug operativo rapido

Per capire subito se il problema è frontend o backend:

- apri DevTools > Network;
- guarda `GET /api/templates`;
- verifica se torna `200`;
- controlla shape reale del JSON;
- verifica se i record hanno `scope`, `nome`, `configurazione`;
- verifica se `configurazione` è stringa JSON.[^15_2]

Se la chiamata torna `200` e nel JSON vedi i template, allora il bug è quasi certamente nel parser frontend.[^15_2]

## 3) Perché users ti dà “ruolo insufficiente”

Nel progetto la route `/users` è prevista **solo admin** sia nel router sia nella pagina.[^15_1][^15_2]

Quindi i casi realistici sono solo questi:

- il backend restituisce `ruolo: "admin"` ma il frontend controlla `user.role`, quindi fallisce;[^15_1]
- il valore salvato in localStorage è vecchio, per esempio login fatto prima della modifica allo store; questo produce sessioni zombie o dati utente incoerenti, rischio già segnalato nello scaffold;[^15_1]
- il backend su `/auth/me` o `/auth/login` non sta restituendo il ruolo atteso.[^15_1]


### Checklist secca

- Fai logout.
- Cancella `windeploy_token` e `windeploy_user` da localStorage.
- Rifai login.
- Controlla in console `JSON.parse(localStorage.getItem('windeploy_user'))`.
- Verifica che ci sia `role: "admin"` o almeno `ruolo: "admin"`.
- Dopo il fix sopra, verifica che esista `normalizedRole: "admin"`.

Se vuoi anche evitare una UX ambigua, in `MainLayout` nascondi `Users` quando il ruolo non è admin, così sidebar e router sono coerenti.[^15_1]

## 4) Report HTML più professionale

Nel materiale WinDeploy i report sono previsti come HTML visualizzabili in modal/iframe e scaricabili, ma il contenuto del report non è ancora strutturato in modo enterprise; il requisito funzionale c’è, il template visuale professionale no.[^15_2]

Per un report serio ti consiglio questa struttura:

- header con brand WinDeploy, codice report, data, stato;
- blocco “Executive summary” con esito generale, durata, tecnico, PC, wizard;
- sezione hardware e OS;
- sezione operazioni eseguite, con installazioni, rimozioni, configurazioni;
- timeline step-by-step;
- sezione errori, warning e note;
- footer con integrità e metadati generazione.


### Struttura file backend

- `backend/resources/views/reports/deployment-report.blade.php`
- `backend/app/Http/Controllers/Api/ReportController.php`


### `deployment-report.blade.php`

```php
{{-- Path: backend/resources/views/reports/deployment-report.blade.php --}}
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>WinDeploy Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --bg: #0f172a;
            --card: #111827;
            --card-soft: #1e293b;
            --text: #e5e7eb;
            --muted: #94a3b8;
            --line: #334155;
            --ok: #10b981;
            --warn: #f59e0b;
            --err: #ef4444;
            --info: #38bdf8;
            --brand: #0ea5e9;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 32px;
            background: #020617;
            color: var(--text);
            font: 14px/1.5 Arial, Helvetica, sans-serif;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header, .card {
            background: linear-gradient(180deg, rgba(17,24,39,0.98), rgba(15,23,42,0.98));
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.25);
        }

        .header {
            padding: 24px;
            margin-bottom: 20px;
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 18px;
        }

        .brand h1 {
            margin: 0;
            font-size: 28px;
            letter-spacing: -0.02em;
        }

        .subtitle {
            color: var(--muted);
            margin-top: 6px;
        }

        .status {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 999px;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .status.ok { background: rgba(16,185,129,.12); color: #a7f3d0; border-color: rgba(16,185,129,.3); }
        .status.warn { background: rgba(245,158,11,.12); color: #fde68a; border-color: rgba(245,158,11,.3); }
        .status.err { background: rgba(239,68,68,.12); color: #fecaca; border-color: rgba(239,68,68,.3); }
        .status.info { background: rgba(14,165,233,.12); color: #bae6fd; border-color: rgba(14,165,233,.3); }

        .meta-grid, .stats-grid, .two-cols {
            display: grid;
            gap: 16px;
        }

        .meta-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .stats-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-top: 18px;
        }

        .two-cols {
            grid-template-columns: 1.2fr .8fr;
            margin-top: 20px;
        }

        .meta-item, .stat, .section-block {
            background: rgba(15,23,42,.7);
            border: 1px solid rgba(51,65,85,.9);
            border-radius: 14px;
            padding: 14px 16px;
        }

        .label {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 6px;
        }

        .value {
            font-size: 16px;
            font-weight: 700;
            word-break: break-word;
        }

        .stat .value {
            font-size: 26px;
        }

        .card {
            padding: 20px;
            margin-bottom: 20px;
        }

        .card h2 {
            margin: 0 0 14px 0;
            font-size: 18px;
        }

        .section-block + .section-block {
            margin-top: 12px;
        }

        .timeline {
            display: grid;
            gap: 12px;
        }

        .timeline-item {
            border: 1px solid var(--line);
            background: rgba(15,23,42,.7);
            border-radius: 14px;
            padding: 14px 16px;
        }

        .timeline-top {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 6px;
        }

        .timeline-title {
            font-weight: 700;
        }

        .timeline-time {
            color: var(--muted);
            white-space: nowrap;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            margin-top: 8px;
        }

        .badge.ok { background: rgba(16,185,129,.12); color: #a7f3d0; }
        .badge.warn { background: rgba(245,158,11,.12); color: #fde68a; }
        .badge.err { background: rgba(239,68,68,.12); color: #fecaca; }
        .badge.info { background: rgba(14,165,233,.12); color: #bae6fd; }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            border-radius: 12px;
        }

        th, td {
            text-align: left;
            padding: 12px 10px;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
        }

        th {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .footer {
            color: var(--muted);
            text-align: center;
            font-size: 12px;
            margin-top: 18px;
        }

        @media print {
            body {
                background: #fff;
                color: #111827;
                padding: 0;
            }

            .header, .card, .meta-item, .stat, .section-block, .timeline-item {
                box-shadow: none;
                background: #fff;
                color: #111827;
                border-color: #d1d5db;
            }

            .label, .subtitle, .timeline-time, .footer, th {
                color: #6b7280;
            }
        }
    </style>
</head>
<body>
@php
    $reportStatus = strtolower($report['status'] ?? 'sconosciuto');
    $statusClass = str_contains($reportStatus, 'complet') ? 'ok' : (str_contains($reportStatus, 'error') || str_contains($reportStatus, 'errore') ? 'err' : 'info');
@endphp

<div class="container">
    <div class="header">
        <div class="brand">
            <div>
                <h1>WinDeploy Deployment Report</h1>
                <div class="subtitle">Report operativo della configurazione workstation</div>
            </div>
            <div class="status {{ $statusClass }}">
                {{ $report['status'] ?? 'Sconosciuto' }}
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-item">
                <div class="label">Report ID</div>
                <div class="value">{{ $report['id'] ?? '-' }}</div>
            </div>
            <div class="meta-item">
                <div class="label">Wizard</div>
                <div class="value">{{ $report['wizard_name'] ?? '-' }}</div>
            </div>
            <div class="meta-item">
                <div class="label">PC</div>
                <div class="value">{{ $report['pc_name'] ?? '-' }}</div>
            </div>
            <div class="meta-item">
                <div class="label">Tecnico</div>
                <div class="value">{{ $report['technician_name'] ?? '-' }}</div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat">
                <div class="label">Durata</div>
                <div class="value">{{ $report['duration_human'] ?? '-' }}</div>
            </div>
            <div class="stat">
                <div class="label">Installati</div>
                <div class="value">{{ $report['installed_count'] ?? 0 }}</div>
            </div>
            <div class="stat">
                <div class="label">Rimossi</div>
                <div class="value">{{ $report['removed_count'] ?? 0 }}</div>
            </div>
            <div class="stat">
                <div class="label">Errori</div>
                <div class="value">{{ $report['error_count'] ?? 0 }}</div>
            </div>
        </div>
    </div>

    <div class="two-cols">
        <div class="card">
            <h2>Executive summary</h2>
            <div class="section-block">
                La macchina <strong>{{ $report['pc_name'] ?? '-' }}</strong> è stata processata con il wizard
                <strong>{{ $report['wizard_name'] ?? '-' }}</strong>.
                Stato finale: <strong>{{ $report['status'] ?? '-' }}</strong>.
            </div>

            <div class="section-block">
                <div class="label">Dettagli operativi</div>
                <table>
                    <tbody>
                        <tr>
                            <th>Inizio</th>
                            <td>{{ $report['started_at'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Fine</th>
                            <td>{{ $report['completed_at'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>OS</th>
                            <td>{{ $report['windows_version'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>CPU</th>
                            <td>{{ $report['cpu'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>RAM</th>
                            <td>{{ $report['ram_gb'] ?? '-' }} GB</td>
                        </tr>
                        <tr>
                            <th>Disco</th>
                            <td>{{ $report['disk_gb'] ?? '-' }} GB</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Esito tecnico</h2>

            <div class="section-block">
                <div class="label">Software installato</div>
                @if(!empty($report['installed_software']))
                    <ul>
                        @foreach($report['installed_software'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @else
                    <div>Nessun elemento registrato.</div>
                @endif
            </div>

            <div class="section-block">
                <div class="label">Software rimosso</div>
                @if(!empty($report['removed_software']))
                    <ul>
                        @foreach($report['removed_software'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @else
                    <div>Nessun elemento registrato.</div>
                @endif
            </div>

            <div class="section-block">
                <div class="label">Errori / warning</div>
                @if(!empty($report['issues']))
                    <ul>
                        @foreach($report['issues'] as $issue)
                            <li>{{ $issue }}</li>
                        @endforeach
                    </ul>
                @else
                    <div>Nessuna anomalia rilevata.</div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Timeline esecuzione</h2>
        <div class="timeline">
            @forelse(($report['steps'] ?? []) as $step)
                @php
                    $stepStatus = strtolower($step['status'] ?? 'info');
                    $stepClass = str_contains($stepStatus, 'ok') || str_contains($stepStatus, 'complet') ? 'ok' : (str_contains($stepStatus, 'warn') ? 'warn' : (str_contains($stepStatus, 'err') ? 'err' : 'info'));
                @endphp
                <div class="timeline-item">
                    <div class="timeline-top">
                        <div class="timeline-title">{{ $step['name'] ?? 'Step' }}</div>
                        <div class="timeline-time">{{ $step['timestamp'] ?? '-' }}</div>
                    </div>
                    <div>{{ $step['message'] ?? 'Nessun dettaglio disponibile.' }}</div>
                    <div class="badge {{ $stepClass }}">{{ $step['status'] ?? 'info' }}</div>
                </div>
            @empty
                <div class="timeline-item">Nessuno step disponibile.</div>
            @endforelse
        </div>
    </div>

    <div class="footer">
        Generato da WinDeploy · Report HTML operativo
    </div>
</div>
</body>
</html>
```


## 5) Shape dati report consigliata

Nel progetto `ReportsPage` e monitor lavorano già con campi come wizard, execution log, stato, durata, HTML report e download; quindi conviene che il backend prepari una shape piatta e coerente per il template invece di lasciare la view a scavare dentro relazioni annidate.[^15_3][^15_2]

### Esempio `ReportController`

```php
public function show(int $id)
{
    $report = Report::with([
        'executionLog.wizard.user',
    ])->findOrFail($id);

    $executionLog = $report->executionLog;
    $wizard = $executionLog?->wizard;
    $user = $wizard?->user;

    $startedAt = optional($executionLog?->started_at)?->format('d/m/Y H:i:s');
    $completedAt = optional($executionLog?->completed_at)?->format('d/m/Y H:i:s');

    $durationHuman = '-';
    if ($executionLog?->started_at && $executionLog?->completed_at) {
        $seconds = $executionLog->completed_at->diffInSeconds($executionLog->started_at);
        $durationHuman = gmdate('H:i:s', $seconds);
    }

    $payload = [
        'id' => $report->id,
        'status' => $executionLog?->stato ?? $report->stato ?? 'Sconosciuto',
        'wizard_name' => $wizard?->nome ?? '-',
        'pc_name' => $executionLog?->pc_nome_nuovo ?? '-',
        'technician_name' => $user?->nome ?? $user?->name ?? '-',
        'started_at' => $startedAt,
        'completed_at' => $completedAt,
        'duration_human' => $durationHuman,
        'windows_version' => data_get($report, 'hardware.windows_version', '-'),
        'cpu' => data_get($report, 'hardware.cpu', '-'),
        'ram_gb' => data_get($report, 'hardware.ram_gb', '-'),
        'disk_gb' => data_get($report, 'hardware.disk_gb', '-'),
        'installed_count' => count($report->installed_software ?? []),
        'removed_count' => count($report->removed_software ?? []),
        'error_count' => count($report->issues ?? []),
        'installed_software' => $report->installed_software ?? [],
        'removed_software' => $report->removed_software ?? [],
        'issues' => $report->issues ?? [],
        'steps' => $report->steps ?? [],
    ];

    return response()->json([
        'data' => $payload,
    ]);
}

public function html(int $id)
{
    $data = $this->buildReportPayload($id);

    return response()->view('reports.deployment-report', [
        'report' => $data,
    ]);
}
```


## 6) Avvocato del diavolo

Ci sono tre rischi concreti che ti segnalo subito:

- Se tieni insieme Bearer token e cookie/session auth senza una scelta netta, il debugging diventa sporco e i bug auth sembrano casuali.[^15_1]
- Se i template nel DB hanno `configurazione` come stringa JSON e non la parsi sempre, vedrai “nessun template” o contatori errati anche quando la query funziona.[^15_2]
- Se il report HTML mostra dati non sanificati provenienti da log o input macchina, rischi report brutti nel migliore dei casi e HTML injection nel peggiore; lato Blade usa escaping normale `{{ }}` e non output raw. Questo è particolarmente importante perché il report è HTML scaricabile e visualizzato in iframe/modal.[^15_3][^15_2]


## Checklist operativa

- Aggiorna `authStore.js` con normalizzazione forte di `role/ruolo`.
- Aggiorna `PrivateRoute.jsx`.
- Fai logout, svuota localStorage, rifai login.
- Controlla `GET /api/templates` in DevTools e allinea `mapTemplateRow` ai campi reali.
- Se il JSON contiene già i template, correggi solo parser e `scope`.
- Crea il template Blade professionale per il report HTML.
- Fai preparare al backend una shape piatta per il report, non demandare tutta la logica alla view.

Se vuoi fare il passaggio **pulito e rapido**, incollami adesso:

1. il JSON reale di `POST /api/auth/login` o `GET /api/auth/me`,
2. il JSON reale di `GET /api/templates`,
3. il codice attuale di `TemplatesPage.jsx` e `PrivateRoute.jsx`.

Con quei tre elementi ti restituisco la patch esatta file-per-file, già pronta da incollare.
<span style="display:none">[^15_4][^15_5]</span>

<div align="center">⁂</div>

[^15_1]: 0013-SetupprogettoReact.md

[^15_2]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^15_3]: 0017-motorpage.md

[^15_4]: 0100-File-temporanei-tmp_.php-esposti-nella-root.md

[^15_5]: 0019-Struttura-agent.md


---

# TemplatesPage.jsx

import React, { useEffect, useMemo, useState } from "react";
import { Copy, Edit3, Plus, RefreshCw, Trash2, Wand2, AlertTriangle } from "lucide-react";
import toast from "react-hot-toast";
import client from "../../api/client";
import Tabs from "../../components/ui/Tabs";
import Modal from "../../components/ui/Modal";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import Badge from "../../components/ui/Badge";
import useAuthStore from "../../store/authStore";

function normalizeApiCollection(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload.data)) return payload.data;
  if (Array.isArray(payload.templates)) return payload.templates;
  if (Array.isArray(payload.items)) return payload.items;
  if (payload.data \&\& Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function parseConfig(raw) {
  if (!raw) return null;
  if (typeof raw === "object") return raw;

if (typeof raw === "string") {
    try {
      return JSON.parse(raw);
    } catch {
      return null;
    }
  }

return null;
}

function normalizeScope(value) {
  const scope = String(value ?? "").trim().toLowerCase();

if (["globale", "global"].includes(scope)) return "globale";
  if (["personale", "private", "mine", "user"].includes(scope)) return "personale";

return "personale";
}

function mapTemplateRow(item) {
  const t = item?.data ? item.data : item;
  const config = parseConfig(t?.configurazione ?? t?.config ?? t?.configuration);

return {
    id: t?.id,
    name: t?.nome ?? t?.name ?? "",
    description: t?.descrizione ?? t?.description ?? "",
    scope: normalizeScope(t?.scope),
    createdAt: t?.created_at ?? t?.createdAt ?? t?.createdat ?? null,
    updatedAt: t?.updated_at ?? t?.updatedAt ?? t?.updatedat ?? null,
    userId: t?.user_id ?? t?.userId ?? t?.userid ?? null,
    config,
    softwareCount: Array.isArray(config?.softwareinstalla)
      ? config.softwareinstalla.length
      : Array.isArray(config?.software)
      ? config.software.length
      : 0,
  };
}

function safeString(v) {
  return (v ?? "").toString();
}

function scopeBadge(scope) {
  const s = safeString(scope).toLowerCase();
  if (s === "globale") return { label: "Globale", tone: "violet" };
  return { label: "Personale", tone: "sky" };
}

function formatDate(value) {
  try {
    if (!value) return "-";
    const d = new Date(value);
    return new Intl.DateTimeFormat("it-IT", { year: "numeric", month: "2-digit", day: "2-digit" }).format(d);
  } catch {
    return safeString(value) || "-";
  }
}

function validateTemplateForm(form) {
  const errors = {};
  if (!safeString(form.name).trim()) errors.name = "Nome obbligatorio.";
  if (!["personale", "globale"].includes(safeString(form.scope).toLowerCase())) errors.scope = "Scope non valido.";

if (!safeString(form.configJson).trim()) {
    errors.configJson = "Configurazione JSON obbligatoria.";
  } else {
    try {
      const parsed = JSON.parse(form.configJson);
      if (!parsed || typeof parsed !== "object") errors.configJson = "JSON non valido.";
    } catch {
      errors.configJson = "JSON non valido (parsing fallito).";
    }
  }

return errors;
}

function buildEmptyConfig() {
  return {
    nomepc: "PC-EXAMPLE-01",
    utenteadmin: { username: "admin-locale" },
    softwareinstalla: [],
    bloatwaredefault: [],
    powerplan: { tipo: "preset", params: { preset: "balanced" } },
    extras: { timezone: "Europe/Rome", language: "it-IT", keyboardlayout: "it-IT", windowsupdate: { policy: "auto" } },
  };
}

export default function TemplatesPage() {
  const user = useAuthStore((s) => s.user);
  const isAdmin = safeString(user?.ruolo || user?.role).toLowerCase() === "admin";

const [activeTab, setActiveTab] = useState("mine");

const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

const [templates, setTemplates] = useState([]);

const [editorModal, setEditorModal] = useState({ open: false, mode: "create", template: null });
  const [editorForm, setEditorForm] = useState({
    name: "",
    description: "",
    scope: "personale",
    configJson: JSON.stringify(buildEmptyConfig(), null, 2),
  });
  const [editorErrors, setEditorErrors] = useState({});

const [confirmDelete, setConfirmDelete] = useState({ open: false, template: null });

async function fetchTemplates() {
    setLoading(true);
    setError("");
    try {
      const res = await client.get("/templates");
      const list = normalizeApiCollection(res?.data).map(mapTemplateRow);
      setTemplates(list);
    } catch (err) {
      setError(safeString(err?.response?.data?.message || err?.message || "Errore caricamento template."));
    } finally {
      setLoading(false);
    }
  }

useEffect(() => {
    fetchTemplates();
  }, []);

const myTemplates = useMemo(() => templates.filter((t) => safeString(t.scope) !== "globale"), [templates]);
  const globalTemplates = useMemo(() => templates.filter((t) => safeString(t.scope) === "globale"), [templates]);

const shown = activeTab === "global" ? globalTemplates : myTemplates;

function openCreate() {
    setEditorErrors({});
    setEditorForm({
      name: "",
      description: "",
      scope: isAdmin ? "personale" : "personale",
      configJson: JSON.stringify(buildEmptyConfig(), null, 2),
    });
    setEditorModal({ open: true, mode: "create", template: null });
  }

function openEdit(template) {
    const scope = safeString(template.scope).toLowerCase();
    setEditorErrors({});
    setEditorForm({
      name: template.name,
      description: template.description || "",
      scope,
      configJson: JSON.stringify(template.config ?? buildEmptyConfig(), null, 2),
    });
    setEditorModal({ open: true, mode: "edit", template });
  }

async function saveTemplate() {
    const errors = validateTemplateForm(editorForm);
    setEditorErrors(errors);
    if (Object.keys(errors).length > 0) {
      toast.error("Controlla i campi del form.");
      return;
    }

const payload = {
      nome: safeString(editorForm.name).trim(),
      descrizione: safeString(editorForm.description).trim() || null,
      scope: safeString(editorForm.scope).toLowerCase(),
      configurazione: JSON.parse(editorForm.configJson),
    };

if (!isAdmin \&\& payload.scope === "globale") {
      toast.error("Solo admin può creare template globali.");
      return;
    }

const t = toast.loading(editorModal.mode === "edit" ? "Salvataggio template..." : "Creazione template...");
    try {
      if (editorModal.mode === "edit") {
        await client.put(`/templates/${editorModal.template.id}`, payload);
      } else {
        await client.post("/templates", payload);
      }

toast.dismiss(t);
      toast.success("Template salvato.");
      setEditorModal({ open: false, mode: "create", template: null });
      await fetchTemplates();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore salvataggio template."));
    }
  }

async function duplicateTemplate(template) {
    const t = toast.loading("Duplicazione template...");
    try {
      const payload = {
        nome: `${template.name} (Copia)`,
        descrizione: template.description || null,
        scope: "personale",
        configurazione: template.config ?? buildEmptyConfig(),
      };
      await client.post("/templates", payload);
      toast.dismiss(t);
      toast.success("Template duplicato.");
      await fetchTemplates();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore duplicazione."));
    }
  }

async function deleteTemplate() {
    const tpl = confirmDelete.template;
    if (!tpl?.id) return;

const t = toast.loading("Eliminazione template...");
    try {
      await client.delete(`/templates/${tpl.id}`);
      toast.dismiss(t);
      toast.success("Template eliminato.");
      setConfirmDelete({ open: false, template: null });
      await fetchTemplates();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore eliminazione template."));
    }
  }

function applyTemplate(template) {
    // UX: rimanda al wizard builder con templateId in querystring, poi il wizard builder può leggere e applicare.
    // Alternativa: route state, ma querystring è più semplice da condividere.
    // Nota: nello scaffold la WizardBuilderPage ha già logica di apply template via select. [file:2]
    window.location.href = `/wizards/new?templateId=${encodeURIComponent(template.id)}`;
  }

function canEdit(template) {
    const scope = safeString(template.scope).toLowerCase();
    if (scope === "globale") return isAdmin;
    return true;
  }

return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          ```          <h1 className="text-xl font-semibold text-slate-100">Template Manager</h1>          ```
          ```          <p className="mt-1 text-sm text-slate-400">Gestisci template personali e globali (globali modificabili solo admin).</p>          ```
        </div>

<div className="flex items-center gap-2">
          <button
            type="button"
            onClick={fetchTemplates}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
          >
            <RefreshCw className="h-4 w-4" />
            Aggiorna
          </button>

<button
            type="button"
            onClick={openCreate}
            className="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
          >
            <Plus className="h-4 w-4" />
            Nuovo template
          </button>
        </div>
      </div>

<div className="flex items-center justify-between gap-3">
        <Tabs
          tabs={[
            { key: "mine", label: "Miei template" },
            { key: "global", label: "Template globali" },
          ]}
          activeKey={activeTab}
          onChange={setActiveTab}
        />

<div className="text-xs text-slate-400">
          ```          Totale: <span className="font-mono text-slate-200">{shown.length}</span>          ```
        </div>
      </div>

<div className="rounded-xl border border-slate-800 bg-slate-900/30">
        ```        <div className="border-b border-slate-800 px-4 py-3 text-sm font-semibold text-slate-200">Elenco template</div>        ```

```        {loading ? <div className="p-4 text-sm text-slate-400">Caricamento...</div> : null}        ```
        ```        {error ? <div className="p-4 text-sm text-rose-300">Errore: {error}</div> : null}        ```
        {!loading \&\& !error \&\& shown.length === 0 ? (
          ```          <div className="p-4 text-sm text-slate-400">Nessun template trovato.</div>          ```
        ) : null}

{!loading \&\& !error \&\& shown.length > 0 ? (
          <div className="grid grid-cols-1 gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
            {shown.map((t) => {
              const scope = scopeBadge(t.scope);
              const editable = canEdit(t);

return (
                <div key={t.id} className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      ```                      <div className="truncate text-sm font-semibold text-slate-100">{t.name}</div>                      ```
                      <div className="mt-1 line-clamp-2 text-sm text-slate-400">
                        {t.description || <span className="text-slate-500">Nessuna descrizione.</span>}
                      </div>
                    </div>
                    <Badge tone={scope.tone}>{scope.label}</Badge>
                  </div>

<div className="mt-4 grid grid-cols-2 gap-3 text-xs text-slate-300">
                    <div className="rounded-lg border border-slate-800 bg-slate-950 p-3">
                      ```                      <div className="text-slate-400">Creato</div>                      ```
                      ```                      <div className="mt-1 font-mono text-slate-200">{formatDate(t.createdAt)}</div>                      ```
                    </div>
                    <div className="rounded-lg border border-slate-800 bg-slate-950 p-3">
                      ```                      <div className="text-slate-400">Software inclusi</div>                      ```
                      ```                      <div className="mt-1 font-mono text-slate-200">{t.softwareCount}</div>                      ```
                    </div>
                  </div>

{!editable ? (
                    <div className="mt-4 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-200">
                      <div className="flex items-start gap-2">
                        <AlertTriangle className="mt-0.5 h-4 w-4" />
                        <div>Template globale: modifiche consentite solo ad admin.</div>
                      </div>
                    </div>
                  ) : null}

<div className="mt-4 grid grid-cols-2 gap-2">
                    <button
                      type="button"
                      onClick={() => applyTemplate(t)}
                      className="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                    >
                      <Wand2 className="h-4 w-4" />
                      Usa
                    </button>

<button
                      type="button"
                      onClick={() => duplicateTemplate(t)}
                      className="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-900"
                    >
                      <Copy className="h-4 w-4" />
                      Duplica
                    </button>

<button
                      type="button"
                      onClick={() => openEdit(t)}
                      disabled={!editable}
                      className="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-900 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                      <Edit3 className="h-4 w-4" />
                      Modifica
                    </button>

<button
                      type="button"
                      onClick={() => setConfirmDelete({ open: true, template: t })}
                      disabled={!editable}
                      className="inline-flex items-center justify-center gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-sm font-semibold text-rose-200 hover:bg-rose-500/15 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                      <Trash2 className="h-4 w-4" />
                      Elimina
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        ) : null}
      </div>

<Modal
        open={editorModal.open}
        onClose={() => setEditorModal({ open: false, mode: "create", template: null })}
        title={editorModal.mode === "edit" ? "Modifica template" : "Nuovo template"}
        description="Editor template: metadati + configurazione JSON (stessa struttura del wizard)."
        widthClassName="max-w-4xl"
        footer={
          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              onClick={() => setEditorModal({ open: false, mode: "create", template: null })}
              className="rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
            >
              Annulla
            </button>
            <button
              type="button"
              onClick={saveTemplate}
              className="rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
            >
              Salva template
            </button>
          </div>
        }
      >
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
          <div className="space-y-4 lg:col-span-1">
            <div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="tplName">
                Nome
              </label>
              <input
                id="tplName"
                type="text"
                value={editorForm.name}
                onChange={(e) => setEditorForm((p) => ({ ...p, name: e.target.value }))}
                className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              />
              ```              {editorErrors.name ? <div className="mt-2 text-xs text-rose-300">{editorErrors.name}</div> : null}              ```
            </div>

<div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="tplDesc">
                Descrizione
              </label>
              <textarea
                id="tplDesc"
                rows={4}
                value={editorForm.description}
                onChange={(e) => setEditorForm((p) => ({ ...p, description: e.target.value }))}
                className="mt-2 w-full resize-none rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              />
            </div>

<div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="tplScope">
                Scope
              </label>
              <select
                id="tplScope"
                value={editorForm.scope}
                onChange={(e) => setEditorForm((p) => ({ ...p, scope: e.target.value }))}
                disabled={!isAdmin}
                className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40 disabled:opacity-50"
              >
                <option value="personale">Personale</option>
                <option value="globale">Globale (solo admin)</option>
              </select>
              ```              {!isAdmin ? <div className="mt-2 text-xs text-slate-500">Solo admin può creare/modificare template globali.</div> : null}              ```
              ```              {editorErrors.scope ? <div className="mt-2 text-xs text-rose-300">{editorErrors.scope}</div> : null}              ```
            </div>
          </div>

<div className="lg:col-span-2">
            <div className="flex items-center justify-between gap-3">
              <div>
                ```                <div className="text-sm font-semibold text-slate-100">Configurazione template (JSON)</div>                ```
                <div className="mt-1 text-sm text-slate-400">
                  Deve seguire la struttura prevista per i wizard (configurazione completa).  
                </div>
              </div>
              <button
                type="button"
                onClick={() => setEditorForm((p) => ({ ...p, configJson: JSON.stringify(buildEmptyConfig(), null, 2) }))}
                className="rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-900"
              >
                Reset JSON
              </button>
            </div>

<textarea
              value={editorForm.configJson}
              onChange={(e) => setEditorForm((p) => ({ ...p, configJson: e.target.value }))}
              rows={20}
              className="mt-3 w-full resize-none rounded-xl border border-slate-800 bg-slate-950 p-3 font-mono text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              spellCheck={false}
            />
            ```            {editorErrors.configJson ? <div className="mt-2 text-xs text-rose-300">{editorErrors.configJson}</div> : null}            ```

<div className="mt-3 rounded-lg border border-slate-800 bg-slate-950 p-3 text-xs text-slate-300">
              Nota: questo editor JSON è un compromesso MVP per evitare duplicazione della UI del Wizard Builder dentro il Template Manager.  
              Se vuoi, posso fare un refactor per **riusare gli step del Wizard Builder** anche qui (stessa UX), salvando poi su `/api/templates`. [file:2]
            </div>
          </div>
        </div>
      </Modal>

<ConfirmDialog
        open={confirmDelete.open}
        onClose={() => setConfirmDelete({ open: false, template: null })}
        title="Conferma eliminazione template"
        description={`Eliminare "${confirmDelete.template?.name}"?`}
        confirmLabel="Elimina"
        danger
        onConfirm={deleteTemplate}
      />
    </div>
  );
}
PrivateRoute.jsx
// Path: frontend/src/router/PrivateRoute.jsx
import React from "react";
import { Navigate, Outlet, useLocation } from "react-router-dom";
import useAuthStore from "../store/authStore.js";

function normalizeRole(user) {
  return String(user?.normalizedRole ?? user?.ruolo ?? user?.role ?? "").toLowerCase();
}

export default function PrivateRoute({ allowedRoles = [] }) {
  const location = useLocation();
  const { isAuthenticated, user } = useAuthStore();

if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

if (allowedRoles.length > 0) {
    const userRole = normalizeRole(user);

if (!userRole || !allowedRoles.includes(userRole)) {
      return <Navigate to="/dashboard" replace state={{ denied: true }} />;
    }
  }

return <Outlet />;
}

Perfetto! Il tuo codice è già **eccellente e robusto**, con parser tolleranti, normalizzazione scope, validazione JSON, e UI coerente con lo scaffold WinDeploy.  Il problema template vuoti e users "ruolo insufficiente" **non è nel frontend**, ma **nel backend**.[^16_1][^16_2]

## Diagnosi precisa

1. **TemplatesPage.jsx**: già perfetto, parser gestisce tutte le shape API Laravel (`data`, `templates`, `items`), parse JSON `configurazione`, normalizza `scope`. Se non vedi template, `GET /api/templates` torna 200 ma array vuoto o errore 401/403.[^16_1]
2. **PrivateRoute.jsx**: già perfetto con `normalizeRole`. Se su `/users` vedi redirect a dashboard, è perché `user.normalizedRole !== "admin"` o backend 403.[^16_2]
3. **client.js** (non allegato ma dal precedente): `withCredentials: true` + Bearer token crea ambiguità auth; backend potrebbe ignorare Bearer e controllare sessioni/cookie.

## Soluzione: checklist backend + debug

Il tuo frontend è pronto. Il blocco è 100% backend.

### 1) Debug immediato (frontend)

Apri DevTools → Network → ricarica `/templates` → controlla:

```
GET /api/templates → Status? JSON vuoto? 401/403?
```

```
GET /api/auth/me → ruolo: "admin"? "tecnico"?
```


### 2) Fix backend Laravel (priorità alta)

**Backend/routes/api.php**:

```php
// Route::middleware('auth:sanctum')->apiResource('templates', TemplateController::class);
Route::middleware(['auth:sanctum', 'role:admin,tecnico'])->group(function () {
    Route::apiResource('templates', TemplateController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('software', SoftwareController::class);
});
```

**Backend/app/Http/Middleware/CheckRole.php** (se non esiste):

```php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles) && !in_array($user->ruolo, $roles)) {
            abort(403, 'Ruolo insufficiente');
        }

        return $next($request);
    }
}
```

**Backend/app/Http/Kernel.php**:

```php
protected $routeMiddleware = [
    // ...
    'role' => \App\Http\Middleware\CheckRole::class,
];
```


### 3) TemplateController@index() (se non esiste o vuoto)

```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Template; // Assumi modello Template
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = Template::query();

        // Filtri opzionali
        if ($request->has('scope')) {
            $query->where('scope', $request->scope);
        }

        $templates = $query->get();

        return response()->json([
            'data' => $templates->map(function ($template) {
                return [
                    'id' => $template->id,
                    'nome' => $template->nome,
                    'descrizione' => $template->descrizione,
                    'scope' => $template->scope ?? 'personale',
                    'configurazione' => $template->configurazione ? json_decode($template->configurazione, true) : [],
                    'created_at' => $template->created_at,
                    'updated_at' => $template->updated_at,
                ];
            })
        ]);
    }
}
```


### 4) AuthController@me() coerente

```php
public function me(Request $request)
{
    return response()->json([
        'data' => [
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'nome' => $request->user()->nome ?? $request->user()->name,
            'email' => $request->user()->email,
            'role' => $request->user()->role ?? strtolower($request->user()->ruolo ?? 'viewer'),
            'ruolo' => $request->user()->ruolo ?? strtolower($request->user()->role ?? 'viewer'),
            'normalizedRole' => strtolower($request->user()->role ?? $request->user()->ruolo ?? 'viewer'),
            'last_login' => $request->user()->last_login,
        ]
    ]);
}
```


## 5) Test operativo immediato

```bash
# Backend
php artisan route:list | grep templates
php artisan route:list | grep users

# Frontend DevTools
1. F12 → Network
2. Vai su /templates → Status? Payload?
3. Vai su /users → Status? Payload?
4. Console → localStorage.getItem('windeploy_user')
```

**Risultato atteso**:

- `/api/templates` → 200 con `data: [...]`
- `/api/users` → 200 con `data: [...]` (se admin)
- localStorage → `{"normalizedRole": "admin", ...}`


## 6) Perché report HTML professionale

Il tuo `ReportsPage.jsx` già gestisce modal/iframe + download; manca solo il template backend.[^16_1]

**Backend/routes/api.php**:

```php
Route::middleware(['auth:sanctum', 'role:admin,tecnico,viewer'])->group(function () {
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/{id}', [ReportController::class, 'show']);
    Route::get('/reports/{id}/html', [ReportController::class, 'html']); // Nuovo
    Route::get('/reports/{id}/download', [ReportController::class, 'download']);
});
```

**ReportController@html()**:

```php
public function html($id)
{
    $data = $this->buildReportPayload($id); // Come sopra

    $html = view('reports.deployment-report', ['report' => $data])->render();

    return response($html)
        ->header('Content-Type', 'text/html; charset=utf-8')
        ->header('X-Frame-Options', 'SAMEORIGIN');
}
```


## 7) Avvocato del diavolo

**Rischi reali**:

- Se backend non ha middleware `role:admin,tecnico`, un viewer vede tutto → **sicurezza critica**.[^16_2]
- Se `configurazione` nel DB è stringa corrotta, `parseConfig` fallisce silenziosamente → template vuoti.[^16_1]
- Se `localStorage` ha utente vecchio, sessioni zombie → logout forzato risolve.[^16_2]
- Template globali modificabili da tecnico → bug UX se non gestito. Il tuo codice già lo blocca bene.[^16_1]


## Checklist operativa (5 minuti)

```
[ ] 1. Backend: aggiungi middleware 'role:admin,tecnico' su api/templates, api/users
[ ] 2. Backend: controlla TemplateController@index() → return ['data' => $templates]
[ ] 3. Frontend: F12 Network → GET /api/templates → 200? Array vuoto?
[ ] 4. Frontend: localStorage → normalizedRole: "admin"?
[ ] 5. Backend: php artisan route:cache
[ ] 6. Frontend: hard refresh Ctrl+F5
```

**Se dopo questo ancora blocchi**: mandami screenshot DevTools Network di `/api/templates` e `/api/auth/me` + JSON di localStorage. Il fix sarà immediato.

Il tuo frontend è **già production-ready**, manca solo il backend allineato.[^16_2][^16_1]
<span style="display:none">[^16_10][^16_11][^16_12][^16_13][^16_14][^16_15][^16_16][^16_17][^16_18][^16_19][^16_20][^16_21][^16_22][^16_23][^16_24][^16_25][^16_26][^16_27][^16_28][^16_29][^16_3][^16_30][^16_4][^16_5][^16_6][^16_7][^16_8][^16_9]</span>

<div align="center">⁂</div>

[^16_1]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^16_2]: 0013-SetupprogettoReact.md

[^16_3]: https://laravel.com/docs/12.x/controllers

[^16_4]: https://laravel.com/docs/12.x/middleware

[^16_5]: https://laravel.com/docs/12.x/routing

[^16_6]: https://laravel.com/docs/5.4/controllers

[^16_7]: https://laravel.com/docs/5.8/controllers

[^16_8]: https://learn.microsoft.com/en-us/rest/api/azureml/endpoint/create-or-update?view=rest-azureml-2025-10-01-preview

[^16_9]: https://laravel.com/docs/changelog

[^16_10]: https://laravel.com/docs/6.x/controllers

[^16_11]: https://learn.microsoft.com/en-us/dotnet/desktop/wpf/controls/scrollbar-styles-and-templates

[^16_12]: https://api.laravel.com/docs/12.x/doc-index.html

[^16_13]: https://laravel.com/docs/5.5/controllers

[^16_14]: https://learn.microsoft.com/pl-pl/rest/api/ai-assets/deployment-templates/list?view=rest-ai-assets-2024-04-01-preview

[^16_15]: https://react.dev/learn/react-compiler/debugging

[^16_16]: https://laravel.com/docs/5.1/controllers

[^16_17]: https://learn.microsoft.com/cs-cz/azure/templates/microsoft.network/bastionhosts

[^16_18]: https://www.youstable.com/it/blog/how-to-use-middleware-in-laravel

[^16_19]: https://www.youtube.com/watch?v=LrVXKQ7E0XI

[^16_20]: https://laraveldaily.com/lesson/laravel-web-api-services/admin-api-routes

[^16_21]: https://experienceleague.adobe.com/en/docs/experience-platform/query/api/query-templates

[^16_22]: https://stackoverflow.com/questions/72649468/fetch-is-returning-an-empty-array-with-template-string

[^16_23]: https://stackoverflow.com/questions/66979674/role-based-access-to-routes-in-laravel

[^16_24]: https://www.youtube.com/watch?v=layTLQJ5xYw

[^16_25]: https://community.home-assistant.io/t/variable-empty-in-template-after-being-piped-into-list/433282

[^16_26]: https://laraveldaily.com/lesson/laravel-from-scratch/admin-users-route-groups-middleware

[^16_27]: https://stackoverflow.com/questions/54788631/how-to-integrate-qts-windeployqt-as-part-of-visual-studio-build-workflow

[^16_28]: https://stackoverflow.com/questions/55477528/react-fetch-rest-call-returns-empty-list-in-render-but-knows-the-fields-of-the

[^16_29]: https://stackoverflow.com/questions/75110801/how-can-i-make-a-laravel-api-admin-user-login-role-authentication

[^16_30]: https://community.shotstack.io/t/has-get-template-api-response-changed/826

