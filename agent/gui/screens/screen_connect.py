# agent/gui/screens/screen_connect.py
# agent/gui/screens/screen_connect.py
import re
import threading
import uuid
import customtkinter as ctk
import requests
import json
import os
import datetime
from pathlib import Path

from agent.config import COLORS
from agent.config import DEV_MODE, API_URL
from agent.api_client import APIClient

def get_mac_address():
    """Recupera l'indirizzo MAC locale formattato (es. 00:1A:2B:3C:4D:5E)."""
    mac_num = uuid.getnode()
    mac_hex = ':'.join(['{:02x}'.format((mac_num >> ele) & 0xff) for ele in range(0,8*6,8)][::-1])
    return mac_hex.upper()

class ScreenConnect(ctk.CTkFrame):
    def __init__(self, parent, controller):
        super().__init__(parent, fg_color="transparent")
        self.controller = controller
        self.pack_propagate(False)

        # --- Indicatori di Progresso ---
        self.step_label = ctk.CTkLabel(
            self, 
            text="Step 1 di 5: Connessione", 
            font=ctk.CTkFont(size=14, weight="bold"),
            text_color=COLORS["text_muted"]
        )
        self.step_label.pack(pady=(40, 10))

        # --- Titolo e Testi ---
        self.title_label = ctk.CTkLabel(
            self, 
            text="Inserisci il Codice Wizard", 
            font=ctk.CTkFont(size=24, weight="bold"),
            text_color=COLORS["text_main"]
        )
        self.title_label.pack(pady=(30, 5))

        self.subtitle_label = ctk.CTkLabel(
            self, 
            text="Il codice a 6 caratteri generato dalla web app (es. WD-7A3F)", 
            font=ctk.CTkFont(size=14),
            text_color=COLORS["text_muted"]
        )
        self.subtitle_label.pack(pady=(0, 30))

        # --- Input Campo Codice ---
        self.code_entry = ctk.CTkEntry(
            self, 
            placeholder_text="WD-XXXX",
            font=ctk.CTkFont(size=22, weight="bold"),
            width=280,
            height=55,
            justify="center",
            fg_color=COLORS["bg_main"],
            border_color=COLORS["border"],
            text_color=COLORS["text_main"]
        )
        self.code_entry.pack(pady=10)
        self.code_entry.bind("<Return>", lambda event: self.handle_connect())

        # --- Label per Errori ---
        self.error_label = ctk.CTkLabel(
            self, 
            text="", 
            font=ctk.CTkFont(size=13),
            text_color=COLORS["error"]
        )
        self.error_label.pack(pady=5)

        # --- Bottone d'Azione ---
        self.connect_btn = ctk.CTkButton(
            self, 
            text="CONNETTI",
            font=ctk.CTkFont(size=16, weight="bold"),
            width=280,
            height=50,
            fg_color=COLORS["primary"],
            hover_color=COLORS["primary_hover"],
            command=self.handle_connect
        )
        self.connect_btn.pack(pady=20)

    def set_error(self, message: str):
        """Visualizza un errore a schermo e formatta l'input in rosso."""
        self.error_label.configure(text=message)
        self.code_entry.configure(border_color=COLORS["error"])

    def clear_error(self):
        """Ripulisce lo stato d'errore."""
        self.error_label.configure(text="")
        self.code_entry.configure(border_color=COLORS["border"])

    def handle_connect(self):
        """Punto d'ingresso per il submit del form."""
        code = self.code_entry.get().strip().upper()
        
        # Riformattiamo il testo nell'entry pulito
        self.code_entry.delete(0, 'end')
        self.code_entry.insert(0, code)
        self.clear_error()

        # Validazione RegEx formato WD-XXXX
        if not re.match(r"^WD-[A-Z0-9]{4}$", code):
            self.set_error("Formato non valido. Il codice deve essere nel formato WD-XXXX.")
            return

        # Modifica UI per lo stato di "Loading" (spinner testuale)
        self.connect_btn.configure(text="CONNESSIONE IN CORSO...", state="disabled", fg_color=COLORS["border"])
        self.code_entry.configure(state="disabled")

        # Delega la chiamata API a un thread separato per prevenire il freeze dell'UI [1].
        threading.Thread(target=self._api_call_thread, args=(code,), daemon=True).start()

    def _api_call_thread(self, code: str):
        """Task asincrono (Thread) che esegue l'autenticazione tramite Laravel."""
        # Use APIClient to perform the network call so networking logic
        # is isolated and testable.
        try:
            payload = {
                "codicewizard": code,
                "macaddress": get_mac_address()
            }
            client = APIClient()
            response = client.authenticate_wizard(code, get_mac_address())

            # Return raw response to the same handlers as before
            if response.status_code == 200:
                # Some backends may occasionally return empty or non-JSON bodies.
                # Decode safely and provide a useful error if parsing fails.
                try:
                    data = response.json()
                except (ValueError, json.JSONDecodeError):
                    # Show the raw text for debugging but keep it user-friendly
                    raw = (response.text or '').strip()
                    display = raw if raw else 'Risposta vuota dal server.'
                    self.after(0, self.set_error, f"Risposta non valida dal server: {display}")
                    return
                # Route back to main thread
                self.after(0, self._on_success, data, code)
            elif response.status_code == 404:
                self.after(0, self.set_error, "Codice wizard non trovato o errato.")
            elif response.status_code == 410:
                try:
                    msg = response.json().get("message", "Il codice è scaduto o è già stato utilizzato.")
                except (ValueError, json.JSONDecodeError):
                    msg = response.text or "Il codice è scaduto o è già stato utilizzato."
                self.after(0, self.set_error, msg)
            elif response.status_code == 422:
                # Laravel usually returns { message: 'The given data was invalid.', errors: { field: [..] } }
                try:
                    body = response.json()
                    # Prefer detailed validation errors when present
                    errors = body.get('errors') if isinstance(body, dict) else None
                    if errors and isinstance(errors, dict):
                        firsts = []
                        for v in errors.values():
                            if isinstance(v, list) and v:
                                firsts.append(str(v[0]))
                        msg = '; '.join(firsts) if firsts else body.get('message', 'Errore di validazione.')
                    else:
                        # Fallback to message field
                        msg = body.get('message', response.text or 'Il wizard non è ancora pronto per l\'esecuzione.')
                except (ValueError, json.JSONDecodeError):
                    msg = response.text or "Il wizard non è ancora pronto per l'esecuzione."
                self.after(0, self.set_error, msg)
            else:
                # Try to extract a friendly message from JSON; if not JSON, handle HTML/SVG
                friendly = None
                try:
                    friendly = response.json().get("message")
                except Exception:
                    pass

                if friendly:
                    self.after(0, self.set_error, friendly)
                else:
                    # If server returned HTML or SVG, save it to a debug file and show a short message
                    content_type = response.headers.get('Content-Type', '')
                    body_preview = (response.text or '').strip()[:200]
                    is_html = 'html' in content_type.lower() or 'svg' in content_type.lower() or (body_preview.startswith('<'))
                    if is_html:
                        # Ensure storage folder
                        debug_dir = Path(__file__).resolve().parents[2] / 'storage' / 'backend_responses'
                        debug_dir.mkdir(parents=True, exist_ok=True)
                        ts = datetime.datetime.utcnow().strftime('%Y%m%dT%H%M%SZ')
                        fname = debug_dir / f"response_{response.status_code}_{ts}.html"
                        try:
                            with open(fname, 'w', encoding='utf-8') as f:
                                f.write(response.text or '')
                        except Exception:
                            fname = None

                        # Compose a helpful message with URL and content-type.
                        ct = response.headers.get('Content-Type', 'unknown')
                        saved_name = fname.name if fname else 'impossibile salvare il file'
                        msg = f"Errore server ({response.status_code}) {API_URL}/agent/auth - Content-Type: {ct}. Salvato: {saved_name}"
                        self.after(0, self.set_error, msg)

                        # Developer helper: automatically open the file in the default browser
                        if DEV_MODE and fname and fname.exists():
                            try:
                                import webbrowser
                                webbrowser.open(str(fname.resolve()))
                            except Exception:
                                pass
                    else:
                        self.after(0, self.set_error, f"Errore del server ({response.status_code}). Riprova." )
                
        except requests.exceptions.ConnectionError:
            self.after(0, self.set_error, "Impossibile raggiungere il server. Controlla la rete LAN/WAN.")
        except requests.exceptions.Timeout:
            self.after(0, self.set_error, "Timeout della richiesta. Il server non risponde.")
        except Exception as e:
            self.after(0, self.set_error, f"Si è verificato un errore critico: {str(e)}")
        finally:
            self.after(0, self._restore_ui)

    def _on_success(self, api_data: dict, code: str):
        """Callback eseguita nel main thread in caso di risposta 200 OK."""
        # Backend may return 'wizard_config' or 'wizardconfig' depending on version
        wizard_cfg = api_data.get("wizard_config") if api_data.get("wizard_config") is not None else api_data.get("wizardconfig", {})
        payload = {
            "wizard_config": wizard_cfg,
            "auth_token": api_data.get("token"),
            "wizard_code": code
        }

        # Prefer ScreenOverview if the controller supports it, otherwise fall back to progress.
        # Avoid accessing attributes that belong to Tkinter internals (e.g. controller.screens)
        # which caused AttributeError on some controller implementations.
        wizard_cfg = payload.get('wizard_config', {})

        if hasattr(self.controller, 'navigate') and callable(getattr(self.controller, 'navigate')):
            # Modern controller: use navigate with legacy screen names
            try:
                # Try overview first
                self.controller.navigate('ScreenOverview', payload)
                return
            except Exception:
                try:
                    self.controller.navigate('ScreenProgress', payload)
                    return
                except Exception:
                    pass

        # Fallback to older show_screen API if present
        if hasattr(self.controller, 'show_screen') and callable(getattr(self.controller, 'show_screen')):
            # populate wizard_config if controller expects it on the instance
            try:
                if isinstance(wizard_cfg, dict) and hasattr(self.controller, 'wizard_config'):
                    self.controller.wizard_config = wizard_cfg
            except Exception:
                pass

            try:
                self.controller.show_screen('overview')
                return
            except Exception:
                try:
                    self.controller.show_screen('progress')
                    return
                except Exception:
                    pass

        # Last resort: try direct attribute-based navigation (very defensive)
        try:
            # map to method names if present
            if hasattr(self.controller, 'on_connect_success'):
                self.controller.on_connect_success(wizard_cfg)
                return
        except Exception:
            pass

        # If nothing worked, display error to the user
        self.set_error('Impossibile aprire la schermata successiva. Contatta il supporto.')

    def _restore_ui(self):
        """Ripristina i componenti allo stato iniziale terminata l'attesa."""
        # It's possible the frame or widgets were destroyed while the background
        # thread completed (navigation to another screen). Guard against
        # TclError / invalid command by checking widget existence and
        # swallowing TclError if it occurs.
        try:
            if getattr(self, 'connect_btn', None) is not None and self.connect_btn.winfo_exists():
                self.connect_btn.configure(text="CONNETTI", state="normal", fg_color=COLORS["primary"])
        except Exception:
            # Best-effort: ignore errors restoring UI when widget is gone
            pass

        try:
            if getattr(self, 'code_entry', None) is not None and self.code_entry.winfo_exists():
                self.code_entry.configure(state="normal")
        except Exception:
            pass
