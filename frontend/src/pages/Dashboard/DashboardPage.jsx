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
