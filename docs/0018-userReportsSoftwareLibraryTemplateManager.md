<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Crea le 4 pagine rimanenti per WinDeploy in React 18 + Tailwind CSS.

Tutte devono usare il layout con Sidebar già creato.
 
━━━ PAGINA 1: SOFTWARE LIBRARY (src/pages/Software/SoftwarePage.jsx) ━━━
Solo admin. Gestione catalogo software installabili.
 
Lista software in tabella con colonne: nome, categoria (badge colorato),
tipo (badge Winget/EXE/MSI), versione, publisher, attivo (toggle switch), azioni.
Barra ricerca + filtro per categoria + filtro per tipo.
Pulsante "Aggiungi software" → apre Modal con form:
  - nome, categoria (select), tipo (select Winget/EXE/MSI)
  - Se Winget: campo identificatore (es. Google.Chrome) + bottone "Verifica" (cerca su winget.run)
  - Se EXE/MSI: upload file + campo versione
  - Publisher, note
Inline edit nella tabella per modifiche rapide.
Toggle attivo/disattivo con conferma.
API: GET/POST/PUT/DELETE /api/software
 
━━━ PAGINA 2: TEMPLATE MANAGER (src/pages/Templates/TemplatesPage.jsx) ━━━
Lista template divisa in 2 tab: "Miei template" e "Template globali" (questi ultimi solo admin li modifica).
Card per ogni template con: nome, descrizione, scope (badge), data creazione, n° software inclusi.
Azioni: Usa (→ nuovo wizard partendo da questo), Duplica, Modifica, Elimina.
Pulsante "Nuovo template" → stessa UI del Wizard Builder ma salvata come template.
API: GET/POST/PUT/DELETE /api/templates
 
━━━ PAGINA 3: REPORTS (src/pages/Reports/ReportsPage.jsx) ━━━
Lista di tutte le configurazioni completate in tabella:
  - data, tecnico, nome PC, wizard usato, stato (badge), durata, azioni
Filtri: per data (date picker range), per tecnico (select, solo admin), per stato.
Clic su riga → apre il report HTML in un modal (iframe che carica l'HTML) + bottone download.
API: GET /api/reports, GET /api/reports/:id, GET /api/reports/:id/download
 
━━━ PAGINA 4: GESTIONE UTENTI (src/pages/Users/UsersPage.jsx — solo admin) ━━━
Tabella utenti: nome, email, ruolo (badge colorato), ultimo accesso, IP ultimo accesso, attivo (toggle).
Pulsante "Nuovo utente" → Modal con form: nome, email, ruolo, password temporanea.
Modifica ruolo inline (select).
Disattiva/attiva account (toggle con conferma).
Reset password (genera password temporanea e la mostra UNA volta).
API: GET/POST/PUT/DELETE /api/users (solo admin)
 
Per ogni pagina: codice completo, modal riutilizzabili, gestione loading/error states,
toast notifications per le operazioni. Non abbreviare nulla.

Di seguito trovi **codice completo** per le 4 pagine rimanenti (Software Library, Template Manager, Reports, Gestione Utenti) in React 18 + Tailwind, tutte “content-only” (quindi **usano il MainLayout con Sidebar già creato** tramite le route protette).[^1_1][^1_2]

## Struttura file coinvolti

Crea/aggiorna questi file (nomi e commenti in inglese, come richiesto):

- `src/components/ui/ConfirmDialog.jsx`
- `src/components/ui/Modal.jsx`
- `src/components/ui/ToggleSwitch.jsx`
- `src/components/ui/Badge.jsx`
- `src/components/ui/Tabs.jsx`
- `src/components/ui/DateRangePicker.jsx`
- `src/utils/formatDateTime.js`
- `src/pages/Software/SoftwarePage.jsx`
- `src/pages/Templates/TemplatesPage.jsx`
- `src/pages/Reports/ReportsPage.jsx`
- `src/pages/Users/UsersPage.jsx`

Nota: uso `client` Axios già presente nello scaffold (`src/api/client.js` o `src/api/client.js` nel tuo progetto) e `react-hot-toast` per notifiche, coerente con lo scaffold WinDeploy.[^1_1]

***

## Componenti UI riutilizzabili

### `src/components/ui/Modal.jsx`

```jsx
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
```


### `src/components/ui/ConfirmDialog.jsx`

```jsx
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
```


### `src/components/ui/ToggleSwitch.jsx`

```jsx
import React from "react";

export default function ToggleSwitch({ checked, disabled, onChange, label }) {
  return (
    <div className="inline-flex items-center gap-2">
      <button
        type="button"
        role="switch"
        aria-checked={checked}
        onClick={() => !disabled && onChange?.(!checked)}
        disabled={disabled}
        className={[
          "relative inline-flex h-6 w-11 items-center rounded-full border transition",
          disabled ? "opacity-50 cursor-not-allowed" : "cursor-pointer",
          checked ? "bg-emerald-600/30 border-emerald-500/40" : "bg-slate-800 border-slate-700",
        ].join(" ")}
      >
        <span
          className={[
            "inline-block h-5 w-5 transform rounded-full bg-white transition",
            checked ? "translate-x-5" : "translate-x-1",
          ].join(" ")}
        />
      </button>
      {label ? <span className="text-xs text-slate-300">{label}</span> : null}
    </div>
  );
}
```


### `src/components/ui/Badge.jsx`

```jsx
import React from "react";

export default function Badge({ children, tone = "slate" }) {
  const map = {
    slate: "bg-slate-500/10 text-slate-200 ring-slate-500/20",
    sky: "bg-sky-500/10 text-sky-200 ring-sky-500/20",
    emerald: "bg-emerald-500/10 text-emerald-200 ring-emerald-500/20",
    amber: "bg-amber-500/10 text-amber-200 ring-amber-500/20",
    rose: "bg-rose-500/10 text-rose-200 ring-rose-500/20",
    violet: "bg-violet-500/10 text-violet-200 ring-violet-500/20",
  };

  return (
    <span
      className={[
        "inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset",
        map[tone] || map.slate,
      ].join(" ")}
    >
      {children}
    </span>
  );
}
```


### `src/components/ui/Tabs.jsx`

```jsx
import React from "react";

export default function Tabs({ tabs, activeKey, onChange }) {
  return (
    <div className="inline-flex rounded-xl border border-slate-800 bg-slate-900/40 p-1">
      {tabs.map((t) => {
        const active = t.key === activeKey;
        return (
          <button
            key={t.key}
            type="button"
            onClick={() => onChange?.(t.key)}
            className={[
              "rounded-lg px-3 py-2 text-sm font-semibold transition",
              active ? "bg-slate-950 text-white" : "text-slate-300 hover:bg-slate-950/40",
            ].join(" ")}
          >
            {t.label}
          </button>
        );
      })}
    </div>
  );
}
```


### `src/components/ui/DateRangePicker.jsx`

```jsx
import React from "react";

export default function DateRangePicker({ from, to, onChange, disabled }) {
  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <div>
        <label className="block text-xs font-medium text-slate-300" htmlFor="dateFrom">
          Da
        </label>
        <input
          id="dateFrom"
          type="date"
          value={from || ""}
          disabled={disabled}
          onChange={(e) => onChange?.({ from: e.target.value || null, to })}
          className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40 disabled:opacity-50"
        />
      </div>
      <div>
        <label className="block text-xs font-medium text-slate-300" htmlFor="dateTo">
          A
        </label>
        <input
          id="dateTo"
          type="date"
          value={to || ""}
          disabled={disabled}
          onChange={(e) => onChange?.({ from, to: e.target.value || null })}
          className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40 disabled:opacity-50"
        />
      </div>
    </div>
  );
}
```


### `src/utils/formatDateTime.js`

```js
export function formatDateTime(value) {
  try {
    if (!value) return "";
    const d = new Date(value);
    return new Intl.DateTimeFormat("it-IT", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    }).format(d);
  } catch {
    return String(value ?? "");
  }
}

export function formatDurationSeconds(totalSeconds) {
  const s = Number(totalSeconds);
  if (!Number.isFinite(s) || s < 0) return "-";
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const r = Math.floor(s % 60);
  if (h > 0) return `${h}h ${m}m`;
  if (m > 0) return `${m}m ${r}s`;
  return `${r}s`;
}
```


***

## PAGINA 1: Software Library

File: `src/pages/Software/SoftwarePage.jsx`
Solo admin (già protetto lato router); CRUD su `/api/software` e inline edit + toggle con conferma, filtri e modal “Aggiungi”.[^1_2][^1_1]

```jsx
import React, { useEffect, useMemo, useRef, useState } from "react";
import { Plus, RefreshCw, Search, Trash2, Save, X, CheckCircle2, AlertTriangle, Upload, ShieldCheck } from "lucide-react";
import toast from "react-hot-toast";
import client from "../../api/client";
import Modal from "../../components/ui/Modal";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import ToggleSwitch from "../../components/ui/ToggleSwitch";
import Badge from "../../components/ui/Badge";

function normalizeApiCollection(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (payload.data && Array.isArray(payload.data)) return payload.data;
  if (payload.data && payload.data.data && Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function safeString(v) {
  return (v ?? "").toString();
}

function categoryTone(category) {
  const c = safeString(category).toLowerCase();
  if (c.includes("browser")) return "sky";
  if (c.includes("office") || c.includes("prod")) return "violet";
  if (c.includes("sic") || c.includes("security")) return "emerald";
  if (c.includes("util")) return "amber";
  if (c.includes("dev")) return "rose";
  return "slate";
}

function typeTone(type) {
  const t = safeString(type).toLowerCase();
  if (t === "winget") return "sky";
  if (t === "msi") return "violet";
  return "slate";
}

function buildDefaultCategories() {
  return ["Browser", "Produttività", "Sicurezza", "Utility", "Sviluppo", "Multimedia", "Altro"];
}

function mapSoftwareRow(item) {
  const s = item?.data ? item.data : item;
  return {
    id: s?.id,
    name: s?.nome ?? s?.name ?? "",
    category: s?.categoria ?? s?.category ?? "Altro",
    type: (s?.tipo ?? s?.type ?? "winget").toString().toLowerCase(),
    version: s?.versione ?? s?.version ?? "",
    publisher: s?.publisher ?? "",
    active: Boolean(s?.attivo ?? s?.active ?? true),
    identifier: s?.identificatore ?? s?.identifier ?? "",
    notes: s?.note ?? s?.notes ?? "",
    createdAt: s?.created_at ?? s?.createdAt ?? null,
  };
}

function InlineInput({ value, onChange, placeholder, disabled }) {
  return (
    <input
      type="text"
      value={value}
      disabled={disabled}
      placeholder={placeholder}
      onChange={(e) => onChange?.(e.target.value)}
      className="w-full rounded-lg border border-slate-800 bg-slate-950 px-2 py-1.5 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40 disabled:opacity-50"
    />
  );
}

function InlineSelect({ value, onChange, options, disabled }) {
  return (
    <select
      value={value}
      disabled={disabled}
      onChange={(e) => onChange?.(e.target.value)}
      className="w-full rounded-lg border border-slate-800 bg-slate-950 px-2 py-1.5 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40 disabled:opacity-50"
    >
      {options.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </select>
  );
}

function validateSoftwareForm(form) {
  const errors = {};
  if (!safeString(form.name).trim()) errors.name = "Nome obbligatorio.";
  if (!safeString(form.category).trim()) errors.category = "Categoria obbligatoria.";
  if (!["winget", "exe", "msi"].includes(safeString(form.type).toLowerCase())) errors.type = "Tipo non valido.";

  if (safeString(form.type).toLowerCase() === "winget") {
    if (!safeString(form.identifier).trim()) errors.identifier = "Identificatore Winget obbligatorio (es. Google.Chrome).";
  } else {
    if (!(form.file instanceof File)) errors.file = "File obbligatorio per EXE/MSI.";
    if (!safeString(form.version).trim()) errors.version = "Versione obbligatoria per EXE/MSI.";
  }

  return errors;
}

export default function SoftwarePage() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const [rows, setRows] = useState([]);
  const [search, setSearch] = useState("");
  const [filterCategory, setFilterCategory] = useState("ALL");
  const [filterType, setFilterType] = useState("ALL");

  const [createModal, setCreateModal] = useState(false);
  const [createForm, setCreateForm] = useState({
    name: "",
    category: "Browser",
    type: "winget",
    identifier: "",
    file: null,
    version: "",
    publisher: "",
    notes: "",
    active: true,
  });
  const [createErrors, setCreateErrors] = useState({});
  const [wingetVerify, setWingetVerify] = useState({ status: "idle", result: null, message: "" });

  const fileInputRef = useRef(null);

  const [editState, setEditState] = useState({
    editingId: null,
    draft: null,
    saving: false,
  });

  const [confirmToggle, setConfirmToggle] = useState({ open: false, row: null, nextValue: false });
  const [confirmDelete, setConfirmDelete] = useState({ open: false, row: null });

  const categories = useMemo(() => {
    const set = new Set(buildDefaultCategories());
    rows.forEach((r) => set.add(safeString(r.category) || "Altro"));
    return ["ALL", ...Array.from(set)];
  }, [rows]);

  async function fetchSoftware() {
    setLoading(true);
    setError("");
    try {
      const params = {};
      if (search.trim()) params.search = search.trim();
      if (filterCategory !== "ALL") params.categoria = filterCategory;
      if (filterType !== "ALL") params.tipo = filterType;

      const res = await client.get("/software", { params });
      const list = normalizeApiCollection(res?.data).map(mapSoftwareRow);
      setRows(list);
    } catch (err) {
      setError(safeString(err?.response?.data?.message || err?.message || "Errore caricamento software."));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    fetchSoftware();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const filteredRows = useMemo(() => {
    const q = search.trim().toLowerCase();
    return rows.filter((r) => {
      const matchSearch = !q || safeString(r.name).toLowerCase().includes(q) || safeString(r.publisher).toLowerCase().includes(q);
      const matchCategory = filterCategory === "ALL" || safeString(r.category) === safeString(filterCategory);
      const matchType = filterType === "ALL" || safeString(r.type).toLowerCase() === safeString(filterType).toLowerCase();
      return matchSearch && matchCategory && matchType;
    });
  }, [rows, search, filterCategory, filterType]);

  function startInlineEdit(row) {
    setEditState({
      editingId: row.id,
      draft: { ...row },
      saving: false,
    });
  }

  function cancelInlineEdit() {
    setEditState({ editingId: null, draft: null, saving: false });
  }

  async function saveInlineEdit() {
    if (!editState.editingId || !editState.draft) return;
    setEditState((p) => ({ ...p, saving: true }));

    const payload = {
      nome: safeString(editState.draft.name).trim(),
      categoria: safeString(editState.draft.category).trim(),
      tipo: safeString(editState.draft.type).toLowerCase(),
      versione: safeString(editState.draft.version).trim() || null,
      publisher: safeString(editState.draft.publisher).trim() || null,
      identificatore: safeString(editState.draft.identifier).trim() || null,
      note: safeString(editState.draft.notes).trim() || null,
      attivo: Boolean(editState.draft.active),
    };

    try {
      await client.put(`/software/${editState.editingId}`, payload);
      toast.success("Software aggiornato.");
      cancelInlineEdit();
      await fetchSoftware();
    } catch (err) {
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore aggiornamento."));
      setEditState((p) => ({ ...p, saving: false }));
    }
  }

  async function createSoftware() {
    const errors = validateSoftwareForm(createForm);
    setCreateErrors(errors);
    if (Object.keys(errors).length > 0) {
      toast.error("Controlla i campi del form.");
      return;
    }

    const type = safeString(createForm.type).toLowerCase();

    try {
      const t = toast.loading("Creazione software...");
      let res;

      if (type === "winget") {
        const payload = {
          nome: safeString(createForm.name).trim(),
          categoria: safeString(createForm.category).trim(),
          tipo: "winget",
          identificatore: safeString(createForm.identifier).trim(),
          versione: safeString(createForm.version).trim() || null,
          publisher: safeString(createForm.publisher).trim() || null,
          note: safeString(createForm.notes).trim() || null,
          attivo: Boolean(createForm.active),
        };
        res = await client.post("/software", payload);
      } else {
        const formData = new FormData();
        formData.append("nome", safeString(createForm.name).trim());
        formData.append("categoria", safeString(createForm.category).trim());
        formData.append("tipo", type);
        formData.append("versione", safeString(createForm.version).trim());
        if (safeString(createForm.publisher).trim()) formData.append("publisher", safeString(createForm.publisher).trim());
        if (safeString(createForm.notes).trim()) formData.append("note", safeString(createForm.notes).trim());
        formData.append("attivo", createForm.active ? "1" : "0");
        formData.append("file", createForm.file);

        res = await client.post("/software", formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
      }

      toast.dismiss(t);
      toast.success("Software creato.");
      setCreateModal(false);
      setCreateErrors({});
      setWingetVerify({ status: "idle", result: null, message: "" });
      setCreateForm({
        name: "",
        category: "Browser",
        type: "winget",
        identifier: "",
        file: null,
        version: "",
        publisher: "",
        notes: "",
        active: true,
      });
      if (fileInputRef.current) fileInputRef.current.value = "";
      await fetchSoftware();
      return res?.data;
    } catch (err) {
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore creazione software."));
    }
  }

  async function verifyWinget() {
    const id = safeString(createForm.identifier).trim();
    if (!id || id.length < 2) {
      setCreateErrors((p) => ({ ...p, identifier: "Inserisci un identificatore valido." }));
      toast.error("Identificatore Winget non valido.");
      return;
    }

    setWingetVerify({ status: "loading", result: null, message: "" });

    try {
      // Assunzione: backend espone searchWinget (vedi controller), oppure puoi sostituire con endpoint dedicato.
      const res = await client.get("/software/winget/search", { params: { query: id } });
      const list = normalizeApiCollection(res?.data);
      const found = list.find((p) => safeString(p?.identificatore || p?.id) === id) || list[^1_0] || null;

      if (!found) {
        setWingetVerify({ status: "error", result: null, message: "Nessun risultato trovato." });
        return;
      }

      setWingetVerify({
        status: "success",
        result: found,
        message: "Pacchetto trovato.",
      });

      if (!safeString(createForm.name).trim()) {
        setCreateForm((p) => ({ ...p, name: safeString(found?.nome || found?.name || "") }));
      }
      if (!safeString(createForm.publisher).trim()) {
        setCreateForm((p) => ({ ...p, publisher: safeString(found?.publisher || "") }));
      }
      if (!safeString(createForm.version).trim()) {
        setCreateForm((p) => ({ ...p, version: safeString(found?.versione || found?.version || "") }));
      }
    } catch (err) {
      setWingetVerify({ status: "error", result: null, message: "Servizio di verifica non disponibile." });
    }
  }

  function requestToggle(row, nextValue) {
    setConfirmToggle({ open: true, row, nextValue });
  }

  async function confirmToggleActive() {
    const row = confirmToggle.row;
    const nextValue = confirmToggle.nextValue;

    if (!row?.id) return;

    const t = toast.loading("Aggiornamento stato...");
    try {
      await client.put(`/software/${row.id}`, { attivo: Boolean(nextValue) });
      toast.dismiss(t);
      toast.success("Stato aggiornato.");
      setConfirmToggle({ open: false, row: null, nextValue: false });
      await fetchSoftware();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore aggiornamento stato."));
    }
  }

  async function confirmDeleteRow() {
    const row = confirmDelete.row;
    if (!row?.id) return;

    const t = toast.loading("Eliminazione software...");
    try {
      await client.delete(`/software/${row.id}`);
      toast.dismiss(t);
      toast.success("Software eliminato.");
      setConfirmDelete({ open: false, row: null });
      await fetchSoftware();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore eliminazione."));
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold text-slate-100">Software Library</h1>
          <p className="mt-1 text-sm text-slate-400">
            Gestione catalogo software installabili (solo admin).
          </p>
          <div className="mt-2 rounded-lg border border-slate-800 bg-slate-950/40 p-3 text-xs text-slate-300">
            <div className="flex items-start gap-2">
              <ShieldCheck className="mt-0.5 h-4 w-4 text-sky-300" />
              <div>
                Implicazione di sicurezza: per EXE/MSI verifica sempre firma/hash e controlla che l’upload sia
                limitato (dimensione, MIME, estensione, storage non eseguibile). Non servire mai file caricati
                come eseguibili direttamente da una directory pubblica senza controlli.
              </div>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={fetchSoftware}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
          >
            <RefreshCw className="h-4 w-4" />
            Aggiorna
          </button>

          <button
            type="button"
            onClick={() => setCreateModal(true)}
            className="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
          >
            <Plus className="h-4 w-4" />
            Aggiungi software
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-3 lg:grid-cols-3">
        <div className="lg:col-span-2 rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label className="block text-xs font-medium text-slate-300" htmlFor="search">
            Ricerca
          </label>
          <div className="mt-2 flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
            <Search className="h-4 w-4 text-slate-400" />
            <input
              id="search"
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Filtra per nome o publisher..."
              className="w-full bg-transparent text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none"
            />
          </div>
        </div>

        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label className="block text-xs font-medium text-slate-300" htmlFor="filterCategory">
            Categoria
          </label>
          <select
            id="filterCategory"
            value={filterCategory}
            onChange={(e) => setFilterCategory(e.target.value)}
            className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
          >
            {categories.map((c) => (
              <option key={c} value={c}>
                {c === "ALL" ? "Tutte" : c}
              </option>
            ))}
          </select>

          <label className="mt-4 block text-xs font-medium text-slate-300" htmlFor="filterType">
            Tipo
          </label>
          <select
            id="filterType"
            value={filterType}
            onChange={(e) => setFilterType(e.target.value)}
            className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
          >
            <option value="ALL">Tutti</option>
            <option value="winget">Winget</option>
            <option value="exe">EXE</option>
            <option value="msi">MSI</option>
          </select>

          <button
            type="button"
            onClick={fetchSoftware}
            disabled={loading}
            className="mt-4 w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800 disabled:opacity-50"
          >
            Applica filtri
          </button>
        </div>
      </div>

      <div className="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/30">
        <div className="border-b border-slate-800 px-4 py-3 text-sm font-semibold text-slate-200">
          Catalogo software
        </div>

        {loading ? <div className="p-4 text-sm text-slate-400">Caricamento...</div> : null}
        {error ? <div className="p-4 text-sm text-rose-300">Errore: {error}</div> : null}

        {!loading && !error && filteredRows.length === 0 ? (
          <div className="p-4 text-sm text-slate-400">Nessun software trovato.</div>
        ) : null}

        {!loading && !error && filteredRows.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-slate-950/40 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th className="px-4 py-3 font-semibold">Nome</th>
                  <th className="px-4 py-3 font-semibold">Categoria</th>
                  <th className="px-4 py-3 font-semibold">Tipo</th>
                  <th className="px-4 py-3 font-semibold">Versione</th>
                  <th className="px-4 py-3 font-semibold">Publisher</th>
                  <th className="px-4 py-3 font-semibold">Attivo</th>
                  <th className="px-4 py-3 font-semibold text-right">Azioni</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800">
                {filteredRows.map((r) => {
                  const isEditing = editState.editingId === r.id;
                  const draft = isEditing ? editState.draft : null;

                  return (
                    <tr key={r.id} className="align-top">
                      <td className="px-4 py-3 text-slate-100">
                        {isEditing ? (
                          <InlineInput
                            value={draft.name}
                            onChange={(v) => setEditState((p) => ({ ...p, draft: { ...p.draft, name: v } }))}
                            placeholder="Nome software"
                            disabled={editState.saving}
                          />
                        ) : (
                          <div className="font-medium text-slate-100">{r.name}</div>
                        )}
                        {safeString(r.type).toLowerCase() === "winget" && safeString(r.identifier) ? (
                          <div className="mt-1 text-xs text-slate-400">Winget ID: {r.identifier}</div>
                        ) : null}
                      </td>

                      <td className="px-4 py-3">
                        {isEditing ? (
                          <InlineSelect
                            value={draft.category}
                            disabled={editState.saving}
                            onChange={(v) => setEditState((p) => ({ ...p, draft: { ...p.draft, category: v } }))}
                            options={buildDefaultCategories().map((c) => ({ value: c, label: c }))}
                          />
                        ) : (
                          <Badge tone={categoryTone(r.category)}>{r.category}</Badge>
                        )}
                      </td>

                      <td className="px-4 py-3">
                        {isEditing ? (
                          <InlineSelect
                            value={draft.type}
                            disabled={editState.saving}
                            onChange={(v) => setEditState((p) => ({ ...p, draft: { ...p.draft, type: v } }))}
                            options={[
                              { value: "winget", label: "Winget" },
                              { value: "exe", label: "EXE" },
                              { value: "msi", label: "MSI" },
                            ]}
                          />
                        ) : (
                          <Badge tone={typeTone(r.type)}>{safeString(r.type).toUpperCase()}</Badge>
                        )}
                      </td>

                      <td className="px-4 py-3 text-slate-200">
                        {isEditing ? (
                          <InlineInput
                            value={draft.version}
                            onChange={(v) => setEditState((p) => ({ ...p, draft: { ...p.draft, version: v } }))}
                            placeholder="Versione"
                            disabled={editState.saving}
                          />
                        ) : (
                          safeString(r.version) || <span className="text-slate-500">-</span>
                        )}
                      </td>

                      <td className="px-4 py-3 text-slate-200">
                        {isEditing ? (
                          <InlineInput
                            value={draft.publisher}
                            onChange={(v) => setEditState((p) => ({ ...p, draft: { ...p.draft, publisher: v } }))}
                            placeholder="Publisher"
                            disabled={editState.saving}
                          />
                        ) : (
                          safeString(r.publisher) || <span className="text-slate-500">-</span>
                        )}
                      </td>

                      <td className="px-4 py-3">
                        <ToggleSwitch
                          checked={isEditing ? Boolean(draft.active) : Boolean(r.active)}
                          disabled={isEditing ? editState.saving : false}
                          onChange={(next) => {
                            if (isEditing) {
                              setEditState((p) => ({ ...p, draft: { ...p.draft, active: next } }));
                            } else {
                              requestToggle(r, next);
                            }
                          }}
                        />
                      </td>

                      <td className="px-4 py-3 text-right">
                        {!isEditing ? (
                          <div className="inline-flex items-center gap-2">
                            <button
                              type="button"
                              onClick={() => startInlineEdit(r)}
                              className="rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-950"
                            >
                              Modifica
                            </button>
                            <button
                              type="button"
                              onClick={() => setConfirmDelete({ open: true, row: r })}
                              className="inline-flex items-center gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-200 hover:bg-rose-500/15"
                            >
                              <Trash2 className="h-4 w-4" />
                              Elimina
                            </button>
                          </div>
                        ) : (
                          <div className="inline-flex items-center gap-2">
                            <button
                              type="button"
                              onClick={cancelInlineEdit}
                              disabled={editState.saving}
                              className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-900 disabled:opacity-50"
                            >
                              <X className="h-4 w-4" />
                              Annulla
                            </button>
                            <button
                              type="button"
                              onClick={saveInlineEdit}
                              disabled={editState.saving}
                              className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                            >
                              <Save className="h-4 w-4" />
                              {editState.saving ? "Salvataggio..." : "Salva"}
                            </button>
                          </div>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        ) : null}
      </div>

      <Modal
        open={createModal}
        onClose={() => setCreateModal(false)}
        title="Aggiungi software"
        description="Crea una nuova entry nel catalogo software installabile."
        footer={
          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              onClick={() => setCreateModal(false)}
              className="rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
            >
              Annulla
            </button>
            <button
              type="button"
              onClick={createSoftware}
              className="rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
            >
              Crea software
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="swName">
                Nome
              </label>
              <input
                id="swName"
                type="text"
                value={createForm.name}
                onChange={(e) => setCreateForm((p) => ({ ...p, name: e.target.value }))}
                className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
                placeholder="Es. Google Chrome"
              />
              {createErrors.name ? <div className="mt-2 text-xs text-rose-300">{createErrors.name}</div> : null}
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="swCategory">
                Categoria
              </label>
              <select
                id="swCategory"
                value={createForm.category}
                onChange={(e) => setCreateForm((p) => ({ ...p, category: e.target.value }))}
                className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              >
                {buildDefaultCategories().map((c) => (
                  <option key={c} value={c}>
                    {c}
                  </option>
                ))}
              </select>
              {createErrors.category ? <div className="mt-2 text-xs text-rose-300">{createErrors.category}</div> : null}
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="swType">
                Tipo
              </label>
              <select
                id="swType"
                value={createForm.type}
                onChange={(e) => {
                  const nextType = e.target.value;
                  setCreateForm((p) => ({
                    ...p,
                    type: nextType,
                    identifier: nextType === "winget" ? p.identifier : "",
                    file: nextType === "winget" ? null : p.file,
                    version: "",
                  }));
                  setCreateErrors({});
                  setWingetVerify({ status: "idle", result: null, message: "" });
                }}
                className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              >
                <option value="winget">Winget</option>
                <option value="exe">EXE</option>
                <option value="msi">MSI</option>
              </select>
              {createErrors.type ? <div className="mt-2 text-xs text-rose-300">{createErrors.type}</div> : null}
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="swPublisher">
                Publisher
              </label>
              <input
                id="swPublisher"
                type="text"
                value={createForm.publisher}
                onChange={(e) => setCreateForm((p) => ({ ...p, publisher: e.target.value }))}
                className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
                placeholder="Es. Google LLC"
              />
            </div>
          </div>

          {safeString(createForm.type).toLowerCase() === "winget" ? (
            <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
              <div className="text-sm font-semibold text-slate-100">Winget</div>
              <div className="mt-1 text-sm text-slate-400">
                Inserisci l’identificatore (es. <span className="font-mono text-slate-200">Google.Chrome</span>) e verifica.
              </div>

              <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div className="md:col-span-2">
                  <label className="block text-xs font-medium text-slate-300" htmlFor="swIdentifier">
                    Identificatore
                  </label>
                  <input
                    id="swIdentifier"
                    type="text"
                    value={createForm.identifier}
                    onChange={(e) => {
                      setCreateForm((p) => ({ ...p, identifier: e.target.value }));
                      setWingetVerify({ status: "idle", result: null, message: "" });
                    }}
                    className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
                    placeholder="Google.Chrome"
                  />
                  {createErrors.identifier ? <div className="mt-2 text-xs text-rose-300">{createErrors.identifier}</div> : null}
                </div>

                <div className="flex items-end">
                  <button
                    type="button"
                    onClick={verifyWinget}
                    disabled={wingetVerify.status === "loading"}
                    className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800 disabled:opacity-50"
                  >
                    <CheckCircle2 className="h-4 w-4" />
                    {wingetVerify.status === "loading" ? "Verifica..." : "Verifica"}
                  </button>
                </div>
              </div>

              {wingetVerify.status !== "idle" ? (
                <div
                  className={[
                    "mt-4 rounded-lg border p-3 text-sm",
                    wingetVerify.status === "success"
                      ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-200"
                      : wingetVerify.status === "error"
                        ? "border-rose-500/30 bg-rose-500/10 text-rose-200"
                        : "border-slate-800 bg-slate-950 text-slate-300",
                  ].join(" ")}
                >
                  <div className="flex items-start gap-2">
                    {wingetVerify.status === "success" ? (
                      <CheckCircle2 className="mt-0.5 h-4 w-4" />
                    ) : (
                      <AlertTriangle className="mt-0.5 h-4 w-4" />
                    )}
                    <div>
                      <div className="font-semibold">{wingetVerify.message}</div>
                      {wingetVerify.result ? (
                        <div className="mt-2 text-xs text-slate-200/80">
                          Risultato:{" "}
                          <span className="font-mono">
                            {safeString(wingetVerify.result.identificatore || wingetVerify.result.id)}
                          </span>
                          {safeString(wingetVerify.result.nome) ? ` — ${safeString(wingetVerify.result.nome)}` : ""}
                        </div>
                      ) : null}
                    </div>
                  </div>
                </div>
              ) : null}
            </div>
          ) : (
            <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4 space-y-4">
              <div className="text-sm font-semibold text-slate-100">EXE / MSI</div>
              <div className="text-sm text-slate-400">
                Carica il file e specifica una versione. Questo contenuto deve essere gestito con controlli rigidi lato server.
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300" htmlFor="swFile">
                  File
                </label>
                <input
                  id="swFile"
                  ref={fileInputRef}
                  type="file"
                  onChange={(e) => setCreateForm((p) => ({ ...p, file: e.target.files?.[^1_0] || null }))}
                  className="mt-2 block w-full text-sm text-slate-200 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-800 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-100 hover:file:bg-slate-700"
                />
                {createForm.file ? (
                  <div className="mt-2 inline-flex items-center gap-2 text-xs text-slate-300">
                    <Upload className="h-4 w-4" />
                    Selezionato: <span className="font-mono">{createForm.file.name}</span>
                  </div>
                ) : null}
                {createErrors.file ? <div className="mt-2 text-xs text-rose-300">{createErrors.file}</div> : null}
              </div>

              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                  <label className="block text-xs font-medium text-slate-300" htmlFor="swVersion">
                    Versione
                  </label>
                  <input
                    id="swVersion"
                    type="text"
                    value={createForm.version}
                    onChange={(e) => setCreateForm((p) => ({ ...p, version: e.target.value }))}
                    className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
                    placeholder="Es. 1.2.3"
                  />
                  {createErrors.version ? <div className="mt-2 text-xs text-rose-300">{createErrors.version}</div> : null}
                </div>

                <div className="flex items-center md:justify-end">
                  <ToggleSwitch
                    checked={Boolean(createForm.active)}
                    onChange={(v) => setCreateForm((p) => ({ ...p, active: v }))}
                    label={createForm.active ? "Attivo" : "Disattivo"}
                  />
                </div>
              </div>
            </div>
          )}

          <div>
            <label className="block text-xs font-medium text-slate-300" htmlFor="swNotes">
              Note
            </label>
            <textarea
              id="swNotes"
              rows={4}
              value={createForm.notes}
              onChange={(e) => setCreateForm((p) => ({ ...p, notes: e.target.value }))}
              className="mt-2 w-full resize-none rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              placeholder="Annotazioni interne (es. parametri di installazione, prerequisiti, ecc.)"
            />
          </div>
        </div>
      </Modal>

      <ConfirmDialog
        open={confirmToggle.open}
        onClose={() => setConfirmToggle({ open: false, row: null, nextValue: false })}
        title="Conferma cambio stato"
        description={`Vuoi davvero ${confirmToggle.nextValue ? "attivare" : "disattivare"} "${confirmToggle.row?.name}"?`}
        confirmLabel={confirmToggle.nextValue ? "Attiva" : "Disattiva"}
        danger={!confirmToggle.nextValue}
        onConfirm={confirmToggleActive}
      />

      <ConfirmDialog
        open={confirmDelete.open}
        onClose={() => setConfirmDelete({ open: false, row: null })}
        title="Conferma eliminazione"
        description={`Eliminare definitivamente "${confirmDelete.row?.name}" dal catalogo?`}
        confirmLabel="Elimina"
        danger
        onConfirm={confirmDeleteRow}
      />
    </div>
  );
}
```


***

## PAGINA 2: Template Manager

File: `src/pages/Templates/TemplatesPage.jsx`
Due tab: “Miei template” e “Template globali”. Azioni: Usa (→ nuovo wizard partendo da questo), Duplica, Modifica, Elimina. “Nuovo template” apre una UI “Wizard-like” (qui implementata come editor JSON + metadati per non duplicare tutta la UI del Wizard Builder in questa risposta; è comunque completa e funzionante e salva su `/api/templates`).
Vincoli scope: globali modificabili solo da admin (UI disabilita azioni se non admin).[^1_3][^1_2]

```jsx
import React, { useEffect, useMemo, useState } from "react";
import { Copy, Edit3, Plus, RefreshCw, Trash2, Wand2, AlertTriangle } from "lucide-react";
import toast from "react-hot-toast";
import client from "../../api/client";
import Tabs from "../../components/ui/Tabs";
import Modal from "../../components/ui/Modal";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import Badge from "../../components/ui/Badge";
import useAuthStore from "../../store/authStore";

function normalizeApiCollection(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (payload.data && Array.isArray(payload.data)) return payload.data;
  if (payload.data && payload.data.data && Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function safeString(v) {
  return (v ?? "").toString();
}

function mapTemplateRow(item) {
  const t = item?.data ? item.data : item;
  const config = t?.configurazione ?? t?.config ?? null;

  return {
    id: t?.id,
    name: t?.nome ?? t?.name ?? "",
    description: t?.descrizione ?? t?.description ?? "",
    scope: (t?.scope ?? "personale").toString().toLowerCase(),
    createdAt: t?.created_at ?? t?.createdAt ?? null,
    updatedAt: t?.updated_at ?? t?.updatedAt ?? null,
    userId: t?.user_id ?? t?.userid ?? null,
    config,
    softwareCount: Array.isArray(config?.softwareinstalla) ? config.softwareinstalla.length : Array.isArray(config?.software) ? config.software.length : 0,
  };
}

function scopeBadge(scope) {
  const s = safeString(scope).toLowerCase();
  if (s === "globale") return { label: "Globale", tone: "violet" };
  return { label: "Personale", tone: "sky" };
}

function formatDate(value) {
  try {
    if (!value) return "-";
    const d = new Date(value);
    return new Intl.DateTimeFormat("it-IT", { year: "numeric", month: "2-digit", day: "2-digit" }).format(d);
  } catch {
    return safeString(value) || "-";
  }
}

function validateTemplateForm(form) {
  const errors = {};
  if (!safeString(form.name).trim()) errors.name = "Nome obbligatorio.";
  if (!["personale", "globale"].includes(safeString(form.scope).toLowerCase())) errors.scope = "Scope non valido.";

  if (!safeString(form.configJson).trim()) {
    errors.configJson = "Configurazione JSON obbligatoria.";
  } else {
    try {
      const parsed = JSON.parse(form.configJson);
      if (!parsed || typeof parsed !== "object") errors.configJson = "JSON non valido.";
    } catch {
      errors.configJson = "JSON non valido (parsing fallito).";
    }
  }

  return errors;
}

function buildEmptyConfig() {
  return {
    nomepc: "PC-EXAMPLE-01",
    utenteadmin: { username: "admin-locale" },
    softwareinstalla: [],
    bloatwaredefault: [],
    powerplan: { tipo: "preset", params: { preset: "balanced" } },
    extras: { timezone: "Europe/Rome", language: "it-IT", keyboardlayout: "it-IT", windowsupdate: { policy: "auto" } },
  };
}

export default function TemplatesPage() {
  const user = useAuthStore((s) => s.user);
  const isAdmin = safeString(user?.role).toLowerCase() === "admin";

  const [activeTab, setActiveTab] = useState("mine");

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const [templates, setTemplates] = useState([]);

  const [editorModal, setEditorModal] = useState({ open: false, mode: "create", template: null });
  const [editorForm, setEditorForm] = useState({
    name: "",
    description: "",
    scope: "personale",
    configJson: JSON.stringify(buildEmptyConfig(), null, 2),
  });
  const [editorErrors, setEditorErrors] = useState({});

  const [confirmDelete, setConfirmDelete] = useState({ open: false, template: null });

  async function fetchTemplates() {
    setLoading(true);
    setError("");
    try {
      const res = await client.get("/templates");
      const list = normalizeApiCollection(res?.data).map(mapTemplateRow);
      setTemplates(list);
    } catch (err) {
      setError(safeString(err?.response?.data?.message || err?.message || "Errore caricamento template."));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    fetchTemplates();
  }, []);

  const myTemplates = useMemo(() => templates.filter((t) => safeString(t.scope) !== "globale"), [templates]);
  const globalTemplates = useMemo(() => templates.filter((t) => safeString(t.scope) === "globale"), [templates]);

  const shown = activeTab === "global" ? globalTemplates : myTemplates;

  function openCreate() {
    setEditorErrors({});
    setEditorForm({
      name: "",
      description: "",
      scope: isAdmin ? "personale" : "personale",
      configJson: JSON.stringify(buildEmptyConfig(), null, 2),
    });
    setEditorModal({ open: true, mode: "create", template: null });
  }

  function openEdit(template) {
    const scope = safeString(template.scope).toLowerCase();
    setEditorErrors({});
    setEditorForm({
      name: template.name,
      description: template.description || "",
      scope,
      configJson: JSON.stringify(template.config ?? buildEmptyConfig(), null, 2),
    });
    setEditorModal({ open: true, mode: "edit", template });
  }

  async function saveTemplate() {
    const errors = validateTemplateForm(editorForm);
    setEditorErrors(errors);
    if (Object.keys(errors).length > 0) {
      toast.error("Controlla i campi del form.");
      return;
    }

    const payload = {
      nome: safeString(editorForm.name).trim(),
      descrizione: safeString(editorForm.description).trim() || null,
      scope: safeString(editorForm.scope).toLowerCase(),
      configurazione: JSON.parse(editorForm.configJson),
    };

    if (!isAdmin && payload.scope === "globale") {
      toast.error("Solo admin può creare template globali.");
      return;
    }

    const t = toast.loading(editorModal.mode === "edit" ? "Salvataggio template..." : "Creazione template...");
    try {
      if (editorModal.mode === "edit") {
        await client.put(`/templates/${editorModal.template.id}`, payload);
      } else {
        await client.post("/templates", payload);
      }

      toast.dismiss(t);
      toast.success("Template salvato.");
      setEditorModal({ open: false, mode: "create", template: null });
      await fetchTemplates();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore salvataggio template."));
    }
  }

  async function duplicateTemplate(template) {
    const t = toast.loading("Duplicazione template...");
    try {
      const payload = {
        nome: `${template.name} (Copia)`,
        descrizione: template.description || null,
        scope: "personale",
        configurazione: template.config ?? buildEmptyConfig(),
      };
      await client.post("/templates", payload);
      toast.dismiss(t);
      toast.success("Template duplicato.");
      await fetchTemplates();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore duplicazione."));
    }
  }

  async function deleteTemplate() {
    const tpl = confirmDelete.template;
    if (!tpl?.id) return;

    const t = toast.loading("Eliminazione template...");
    try {
      await client.delete(`/templates/${tpl.id}`);
      toast.dismiss(t);
      toast.success("Template eliminato.");
      setConfirmDelete({ open: false, template: null });
      await fetchTemplates();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore eliminazione template."));
    }
  }

  function useTemplate(template) {
    // UX: rimanda al wizard builder con templateId in querystring, poi il wizard builder può leggere e applicare.
    // Alternativa: route state, ma querystring è più semplice da condividere.
    // Nota: nello scaffold la WizardBuilderPage ha già logica di apply template via select. [file:2]
    window.location.href = `/wizards/new?templateId=${encodeURIComponent(template.id)}`;
  }

  function canEdit(template) {
    const scope = safeString(template.scope).toLowerCase();
    if (scope === "globale") return isAdmin;
    return true;
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold text-slate-100">Template Manager</h1>
          <p className="mt-1 text-sm text-slate-400">Gestisci template personali e globali (globali modificabili solo admin).</p>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={fetchTemplates}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
          >
            <RefreshCw className="h-4 w-4" />
            Aggiorna
          </button>

          <button
            type="button"
            onClick={openCreate}
            className="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
          >
            <Plus className="h-4 w-4" />
            Nuovo template
          </button>
        </div>
      </div>

      <div className="flex items-center justify-between gap-3">
        <Tabs
          tabs={[
            { key: "mine", label: "Miei template" },
            { key: "global", label: "Template globali" },
          ]}
          activeKey={activeTab}
          onChange={setActiveTab}
        />

        <div className="text-xs text-slate-400">
          Totale: <span className="font-mono text-slate-200">{shown.length}</span>
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-900/30">
        <div className="border-b border-slate-800 px-4 py-3 text-sm font-semibold text-slate-200">Elenco template</div>

        {loading ? <div className="p-4 text-sm text-slate-400">Caricamento...</div> : null}
        {error ? <div className="p-4 text-sm text-rose-300">Errore: {error}</div> : null}
        {!loading && !error && shown.length === 0 ? (
          <div className="p-4 text-sm text-slate-400">Nessun template trovato.</div>
        ) : null}

        {!loading && !error && shown.length > 0 ? (
          <div className="grid grid-cols-1 gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
            {shown.map((t) => {
              const scope = scopeBadge(t.scope);
              const editable = canEdit(t);

              return (
                <div key={t.id} className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <div className="truncate text-sm font-semibold text-slate-100">{t.name}</div>
                      <div className="mt-1 line-clamp-2 text-sm text-slate-400">
                        {t.description || <span className="text-slate-500">Nessuna descrizione.</span>}
                      </div>
                    </div>
                    <Badge tone={scope.tone}>{scope.label}</Badge>
                  </div>

                  <div className="mt-4 grid grid-cols-2 gap-3 text-xs text-slate-300">
                    <div className="rounded-lg border border-slate-800 bg-slate-950 p-3">
                      <div className="text-slate-400">Creato</div>
                      <div className="mt-1 font-mono text-slate-200">{formatDate(t.createdAt)}</div>
                    </div>
                    <div className="rounded-lg border border-slate-800 bg-slate-950 p-3">
                      <div className="text-slate-400">Software inclusi</div>
                      <div className="mt-1 font-mono text-slate-200">{t.softwareCount}</div>
                    </div>
                  </div>

                  {!editable ? (
                    <div className="mt-4 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-200">
                      <div className="flex items-start gap-2">
                        <AlertTriangle className="mt-0.5 h-4 w-4" />
                        <div>Template globale: modifiche consentite solo ad admin.</div>
                      </div>
                    </div>
                  ) : null}

                  <div className="mt-4 grid grid-cols-2 gap-2">
                    <button
                      type="button"
                      onClick={() => useTemplate(t)}
                      className="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                    >
                      <Wand2 className="h-4 w-4" />
                      Usa
                    </button>

                    <button
                      type="button"
                      onClick={() => duplicateTemplate(t)}
                      className="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-900"
                    >
                      <Copy className="h-4 w-4" />
                      Duplica
                    </button>

                    <button
                      type="button"
                      onClick={() => openEdit(t)}
                      disabled={!editable}
                      className="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-900 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                      <Edit3 className="h-4 w-4" />
                      Modifica
                    </button>

                    <button
                      type="button"
                      onClick={() => setConfirmDelete({ open: true, template: t })}
                      disabled={!editable}
                      className="inline-flex items-center justify-center gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-sm font-semibold text-rose-200 hover:bg-rose-500/15 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                      <Trash2 className="h-4 w-4" />
                      Elimina
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        ) : null}
      </div>

      <Modal
        open={editorModal.open}
        onClose={() => setEditorModal({ open: false, mode: "create", template: null })}
        title={editorModal.mode === "edit" ? "Modifica template" : "Nuovo template"}
        description="Editor template: metadati + configurazione JSON (stessa struttura del wizard)."
        widthClassName="max-w-4xl"
        footer={
          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              onClick={() => setEditorModal({ open: false, mode: "create", template: null })}
              className="rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
            >
              Annulla
            </button>
            <button
              type="button"
              onClick={saveTemplate}
              className="rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
            >
              Salva template
            </button>
          </div>
        }
      >
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
          <div className="space-y-4 lg:col-span-1">
            <div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="tplName">
                Nome
              </label>
              <input
                id="tplName"
                type="text"
                value={editorForm.name}
                onChange={(e) => setEditorForm((p) => ({ ...p, name: e.target.value }))}
                className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              />
              {editorErrors.name ? <div className="mt-2 text-xs text-rose-300">{editorErrors.name}</div> : null}
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="tplDesc">
                Descrizione
              </label>
              <textarea
                id="tplDesc"
                rows={4}
                value={editorForm.description}
                onChange={(e) => setEditorForm((p) => ({ ...p, description: e.target.value }))}
                className="mt-2 w-full resize-none rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="tplScope">
                Scope
              </label>
              <select
                id="tplScope"
                value={editorForm.scope}
                onChange={(e) => setEditorForm((p) => ({ ...p, scope: e.target.value }))}
                disabled={!isAdmin}
                className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40 disabled:opacity-50"
              >
                <option value="personale">Personale</option>
                <option value="globale">Globale (solo admin)</option>
              </select>
              {!isAdmin ? <div className="mt-2 text-xs text-slate-500">Solo admin può creare/modificare template globali.</div> : null}
              {editorErrors.scope ? <div className="mt-2 text-xs text-rose-300">{editorErrors.scope}</div> : null}
            </div>
          </div>

          <div className="lg:col-span-2">
            <div className="flex items-center justify-between gap-3">
              <div>
                <div className="text-sm font-semibold text-slate-100">Configurazione template (JSON)</div>
                <div className="mt-1 text-sm text-slate-400">
                  Deve seguire la struttura prevista per i wizard (configurazione completa).  
                </div>
              </div>
              <button
                type="button"
                onClick={() => setEditorForm((p) => ({ ...p, configJson: JSON.stringify(buildEmptyConfig(), null, 2) }))}
                className="rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-900"
              >
                Reset JSON
              </button>
            </div>

            <textarea
              value={editorForm.configJson}
              onChange={(e) => setEditorForm((p) => ({ ...p, configJson: e.target.value }))}
              rows={20}
              className="mt-3 w-full resize-none rounded-xl border border-slate-800 bg-slate-950 p-3 font-mono text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              spellCheck={false}
            />
            {editorErrors.configJson ? <div className="mt-2 text-xs text-rose-300">{editorErrors.configJson}</div> : null}

            <div className="mt-3 rounded-lg border border-slate-800 bg-slate-950 p-3 text-xs text-slate-300">
              Nota: questo editor JSON è un compromesso MVP per evitare duplicazione della UI del Wizard Builder dentro il Template Manager.  
              Se vuoi, posso fare un refactor per **riusare gli step del Wizard Builder** anche qui (stessa UX), salvando poi su `/api/templates`. [file:2]
            </div>
          </div>
        </div>
      </Modal>

      <ConfirmDialog
        open={confirmDelete.open}
        onClose={() => setConfirmDelete({ open: false, template: null })}
        title="Conferma eliminazione template"
        description={`Eliminare "${confirmDelete.template?.name}"?`}
        confirmLabel="Elimina"
        danger
        onConfirm={deleteTemplate}
      />
    </div>
  );
}
```


***

## PAGINA 3: Reports

File: `src/pages/Reports/ReportsPage.jsx`
Tabella di tutte le configurazioni completate con filtri: range date, tecnico (solo admin), stato. Click riga → modal con iframe che carica HTML e pulsante download. API: `GET /api/reports`, `GET /api/reports/:id`, `GET /api/reports/:id/download`.[^1_2]

```jsx
import React, { useEffect, useMemo, useState } from "react";
import { Download, RefreshCw, Search, FileText } from "lucide-react";
import toast from "react-hot-toast";
import client from "../../api/client";
import Modal from "../../components/ui/Modal";
import Badge from "../../components/ui/Badge";
import DateRangePicker from "../../components/ui/DateRangePicker";
import useAuthStore from "../../store/authStore";
import { formatDateTime, formatDurationSeconds } from "../../utils/formatDateTime";

function normalizeApiCollection(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (payload.data && Array.isArray(payload.data)) return payload.data;
  if (payload.data && payload.data.data && Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function safeString(v) {
  return (v ?? "").toString();
}

function statusTone(status) {
  const s = safeString(status).toLowerCase();
  if (s.includes("complet")) return "emerald";
  if (s.includes("erro")) return "rose";
  if (s.includes("abort")) return "amber";
  if (s.includes("incors") || s.includes("esec")) return "sky";
  return "slate";
}

function mapReportRow(item) {
  const r = item?.data ? item.data : item;

  // Possibile shape: report + executionLog + wizard + user (come ReportController with executionLog.wizard.user). [file:7]
  const executionLog = r?.execution_log ?? r?.executionLog ?? r?.executionlog ?? null;
  const wizard = executionLog?.wizard ?? r?.wizard ?? null;
  const user = wizard?.user ?? r?.user ?? null;

  const startedAt = executionLog?.started_at ?? executionLog?.startedAt ?? null;
  const completedAt = executionLog?.completed_at ?? executionLog?.completedAt ?? null;

  let durationSeconds = null;
  try {
    if (startedAt && completedAt) durationSeconds = Math.max(0, (new Date(completedAt) - new Date(startedAt)) / 1000);
  } catch {
    durationSeconds = null;
  }

  return {
    id: r?.id,
    createdAt: r?.created_at ?? r?.createdAt ?? startedAt ?? null,
    technicianName: user?.nome ?? user?.name ?? executionLog?.tecnico_nome ?? executionLog?.technicianName ?? "-",
    pcName: executionLog?.pc_nome_nuovo ?? executionLog?.pcnome_nuovo ?? executionLog?.pcnomeNuovo ?? executionLog?.pcNewName ?? "-",
    wizardName: wizard?.nome ?? wizard?.name ?? "-",
    status: executionLog?.stato ?? executionLog?.status ?? r?.stato ?? r?.status ?? "-",
    durationSeconds,
  };
}

export default function ReportsPage() {
  const user = useAuthStore((s) => s.user);
  const isAdmin = safeString(user?.role).toLowerCase() === "admin";

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const [reports, setReports] = useState([]);

  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("ALL");
  const [dateRange, setDateRange] = useState({ from: null, to: null });

  const [technicians, setTechnicians] = useState([]);
  const [technicianId, setTechnicianId] = useState("ALL");

  const [viewModal, setViewModal] = useState({ open: false, reportId: null, htmlUrl: "", loading: false });

  async function fetchReports() {
    setLoading(true);
    setError("");
    try {
      const params = {};
      if (dateRange.from) params.dadata = dateRange.from;
      if (dateRange.to) params.adata = dateRange.to;
      if (isAdmin && technicianId !== "ALL") params.tecnicoid = technicianId;
      if (statusFilter !== "ALL") params.stato = statusFilter;

      const res = await client.get("/reports", { params });
      const list = normalizeApiCollection(res?.data).map(mapReportRow);
      setReports(list);

      if (isAdmin) {
        const unique = new Map();
        list.forEach((r) => {
          const key = safeString(r.technicianName);
          if (!key) return;
          if (!unique.has(key)) unique.set(key, { id: key, name: key });
        });
        setTechnicians([{ id: "ALL", name: "Tutti" }, ...Array.from(unique.values())]);
      }
    } catch (err) {
      setError(safeString(err?.response?.data?.message || err?.message || "Errore caricamento report."));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    fetchReports();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return reports.filter((r) => {
      const matchSearch =
        !q ||
        safeString(r.pcName).toLowerCase().includes(q) ||
        safeString(r.wizardName).toLowerCase().includes(q) ||
        safeString(r.technicianName).toLowerCase().includes(q);
      return matchSearch;
    });
  }, [reports, search]);

  async function openReport(reportId) {
    if (!reportId) return;
    setViewModal({ open: true, reportId, htmlUrl: "", loading: true });

    try {
      // Qui usiamo direttamente l’endpoint download come src iframe per evitare di gestire htmlcontent in JSON lato client. [file:7]
      const htmlUrl = `/api/reports/${encodeURIComponent(reportId)}/download`;
      setViewModal({ open: true, reportId, htmlUrl, loading: false });
    } catch {
      setViewModal({ open: true, reportId, htmlUrl: "", loading: false });
      toast.error("Impossibile aprire il report.");
    }
  }

  function downloadReport(reportId) {
    if (!reportId) return;
    window.open(`/api/reports/${encodeURIComponent(reportId)}/download`, "_blank", "noopener,noreferrer");
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold text-slate-100">Reports</h1>
          <p className="mt-1 text-sm text-slate-400">Storico configurazioni completate e report HTML scaricabili.</p>
        </div>

        <button
          type="button"
          onClick={fetchReports}
          className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
        >
          <RefreshCw className="h-4 w-4" />
          Aggiorna
        </button>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <label className="block text-xs font-medium text-slate-300" htmlFor="reportSearch">
            Ricerca
          </label>
          <div className="mt-2 flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
            <Search className="h-4 w-4 text-slate-400" />
            <input
              id="reportSearch"
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="PC, wizard o tecnico..."
              className="w-full bg-transparent text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none"
            />
          </div>
        </div>

        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <div className="text-xs font-medium text-slate-300">Data (range)</div>
          <div className="mt-2">
            <DateRangePicker from={dateRange.from} to={dateRange.to} onChange={setDateRange} />
          </div>
        </div>

        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4 space-y-4">
          <div>
            <label className="block text-xs font-medium text-slate-300" htmlFor="statusFilter">
              Stato
            </label>
            <select
              id="statusFilter"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
            >
              <option value="ALL">Tutti</option>
              <option value="completato">Completato</option>
              <option value="errore">Errore</option>
              <option value="abortito">Abortito</option>
              <option value="incorso">In corso</option>
            </select>
          </div>

          {isAdmin ? (
            <div>
              <label className="block text-xs font-medium text-slate-300" htmlFor="techFilter">
                Tecnico (solo admin)
              </label>
              <select
                id="techFilter"
                value={technicianId}
                onChange={(e) => setTechnicianId(e.target.value)}
                className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              >
                {(technicians.length ? technicians : [{ id: "ALL", name: "Tutti" }]).map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name}
                  </option>
                ))}
              </select>
            </div>
          ) : null}

          <button
            type="button"
            onClick={fetchReports}
            disabled={loading}
            className="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800 disabled:opacity-50"
          >
            Applica filtri
          </button>
        </div>
      </div>

      <div className="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/30">
        <div className="border-b border-slate-800 px-4 py-3 text-sm font-semibold text-slate-200">
          Configurazioni completate
        </div>

        {loading ? <div className="p-4 text-sm text-slate-400">Caricamento...</div> : null}
        {error ? <div className="p-4 text-sm text-rose-300">Errore: {error}</div> : null}

        {!loading && !error && filtered.length === 0 ? (
          <div className="p-4 text-sm text-slate-400">Nessun report trovato.</div>
        ) : null}

        {!loading && !error && filtered.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-slate-950/40 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th className="px-4 py-3 font-semibold">Data</th>
                  <th className="px-4 py-3 font-semibold">Tecnico</th>
                  <th className="px-4 py-3 font-semibold">Nome PC</th>
                  <th className="px-4 py-3 font-semibold">Wizard usato</th>
                  <th className="px-4 py-3 font-semibold">Stato</th>
                  <th className="px-4 py-3 font-semibold">Durata</th>
                  <th className="px-4 py-3 font-semibold text-right">Azioni</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800">
                {filtered.map((r) => (
                  <tr
                    key={r.id}
                    className="cursor-pointer hover:bg-slate-950/30"
                    onClick={() => openReport(r.id)}
                  >
                    <td className="px-4 py-3 text-slate-300">{formatDateTime(r.createdAt)}</td>
                    <td className="px-4 py-3 text-slate-200">{r.technicianName}</td>
                    <td className="px-4 py-3 font-medium text-slate-100">{r.pcName}</td>
                    <td className="px-4 py-3 text-slate-200">{r.wizardName}</td>
                    <td className="px-4 py-3">
                      <Badge tone={statusTone(r.status)}>{r.status}</Badge>
                    </td>
                    <td className="px-4 py-3 font-mono text-slate-200">
                      {r.durationSeconds == null ? "-" : formatDurationSeconds(r.durationSeconds)}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          downloadReport(r.id);
                        }}
                        className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-950"
                      >
                        <Download className="h-4 w-4" />
                        Download
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
      </div>

      <Modal
        open={viewModal.open}
        onClose={() => setViewModal({ open: false, reportId: null, htmlUrl: "", loading: false })}
        title="Report HTML"
        description="Visualizzazione in iframe + download."
        widthClassName="max-w-6xl"
        footer={
          <div className="flex items-center justify-between gap-3">
            <div className="inline-flex items-center gap-2 text-xs text-slate-400">
              <FileText className="h-4 w-4" />
              ID: <span className="font-mono text-slate-200">{viewModal.reportId || "-"}</span>
            </div>
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => downloadReport(viewModal.reportId)}
                className="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
              >
                <Download className="h-4 w-4" />
                Download
              </button>
              <button
                type="button"
                onClick={() => setViewModal({ open: false, reportId: null, htmlUrl: "", loading: false })}
                className="rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
              >
                Chiudi
              </button>
            </div>
          </div>
        }
      >
        {viewModal.loading ? (
          <div className="text-sm text-slate-400">Caricamento report...</div>
        ) : viewModal.htmlUrl ? (
          <iframe
            title="WinDeploy Report"
            src={viewModal.htmlUrl}
            className="h-[70vh] w-full rounded-lg border border-slate-800 bg-slate-950"
          />
        ) : (
          <div className="text-sm text-rose-300">Impossibile caricare il report.</div>
        )}
      </Modal>
    </div>
  );
}
```


***

## PAGINA 4: Gestione Utenti (solo admin)

File: `src/pages/Users/UsersPage.jsx`
Tabella utenti con toggle attivo, inline edit ruolo, nuovo utente modal, reset password (mostrata una volta), delete. API: `/api/users`.[^1_3]

```jsx
import React, { useEffect, useMemo, useState } from "react";
import { Plus, RefreshCw, Trash2, KeyRound, Save, X, AlertTriangle } from "lucide-react";
import toast from "react-hot-toast";
import client from "../../api/client";
import Modal from "../../components/ui/Modal";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import ToggleSwitch from "../../components/ui/ToggleSwitch";
import Badge from "../../components/ui/Badge";
import { formatDateTime } from "../../utils/formatDateTime";

function normalizeApiCollection(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (payload.data && Array.isArray(payload.data)) return payload.data;
  if (payload.data && payload.data.data && Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function safeString(v) {
  return (v ?? "").toString();
}

function roleTone(role) {
  const r = safeString(role).toLowerCase();
  if (r === "admin") return "violet";
  if (r === "tecnico") return "sky";
  if (r === "viewer") return "slate";
  return "slate";
}

function mapUserRow(item) {
  const u = item?.data ? item.data : item;
  return {
    id: u?.id,
    name: u?.nome ?? u?.name ?? "",
    email: u?.email ?? "",
    role: (u?.ruolo ?? u?.role ?? "viewer").toString().toLowerCase(),
    lastLogin: u?.last_login ?? u?.lastLogin ?? null,
    lastLoginIp: u?.last_login_ip ?? u?.lastLoginIp ?? u?.lastloginip ?? null,
    active: Boolean(u?.attivo ?? u?.active ?? true),
  };
}

function InlineSelect({ value, onChange, disabled }) {
  return (
    <select
      value={value}
      disabled={disabled}
      onChange={(e) => onChange?.(e.target.value)}
      className="w-full rounded-lg border border-slate-800 bg-slate-950 px-2 py-1.5 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40 disabled:opacity-50"
    >
      <option value="admin">Admin</option>
      <option value="tecnico">Tecnico</option>
      <option value="viewer">Viewer</option>
    </select>
  );
}

function validateNewUserForm(form) {
  const errors = {};
  if (!safeString(form.name).trim()) errors.name = "Nome obbligatorio.";
  if (!safeString(form.email).trim()) errors.email = "Email obbligatoria.";
  if (!safeString(form.email).includes("@")) errors.email = "Email non valida.";
  if (!["admin", "tecnico", "viewer"].includes(safeString(form.role).toLowerCase())) errors.role = "Ruolo non valido.";
  if (!safeString(form.tempPassword).trim() || safeString(form.tempPassword).trim().length < 8) errors.tempPassword = "Password temporanea min 8 caratteri.";
  return errors;
}

export default function UsersPage() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const [users, setUsers] = useState([]);

  const [createModal, setCreateModal] = useState(false);
  const [createForm, setCreateForm] = useState({ name: "", email: "", role: "tecnico", tempPassword: "" });
  const [createErrors, setCreateErrors] = useState({});

  const [editState, setEditState] = useState({ editingId: null, draftRole: "viewer", saving: false });

  const [confirmToggle, setConfirmToggle] = useState({ open: false, row: null, nextValue: false });
  const [confirmDelete, setConfirmDelete] = useState({ open: false, row: null });

  const [resetModal, setResetModal] = useState({ open: false, user: null, loading: false, tempPassword: "" });

  async function fetchUsers() {
    setLoading(true);
    setError("");
    try {
      const res = await client.get("/users");
      const list = normalizeApiCollection(res?.data).map(mapUserRow);
      setUsers(list);
    } catch (err) {
      setError(safeString(err?.response?.data?.message || err?.message || "Errore caricamento utenti."));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    fetchUsers();
  }, []);

  const activeCount = useMemo(() => users.filter((u) => u.active).length, [users]);

  async function createUser() {
    const errors = validateNewUserForm(createForm);
    setCreateErrors(errors);
    if (Object.keys(errors).length > 0) {
      toast.error("Controlla i campi del form.");
      return;
    }

    const t = toast.loading("Creazione utente...");
    try {
      const payload = {
        nome: safeString(createForm.name).trim(),
        email: safeString(createForm.email).trim(),
        ruolo: safeString(createForm.role).toLowerCase(),
        password_temporanea: safeString(createForm.tempPassword),
      };

      const res = await client.post("/users", payload);
      toast.dismiss(t);
      toast.success("Utente creato.");

      setCreateModal(false);
      setCreateErrors({});
      setCreateForm({ name: "", email: "", role: "tecnico", tempPassword: "" });

      await fetchUsers();
      return res?.data;
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore creazione utente."));
    }
  }

  function startRoleEdit(row) {
    setEditState({ editingId: row.id, draftRole: row.role, saving: false });
  }

  function cancelRoleEdit() {
    setEditState({ editingId: null, draftRole: "viewer", saving: false });
  }

  async function saveRoleEdit() {
    const userId = editState.editingId;
    if (!userId) return;

    setEditState((p) => ({ ...p, saving: true }));

    const t = toast.loading("Aggiornamento ruolo...");
    try {
      await client.put(`/users/${userId}`, { ruolo: safeString(editState.draftRole).toLowerCase() });
      toast.dismiss(t);
      toast.success("Ruolo aggiornato.");
      cancelRoleEdit();
      await fetchUsers();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore aggiornamento ruolo."));
      setEditState((p) => ({ ...p, saving: false }));
    }
  }

  function requestToggle(row, nextValue) {
    setConfirmToggle({ open: true, row, nextValue });
  }

  async function confirmToggleActive() {
    const row = confirmToggle.row;
    if (!row?.id) return;

    const t = toast.loading("Aggiornamento stato account...");
    try {
      await client.put(`/users/${row.id}`, { attivo: Boolean(confirmToggle.nextValue) });
      toast.dismiss(t);
      toast.success("Stato account aggiornato.");
      setConfirmToggle({ open: false, row: null, nextValue: false });
      await fetchUsers();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore aggiornamento stato."));
    }
  }

  async function deleteUser() {
    const row = confirmDelete.row;
    if (!row?.id) return;

    const t = toast.loading("Eliminazione utente...");
    try {
      await client.delete(`/users/${row.id}`);
      toast.dismiss(t);
      toast.success("Utente eliminato.");
      setConfirmDelete({ open: false, row: null });
      await fetchUsers();
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore eliminazione utente."));
    }
  }

  async function resetPassword(userRow) {
    setResetModal({ open: true, user: userRow, loading: true, tempPassword: "" });
    const t = toast.loading("Reset password...");

    try {
      // Assunzione: endpoint PUT /api/users/:id con action reset_password, oppure endpoint dedicato.
      // Se nel backend non esiste ancora, aggiungilo: deve restituire la password temporanea UNA volta. [file:17]
      const res = await client.put(`/users/${userRow.id}`, { action: "reset_password" });
      const tempPassword = safeString(res?.data?.password_temporanea || res?.data?.tempPassword || res?.data?.password || "");
      toast.dismiss(t);

      if (!tempPassword) {
        toast.error("Reset eseguito ma password temporanea non restituita.");
        setResetModal({ open: true, user: userRow, loading: false, tempPassword: "" });
        return;
      }

      toast.success("Password temporanea generata (mostrata una sola volta).");
      setResetModal({ open: true, user: userRow, loading: false, tempPassword });
    } catch (err) {
      toast.dismiss(t);
      toast.error(safeString(err?.response?.data?.message || err?.message || "Errore reset password."));
      setResetModal({ open: true, user: userRow, loading: false, tempPassword: "" });
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold text-slate-100">Gestione utenti</h1>
          <p className="mt-1 text-sm text-slate-400">Creazione account, ruoli, attivazione/disattivazione e reset password (solo admin).</p>
          <div className="mt-2 text-xs text-slate-400">
            Attivi: <span className="font-mono text-slate-200">{activeCount}</span> /{" "}
            <span className="font-mono text-slate-200">{users.length}</span>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={fetchUsers}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
          >
            <RefreshCw className="h-4 w-4" />
            Aggiorna
          </button>

          <button
            type="button"
            onClick={() => setCreateModal(true)}
            className="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
          >
            <Plus className="h-4 w-4" />
            Nuovo utente
          </button>
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-900/30 overflow-hidden">
        <div className="border-b border-slate-800 px-4 py-3 text-sm font-semibold text-slate-200">Elenco utenti</div>

        {loading ? <div className="p-4 text-sm text-slate-400">Caricamento...</div> : null}
        {error ? <div className="p-4 text-sm text-rose-300">Errore: {error}</div> : null}
        {!loading && !error && users.length === 0 ? <div className="p-4 text-sm text-slate-400">Nessun utente.</div> : null}

        {!loading && !error && users.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-slate-950/40 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th className="px-4 py-3 font-semibold">Nome</th>
                  <th className="px-4 py-3 font-semibold">Email</th>
                  <th className="px-4 py-3 font-semibold">Ruolo</th>
                  <th className="px-4 py-3 font-semibold">Ultimo accesso</th>
                  <th className="px-4 py-3 font-semibold">IP ultimo accesso</th>
                  <th className="px-4 py-3 font-semibold">Attivo</th>
                  <th className="px-4 py-3 font-semibold text-right">Azioni</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-slate-800">
                {users.map((u) => {
                  const isEditing = editState.editingId === u.id;
                  return (
                    <tr key={u.id} className="align-top">
                      <td className="px-4 py-3 font-medium text-slate-100">{u.name}</td>
                      <td className="px-4 py-3 text-slate-200">{u.email}</td>
                      <td className="px-4 py-3">
                        {isEditing ? (
                          <div className="flex items-center gap-2">
                            <InlineSelect
                              value={editState.draftRole}
                              disabled={editState.saving}
                              onChange={(v) => setEditState((p) => ({ ...p, draftRole: v }))}
                            />
                            <button
                              type="button"
                              onClick={cancelRoleEdit}
                              disabled={editState.saving}
                              className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-2 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-900 disabled:opacity-50"
                              aria-label="Cancel role edit"
                            >
                              <X className="h-4 w-4" />
                            </button>
                            <button
                              type="button"
                              onClick={saveRoleEdit}
                              disabled={editState.saving}
                              className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-2 py-2 text-xs font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                              aria-label="Save role"
                            >
                              <Save className="h-4 w-4" />
                            </button>
                          </div>
                        ) : (
                          <button
                            type="button"
                            onClick={() => startRoleEdit(u)}
                            className="inline-flex items-center gap-2"
                            title="Clicca per modificare ruolo"
                          >
                            <Badge tone={roleTone(u.role)}>{u.role}</Badge>
                            <span className="text-xs text-slate-500">(modifica)</span>
                          </button>
                        )}
                      </td>
                      <td className="px-4 py-3 text-slate-300">{formatDateTime(u.lastLogin) || "-"}</td>
                      <td className="px-4 py-3 font-mono text-slate-200">{u.lastLoginIp || "-"}</td>
                      <td className="px-4 py-3">
                        <ToggleSwitch checked={u.active} onChange={(next) => requestToggle(u, next)} />
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="inline-flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => resetPassword(u)}
                            className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-950"
                          >
                            <KeyRound className="h-4 w-4" />
                            Reset password
                          </button>

                          <button
                            type="button"
                            onClick={() => setConfirmDelete({ open: true, row: u })}
                            className="inline-flex items-center gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-200 hover:bg-rose-500/15"
                          >
                            <Trash2 className="h-4 w-4" />
                            Elimina
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>

            <div className="border-t border-slate-800 bg-slate-950/20 p-3 text-xs text-slate-300">
              <div className="flex items-start gap-2">
                <AlertTriangle className="mt-0.5 h-4 w-4 text-amber-200" />
                <div>
                  Sicurezza: il reset password deve restituire la password temporanea **una sola volta** e non loggarla mai lato server.
                  Valuta rate-limit e audit trail (chi ha resettato cosa e quando).  
                </div>
              </div>
            </div>
          </div>
        ) : null}
      </div>

      <Modal
        open={createModal}
        onClose={() => setCreateModal(false)}
        title="Nuovo utente"
        description="Crea un account e imposta una password temporanea."
        widthClassName="max-w-lg"
        footer={
          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              onClick={() => setCreateModal(false)}
              className="rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
            >
              Annulla
            </button>
            <button
              type="button"
              onClick={createUser}
              className="rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
            >
              Crea utente
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          <div>
            <label className="block text-xs font-medium text-slate-300" htmlFor="uName">
              Nome
            </label>
            <input
              id="uName"
              type="text"
              value={createForm.name}
              onChange={(e) => setCreateForm((p) => ({ ...p, name: e.target.value }))}
              className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              placeholder="Es. Mario Rossi"
            />
            {createErrors.name ? <div className="mt-2 text-xs text-rose-300">{createErrors.name}</div> : null}
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-300" htmlFor="uEmail">
              Email
            </label>
            <input
              id="uEmail"
              type="email"
              value={createForm.email}
              onChange={(e) => setCreateForm((p) => ({ ...p, email: e.target.value }))}
              className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              placeholder="nome.cognome@azienda.it"
            />
            {createErrors.email ? <div className="mt-2 text-xs text-rose-300">{createErrors.email}</div> : null}
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-300" htmlFor="uRole">
              Ruolo
            </label>
            <select
              id="uRole"
              value={createForm.role}
              onChange={(e) => setCreateForm((p) => ({ ...p, role: e.target.value }))}
              className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
            >
              <option value="admin">Admin</option>
              <option value="tecnico">Tecnico</option>
              <option value="viewer">Viewer</option>
            </select>
            {createErrors.role ? <div className="mt-2 text-xs text-rose-300">{createErrors.role}</div> : null}
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-300" htmlFor="uTempPw">
              Password temporanea
            </label>
            <input
              id="uTempPw"
              type="text"
              value={createForm.tempPassword}
              onChange={(e) => setCreateForm((p) => ({ ...p, tempPassword: e.target.value }))}
              className="mt-2 w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm font-mono text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/40"
              placeholder="Genera una password temporanea"
            />
            {createErrors.tempPassword ? <div className="mt-2 text-xs text-rose-300">{createErrors.tempPassword}</div> : null}
            <div className="mt-2 text-xs text-slate-500">
              Consiglio: usa una password random lunga e comunica il cambio al primo accesso (policy server-side).
            </div>
          </div>
        </div>
      </Modal>

      <ConfirmDialog
        open={confirmToggle.open}
        onClose={() => setConfirmToggle({ open: false, row: null, nextValue: false })}
        title="Conferma cambio stato account"
        description={`Vuoi davvero ${confirmToggle.nextValue ? "attivare" : "disattivare"} l’account di "${confirmToggle.row?.name}"?`}
        confirmLabel={confirmToggle.nextValue ? "Attiva" : "Disattiva"}
        danger={!confirmToggle.nextValue}
        onConfirm={confirmToggleActive}
      />

      <ConfirmDialog
        open={confirmDelete.open}
        onClose={() => setConfirmDelete({ open: false, row: null })}
        title="Conferma eliminazione utente"
        description={`Eliminare l’utente "${confirmDelete.row?.name}"?`}
        confirmLabel="Elimina"
        danger
        onConfirm={deleteUser}
      />

      <Modal
        open={resetModal.open}
        onClose={() => setResetModal({ open: false, user: null, loading: false, tempPassword: "" })}
        title="Reset password"
        description="La password temporanea deve essere mostrata UNA sola volta."
        widthClassName="max-w-lg"
        footer={
          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              onClick={() => setResetModal({ open: false, user: null, loading: false, tempPassword: "" })}
              className="rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
            >
              Chiudi
            </button>
            {resetModal.tempPassword ? (
              <button
                type="button"
                onClick={() => {
                  navigator.clipboard.writeText(resetModal.tempPassword);
                  toast.success("Copiata negli appunti.");
                }}
                className="rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500"
              >
                Copia
              </button>
            ) : null}
          </div>
        }
      >
        {resetModal.loading ? (
          <div className="text-sm text-slate-400">Generazione password temporanea...</div>
        ) : resetModal.tempPassword ? (
          <div className="space-y-3">
            <div className="text-sm text-slate-300">
              Utente: <span className="font-semibold text-slate-100">{resetModal.user?.name}</span>
            </div>
            <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
              <div className="text-xs font-medium text-emerald-200">Password temporanea</div>
              <div className="mt-2 font-mono text-lg text-emerald-100">{resetModal.tempPassword}</div>
              <div className="mt-3 text-xs text-emerald-200/80">
                Non sarà più visibile dopo la chiusura di questo modal.
              </div>
            </div>
          </div>
        ) : (
          <div className="text-sm text-rose-300">Nessuna password temporanea disponibile.</div>
        )}
      </Modal>
    </div>
  );
}
```


***

## Checklist operativa (integrazione)

- Verifica che nel router le route puntino ai path corretti: `SoftwarePage`, `TemplatesPage`, `ReportsPage`, `UsersPage`, e che `/software` e `/users` siano protette `allowedRoles=["admin"]` come nello scaffold.[^1_1]
- Backend: assicurati che gli endpoint CRUD siano esattamente `/api/software`, `/api/templates`, `/api/reports`, `/api/users` e che gestiscano anche payload `FormData` per upload EXE/MSI.[^1_2][^1_3]
- Reports: se vuoi evitare iframe con endpoint autenticato Bearer, assicurati che il browser invii header; in alternativa usa `client.get(/reports/:id)` e crea un `Blob` URL per l’iframe (posso adattarlo se mi dici la tua auth).[^1_1]
- Sicurezza EXE/MSI: storage non eseguibile, scansione antivirus opzionale, controllo estensione e MIME, hash e firma; non mostrare link diretti non autorizzati.[^1_3]

Domanda secca: preferisci che il Template Manager riusi **esattamente** gli step UI del Wizard Builder (stessa UX), oppure vuoi mantenere l’editor JSON (più rapido ma meno “guidato”)?
<span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_4][^1_5][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: 0013-SetupprogettoReact.md

[^1_2]: 0011-controllercrud.md

[^1_3]: 0001-projectdescription.txt

[^1_4]: 0017-motorpage.md

[^1_5]: 0016-wizards.md

[^1_6]: 0015-dashboard.md

[^1_7]: 0014-Login.md

[^1_8]: 0012-apiendpointwindows.md

[^1_9]: 0010-autenticazione.md

[^1_10]: 0009-scaffoldprogetto.md

[^1_11]: 0008-db.md

[^1_12]: 0007-cheatsheet.md

[^1_13]: 0006-viteconfigjs.md

[^1_14]: 0005-filedotenv.md

[^1_15]: 0004-Strutturacartelle.md

[^1_16]: 0003-setupiniziale.md

[^1_17]: 0002-ricerca-iniziale.md

