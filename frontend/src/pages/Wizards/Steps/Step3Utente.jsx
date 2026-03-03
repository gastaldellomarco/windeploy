import React, { useMemo, useState } from 'react';

function StrengthBar({ score }) {
  const pct = Math.round((Math.min(Math.max(score, 0), 5) / 5) * 100);
  let cls = 'bg-rose-500';
  if (score >= 3) cls = 'bg-amber-500';
  if (score >= 4) cls = 'bg-emerald-500';

  return (
    <div className="mt-2">
      <div className="h-2 w-full overflow-hidden rounded-full bg-slate-800">
        <div className={`h-full ${cls}`} style={{ width: `${pct}%` }} />
      </div>
      <div className="mt-1 text-xs text-slate-400">
        Strength: {score}/5 (consigliato ≥ 3)
      </div>
    </div>
  );
}

export default function Step3Utente({ wizard, dispatch, validateUsername, passwordStrength }) {
  const [showPw, setShowPw] = useState(false);
  const [showPw2, setShowPw2] = useState(false);

  const userValidation = useMemo(
    () => validateUsername(wizard.localAdmin.username),
    [wizard.localAdmin.username, validateUsername]
  );

  const pwScore = useMemo(
    () => passwordStrength(wizard.localAdmin.password),
    [wizard.localAdmin.password, passwordStrength]
  );

  const pwMatch = wizard.localAdmin.password && wizard.localAdmin.passwordConfirm
    ? wizard.localAdmin.password === wizard.localAdmin.passwordConfirm
    : true;

  return (
    <div className="space-y-5">
      <div>
        <div className="text-sm font-semibold text-slate-100">STEP 3 — Utente admin locale</div>
        <div className="mt-1 text-sm text-slate-400">
          Le credenziali vengono inviate su HTTPS al backend e devono essere cifrate server-side.
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <label className="block text-xs font-medium text-slate-300" htmlFor="adminUser">
          Username
        </label>
        <input
          id="adminUser"
          type="text"
          value={wizard.localAdmin.username}
          onChange={(e) =>
            dispatch({ type: 'PATCH_PATH', payload: { path: ['localAdmin', 'username'], value: e.target.value } })
          }
          placeholder="Es. admin-locale"
          className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
        />
        {!userValidation.ok && <div className="mt-2 text-sm text-rose-300">{userValidation.message}</div>}
        {userValidation.ok && wizard.localAdmin.username && <div className="mt-2 text-sm text-emerald-300">Username valido.</div>}
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label className="block text-xs font-medium text-slate-300" htmlFor="pw">
            Password
          </label>
          <div className="mt-2 flex items-center gap-2">
            <input
              id="pw"
              type={showPw ? 'text' : 'password'}
              value={wizard.localAdmin.password}
              onChange={(e) =>
                dispatch({ type: 'PATCH_PATH', payload: { path: ['localAdmin', 'password'], value: e.target.value } })
              }
              className="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
            />
            <button
              type="button"
              onClick={() => setShowPw((v) => !v)}
              className="rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-sm text-slate-200 hover:bg-slate-950"
            >
              {showPw ? 'Nascondi' : 'Mostra'}
            </button>
          </div>
          <StrengthBar score={pwScore} />
        </div>

        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label className="block text-xs font-medium text-slate-300" htmlFor="pw2">
            Conferma password
          </label>
          <div className="mt-2 flex items-center gap-2">
            <input
              id="pw2"
              type={showPw2 ? 'text' : 'password'}
              value={wizard.localAdmin.passwordConfirm}
              onChange={(e) =>
                dispatch({
                  type: 'PATCH_PATH',
                  payload: { path: ['localAdmin', 'passwordConfirm'], value: e.target.value },
                })
              }
              className="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
            />
            <button
              type="button"
              onClick={() => setShowPw2((v) => !v)}
              className="rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-sm text-slate-200 hover:bg-slate-950"
            >
              {showPw2 ? 'Nascondi' : 'Mostra'}
            </button>
          </div>

          {!pwMatch && (
            <div className="mt-2 text-sm text-rose-300">Le password non coincidono.</div>
          )}
          {pwMatch && wizard.localAdmin.passwordConfirm && (
            <div className="mt-2 text-sm text-emerald-300">Conferma OK.</div>
          )}
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <div className="flex items-center gap-2">
          <input
            id="removeMs"
            type="checkbox"
            checked={wizard.localAdmin.removeMicrosoftSetupAccount}
            onChange={(e) =>
              dispatch({
                type: 'PATCH_PATH',
                payload: { path: ['localAdmin', 'removeMicrosoftSetupAccount'], value: e.target.checked },
              })
            }
            className="h-4 w-4 rounded border-slate-700 bg-slate-950 text-sky-500"
          />
          <label htmlFor="removeMs" className="text-sm text-slate-200">
            Rimuovi account Microsoft di setup iniziale
          </label>
        </div>

        <div className="mt-3 rounded-lg border border-slate-800 bg-slate-950 p-3 text-xs text-slate-300">
          Nota: la password viene cifrata e non sarà visibile dopo il salvataggio.
        </div>
      </div>
    </div>
  );
}
