export function formatDateTime(value) {
  try {
    if (!value) return "";
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

export function formatDurationSeconds(totalSeconds) {
  const s = Number(totalSeconds);
  if (!Number.isFinite(s) || s < 0) return "-";
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const r = Math.floor(s % 60);
  if (h > 0) return `${h}h ${m}m`;
  if (m > 0) return `${m}m ${r}s`;
  return `${r}s`;
}
