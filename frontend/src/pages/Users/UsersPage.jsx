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
