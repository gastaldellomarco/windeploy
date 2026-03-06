# agent/gui/screens/screen_complete.py
import os
import threading
import customtkinter as ctk

# Import del modulo logger
try:
    from logger import get_log_path
except ImportError:
    # Fallback di sicurezza in caso di esecuzione disconnessa
    get_log_path = lambda x: None

try:
    from config import DARK_BG, ACCENT, TEXT_MAIN, TEXT_MUTED, SUCCESS_COLOR, ERROR_COLOR, BORDER_COLOR
except ImportError:
    from config import COLORS
    DARK_BG = COLORS.get("bg_main", "#111827")
    ACCENT = COLORS.get("primary", "#2563EB")
    TEXT_MAIN = COLORS.get("text_main", "#F9FAFB")
    TEXT_MUTED = COLORS.get("text_muted", "#9CA3AF")
    SUCCESS_COLOR = COLORS.get("success", "#10B981")
    ERROR_COLOR = COLORS.get("error", "#EF4444")
    BORDER_COLOR = COLORS.get("border", "#374151")

class ScreenComplete(ctk.CTkFrame):
    def __init__(self, master, result: dict, on_close: callable):
        super().__init__(master, fg_color="transparent")
        self.result = result
        self.on_close = on_close
        
        self.grid_columnconfigure(0, weight=1)
        self.grid_rowconfigure(4, weight=1)  # Spazio flessibile prima dei bottoni
        
        success = self.result.get("success", False)
        
        # --- Layout: Icona e Titolo ---
        icon_text = "✅" if success else "⚠️"
        title_text = "Configurazione completata" if success else "Completato con errori"
        title_color = SUCCESS_COLOR if success else "#EAB308"  # Giallo per warning
        
        self.icon_label = ctk.CTkLabel(self, text=icon_text, font=ctk.CTkFont(size=72))
        self.icon_label.grid(row=0, column=0, pady=(60, 10))
        
        self.title_label = ctk.CTkLabel(
            self, text=title_text, font=ctk.CTkFont(size=28, weight="bold"), text_color=title_color
        )
        self.title_label.grid(row=1, column=0, pady=(0, 20))
        
        # --- Riepilogo Numerico ---
        steps_ok = self.result.get("steps_ok", 0)
        steps_total = self.result.get("steps_total", 0)
        duration_sec = self.result.get("duration_seconds", 0)
        duration_min = max(1, duration_sec // 60) if duration_sec > 0 else 0
        
        summary_text = f"{steps_ok}/{steps_total} step completati in ~{duration_min} minuti"
        self.summary_label = ctk.CTkLabel(
            self, text=summary_text, font=ctk.CTkFont(size=18), text_color=TEXT_MAIN
        )
        self.summary_label.grid(row=2, column=0, pady=(0, 20))
        
        # --- Lista Errori (Se presenti) ---
        steps_failed = self.result.get("steps_failed", 0)
        if steps_failed > 0:
            self.errors_frame = ctk.CTkScrollableFrame(self, fg_color=DARK_BG, border_color=ERROR_COLOR, border_width=1, height=120, width=450)
            self.errors_frame.grid(row=3, column=0, pady=(0, 20))
            
            ctk.CTkLabel(self.errors_frame, text=f"{steps_failed} operazioni fallite:", text_color=ERROR_COLOR, font=ctk.CTkFont(weight="bold")).pack(anchor="w", padx=10, pady=5)
            
            failed_steps = self.result.get("failed_steps", [])
            for err in failed_steps:
                ctk.CTkLabel(
                    self.errors_frame, text=f"• {err}", 
                    text_color=TEXT_MAIN, font=ctk.CTkFont(size=13)
                ).pack(anchor="w", padx=15, pady=2)
                
        # --- Bottoni Footer ---
        self.buttons_frame = ctk.CTkFrame(self, fg_color="transparent")
        self.buttons_frame.grid(row=5, column=0, pady=20)
        
        report_path = self.result.get("report_path")
        if report_path and os.path.exists(report_path):
            self.btn_report = ctk.CTkButton(
                self.buttons_frame,
                text="📄 Apri Report",
                fg_color=ACCENT,
                text_color=TEXT_MAIN,
                height=45,
                width=160,
                font=ctk.CTkFont(weight="bold", size=15),
                command=self._open_report
            )
            self.btn_report.pack(side="left", padx=10)
        else:
            self.lbl_no_report = ctk.CTkLabel(
                self.buttons_frame, text="Report non disponibile", text_color=TEXT_MUTED,
                font=ctk.CTkFont(size=13)
            )
            self.lbl_no_report.pack(side="left", padx=10)

        # Bottone Visualizzatore Log Locale (Mostrato solo se esiste)
        wizard_code = self.result.get("wizard_code", "")
        self.log_path = get_log_path(wizard_code)
        
        if self.log_path and self.log_path.exists():
            self.btn_log = ctk.CTkButton(
                self.buttons_frame,
                text="📋 Mostra log locale",
                fg_color=DARK_BG,
                border_width=2,
                border_color=ACCENT,
                text_color=TEXT_MAIN,
                hover_color=BORDER_COLOR,
                height=45,
                width=180,
                font=ctk.CTkFont(weight="bold", size=15),
                command=self._show_log_viewer
            )
            self.btn_log.pack(side="left", padx=10)
            
        self.btn_close = ctk.CTkButton(
            self.buttons_frame,
            text="✖ Chiudi applicazione",
            fg_color="transparent",
            border_width=2,
            border_color=BORDER_COLOR,
            text_color=TEXT_MAIN,
            hover_color=BORDER_COLOR,
            height=45,
            width=180,
            font=ctk.CTkFont(weight="bold", size=15),
            command=self.on_close
        )
        self.btn_close.pack(side="left", padx=10)
        
        # --- Nota in fondo ---
        self.note_label = ctk.CTkLabel(
            self, text="I log completi sono disponibili in %PROGRAMDATA%\\WinDeploy\\logs\\",
            font=ctk.CTkFont(size=12), text_color=TEXT_MUTED
        )
        self.note_label.grid(row=6, column=0, pady=(20, 20))
        
    def _open_report(self):
        report_path = self.result.get("report_path")
        if report_path and os.path.exists(report_path):
            try:
                os.startfile(report_path)  # Funziona nativamente su Windows
            except Exception as e:
                print(f"Errore apertura report: {e}")

    def _show_log_viewer(self):
        """Apre una finestra Toplevel modale per ispezionare il log."""
        viewer = ctk.CTkToplevel(self)
        viewer.title(f"Log esecuzione — {self.result.get('wizard_code', 'N/A')}")
        viewer.geometry("800x500")
        viewer.grab_set()  # Blocca l'interazione con la main window
        
        viewer.grid_columnconfigure(0, weight=1)
        viewer.grid_rowconfigure(0, weight=1)

        # Textbox readonly per il contenuto
        self.log_textbox = ctk.CTkTextbox(viewer, font=ctk.CTkFont(family="Consolas", size=12), wrap="none", fg_color=DARK_BG)
        self.log_textbox.grid(row=0, column=0, sticky="nsew", padx=15, pady=15)
        self.log_textbox.insert("1.0", "Caricamento log in corso...\n")
        self.log_textbox.configure(state="disabled")

        # Container bottoni finestra
        btn_frame = ctk.CTkFrame(viewer, fg_color="transparent")
        btn_frame.grid(row=1, column=0, pady=(0, 15))

        btn_copy = ctk.CTkButton(
            btn_frame, text="📋 Copia path log", width=150, fg_color=BORDER_COLOR, hover_color=DARK_BG,
            command=lambda: (viewer.clipboard_clear(), viewer.clipboard_append(str(self.log_path)))
        )
        btn_copy.pack(side="left", padx=10)

        btn_close = ctk.CTkButton(
            btn_frame, text="✖ Chiudi", width=150, fg_color=ACCENT,
            command=viewer.destroy
        )
        btn_close.pack(side="left", padx=10)

        # Lancia il caricamento file nel thread per evitare freeze di Tkinter
        threading.Thread(target=self._load_log_async, daemon=True).start()

    def _load_log_async(self):
        """Legge il log dal filesystem in un thread separato."""
        try:
            file_size = os.path.getsize(self.log_path)
            MAX_SIZE = 2 * 1024 * 1024  # 2MB Limit
            
            if file_size > MAX_SIZE:
                # Se è gigante (es. infiniti output di winget), leggiamo solo la coda per efficienza
                with open(self.log_path, 'r', encoding='utf-8', errors='replace') as f:
                    lines = f.readlines()
                    content = "".join(lines[-500:])
                prefix = f"⚠️ Log troncato — mostrate le ultime 500 righe. Path completo: {self.log_path}\n{'-'*80}\n\n"
                final_text = prefix + content
            else:
                with open(self.log_path, 'r', encoding='utf-8', errors='replace') as f:
                    final_text = f.read()

            # Passa l'aggiornamento grafico al main thread
            self.after(0, self._update_log_textbox, final_text)
            
        except Exception as e:
            self.after(0, self._update_log_textbox, f"[ERRORE] Impossibile leggere il file di log: {e}")

    def _update_log_textbox(self, text: str):
        """Callback eseguita nel Main Thread di Tkinter."""
        self.log_textbox.configure(state="normal")
        self.log_textbox.delete("1.0", "end")
        self.log_textbox.insert("1.0", text)
        self.log_textbox.see("end")  # Autoscroll alla fine
        self.log_textbox.configure(state="disabled")
