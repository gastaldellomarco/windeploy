import customtkinter as ctk
import threading
import json
import datetime
from pathlib import Path
from agent.config import COLORS, DEV_MODE
from agent.api_client import APIClient


class ScreenOverview(ctk.CTkFrame):
    """Overview screen shown after successful auth."""

    def __init__(self, parent, controller):
        super().__init__(parent, fg_color=COLORS["bg_card"])
        self.controller = controller

        self.title = ctk.CTkLabel(
            self,
            text="Connessione effettuata",
            font=ctk.CTkFont(size=22, weight="bold"),
            text_color=COLORS["text_main"],
        )
        self.title.pack(pady=(40, 10))

        self.info = ctk.CTkLabel(
            self,
            text="Caricamento configurazione...",
            font=ctk.CTkFont(size=14),
            text_color=COLORS["text_muted"],
        )
        self.info.pack(pady=(10, 20))

        self.ok_btn = ctk.CTkButton(
            self,
            text="AVVIA ESECUZIONE",
            width=200,
            command=self._on_start,
            fg_color=COLORS["primary"],
            hover_color=COLORS["primary_hover"],
        )
        self.ok_btn.pack(pady=20)

        self.status_label = ctk.CTkLabel(
            self, text="", font=ctk.CTkFont(size=12), text_color=COLORS["error"]
        )
        self.status_label.pack(pady=(5, 0))

    # ------------------------------------------------------------------ #
    #  Helper: aggiorna widget da thread secondario in modo sicuro        #
    # ------------------------------------------------------------------ #
    def _set_status(self, text: str, color: str = None):
        """Thread-safe: aggiorna status_label nel main thread."""
        c = color or COLORS["error"]
        self.after(0, lambda: self.status_label.configure(text=text, text_color=c))

    def _restore_button(self):
        """Thread-safe: riabilita il bottone nel main thread."""
        self.after(0, lambda: self.ok_btn.configure(
            state="normal",
            text="AVVIA ESECUZIONE",
            fg_color=COLORS["primary"],
        ))

    # ------------------------------------------------------------------ #
    #  Lifecycle                                                           #
    # ------------------------------------------------------------------ #
    def on_show(self, app_state: dict):
        """Aggiorna la UI con i dati dell'app_state al momento della navigazione."""
        # Reset status
        self.after(0, lambda: self.status_label.configure(text=""))
        self.after(0, lambda: self.ok_btn.configure(state="normal", text="AVVIA ESECUZIONE"))

        wizard = app_state.get("wizard_config") or {}
        if isinstance(wizard, str):
            try:
                wizard = json.loads(wizard)
            except Exception:
                wizard = {}

        # Supporta più nomi di chiave possibili dal backend
        name = (
            wizard.get("pc_name")
            or wizard.get("nome_pc")
            or wizard.get("nome")
            or "PC non specificato"
        )

        software_list = wizard.get("software", [])
        software_count = len(software_list) if isinstance(software_list, list) else 0

        uninstall_list = wizard.get("uninstall") or wizard.get("disinstalla", [])
        uninstall_count = len(uninstall_list) if isinstance(uninstall_list, list) else 0

        summary = f"PC: {name} • Software: {software_count} • Disinstallazioni: {uninstall_count}"
        self.after(0, lambda s=summary: self.info.configure(text=s))

        token = app_state.get("auth_token")
        if token:
            self.after(0, lambda: self.status_label.configure(
                text="Token ricevuto. Pronto all'avvio.",
                text_color=COLORS.get("success", "#22c55e"),
            ))

    # ------------------------------------------------------------------ #
    #  Avvio esecuzione                                                    #
    # ------------------------------------------------------------------ #
    def _on_start(self):
        token = self.controller.app_state.get("auth_token")
        wizard_config = self.controller.app_state.get("wizard_config", {})

        if not token:
            self._set_status("Token mancante. Torna alla schermata di connessione.")
            return

        self.ok_btn.configure(state="disabled", text="AVVIO IN CORSO...",
                              fg_color=COLORS["border"])
        self.after(0, lambda: self.status_label.configure(
            text="Avvio esecuzione in corso...",
            text_color=COLORS["text_muted"],
        ))

        threading.Thread(
            target=self._start_thread,
            args=(token, wizard_config),
            daemon=True,
        ).start()

    def _start_thread(self, token: str, wizard_config: dict):
        client = APIClient()
        try:
            resp = client.start_execution(wizard_config, token=token)

            # ---- Risposta 200 OK ----------------------------------------
            if resp.status_code == 200:
                try:
                    data = resp.json()
                except Exception:
                    data = None

                if not data:
                    saved_msg = self._save_debug_response(resp, "start_response")
                    self._set_status(f"Risposta non JSON dal server. {saved_msg}")
                    return

                execution_log_id = data.get("execution_log_id")
                if not execution_log_id:
                    detail = json.dumps(data)[:300]
                    saved_msg = self._save_debug_json(data, "start_json")
                    self._set_status(
                        f"execution_log_id mancante nella risposta. {saved_msg}\n{detail}"
                    )
                    return

                # ✅ Successo: naviga alla schermata di progresso
                payload = {
                    "wizard_config": wizard_config,
                    "execution_log_id": execution_log_id,
                    "auth_token": token,
                }
                self.after(0, self.controller.navigate, "ScreenProgress", payload)
                return  # non eseguire _restore_button: la schermata cambierà

            # ---- Errore HTTP ≠ 200 --------------------------------------
            friendly = None
            try:
                body = resp.json()
                friendly = body.get("message")
                # Includi anche gli errori di validazione Laravel
                if not friendly and body.get("errors"):
                    errors = body["errors"]
                    friendly = " | ".join(
                        f"{k}: {v[0]}" for k, v in errors.items()
                    )
            except Exception:
                pass

            if friendly:
                self._set_status(f"Errore {resp.status_code}: {friendly}")
            else:
                saved = self._save_debug_response(resp, "start_error")
                self._set_status(
                    f"Errore server ({resp.status_code}). Risposta salvata: {saved}"
                )

        except Exception as e:
            self._set_status(f"Eccezione: {type(e).__name__}: {e}")

        finally:
            self._restore_button()

    # ------------------------------------------------------------------ #
    #  Utility debug                                                       #
    # ------------------------------------------------------------------ #
    def _save_debug_response(self, resp, prefix: str) -> str:
        try:
            debug_dir = Path(__file__).resolve().parents[2] / "storage" / "backend_responses"
            debug_dir.mkdir(parents=True, exist_ok=True)
            ts = datetime.datetime.utcnow().strftime("%Y%m%dT%H%M%SZ")
            fname = debug_dir / f"{prefix}_{resp.status_code}_{ts}.html"
            fname.write_text(resp.text or "", encoding="utf-8")
            if DEV_MODE:
                try:
                    import webbrowser
                    webbrowser.open(str(fname.resolve()))
                except Exception:
                    pass
            return fname.name
        except Exception as e:
            return f"(impossibile salvare: {e})"

    def _save_debug_json(self, data: dict, prefix: str) -> str:
        try:
            debug_dir = Path(__file__).resolve().parents[2] / "storage" / "backend_responses"
            debug_dir.mkdir(parents=True, exist_ok=True)
            ts = datetime.datetime.utcnow().strftime("%Y%m%dT%H%M%SZ")
            fname = debug_dir / f"{prefix}_{ts}.json"
            fname.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
            if DEV_MODE:
                try:
                    import webbrowser
                    webbrowser.open(str(fname.resolve()))
                except Exception:
                    pass
            return fname.name
        except Exception as e:
            return f"(impossibile salvare: {e})"
