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

  // Possibile shape: report + executionLog + wizard + user (come ReportController with executionLog.wizard.user). [file:7]
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
      if (isAdmin && technicianId !== "ALL") params.tecnicoid = technicianId;
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
      // Qui usiamo direttamente l’endpoint download come src iframe per evitare di gestire htmlcontent in JSON lato client. [file:7]
      const htmlUrl = `/api/reports/${encodeURIComponent(reportId)}/download`;
      setViewModal({ open: true, reportId, htmlUrl, loading: false });
    } catch {
      setViewModal({ open: true, reportId, htmlUrl: "", loading: false });
      toast.error("Impossibile aprire il report.");
    }
  }

  function downloadReport(reportId) {
    if (!reportId) return;
    window.open(`/api/reports/${encodeURIComponent(reportId)}/download`, "_blank", "noopener,noreferrer");
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold text-slate-100">Reports</h1>
          <p className="mt-1 text-sm text-slate-400">Storico configurazioni completate e report HTML scaricabili.</p>
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
              ID: <span className="font-mono text-slate-200">{viewModal.reportId || "-"}</span>
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
