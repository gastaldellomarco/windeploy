import React from "react";

export default function Tabs({ tabs, activeKey, onChange }) {
  return (
    <div className="inline-flex rounded-xl border border-slate-800 bg-slate-900/40 p-1">
      {tabs.map((t) => {
        const active = t.key === activeKey;
        return (
          <button
            key={t.key}
            type="button"
            onClick={() => onChange?.(t.key)}
            className={[
              "rounded-lg px-3 py-2 text-sm font-semibold transition",
              active ? "bg-slate-950 text-white" : "text-slate-300 hover:bg-slate-950/40",
            ].join(" ")}
          >
            {t.label}
          </button>
        );
      })}
    </div>
  );
}
