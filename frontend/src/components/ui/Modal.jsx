import React, { useEffect } from "react";
import { X } from "lucide-react";

export default function Modal({
  open,
  title,
  description,
  children,
  footer,
  onClose,
  widthClassName = "max-w-2xl",
}) {
  useEffect(() => {
    if (!open) return;

    function onKeyDown(e) {
      if (e.key === "Escape") onClose?.();
    }

    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
      role="dialog"
      aria-modal="true"
    >
      <div className={`w-full ${widthClassName} rounded-xl border border-slate-800 bg-slate-950 shadow-xl`}>
        <div className="flex items-start justify-between gap-3 border-b border-slate-800 px-5 py-4">
          <div className="min-w-0">
            <div className="truncate text-base font-semibold text-slate-100">{title}</div>
            {description ? (
              <div className="mt-1 text-sm text-slate-400">{description}</div>
            ) : null}
          </div>

          <button
            type="button"
            onClick={onClose}
            className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 text-slate-200 hover:bg-slate-900"
            aria-label="Close modal"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="px-5 py-4">{children}</div>

        {footer ? <div className="border-t border-slate-800 px-5 py-4">{footer}</div> : null}
      </div>
    </div>
  );
}
