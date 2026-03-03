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
					<div className="text-xs uppercase tracking-wide text-slate-400">Monitor</div>
					<h1 className="mt-1 truncate text-xl font-semibold text-slate-100">{data?.wizard?.name ?? "Wizard"}</h1>

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
					<div className={`rounded-2xl border px-5 py-3 text-base font-semibold ${badge.cls}`}>{badge.label}</div>

					<div className="flex items-center gap-2">
						<button type="button" onClick={actions.refetch} className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900">Aggiorna</button>

						<button type="button" onClick={() => (ui.isPolling ? actions.stopPolling() : actions.startPolling())} className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900">{ui.isPolling ? "Ferma polling" : "Avvia polling"}</button>

						<div className="inline-flex items-center rounded px-3 py-2 text-xs text-slate-400">{ui.isPolling ? "Polling: 5s" : "Polling: off"}</div>

						<NavLink to="/wizards" className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900">Torna ai wizard</NavLink>
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
					<div className="mt-3 text-xs text-slate-500">{ui.lastUpdatedAt ? `Ultimo aggiornamento: ${formatTs(ui.lastUpdatedAt)}` : "—"}{ui.isPolling && !showReportCta ? " • Polling ogni 5s" : " • Polling fermo"}</div>
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
						<button type="button" onClick={() => navigate(`/reports/${id}`)} className="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500">Vedi Report</button>
					) : null}
				</div>

				{ui.isLoading ? (
					<div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4 text-sm text-slate-400">Caricamento monitor...</div>
				) : null}

				{!ui.isLoading && (data?.steps?.length ?? 0) === 0 ? (
					<div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4 text-sm text-slate-400">Nessuno step ancora ricevuto.</div>
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
					<div className="mt-1 text-sm text-emerald-200/80">Il PC ha terminato l’esecuzione del wizard. Puoi scaricare il report o avviare una nuova configurazione.</div>

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
						<a href={`/api/wizards/${id}/report.html`} className="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Scarica Report HTML</a>

						<NavLink to="/wizards/new" className="inline-flex items-center justify-center rounded-lg border border-emerald-500/30 bg-slate-950/30 px-4 py-2 text-sm font-semibold text-emerald-100 hover:bg-slate-950/50">Configura un altro PC</NavLink>
					</div>

					<div className="mt-3 text-xs text-emerald-200/70">Suggerimento: se vuoi evitare link diretti non autenticati al report, esponi il download solo tramite endpoint protetto (token utente).</div>
				</div>
			) : null}
		</div>
	);
}
