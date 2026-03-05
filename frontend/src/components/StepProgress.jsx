import React from 'react';

function StepProgress({ steps, currentStepIndex, completedMap, onStepClick }) {
  return (
    <div className="w-full">
      <div className="flex flex-wrap items-stretch justify-between gap-1 -mx-0.5">
        {steps.map((step, idx) => {
          const isActive = idx === currentStepIndex;
          const isCompleted = Boolean(completedMap?.[idx]);
          const isClickable = typeof onStepClick === 'function';

          return (
            <button
              key={step.key}
              type="button"
              onClick={() => isClickable && onStepClick(idx)}
              className={`flex-1 min-w-[90px] text-left rounded-lg border px-2 py-2 transition min-h-[80px] flex flex-col justify-between overflow-hidden ${
                isActive
                  ? 'border-sky-500 bg-sky-500/10'
                  : 'border-slate-800 bg-slate-900/40 hover:bg-slate-900/70'
              } ${isClickable ? 'cursor-pointer' : 'cursor-default'}`}
              aria-current={isActive ? 'step' : undefined}
            >
              <div className="flex items-start justify-between gap-1 overflow-hidden">
                <div className="min-w-0 flex-1 overflow-hidden">
                  <div className="text-[10px] uppercase tracking-wide text-slate-400 truncate">
                    Step {idx + 1}
                  </div>
                  <div className="truncate text-xs font-medium text-slate-100">
                    {step.label}
                  </div>
                </div>

                <div className="shrink-0">
                  {isCompleted ? (
                    <span className="inline-flex items-center rounded-full bg-emerald-500/15 px-1.5 py-0.5 text-[10px] font-medium text-emerald-300 whitespace-nowrap">
                      OK
                    </span>
                  ) : (
                    <span className="inline-flex items-center rounded-full bg-slate-700/30 px-1.5 py-0.5 text-[10px] font-medium text-slate-300 whitespace-nowrap">
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
