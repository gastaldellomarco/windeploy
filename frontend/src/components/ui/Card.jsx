import React from 'react';

export function Card({ title, children }) {
  return (
    <div className="rounded-xl border border-slate-800 bg-slate-900/70 p-4">
      {title && <div className="text-xs font-medium text-slate-400 mb-2">{title}</div>}
      {children}
    </div>
  );
}
