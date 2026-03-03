import React from "react";

export default function Badge({ children, tone = "slate" }) {
  const map = {
    slate: "bg-slate-500/10 text-slate-200 ring-slate-500/20",
    sky: "bg-sky-500/10 text-sky-200 ring-sky-500/20",
    emerald: "bg-emerald-500/10 text-emerald-200 ring-emerald-500/20",
    amber: "bg-amber-500/10 text-amber-200 ring-amber-500/20",
    rose: "bg-rose-500/10 text-rose-200 ring-rose-500/20",
    violet: "bg-violet-500/10 text-violet-200 ring-violet-500/20",
  };

  return (
    <span
      className={[
        "inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset",
        map[tone] || map.slate,
      ].join(" ")}
    >
      {children}
    </span>
  );
}
