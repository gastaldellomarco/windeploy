import React from 'react';

function StepProgress({ steps, currentStepIndex, completedMap, onStepClick }) {
  return (
    <div className="w-full">
      <div className="flex items-center justify-between gap-2">
        {steps.map((step, idx) => {
          const isActive = idx === currentStepIndex;
          const isCompleted = Boolean(completedMap?.[idx]);
          const isClickable = typeof onStepClick === 'function';

          return (
            <button
              key={step.key}
              type="button"
              onClick={() => isClickable && onStepClick(idx)}
              className={`flex-1 text-left rounded-lg border px-3 py-2 transition ${
                isActive
                  ? 'border-sky-500 bg-sky-500/10'
                  : 'border-slate-800 bg-slate-900/40 hover:bg-slate-900/70'
              } ${isClickable ? 'cursor-pointer' : 'cursor-default'}`}
              aria-current={isActive ? 'step' : undefined}
            >
              <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                  <div className="text-[11px] uppercase tracking-wide text-slate-400">
                    Step {idx + 1}
                  </div>
                  <div className="truncate text-sm font-medium text-slate-100">
                    {step.label}
                  </div>
                </div>

                <div className="shrink-0">
                  {isCompleted ? (
                    <span className="inline-flex items-center rounded-full bg-emerald-500/15 px-2 py-1 text-xs font-medium text-emerald-300">
                      OK
                    </span>
                  ) : (
                    <span className="inline-flex items-center rounded-full bg-slate-700/30 px-2 py-1 text-xs font-medium text-slate-300">
                      —
                    </span>
                  )}
                </div>
              </div>
            </button>
          );
        })}
      </div>

      <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-800">
        <div
          className="h-full rounded-full bg-sky-500 transition-all"
          style={{
            width: `${Math.round(((currentStepIndex + 1) / steps.length) * 100)}%`,
          }}
        />
      </div>

      <div className="mt-1 flex justify-between text-xs text-slate-400">
        <span>
          {currentStepIndex + 1}/{steps.length}
        </span>
        <span>
          {Math.round(((currentStepIndex + 1) / steps.length) * 100)}%
        </span>
      </div>
    </div>
  );
}

export default StepProgress;
