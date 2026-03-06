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
  if (Array.isArray(payload.data)) return payload.data;
  if (Array.isArray(payload.templates)) return payload.templates;
  if (Array.isArray(payload.items)) return payload.items;
  if (payload.data && Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function parseConfig(raw) {
  if (!raw) return null;
  if (typeof raw === "object") return raw;

  if (typeof raw === "string") {
    try {
      return JSON.parse(raw);
    } catch {
      return null;
    }
  }

  return null;
}

function normalizeScope(value) {
  const scope = String(value ?? "").trim().toLowerCase();

  if (["globale", "global"].includes(scope)) return "globale";
  if (["personale", "private", "mine", "user"].includes(scope)) return "personale";

  return "personale";
}

function mapTemplateRow(item) {
  const t = item?.data ? item.data : item;
  const config = parseConfig(t?.configurazione ?? t?.config ?? t?.configuration);

  return {
    id: t?.id,
    name: t?.nome ?? t?.name ?? "",
    description: t?.descrizione ?? t?.description ?? "",
    scope: normalizeScope(t?.scope),
    createdAt: t?.created_at ?? t?.createdAt ?? t?.createdat ?? null,
    updatedAt: t?.updated_at ?? t?.updatedAt ?? t?.updatedat ?? null,
    userId: t?.user_id ?? t?.userId ?? t?.userid ?? null,
    config,
    softwareCount: Array.isArray(config?.softwareinstalla)
      ? config.softwareinstalla.length
      : Array.isArray(config?.software)
      ? config.software.length
      : 0,
  };
}

function safeString(v) {
  return (v ?? "").toString();
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
  const isAdmin = safeString(user?.ruolo || user?.role).toLowerCase() === "admin";

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

  function applyTemplate(template) {
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
                      onClick={() => applyTemplate(t)}
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
