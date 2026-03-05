// Path: frontend/src/components/ErrorBoundary/WizardErrorBoundary.jsx
import React from "react";
import { ErrorBoundary } from "react-error-boundary";
import {
  useWizardRecoveryStore,
  WIZARD_RECOVERY_STORAGE_KEY,
} from "../../store/wizardRecoveryStore";

function safeReadRecovery() {
  try {
    const raw = window.localStorage.getItem(WIZARD_RECOVERY_STORAGE_KEY);
    if (!raw) return null;
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

function safeWriteRecovery(payload) {
  try {
    window.localStorage.setItem(WIZARD_RECOVERY_STORAGE_KEY, JSON.stringify(payload));
  } catch {
    // ignore
  }
}

function safeClearRecovery() {
  try {
    window.localStorage.removeItem(WIZARD_RECOVERY_STORAGE_KEY);
  } catch {
    // ignore
  }
}

function WizardFallback({ error, resetErrorBoundary }) {
  const showTechDetails = Boolean(import.meta?.env?.DEV);

  return (
    <div className="rounded-2xl border border-rose-200 bg-white p-5 shadow-sm">
      <div className="flex items-start gap-4">
        <div className="text-3xl leading-none" aria-hidden="true">
          ⚠️
        </div>
        <div className="min-w-0">
          <div className="text-lg font-semibold text-slate-900">Errore durante la compilazione del wizard.</div>
          <div className="mt-1 text-sm text-slate-600">
            I tuoi progressi sono stati salvati automaticamente.
          </div>

          <div className="mt-4 flex flex-col gap-2 sm:flex-row">
            <button
              type="button"
              onClick={() => {
                const recovered = safeReadRecovery();
                if (recovered) {
                  useWizardRecoveryStore.getState().requestApplySnapshot(recovered);
                  resetErrorBoundary();
                  return;
                }

                // English comment: No recovery found; just retry rendering.
                resetErrorBoundary();
              }}
              className="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500"
            >
              Riprendi da dove eri
            </button>

            <button
              type="button"
              onClick={() => {
                safeClearRecovery();
                useWizardRecoveryStore.getState().requestResetWizard();
                resetErrorBoundary();
              }}
              className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50"
            >
              Ricomincia da capo
            </button>
          </div>

          {showTechDetails ? (
            <details className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
              <summary className="cursor-pointer text-sm font-medium text-slate-800">
                Dettagli tecnici (solo sviluppo)
              </summary>
              <pre className="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words text-xs text-slate-800">
                {String(error?.stack || error?.message || "")}
              </pre>
            </details>
          ) : null}
        </div>
      </div>
    </div>
  );
}

export default function WizardErrorBoundary({ children, resetKeys }) {
  const handleError = React.useCallback((error) => {
    // English comment: Snapshot is read from Zustand store to avoid tight coupling via props.
    const snapshot = useWizardRecoveryStore.getState().latestSnapshot;

    const payload = {
      snapshot: snapshot ?? null,
      url: window.location.href,
      timestamp: new Date().toISOString(),
    };

    safeWriteRecovery(payload);

    if (import.meta?.env?.DEV) {
      console.error("WizardErrorBoundary caught error:", error);
    }
  }, []);

  return (
    <ErrorBoundary
      onError={handleError}
      FallbackComponent={WizardFallback}
      resetKeys={resetKeys}
    >
      {children}
    </ErrorBoundary>
  );
}
