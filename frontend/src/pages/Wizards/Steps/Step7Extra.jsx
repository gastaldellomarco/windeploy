import React, { useEffect } from 'react';

const TIMEZONES = [
  'Europe/Rome',
  'Europe/Berlin',
  'Europe/London',
  'UTC',
  'America/New_York',
  'Asia/Dubai',
  'Asia/Tokyo',
];

const LANGS = [
  { id: 'it-IT', label: 'Italiano (IT)' },
  { id: 'en-US', label: 'English (US)' },
  { id: 'en-GB', label: 'English (UK)' },
  { id: 'de-DE', label: 'Deutsch (DE)' },
  { id: 'fr-FR', label: 'Français (FR)' },
];

const KEYBOARDS = [
  { id: 'it-IT', label: 'Tastiera IT' },
  { id: 'en-US', label: 'Tastiera US' },
  { id: 'en-GB', label: 'Tastiera UK' },
  { id: 'de-DE', label: 'Tastiera DE' },
];

export default function Step7Extra({ wizard, dispatch }) {
  useEffect(() => {
    return () => {
      if (wizard.extras.wallpaperPreviewUrl) {
        try {
          URL.revokeObjectURL(wizard.extras.wallpaperPreviewUrl);
        } catch {
          // ignore errors when revoking the object URL (best-effort cleanup)
        }
      }
    };
  }, [wizard.extras.wallpaperPreviewUrl]);

  function handleWallpaperChange(file) {
    if (!file) {
      dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'wallpaperFile'], value: null } });
      dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'wallpaperPreviewUrl'], value: '' } });
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      alert('File troppo grande: max 5MB');
      return;
    }

    const previewUrl = URL.createObjectURL(file);
    dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'wallpaperFile'], value: file } });
    dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'wallpaperPreviewUrl'], value: previewUrl } });
  }

  return (
    <div className="space-y-5">
      <div>
        <div className="text-sm font-semibold text-slate-100">STEP 7 — Extra opzionali</div>
        <div className="mt-1 text-sm text-slate-400">Impostazioni aggiuntive (timezone, lingua, wallpaper, Wi-Fi, update policy).</div>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label className="block text-xs font-medium text-slate-300" htmlFor="tz">
            Timezone
          </label>
          <select
            id="tz"
            value={wizard.extras.timezone}
            onChange={(e) => dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'timezone'], value: e.target.value } })}
            className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
          >
            {TIMEZONES.map((tz) => (
              <option key={tz} value={tz}>{tz}</option>
            ))}
          </select>
        </div>

        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label className="block text-xs font-medium text-slate-300" htmlFor="lang">
            Lingua
          </label>
          <select
            id="lang"
            value={wizard.extras.language}
            onChange={(e) => dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'language'], value: e.target.value } })}
            className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
          >
            {LANGS.map((l) => (
              <option key={l.id} value={l.id}>{l.label}</option>
            ))}
          </select>

          <label className="mt-4 block text-xs font-medium text-slate-300" htmlFor="kbd">
            Tastiera
          </label>
          <select
            id="kbd"
            value={wizard.extras.keyboardLayout}
            onChange={(e) => dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'keyboardLayout'], value: e.target.value } })}
            className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
          >
            {KEYBOARDS.map((k) => (
              <option key={k.id} value={k.id}>{k.label}</option>
            ))}
          </select>
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <div className="text-sm font-semibold text-slate-100">Wallpaper aziendale</div>
        <div className="mt-1 text-sm text-slate-400">Upload immagine (max 5MB) con preview.</div>

        <input
          type="file"
          accept="image/*"
          onChange={(e) => handleWallpaperChange(e.target.files?.[0] || null)}
          className="mt-3 block w-full text-sm text-slate-200 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-800 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-100 hover:file:bg-slate-700"
        />

        {wizard.extras.wallpaperPreviewUrl && (
          <div className="mt-4">
            <div className="text-xs text-slate-400">Preview:</div>
            <img
              src={wizard.extras.wallpaperPreviewUrl}
              alt="Wallpaper preview"
              className="mt-2 max-h-56 w-full rounded-lg border border-slate-800 object-cover"
            />
          </div>
        )}
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <div className="flex items-center justify-between gap-3">
          <div>
            <div className="text-sm font-semibold text-slate-100">Wi‑Fi</div>
            <div className="mt-1 text-sm text-slate-400">Se abilitato, l’agent userà SSID e password (da cifrare server-side).</div>
          </div>

          <button
            type="button"
            onClick={() =>
              dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'wifiEnabled'], value: !wizard.extras.wifiEnabled } })
            }
            className={`rounded-full px-3 py-2 text-sm font-medium transition ${
              wizard.extras.wifiEnabled ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-200'
            }`}
          >
            {wizard.extras.wifiEnabled ? 'ON' : 'OFF'}
          </button>
        </div>

        {wizard.extras.wifiEnabled && (
          <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="ssid">
                SSID
              </label>
              <input
                id="ssid"
                type="text"
                value={wizard.extras.wifiSsid}
                onChange={(e) => dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'wifiSsid'], value: e.target.value } })}
                className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="wifipw">
                Password (cifrata)
              </label>
              <input
                id="wifipw"
                type="password"
                value={wizard.extras.wifiPassword}
                onChange={(e) => dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'wifiPassword'], value: e.target.value } })}
                className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>

            <div className="md:col-span-2 rounded-lg border border-slate-800 bg-slate-950 p-3 text-xs text-slate-300">
              Implicazione di sicurezza: evita di loggare o ri-mostrare questa password dopo il salvataggio. [file:8]
            </div>
          </div>
        )}
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
        <label className="block text-xs font-medium text-slate-300" htmlFor="wu">
          Windows Update
        </label>
        <select
          id="wu"
          value={wizard.extras.windowsUpdatePolicy}
          onChange={(e) => dispatch({ type: 'PATCH_PATH', payload: { path: ['extras', 'windowsUpdatePolicy'], value: e.target.value } })}
          className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
        >
          <option value="auto">Automatico</option>
          <option value="downloadonly">Solo scarica</option>
          <option value="manual">Manuale</option>
        </select>
      </div>
    </div>
  );
}
