// Path: frontend/src/components/ErrorBoundary/GlobalErrorBoundary.jsx
import React from "react";
import { ErrorBoundary } from "react-error-boundary";

const API_ENDPOINT = "/api/errors/log";

function buildPayload(error, componentStack) {
  return {
    message: error?.message || "Unknown error",
    stack: error?.stack || null,
    component_stack: componentStack || null,
    url: window.location.href,
    user_agent: navigator.userAgent,
    timestamp: new Date().toISOString(),
  };
}

function fireAndForgetLog(payload) {
  // English comment: Never block fallback rendering; ignore all network errors silently.
  try {
    const body = JSON.stringify(payload);

    if (typeof navigator !== "undefined" && typeof navigator.sendBeacon === "function") {
      const blob = new Blob([body], { type: "application/json" });
      navigator.sendBeacon(API_ENDPOINT, blob);
      return;
    }

    void fetch(API_ENDPOINT, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body,
      keepalive: true,
    }).catch(() => {});
  } catch {
    // ignore
  }
}

function GlobalFallback({ error, componentStack }) {
  const showTechDetails = Boolean(import.meta?.env?.DEV);

  return (
    <div className="min-h-screen w-full" style={{ backgroundColor: "#F0F4F8" }}>
      <div className="mx-auto flex min-h-screen max-w-2xl items-center justify-center p-6">
        <div className="w-full rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-start gap-4">
            <div className="text-4xl leading-none" aria-hidden="true">
              ⚠️
            </div>
            <div className="min-w-0">
              <h1 className="text-xl font-semibold text-slate-900">Qualcosa è andato storto</h1>
              <p className="mt-2 text-sm text-slate-600">
                Si è verificato un errore inatteso. I tuoi dati non sono stati persi. Prova a ricaricare la pagina.
              </p>

              <div className="mt-5 flex flex-col gap-2 sm:flex-row">
                <button
                  type="button"
                  onClick={() => window.location.reload()}
                  className="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500"
                >
                  Ricarica pagina
                </button>

                <button
                  type="button"
                  onClick={() => {
                    // English comment: Global boundary is outside Router, so we use hard navigation.
                    window.location.href = "/dashboard";
                  }}
                  className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50"
                >
                  Torna alla dashboard
                </button>
              </div>

              {showTechDetails ? (
                <details className="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-3">
                  <summary className="cursor-pointer text-sm font-medium text-slate-800">
                    Dettagli tecnici (solo sviluppo)
                  </summary>
                  <div className="mt-3 space-y-3">
                    <div>
                      <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">Message</div>
                      <pre className="mt-1 whitespace-pre-wrap break-words rounded bg-white p-2 text-xs text-slate-800">
                        {String(error?.message || "")}
                      </pre>
                    </div>

                    <div>
                      <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">Stack</div>
                      <pre className="mt-1 max-h-64 overflow-auto whitespace-pre-wrap break-words rounded bg-white p-2 text-xs text-slate-800">
                        {String(error?.stack || "")}
                      </pre>
                    </div>

                    <div>
                      <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">Component stack</div>
                      <pre className="mt-1 max-h-64 overflow-auto whitespace-pre-wrap break-words rounded bg-white p-2 text-xs text-slate-800">
                        {String(componentStack || "")}
                      </pre>
                    </div>
                  </div>
                </details>
              ) : null}
            </div>
          </div>

          <div className="mt-5 text-xs text-slate-500">
            Suggerimento: se l’errore persiste, contatta il supporto IT indicando cosa stavi facendo.
          </div>
        </div>
      </div>
    </div>
  );
}

export default function GlobalErrorBoundary({ children }) {
  const componentStackRef = React.useRef("");

  const handleError = React.useCallback((error, info) => {
    componentStackRef.current = info?.componentStack || "";

    if (import.meta?.env?.DEV) {
      // English comment: In dev, do not send logs to backend (noise), keep console.
      console.error("GlobalErrorBoundary caught error:", error, info);
      return;
    }

    const payload = buildPayload(error, componentStackRef.current);
    fireAndForgetLog(payload);
  }, []);

  const fallbackRender = React.useCallback(
    ({ error }) => <GlobalFallback error={error} componentStack={componentStackRef.current} />,
    []
  );

  return (
    <ErrorBoundary onError={handleError} fallbackRender={fallbackRender}>
      {children}
    </ErrorBoundary>
  );
}
