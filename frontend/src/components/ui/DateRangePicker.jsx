import React from "react";

export default function DateRangePicker({ from, to, onChange, disabled }) {
  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <div>
        <label className="block text-xs font-medium text-slate-300" htmlFor="dateFrom">
          Da
        </label>
        <input
          id="dateFrom"
          type="date"
          value={from || ""}
          disabled={disabled}
          onChange={(e) => onChange?.({ from: e.target.value || null, to })}
          className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40 disabled:opacity-50"
        />
      </div>
      <div>
        <label className="block text-xs font-medium text-slate-300" htmlFor="dateTo">
          A
        </label>
        <input
          id="dateTo"
          type="date"
          value={to || ""}
          disabled={disabled}
          onChange={(e) => onChange?.({ from, to: e.target.value || null })}
          className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40 disabled:opacity-50"
        />
      </div>
    </div>
  );
}
