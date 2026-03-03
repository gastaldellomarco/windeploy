import React from "react";
import Modal from "./Modal";

export default function ConfirmDialog({
  open,
  title = "Conferma azione",
  description,
  confirmLabel = "Conferma",
  cancelLabel = "Annulla",
  danger = false,
  loading = false,
  onConfirm,
  onClose,
}) {
  return (
    <Modal
      open={open}
      title={title}
      description={description}
      onClose={loading ? undefined : onClose}
      widthClassName="max-w-md"
      footer={
        <div className="flex items-center justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            disabled={loading}
            className="rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900 disabled:opacity-50"
          >
            {cancelLabel}
          </button>
          <button
            type="button"
            onClick={onConfirm}
            disabled={loading}
            className={[
              "rounded-lg px-3 py-2 text-sm font-semibold text-white disabled:opacity-50",
              danger ? "bg-rose-600 hover:bg-rose-500" : "bg-sky-600 hover:bg-sky-500",
            ].join(" ")}
          >
            {loading ? "Operazione..." : confirmLabel}
          </button>
        </div>
      }
    >
      <div className="text-sm text-slate-200">
        {description || "Sei sicuro di voler continuare? Questa operazione potrebbe essere irreversibile."}
      </div>
    </Modal>
  );
}
