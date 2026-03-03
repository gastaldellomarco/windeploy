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
      const found = list.find((p) => safeString(p?.identificatore || p?.id) === id) || list[0] || null;

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
    } catch {
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
                  onChange={(e) => setCreateForm((p) => ({ ...p, file: e.target.files?.[0] || null }))}
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
