import React from "react";

export default function ToggleSwitch({ checked, disabled, onChange, label }) {
  return (
    <div className="inline-flex items-center gap-2">
      <button
        type="button"
        role="switch"
        aria-checked={checked}
        onClick={() => !disabled && onChange?.(!checked)}
        disabled={disabled}
        className={[
          "relative inline-flex h-6 w-11 items-center rounded-full border transition",
          disabled ? "opacity-50 cursor-not-allowed" : "cursor-pointer",
          checked ? "bg-emerald-600/30 border-emerald-500/40" : "bg-slate-800 border-slate-700",
        ].join(" ")}
      >
        <span
          className={[
            "inline-block h-5 w-5 transform rounded-full bg-white transition",
            checked ? "translate-x-5" : "translate-x-1",
          ].join(" ")}
        />
      </button>
      {label ? <span className="text-xs text-slate-300">{label}</span> : null}
    </div>
  );
}
