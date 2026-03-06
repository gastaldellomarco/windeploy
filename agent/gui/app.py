# agent/gui/app.py
import customtkinter as ctk
from config import AGENT_VERSION, WINDOW_WIDTH, WINDOW_HEIGHT, COLORS

# Import di tutte le schermate del flusso a 6 step
from gui.screens.screen_connect import ScreenConnect
from gui.screens.screen_overview import ScreenOverview
from gui.screens.screen_uninstall import ScreenUninstall
from gui.screens.screen_progress import ScreenProgress
from gui.screens.screen_complete import ScreenComplete

class WinDeployApp(ctk.CTk):
    def __init__(self):
        super().__init__()

        # --- Setup Finestra Principale ---
        self.title("WinDeploy Agent")
        self.geometry(f"{WINDOW_WIDTH}x{WINDOW_HEIGHT}")
        self.resizable(False, False)
        
        # Tema e colori globali
        ctk.set_appearance_mode("dark")
        self.configure(fg_color=COLORS.get("bg_main", "#111827"))

        # --- Stato Condiviso dell'Applicazione ---
        self.wizard_config = {}
        self.execution_result = {}
        self.current_frame = None

        # --- Layout Principale ---
        self.main_container = ctk.CTkFrame(self, fg_color="transparent")
        self.main_container.pack(fill="both", expand=True, padx=20, pady=20)

        # Header (Logo in alto a sinistra)
        self.header_frame = ctk.CTkFrame(self.main_container, fg_color="transparent")
        self.header_frame.pack(fill="x", pady=(0, 20))
        
        self.logo_label = ctk.CTkLabel(
            self.header_frame, 
            text="⚙️ WinDeploy", 
            font=ctk.CTkFont(size=24, weight="bold"),
            text_color=COLORS.get("primary", "#2563EB")
        )
        self.logo_label.pack(side="left")

        # Container centrale per le schermate (Screens)
        self.screen_container = ctk.CTkFrame(
            self.main_container, 
            fg_color=COLORS.get("bg_card", "#1F2937"), 
            corner_radius=10
        )
        self.screen_container.pack(fill="both", expand=True)
        self.screen_container.grid_rowconfigure(0, weight=1)
        self.screen_container.grid_columnconfigure(0, weight=1)

        # Footer (Versione in basso a destra)
        self.footer_frame = ctk.CTkFrame(self.main_container, fg_color="transparent")
        self.footer_frame.pack(fill="x", pady=(10, 0))
        
        self.version_label = ctk.CTkLabel(
            self.footer_frame, 
            text=f"v{AGENT_VERSION}", 
            font=ctk.CTkFont(size=12),
            text_color=COLORS.get("text_muted", "#9CA3AF")
        )
        self.version_label.pack(side="right")

        # Entry Point del flusso
        self.show_screen("connect")

    def show_screen(self, name: str):
        """
        Pattern Lazy Instantiation: Distrugge il frame corrente se esiste, 
        istanzia il nuovo frame passando i parametri di stato/callbacks, 
        e lo posizione nel grid.
        """
        if self.current_frame is not None:
            self.current_frame.destroy()

        if name == 'connect':
            # Supporto nativo per schermata base che usava parent/controller
            self.current_frame = ScreenConnect(parent=self.screen_container, controller=self)
            
        elif name == 'overview':
            self.current_frame = ScreenOverview(
                master=self.screen_container,
                wizard_config=self.wizard_config,
                on_start=self.on_overview_start,
                on_back=self.on_overview_back
            )

        elif name == 'uninstall':
            # Try/Except come Adapter: se screen_uninstall è rimasto allo stile 0020
            # viene instanziato in retro-compatibilità, altrimenti usa i callback moderni
            try:
                self.current_frame = ScreenUninstall(
                    master=self.screen_container,
                    wizard_config=self.wizard_config,
                    on_confirm=self.on_uninstall_confirm,
                    on_skip=self.on_uninstall_skip
                )
            except TypeError:
                self.current_frame = ScreenUninstall(parent=self.screen_container, controller=self)
                if hasattr(self.current_frame, "on_show"):
                    self.current_frame.on_show({"wizard_config": self.wizard_config})

        elif name == 'progress':
            try:
                self.current_frame = ScreenProgress(
                    master=self.screen_container,
                    wizard_config=self.wizard_config,
                    on_complete=self.on_progress_complete,
                    on_error=self.on_progress_error
                )
            except TypeError:
                self.current_frame = ScreenProgress(parent=self.screen_container, controller=self)

        elif name == 'complete':
            self.current_frame = ScreenComplete(
                master=self.screen_container,
                result=self.execution_result,
                on_close=self.on_complete_close
            )
        else:
            print(f"[ERROR] Nome schermata sconosciuto: {name}")
            return

        # Fa il grid del nuovo frame (usiamo grid invece di pack perché screen_container
        # è configurato con grid_rowconfigure/columnconfigure)
        self.current_frame.grid(row=0, column=0, sticky="nsew")

    # ==========================================
    # SISTEMA DI CALLBACK E ROUTING A 6 STEP
    # ==========================================

    def on_connect_success(self, wizard_config: dict):
        """ Ricevuto codice valido dall'API """
        self.wizard_config = wizard_config
        self.show_screen('overview')

    def on_overview_start(self):
        """
        Transizione all'avvio operazioni.
        Logica: Se il dizionario wizard_config non contiene la chiave 'bloatware_to_remove' 
        oppure se la lista è vuota, l'operatore non deve perdere tempo sulla schermata
        di disinstallazione. Si salta direttamente al progress agent.
        """
        bloatware = self.wizard_config.get('bloatware_to_remove', [])
        if not bloatware:
            self.show_screen('progress')
        else:
            self.show_screen('uninstall')

    def on_overview_back(self):
        """ Torna alla selezione codice """
        self.show_screen('connect')

    def on_uninstall_confirm(self, selected_apps: list):
        """ Confermate le app da rimuovere dalla UI """
        self.wizard_config['bloatware_to_remove'] = selected_apps
        self.show_screen('progress')

    def on_uninstall_skip(self):
        """ Skipa la rimozione app manually """
        self.show_screen('progress')

    def on_progress_complete(self, result: dict):
        """ Worker concluso con successo """
        self.execution_result = result
        
        # --- AGGIUNTA UPLOAD LOG ---
        wizard_code = self.wizard_config.get('wizard_code', '')
        try:
            from logger import get_log_path
            import threading
            
            log_path = get_log_path(wizard_code)
            if log_path and hasattr(self, 'api_client'):
                threading.Thread(
                    target=self.api_client.upload_log,
                    args=(log_path, wizard_code),
                    daemon=True
                ).start()
        except Exception as e:
            print(f"[WARN] Impossibile avviare il task log_upload: {e}")
        # ---------------------------

        self.show_screen('complete')

    def on_progress_error(self, result: dict):
        """ Worker bloccato da eccezione critica """
        self.execution_result = result
        
        # --- AGGIUNTA UPLOAD LOG ---
        wizard_code = self.wizard_config.get('wizard_code', '')
        try:
            from logger import get_log_path
            import threading
            
            log_path = get_log_path(wizard_code)
            if log_path and hasattr(self, 'api_client'):
                threading.Thread(
                    target=self.api_client.upload_log,
                    args=(log_path, wizard_code),
                    daemon=True
                ).start()
        except Exception as e:
            pass
        # ---------------------------
        
        self.show_screen('complete')

    def on_complete_close(self):
        """ Uscita definitiva """
        self.destroy()

    # ==========================================
    # RETROCOMPATIBILITÀ VECCHIE SCHERMATE (0019/0021)
    # ==========================================
    
    def navigate(self, screen_name: str, payload: dict = None):
        """ 
        Adapter per ScreenConnect e ScreenProgress che richiamano ancora 
        self.controller.navigate(...) internamente. Invia le vecchie rotte
        verso il nuovo motore lazy `show_screen`.
        """
        if payload and "wizard_config" in payload:
            self.wizard_config = payload["wizard_config"]

        if screen_name == "ScreenOverview":
            self.show_screen("overview")
        elif screen_name == "ScreenUninstall":
            self.show_screen("uninstall")
        elif screen_name == "ScreenProgress":
            self.show_screen("progress")
        elif screen_name == "ScreenComplete":
            if payload and "result" in payload:
                self.execution_result = payload["result"]
            self.show_screen("complete")
        elif screen_name == "ScreenConnect":
            self.show_screen("connect")

if __name__ == "__main__":
    app = WinDeployApp()
    app.mainloop()
