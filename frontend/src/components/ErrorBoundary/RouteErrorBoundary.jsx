// Path: frontend/src/components/ErrorBoundary/RouteErrorBoundary.jsx
import React from "react";
import { ErrorBoundary } from "react-error-boundary";
import { useNavigate } from "react-router-dom";

function RouteFallback({ error, resetErrorBoundary }) {
  const navigate = useNavigate();
  const showTechDetails = Boolean(import.meta?.env?.DEV);

  return (
    <div className="w-full">
      <div className="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-amber-950">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="text-sm font-semibold">Questa pagina ha riscontrato un errore.</div>

          <div className="flex flex-col gap-2 sm:flex-row">
            <button
              type="button"
              onClick={resetErrorBoundary}
              className="inline-flex items-center justify-center rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-500"
            >
              Riprova
            </button>

            <button
              type="button"
              onClick={() => navigate("/dashboard")}
              className="inline-flex items-center justify-center rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-100"
            >
              Vai alla dashboard
            </button>
          </div>
        </div>

        {showTechDetails ? (
          <details className="mt-3 rounded-lg border border-amber-200 bg-white p-3">
            <summary className="cursor-pointer text-sm font-medium">Dettagli tecnici (solo sviluppo)</summary>
            <pre className="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words text-xs text-slate-800">
              {String(error?.stack || error?.message || "")}
            </pre>
          </details>
        ) : null}
      </div>
    </div>
  );
}

export default function RouteErrorBoundary({ children, resetKeys }) {
  return (
    <ErrorBoundary FallbackComponent={RouteFallback} resetKeys={resetKeys}>
      {children}
    </ErrorBoundary>
  );
}
