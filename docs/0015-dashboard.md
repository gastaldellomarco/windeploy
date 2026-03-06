<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Crea la Dashboard principale per WinDeploy in React 18 + Tailwind CSS + Recharts.

LAYOUT: sidebar sinistra fissa (navigazione), area contenuto a destra.
Sidebar: logo in alto, voci di menu con icone Lucide, nome utente e ruolo in basso,
bottone logout. Usa colori: sidebar \#1E3A5F testo bianco, contenuto sfondo \#F0F4F8.
 
SEZIONI DELLA DASHBOARD:

1. STATISTICHE (4 card in griglia 2x2):
   - PC configurati questo mese (icona Monitor)
   - Wizard attivi (icona Zap) 
   - Software più installato questo mese (icona Package)
   - Configurazioni con errori (icona AlertTriangle, rosso se > 0)
2. GRAFICO (Recharts BarChart):
   - PC configurati per settimana (ultimi 30 giorni)
   - Dati mockati per ora, poi verranno dall'API GET /api/stats
3. ATTIVITÀ RECENTE (tabella):
   - Ultime 5 configurazioni con: data, tecnico, nome PC, stato (badge colorato)
   - Bottone "Vedi tutti i report"
4. WIZARD IN ESECUZIONE (se presenti):
   - Card con progress bar per ogni wizard nello stato "in_esecuzione"
   - Aggiornamento ogni 10 secondi (useInterval custom hook)
 
Chiamate API:
GET /api/stats → { pc_mese, wizard_attivi, software_top, errori, grafico_settimanale }
GET /api/wizards?stato=in_esecuzione → lista wizard attivi
 
Scrivi il file completo: src/pages/Dashboard/index.jsx
Con layout Sidebar incluso come componente separato: src/components/Layout/Sidebar.jsx
Codice completo con Tailwind, dati mockati dove l'API non esiste ancora.
il file della dashborad si chiama DashboardPage.jsx

Di seguito trovi i **2 file completi** richiesti: una Sidebar fissa a sinistra e la Dashboard principale a destra con cards, BarChart Recharts, tabella attività e sezione wizard in esecuzione con polling ogni 10s. La Dashboard usa mock data quando le API non sono disponibili e “switcha” ai dati reali quando rispondono.[^1_1][^1_2]

## Struttura file coinvolti

- `src/components/Layout/Sidebar.jsx` (layout sidebar fissa con navigazione + user box + logout)
- `src/pages/Dashboard/DashboardPage.jsx` (pagina dashboard: stats, grafico, attività, wizard in esecuzione)

Dipendenze attese (già previste nello scaffold WinDeploy):

- `lucide-react`, `recharts`, `react-router-dom`, `axios`, `zustand`[^1_2][^1_1]

***

## File: `src/components/Layout/Sidebar.jsx`

```jsx
import React, { useMemo } from "react";
import { NavLink, useNavigate } from "react-router-dom";
import {
  LayoutDashboard,
  Wand2,
  FileText,
  Package,
  ClipboardList,
  Users,
  LogOut,
} from "lucide-react";
import { useAuthStore } from "../../store/authStore";

function buildNavLinkClass({ isActive }) {
  return [
    "flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition",
    isActive ? "bg-white/15 text-white" : "text-white/85 hover:bg-white/10 hover:text-white",
  ].join(" ");
}

export default function Sidebar() {
  const navigate = useNavigate();
  const user = useAuthStore((s) => s.user);
  const logout = useAuthStore((s) => s.logout);

  const roleLabel = useMemo(() => {
    const role = user?.role || "viewer";
    if (role === "admin") return "Admin";
    if (role === "tecnico") return "Tecnico";
    return "Viewer";
  }, [user?.role]);

  function handleLogout() {
    logout();
    navigate("/login", { replace: true });
  }

  return (
    <aside
      className="fixed left-0 top-0 h-screen w-64 shrink-0 text-white"
      style={{ backgroundColor: "#1E3A5F" }}
    >
      <div className="flex h-full flex-col">
        <div className="border-b border-white/10 px-5 py-5">
          <div className="flex items-center gap-3">
            <div className="grid h-10 w-10 place-items-center rounded-xl bg-white/15 font-bold tracking-wide">
              WD
            </div>
            <div className="leading-tight">
              <div className="text-base font-semibold">WinDeploy</div>
              <div className="text-xs text-white/70">IT Provisioning Platform</div>
            </div>
          </div>
        </div>

        <nav className="flex-1 overflow-y-auto px-3 py-4">
          <div className="space-y-1">
            <NavLink to="/dashboard" className={buildNavLinkClass} end>
              <LayoutDashboard className="h-4 w-4" />
              Dashboard
            </NavLink>

            <NavLink to="/wizards" className={buildNavLinkClass}>
              <Wand2 className="h-4 w-4" />
              Wizards
            </NavLink>

            <NavLink to="/templates" className={buildNavLinkClass}>
              <FileText className="h-4 w-4" />
              Template
            </NavLink>

            <NavLink to="/software" className={buildNavLinkClass}>
              <Package className="h-4 w-4" />
              Software library
            </NavLink>

            <NavLink to="/reports" className={buildNavLinkClass}>
              <ClipboardList className="h-4 w-4" />
              Report
            </NavLink>

            <NavLink to="/users" className={buildNavLinkClass}>
              <Users className="h-4 w-4" />
              Utenti
            </NavLink>
          </div>
        </nav>

        <div className="border-t border-white/10 px-5 py-4">
          <div className="mb-3">
            <div className="text-sm font-semibold leading-tight">
              {user?.name || "Utente"}
            </div>
            <div className="text-xs text-white/75">{roleLabel}</div>
          </div>

          <button
            type="button"
            onClick={handleLogout}
            className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-white/10 px-3 py-2 text-sm font-medium text-white transition hover:bg-white/15"
          >
            <LogOut className="h-4 w-4" />
            Logout
          </button>
        </div>
      </div>
    </aside>
  );
}
```


***

## File: `src/pages/Dashboard/DashboardPage.jsx`

```jsx
import React, { useEffect, useMemo, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  Monitor,
  Zap,
  Package,
  TriangleAlert,
  ArrowRight,
} from "lucide-react";
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
} from "recharts";
import client from "../../api/client";
import Sidebar from "../../components/Layout/Sidebar";

function useInterval(callback, delay) {
  const savedCallback = useRef(callback);

  useEffect(() => {
    savedCallback.current = callback;
  }, [callback]);

  useEffect(() => {
    if (delay === null || delay === undefined) return;
    const id = setInterval(() => savedCallback.current?.(), delay);
    return () => clearInterval(id);
  }, [delay]);
}

function formatDateTime(dateValue) {
  try {
    const d = new Date(dateValue);
    return new Intl.DateTimeFormat("it-IT", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    }).format(d);
  } catch {
    return String(dateValue || "");
  }
}

function statusBadgeClass(status) {
  const base =
    "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium";
  if (status === "completato") return `${base} bg-emerald-100 text-emerald-800`;
  if (status === "in_esecuzione") return `${base} bg-sky-100 text-sky-800`;
  if (status === "errore") return `${base} bg-red-100 text-red-800`;
  return `${base} bg-slate-100 text-slate-800`;
}

function normalizeStatsPayload(payload) {
  return {
    pc_mese: payload?.pc_mese ?? 0,
    wizard_attivi: payload?.wizard_attivi ?? 0,
    software_top: payload?.software_top ?? "—",
    errori: payload?.errori ?? 0,
    grafico_settimanale: Array.isArray(payload?.grafico_settimanale)
      ? payload.grafico_settimanale
      : [],
  };
}

const mockStats = {
  pc_mese: 18,
  wizard_attivi: 3,
  software_top: "Google Chrome",
  errori: 2,
  grafico_settimanale: [
    { week: "W-4", configured: 3 },
    { week: "W-3", configured: 5 },
    { week: "W-2", configured: 4 },
    { week: "W-1", configured: 6 },
  ],
};

const mockRecentActivity = [
  {
    id: "rep_001",
    date: new Date(Date.now() - 1000 * 60 * 40).toISOString(),
    tecnico: "Marco R.",
    pc: "PC-ACCT-017",
    stato: "completato",
  },
  {
    id: "rep_002",
    date: new Date(Date.now() - 1000 * 60 * 110).toISOString(),
    tecnico: "Sara L.",
    pc: "PC-HR-004",
    stato: "errore",
  },
  {
    id: "rep_003",
    date: new Date(Date.now() - 1000 * 60 * 200).toISOString(),
    tecnico: "Marco R.",
    pc: "PC-SALES-012",
    stato: "completato",
  },
  {
    id: "rep_004",
    date: new Date(Date.now() - 1000 * 60 * 310).toISOString(),
    tecnico: "Luca P.",
    pc: "PC-OPS-009",
    stato: "in_esecuzione",
  },
  {
    id: "rep_005",
    date: new Date(Date.now() - 1000 * 60 * 480).toISOString(),
    tecnico: "Giulia C.",
    pc: "PC-ENG-021",
    stato: "completato",
  },
];

const mockWizardsRunning = [
  {
    id: "wiz_101",
    name: "Onboarding Finance",
    pc_name: "PC-FIN-022",
    progress: 62,
    current_step: "Installazione software",
  },
  {
    id: "wiz_102",
    name: "Sales Standard",
    pc_name: "PC-SALES-013",
    progress: 28,
    current_step: "Disinstallazione bloatware",
  },
];

export default function DashboardPage() {
  const navigate = useNavigate();

  const [stats, setStats] = useState(() => normalizeStatsPayload(mockStats));
  const [statsLoading, setStatsLoading] = useState(false);

  const [recentActivity] = useState(() => mockRecentActivity);

  const [wizardsRunning, setWizardsRunning] = useState(() => mockWizardsRunning);
  const [wizardsLoading, setWizardsLoading] = useState(false);

  const chartData = useMemo(() => {
    const source = stats.grafico_settimanale?.length
      ? stats.grafico_settimanale
      : mockStats.grafico_settimanale;

    return source.map((row, idx) => ({
      label: row.week ?? `W-${idx + 1}`,
      configured: Number(row.configured ?? row.pc ?? row.value ?? 0),
    }));
  }, [stats.grafico_settimanale]);

  async function fetchStats() {
    setStatsLoading(true);
    try {
      const res = await client.get("/stats");
      const normalized = normalizeStatsPayload(res?.data);
      setStats(normalized);
    } catch {
      setStats(normalizeStatsPayload(mockStats));
    } finally {
      setStatsLoading(false);
    }
  }

  async function fetchRunningWizards() {
    setWizardsLoading(true);
    try {
      const res = await client.get("/wizards", {
        params: { stato: "in_esecuzione" },
      });
      const list = Array.isArray(res?.data) ? res.data : res?.data?.data;
      if (Array.isArray(list)) {
        const normalized = list.map((w, index) => ({
          id: w.id ?? `wiz_${index}`,
          name: w.name ?? w.nome ?? "Wizard",
          pc_name: w.pc_name ?? w.pc ?? w.pc_nome ?? "—",
          progress: Number(w.progress ?? w.percent ?? w.percentage ?? 0),
          current_step: w.current_step ?? w.step ?? w.step_name ?? "—",
        }));
        setWizardsRunning(normalized);
      } else {
        setWizardsRunning(mockWizardsRunning);
      }
    } catch {
      setWizardsRunning(mockWizardsRunning);
    } finally {
      setWizardsLoading(false);
    }
  }

  useEffect(() => {
    fetchStats();
    fetchRunningWizards();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useInterval(() => {
    fetchRunningWizards();
  }, 10_000);

  return (
    <div className="min-h-screen" style={{ backgroundColor: "#F0F4F8" }}>
      <Sidebar />

      <main className="ml-64 min-h-screen px-6 py-6">
        <div className="mx-auto w-full max-w-6xl space-y-6">
          <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
            <div>
              <h1 className="text-xl font-semibold text-slate-900">Dashboard</h1>
              <p className="mt-1 text-sm text-slate-600">
                Panoramica operativa di configurazioni, wizard e attività recenti.
              </p>
            </div>

            <div className="text-xs text-slate-500">
              {statsLoading ? "Aggiornamento statistiche…" : "Dati aggiornati"}
            </div>
          </div>

          {/* 1) STATISTICHE */}
          <section className="space-y-3">
            <div className="text-sm font-semibold text-slate-900">Statistiche</div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <div className="text-xs font-medium text-slate-500">
                      PC configurati questo mese
                    </div>
                    <div className="mt-2 text-3xl font-semibold text-slate-900">
                      {stats.pc_mese}
                    </div>
                  </div>
                  <div className="rounded-lg bg-slate-100 p-2 text-slate-700">
                    <Monitor className="h-5 w-5" />
                  </div>
                </div>
              </div>

              <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <div className="text-xs font-medium text-slate-500">Wizard attivi</div>
                    <div className="mt-2 text-3xl font-semibold text-slate-900">
                      {stats.wizard_attivi}
                    </div>
                  </div>
                  <div className="rounded-lg bg-slate-100 p-2 text-slate-700">
                    <Zap className="h-5 w-5" />
                  </div>
                </div>
              </div>

              <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <div className="text-xs font-medium text-slate-500">
                      Software più installato (mese)
                    </div>
                    <div className="mt-2 text-lg font-semibold text-slate-900">
                      {stats.software_top || "—"}
                    </div>
                  </div>
                  <div className="rounded-lg bg-slate-100 p-2 text-slate-700">
                    <Package className="h-5 w-5" />
                  </div>
                </div>
              </div>

              <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <div className="text-xs font-medium text-slate-500">
                      Configurazioni con errori
                    </div>
                    <div
                      className={[
                        "mt-2 text-3xl font-semibold",
                        stats.errori > 0 ? "text-red-600" : "text-slate-900",
                      ].join(" ")}
                    >
                      {stats.errori}
                    </div>
                  </div>
                  <div
                    className={[
                      "rounded-lg p-2",
                      stats.errori > 0 ? "bg-red-100 text-red-700" : "bg-slate-100 text-slate-700",
                    ].join(" ")}
                  >
                    <TriangleAlert className="h-5 w-5" />
                  </div>
                </div>

                {stats.errori > 0 ? (
                  <div className="mt-3 text-xs text-red-700">
                    Attenzione: ci sono configurazioni con esito “errore”.
                  </div>
                ) : (
                  <div className="mt-3 text-xs text-slate-500">
                    Nessun errore rilevato nelle ultime esecuzioni.
                  </div>
                )}
              </div>
            </div>
          </section>

          {/* 2) GRAFICO */}
          <section className="space-y-3">
            <div className="text-sm font-semibold text-slate-900">PC configurati (ultimi 30 giorni)</div>

            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <div className="h-72">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={chartData} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0" />
                    <XAxis dataKey="label" stroke="#64748B" tickLine={false} axisLine={{ stroke: "#E2E8F0" }} />
                    <YAxis stroke="#64748B" tickLine={false} axisLine={{ stroke: "#E2E8F0" }} allowDecimals={false} />
                    <Tooltip
                      cursor={{ fill: "rgba(15, 23, 42, 0.06)" }}
                      contentStyle={{
                        borderRadius: 12,
                        border: "1px solid #E2E8F0",
                      }}
                      labelStyle={{ color: "#0F172A" }}
                    />
                    <Bar dataKey="configured" fill="#2E75B6" radius={[8, 8, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </div>

              <div className="mt-3 text-xs text-slate-500">
                Nota: dati attualmente mockati; in produzione arrivano da GET /api/stats (campo grafico_settimanale).
              </div>
            </div>
          </section>

          {/* 3) ATTIVITÀ RECENTE */}
          <section className="space-y-3">
            <div className="flex items-center justify-between gap-3">
              <div className="text-sm font-semibold text-slate-900">Attività recente</div>
              <button
                type="button"
                onClick={() => navigate("/reports")}
                className="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white transition hover:bg-slate-800"
              >
                Vedi tutti i report
                <ArrowRight className="h-4 w-4" />
              </button>
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <div className="overflow-x-auto">
                <table className="min-w-full text-left text-sm">
                  <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                      <th className="px-4 py-3 font-semibold">Data</th>
                      <th className="px-4 py-3 font-semibold">Tecnico</th>
                      <th className="px-4 py-3 font-semibold">Nome PC</th>
                      <th className="px-4 py-3 font-semibold">Stato</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {recentActivity.slice(0, 5).map((row) => (
                      <tr key={row.id} className="text-slate-700">
                        <td className="whitespace-nowrap px-4 py-3 text-slate-600">
                          {formatDateTime(row.date)}
                        </td>
                        <td className="whitespace-nowrap px-4 py-3">{row.tecnico}</td>
                        <td className="whitespace-nowrap px-4 py-3 font-medium text-slate-900">
                          {row.pc}
                        </td>
                        <td className="whitespace-nowrap px-4 py-3">
                          <span className={statusBadgeClass(row.stato)}>{row.stato}</span>
                        </td>
                      </tr>
                    ))}
                    {recentActivity.length === 0 ? (
                      <tr>
                        <td className="px-4 py-6 text-center text-sm text-slate-500" colSpan={4}>
                          Nessuna attività recente.
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          {/* 4) WIZARD IN ESECUZIONE */}
          {wizardsRunning?.length > 0 ? (
            <section className="space-y-3">
              <div className="flex items-center justify-between">
                <div className="text-sm font-semibold text-slate-900">Wizard in esecuzione</div>
                <div className="text-xs text-slate-500">
                  {wizardsLoading ? "Aggiornamento…" : "Polling ogni 10s"}
                </div>
              </div>

              <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {wizardsRunning.map((w) => (
                  <div key={w.id} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <div className="text-sm font-semibold text-slate-900">{w.name}</div>
                        <div className="mt-1 text-xs text-slate-600">
                          PC: <span className="font-medium text-slate-800">{w.pc_name}</span>
                        </div>
                        <div className="mt-1 text-xs text-slate-500">Step: {w.current_step}</div>
                      </div>

                      <div className="text-right">
                        <div className="text-sm font-semibold text-slate-900">
                          {Math.max(0, Math.min(100, Number(w.progress) || 0))}%
                        </div>
                        <div className="text-xs text-slate-500">Progresso</div>
                      </div>
                    </div>

                    <div className="mt-3">
                      <div className="h-2 w-full rounded-full bg-slate-100">
                        <div
                          className="h-2 rounded-full bg-sky-600 transition-all"
                          style={{
                            width: `${Math.max(0, Math.min(100, Number(w.progress) || 0))}%`,
                          }}
                        />
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </section>
          ) : null}
        </div>
      </main>
    </div>
  );
}
```


***

## Checklist operativa (per non incastrarti)

- Verifica che `client` sia un Axios instance con `baseURL: "/api"` (così `client.get("/stats")` → `/api/stats`) come nello scaffold.[^1_1]
- Assicurati che le route `"/dashboard"`, `"/reports"` esistano nel router e che il layout generale non faccia già una sidebar (altrimenti avrai sidebar doppia).[^1_1]
- Se l’endpoint `GET /api/wizards?stato=in_esecuzione` ritorna `{ data: [...] }` (pagination Laravel), il codice lo gestisce già (`res.data.data`).[^1_1]


## Note sicurezza (da “avvocato del diavolo”)

- Il polling ogni 10s può diventare costoso lato API se hai molti tecnici con dashboard aperta: valuta ETag/Cache-Control o in futuro SSE/WebSocket per lo stato wizard.[^1_3]
- Se stai usando token in `localStorage` (Zustand), resta valido il rischio XSS: evita `dangerouslySetInnerHTML`, imposta CSP, sanitizza input e riduci dipendenze non necessarie.[^1_4][^1_1]

Vuoi che aggiunga anche una `TopBar` (a destra) con ricerca rapida e selettore “Team / Mio” per filtrare attività e grafico, oppure preferisci mantenerla minimale per MVP?
<span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_19][^1_20][^1_21][^1_22][^1_23][^1_24][^1_25][^1_26][^1_27][^1_28][^1_29][^1_30][^1_31][^1_32][^1_33][^1_34][^1_35][^1_36][^1_37][^1_38][^1_39][^1_5][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: 0013-SetupprogettoReact.md

[^1_2]: https://recharts.github.io/en-US/examples/

[^1_3]: 0001-projectdescription.txt

[^1_4]: 0014-Login.md

[^1_5]: 0012-apiendpointwindows.md

[^1_6]: 0011-controllercrud.md

[^1_7]: 0010-autenticazione.md

[^1_8]: 0009-scaffoldprogetto.md

[^1_9]: 0008-db.md

[^1_10]: 0007-cheatsheet.md

[^1_11]: 0006-viteconfigjs.md

[^1_12]: 0005-filedotenv.md

[^1_13]: 0004-Strutturacartelle.md

[^1_14]: 0003-setupiniziale.md

[^1_15]: 0002-ricerca-iniziale.md

[^1_16]: https://tailwindcss.com/docs/height

[^1_17]: https://tailwindcss.com/docs/min-height

[^1_18]: https://tailwindcss.com/docs/flex-shrink

[^1_19]: https://tailwindcss.com/docs/top-right-bottom-left

[^1_20]: https://v1.tailwindcss.com/docs/height

[^1_21]: https://tailwindcss.com/docs/flex

[^1_22]: https://tailwindcss.com/docs/min-width

[^1_23]: https://tailwindcss.com/docs/width

[^1_24]: https://tailwindcss.com/docs/overflow

[^1_25]: https://tailwindcss.com/docs/position

[^1_26]: https://www.dhiwise.com/post/simplify-data-visualization-with-recharts-responsivecontainer

[^1_27]: https://www.reddit.com/r/reactjs/comments/12e0c8n/what_is_a_good_way_to_make_a_bar_chart_in/

[^1_28]: https://stackoverflow.com/questions/47614196/rechart-responsive-container-does-not-render

[^1_29]: https://refine.dev/blog/recharts/

[^1_30]: https://app.unpkg.com/lucide-react@0.436.0/files/dist/umd/lucide-react.js.map

[^1_31]: https://gist.github.com/BjornDCode/5cb836a6b23638d6d02f5cb6ed59a04a

[^1_32]: https://natehaebigkerber.substack.com/p/building-beautiful-graphs-in-react

[^1_33]: https://lucide.dev/icons/triangle-alert

[^1_34]: https://tallpad.com/series/tailwind/lessons/build-a-sidebar-navigation

[^1_35]: https://stackblitz.com/edit/react-sn3kjj

[^1_36]: https://tamagui.dev/ui/lucide-icons

[^1_37]: https://tailkits.com/blog/tailwind-fixed-positioning/

[^1_38]: https://github.com/recharts/recharts/issues/2166

[^1_39]: https://lucide.dev/guide/packages/lucide-react


---

# non mi piace,

in C:\\xampp\\htdocs\\windeploy\\frontend\\src\\components\\layout ho un file che si chiama: MainLayout.jsx
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
          ```          <div className="text-lg font-semibold">WinDeploy</div>          ```
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

Ok: **non tocco più la Sidebar separata** e mi allineo al tuo `MainLayout.jsx` esistente (con `Outlet`). Quindi la dashboard deve essere **solo contenuto** (niente sidebar, niente wrapper `ml-64`, niente layout “fixed”), con palette coerente col tuo tema scuro (`bg-slate-950`) e componenti più “compatti” e professionali.[^2_1]

Sotto trovi **il file completo** `DashboardPage.jsx` (stesso nome che mi hai chiesto prima), pronto da incollare in `C:\xampp\htdocs\windeploy\frontend\src\pages\Dashboard\DashboardPage.jsx` (o nel path che usi nel router). Usa Recharts, Lucide e chiama le API:

- `GET /api/stats` → `client.get("/stats")`
- `GET /api/wizards?stato=in_esecuzione` → `client.get("/wizards", { params: { stato: "in_esecuzione" }})`
come da scaffold con `baseURL: "api"` sull’Axios client.[^2_1]


## Struttura file coinvolti

- (già esistente) `src/components/layout/MainLayout.jsx` → contiene Sidebar + `<Outlet />`[^2_1]
- (da aggiornare) `src/pages/Dashboard/DashboardPage.jsx` → **solo contenuto pagina** (questa risposta)[^2_1]

***

## File: `src/pages/Dashboard/DashboardPage.jsx`

```jsx
import React, { useEffect, useMemo, useRef, useState } from "react";
import {
  Monitor,
  Zap,
  Package,
  TriangleAlert,
  Activity,
  ArrowRight,
} from "lucide-react";
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
} from "recharts";
import { useNavigate } from "react-router-dom";
import client from "../../api/client";

function useInterval(callback, delay) {
  const savedCallback = useRef(callback);

  useEffect(() => {
    savedCallback.current = callback;
  }, [callback]);

  useEffect(() => {
    if (delay === null || delay === undefined) return;
    const id = setInterval(() => savedCallback.current?.(), delay);
    return () => clearInterval(id);
  }, [delay]);
}

function clampNumber(value, min, max) {
  const n = Number(value);
  if (Number.isNaN(n)) return min;
  return Math.max(min, Math.min(max, n));
}

function formatDateTime(value) {
  try {
    const d = new Date(value);
    return new Intl.DateTimeFormat("it-IT", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    }).format(d);
  } catch {
    return String(value ?? "");
  }
}

function statusBadgeClass(status) {
  const base =
    "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset";
  if (status === "completato")
    return `${base} bg-emerald-500/10 text-emerald-200 ring-emerald-500/20`;
  if (status === "in_esecuzione")
    return `${base} bg-sky-500/10 text-sky-200 ring-sky-500/20`;
  if (status === "errore")
    return `${base} bg-red-500/10 text-red-200 ring-red-500/20`;
  return `${base} bg-slate-500/10 text-slate-200 ring-slate-500/20`;
}

function normalizeStatsPayload(payload) {
  return {
    pc_mese: payload?.pc_mese ?? 0,
    wizard_attivi: payload?.wizard_attivi ?? 0,
    software_top: payload?.software_top ?? "—",
    errori: payload?.errori ?? 0,
    grafico_settimanale: Array.isArray(payload?.grafico_settimanale)
      ? payload.grafico_settimanale
      : [],
  };
}

const mockStats = {
  pc_mese: 18,
  wizard_attivi: 3,
  software_top: "Google Chrome",
  errori: 2,
  grafico_settimanale: [
    { week: "W-4", configured: 3 },
    { week: "W-3", configured: 5 },
    { week: "W-2", configured: 4 },
    { week: "W-1", configured: 6 },
  ],
};

const mockRecentActivity = [
  {
    id: "rep_001",
    date: new Date(Date.now() - 1000 * 60 * 40).toISOString(),
    tecnico: "Marco R.",
    pc: "PC-ACCT-017",
    stato: "completato",
  },
  {
    id: "rep_002",
    date: new Date(Date.now() - 1000 * 60 * 110).toISOString(),
    tecnico: "Sara L.",
    pc: "PC-HR-004",
    stato: "errore",
  },
  {
    id: "rep_003",
    date: new Date(Date.now() - 1000 * 60 * 200).toISOString(),
    tecnico: "Marco R.",
    pc: "PC-SALES-012",
    stato: "completato",
  },
  {
    id: "rep_004",
    date: new Date(Date.now() - 1000 * 60 * 310).toISOString(),
    tecnico: "Luca P.",
    pc: "PC-OPS-009",
    stato: "in_esecuzione",
  },
  {
    id: "rep_005",
    date: new Date(Date.now() - 1000 * 60 * 480).toISOString(),
    tecnico: "Giulia C.",
    pc: "PC-ENG-021",
    stato: "completato",
  },
];

const mockWizardsRunning = [
  {
    id: "wiz_101",
    name: "Onboarding Finance",
    pc_name: "PC-FIN-022",
    progress: 62,
    current_step: "Installazione software",
  },
  {
    id: "wiz_102",
    name: "Sales Standard",
    pc_name: "PC-SALES-013",
    progress: 28,
    current_step: "Disinstallazione bloatware",
  },
];

function Panel({ title, icon: Icon, rightSlot, children }) {
  return (
    <div className="rounded-xl border border-slate-800 bg-slate-900/60 shadow-sm">
      <div className="flex items-center justify-between gap-3 border-b border-slate-800 px-4 py-3">
        <div className="flex items-center gap-2">
          {Icon ? <Icon className="h-4 w-4 text-slate-300" /> : null}
          <div className="text-sm font-semibold text-white">{title}</div>
        </div>
        {rightSlot}
      </div>
      <div className="p-4">{children}</div>
    </div>
  );
}

function StatCard({ label, value, icon: Icon, tone = "default", helper }) {
  const toneMap = {
    default: {
      box: "bg-slate-900/70 border-slate-800",
      icon: "bg-slate-800 text-slate-200",
      value: "text-white",
      helper: "text-slate-400",
    },
    danger: {
      box: "bg-red-950/30 border-red-900/40",
      icon: "bg-red-500/15 text-red-200",
      value: "text-red-200",
      helper: "text-red-200/70",
    },
  };

  const t = toneMap[tone] ?? toneMap.default;

  return (
    <div className={`rounded-xl border p-4 ${t.box}`}>
      <div className="flex items-start justify-between gap-4">
        <div>
          <div className="text-xs font-medium text-slate-400">{label}</div>
          <div className={`mt-2 text-3xl font-semibold ${t.value}`}>{value}</div>
          {helper ? <div className={`mt-2 text-xs ${t.helper}`}>{helper}</div> : null}
        </div>
        <div className={`rounded-lg p-2 ${t.icon}`}>
          {Icon ? <Icon className="h-5 w-5" /> : null}
        </div>
      </div>
    </div>
  );
}

export default function DashboardPage() {
  const navigate = useNavigate();

  const [stats, setStats] = useState(() => normalizeStatsPayload(mockStats));
  const [statsLoading, setStatsLoading] = useState(false);
  const [statsUsingMock, setStatsUsingMock] = useState(true);

  const [recentActivity] = useState(() => mockRecentActivity);

  const [wizardsRunning, setWizardsRunning] = useState(() => mockWizardsRunning);
  const [wizardsLoading, setWizardsLoading] = useState(false);
  const [wizardsUsingMock, setWizardsUsingMock] = useState(true);

  const chartData = useMemo(() => {
    const rows =
      stats.grafico_settimanale?.length > 0
        ? stats.grafico_settimanale
        : mockStats.grafico_settimanale;

    return rows.map((row, idx) => ({
      label: row.week ?? `W-${idx + 1}`,
      configured: Number(row.configured ?? row.pc ?? row.value ?? 0),
    }));
  }, [stats.grafico_settimanale]);

  async function fetchStats() {
    setStatsLoading(true);
    try {
      const res = await client.get("/stats");
      const normalized = normalizeStatsPayload(res?.data);
      setStats(normalized);
      setStatsUsingMock(false);
    } catch {
      setStats(normalizeStatsPayload(mockStats));
      setStatsUsingMock(true);
    } finally {
      setStatsLoading(false);
    }
  }

  async function fetchRunningWizards() {
    setWizardsLoading(true);
    try {
      const res = await client.get("/wizards", {
        params: { stato: "in_esecuzione" },
      });

      const list = Array.isArray(res?.data) ? res.data : res?.data?.data;
      if (Array.isArray(list)) {
        const normalized = list.map((w, index) => ({
          id: w.id ?? `wiz_${index}`,
          name: w.name ?? w.nome ?? "Wizard",
          pc_name: w.pc_name ?? w.pc ?? w.pc_nome ?? "—",
          progress: clampNumber(w.progress ?? w.percent ?? w.percentage ?? 0, 0, 100),
          current_step: w.current_step ?? w.step ?? w.step_name ?? "—",
        }));
        setWizardsRunning(normalized);
        setWizardsUsingMock(false);
      } else {
        setWizardsRunning(mockWizardsRunning);
        setWizardsUsingMock(true);
      }
    } catch {
      setWizardsRunning(mockWizardsRunning);
      setWizardsUsingMock(true);
    } finally {
      setWizardsLoading(false);
    }
  }

  useEffect(() => {
    fetchStats();
    fetchRunningWizards();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useInterval(() => {
    fetchRunningWizards();
  }, 10_000);

  const hasErrors = Number(stats.errori) > 0;

  return (
    <div className="space-y-6">
      <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
          <h1 className="text-xl font-semibold text-white">Dashboard</h1>
          <p className="mt-1 text-sm text-slate-400">
            Stato configurazioni, wizard e attività del team.
          </p>
        </div>

        <div className="text-xs text-slate-400">
          {statsLoading ? "Aggiornamento…" : "Pronto"}
        </div>
      </div>

      {/* 1) STATISTICHE */}
      <Panel title="Statistiche" icon={Activity}>
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
          <StatCard
            label="PC configurati questo mese"
            value={stats.pc_mese}
            icon={Monitor}
          />
          <StatCard
            label="Wizard attivi"
            value={stats.wizard_attivi}
            icon={Zap}
          />
          <StatCard
            label="Software più installato (mese)"
            value={stats.software_top || "—"}
            icon={Package}
            helper={statsUsingMock ? "Mock data" : "Live"}
          />
          <StatCard
            label="Configurazioni con errori"
            value={stats.errori}
            icon={TriangleAlert}
            tone={hasErrors ? "danger" : "default"}
            helper={
              hasErrors
                ? "Verifica i report: ci sono esecuzioni con stato “errore”."
                : "Nessun errore rilevato."
            }
          />
        </div>
      </Panel>

      {/* 2) GRAFICO */}
      <Panel
        title="PC configurati per settimana (ultimi 30 giorni)"
        icon={Monitor}
        rightSlot={
          <div className="text-xs text-slate-400">
            {statsUsingMock ? "Mock" : "Live"}
          </div>
        }
      >
        <div className="h-72">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={chartData} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#1F2937" />
              <XAxis
                dataKey="label"
                stroke="#94A3B8"
                tickLine={false}
                axisLine={{ stroke: "#1F2937" }}
              />
              <YAxis
                stroke="#94A3B8"
                tickLine={false}
                axisLine={{ stroke: "#1F2937" }}
                allowDecimals={false}
              />
              <Tooltip
                cursor={{ fill: "rgba(148, 163, 184, 0.08)" }}
                contentStyle={{
                  background: "rgba(15, 23, 42, 0.95)",
                  border: "1px solid rgba(51, 65, 85, 0.8)",
                  borderRadius: 12,
                  color: "#E2E8F0",
                }}
                labelStyle={{ color: "#E2E8F0" }}
              />
              <Bar dataKey="configured" fill="#38BDF8" radius={[8, 8, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>

        <div className="mt-3 text-xs text-slate-500">
          Fonte prevista: GET /api/stats → grafico_settimanale.
        </div>
      </Panel>

      {/* 3) ATTIVITÀ RECENTE */}
      <Panel
        title="Attività recente"
        icon={Activity}
        rightSlot={
          <button
            type="button"
            onClick={() => navigate("/reports")}
            className="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-sky-500"
          >
            Vedi tutti i report
            <ArrowRight className="h-4 w-4" />
          </button>
        }
      >
        <div className="overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead className="text-xs uppercase text-slate-400">
              <tr className="border-b border-slate-800">
                <th className="px-3 py-2 font-semibold">Data</th>
                <th className="px-3 py-2 font-semibold">Tecnico</th>
                <th className="px-3 py-2 font-semibold">Nome PC</th>
                <th className="px-3 py-2 font-semibold">Stato</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {recentActivity.slice(0, 5).map((row) => (
                <tr key={row.id} className="text-slate-200">
                  <td className="whitespace-nowrap px-3 py-2 text-slate-300">
                    {formatDateTime(row.date)}
                  </td>
                  <td className="whitespace-nowrap px-3 py-2">{row.tecnico}</td>
                  <td className="whitespace-nowrap px-3 py-2 font-medium text-white">
                    {row.pc}
                  </td>
                  <td className="whitespace-nowrap px-3 py-2">
                    <span className={statusBadgeClass(row.stato)}>{row.stato}</span>
                  </td>
                </tr>
              ))}

              {recentActivity.length === 0 ? (
                <tr>
                  <td className="px-3 py-6 text-center text-sm text-slate-400" colSpan={4}>
                    Nessuna attività recente.
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </Panel>

      {/* 4) WIZARD IN ESECUZIONE */}
      {wizardsRunning?.length > 0 ? (
        <Panel
          title="Wizard in esecuzione"
          icon={Zap}
          rightSlot={
            <div className="text-xs text-slate-400">
              {wizardsLoading ? "Aggiornamento…" : wizardsUsingMock ? "Mock" : "Live"} · refresh 10s
            </div>
          }
        >
          <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            {wizardsRunning.map((w) => (
              <div key={w.id} className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
                <div className="flex items-start justify-between gap-4">
                  <div className="min-w-0">
                    <div className="truncate text-sm font-semibold text-white">{w.name}</div>
                    <div className="mt-1 text-xs text-slate-400">
                      PC: <span className="font-medium text-slate-200">{w.pc_name}</span>
                    </div>
                    <div className="mt-1 truncate text-xs text-slate-500">
                      Step: {w.current_step}
                    </div>
                  </div>

                  <div className="shrink-0 text-right">
                    <div className="text-sm font-semibold text-slate-100">{w.progress}%</div>
                    <div className="text-xs text-slate-500">Progresso</div>
                  </div>
                </div>

                <div className="mt-3">
                  <div className="h-2 w-full rounded-full bg-slate-800">
                    <div
                      className="h-2 rounded-full bg-sky-500 transition-all"
                      style={{ width: `${clampNumber(w.progress, 0, 100)}%` }}
                    />
                  </div>
                </div>
              </div>
            ))}
          </div>

          <div className="mt-3 text-xs text-slate-500">
            Fonte prevista: GET /api/wizards?stato=in_esecuzione.
          </div>
        </Panel>
      ) : null}
    </div>
  );
}
```


***

## Perché la tua versione “non piace” (e cosa ho corretto)

- Il tuo `MainLayout.jsx` **già definisce** il layout e il tema (sidebar + `bg-slate-950` + `max-w-6xl`), quindi la dashboard deve essere “content-only”; altrimenti ti trovi spaziature strane e un look incoerente.[^2_1]
- Ho portato la dashboard su uno stile **dark coherent** (cards `bg-slate-900/60`, bordi `border-slate-800`) così non sembra una pagina “bianca incollata” sopra un’app scura.[^2_1]
- Recharts in dark mode: tooltip scuro e grid scura per leggibilità (Recharts permette styling via props e tooltip `contentStyle`).[^2_2]


## Checklist rapida

- Metti il file in `src/pages/Dashboard/DashboardPage.jsx` e verifica che il router importi questo path.[^2_1]
- Controlla che `src/api/client.js` sia davvero `baseURL: "api"` (così `/stats` = `/api/stats`).[^2_1]
- Se l’API wizards ritorna paginata Laravel (`{ data: [...] }`), è già gestito con `res.data.data`.[^2_1]

Domanda secca: vuoi che le card statistiche siano **più “enterprise”** (con mini-trend “+12% vs mese scorso” e sparkline) o preferisci restare minimal fino a quando non hai i dati reali da `/api/stats`?
<span style="display:none">[^2_3]</span>

<div align="center">⁂</div>

[^2_1]: 0013-SetupprogettoReact.md

[^2_2]: https://recharts.github.io/en-US/examples/

[^2_3]: image.jpg

