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

  // Prefer canonical schema: { schema_version, wizard, pc, hardware, execution, steps, summary }
  const canonical = p.schema_version ? p : p;

  const wizardRaw = canonical.wizard ?? canonical.wizardData ?? canonical.data?.wizard ?? {};
  const wizard = {
    id: wizardRaw.id ?? canonical.wizard_id ?? canonical.wizardId ?? null,
    name: wizardRaw.name ?? wizardRaw.nome ?? canonical.nome ?? canonical.name ?? "Wizard",
    code: wizardRaw.code ?? wizardRaw.codice_univoco ?? canonical.codice_univoco ?? canonical.code ?? "",
    status: normalizeStatus(wizardRaw.stato ?? wizardRaw.status ?? canonical.stato ?? canonical.status),
  };

  const pcRaw = canonical.pc ?? canonical.machine ?? canonical.client ?? {};
  const pc = {
    name: pcRaw.name ?? pcRaw.nome ?? canonical.pc_nome ?? canonical.pcName ?? null,
  };

  const hwRaw = canonical.hardware ?? canonical.hardwareinfo ?? canonical.hw ?? canonical.pcinfo ?? {};
  const hardware = {
    cpu: hwRaw.cpu ?? null,
    ramGb: hwRaw.ram_gb ?? hwRaw.ramGb ?? hwRaw.ram ?? null,
    diskGb: hwRaw.disco_gb ?? hwRaw.discoGb ?? hwRaw.disco ?? hwRaw.disk_gb ?? hwRaw.diskGb ?? null,
    windowsVersion: hwRaw.windows_version ?? hwRaw.windowsVersion ?? hwRaw.windows ?? null,
  };

  // Steps: prefer canonical steps array, fallback to legacy log/logdettagliato
  const stepsRaw = safeArray(canonical.steps ?? canonical.execution?.logdettagliato ?? canonical.logdettagliato ?? canonical.log ?? []);
  const steps = stepsRaw
    .map((s, idx) => {
      const name = s.name ?? s.nome ?? s.step ?? s.titolo ?? `Step ${idx + 1}`;
      const status = normalizeStepStatus(s.status ?? s.stato ?? s.esito ?? s.result);
      const message = s.message ?? s.messaggio ?? s.dettaglio ?? s.detail ?? "";
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

  const summaryRaw = canonical.summary ?? canonical.sommario ?? canonical.sommariofinale ?? null;
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
  // defer first fetch to avoid synchronous setState inside the effect body
  const t = window.setTimeout(() => fetchOnce(), 0);
  return () => window.clearTimeout(t);
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
