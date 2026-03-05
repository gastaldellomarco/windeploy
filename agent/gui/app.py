# agent/gui/app.py
import customtkinter as ctk
from config import AGENT_VERSION, WINDOW_WIDTH, WINDOW_HEIGHT, COLORS

# Import delle schermate (qui importiamo solo la prima per brevità)
from gui.screens.screen_connect import ScreenConnect

class WinDeployApp(ctk.CTk):
    def __init__(self):
        super().__init__()

        # --- Setup Finestra Principale ---
        self.title("WinDeploy Agent")
        self.geometry(f"{WINDOW_WIDTH}x{WINDOW_HEIGHT}")
        self.resizable(False, False)
        
        # Tema e colori globali
        ctk.set_appearance_mode("dark")
        self.configure(fg_color=COLORS["bg_main"])

        # --- Stato dell'Applicazione ---
        # Questo dizionario agisce da "store" globale per passare JWT token e wizard_config
        self.app_state = {}

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
            text_color=COLORS["primary"]
        )
        self.logo_label.pack(side="left")

        # Container centrale per le schermate (Screens)
        self.screen_container = ctk.CTkFrame(
            self.main_container, 
            fg_color=COLORS["bg_card"], 
            corner_radius=10
        )
        self.screen_container.pack(fill="both", expand=True)
        self.screen_container.grid_rowconfigure(0, weight=1)
        self.screen_container.grid_columnconfigure(0, weight=1)

        # --- Inizializzazione Schermate ---
        self.screens = {}
        self.current_screen = None
        self._init_screens()

        # Footer (Versione in basso a destra)
        self.footer_frame = ctk.CTkFrame(self.main_container, fg_color="transparent")
        self.footer_frame.pack(fill="x", pady=(10, 0))
        
        self.version_label = ctk.CTkLabel(
            self.footer_frame, 
            text=f"v{AGENT_VERSION}", 
            font=ctk.CTkFont(size=12),
            text_color=COLORS["text_muted"]
        )
        self.version_label.pack(side="right")

        # Avvio sull'entry point
        self.navigate("ScreenConnect")

    def _init_screens(self):
        """Istanzia le schermate e le posiziona nella griglia sovrapposte."""
        # Dynamically import all modules under gui.screens that start with 'screen_'
        import importlib
        import pkgutil
        from pathlib import Path

        screens_pkg = 'gui.screens'
        pkg_path = Path(__file__).resolve().parent / 'screens'

        for finder, name, ispkg in pkgutil.iter_modules([str(pkg_path)]):
            if not name.startswith('screen_'):
                continue
            module_name = f"{screens_pkg}.{name}"
            try:
                mod = importlib.import_module(module_name)
            except Exception:
                continue

            # find a class in the module that starts with 'Screen'
            cls = None
            for attr in dir(mod):
                if attr.startswith('Screen'):
                    candidate = getattr(mod, attr)
                    if isinstance(candidate, type):
                        cls = candidate
                        break

            if cls:
                # register using the class name as key (e.g. ScreenConnect)
                try:
                    self.screens[cls.__name__] = cls(parent=self.screen_container, controller=self)
                except Exception:
                    # skip screens that fail to instantiate
                    pass

        for screen in self.screens.values():
            screen.grid(row=0, column=0, sticky="nsew")

    def navigate(self, screen_name: str, payload: dict = None):
        """
        Gestisce il cambio schermata e il passaggio di dati.
        """
        # Aggiorna lo stato globale con i nuovi dati in ingresso
        if payload:
            self.app_state.update(payload)

        if screen_name in self.screens:
            # tkraise() porta in primo piano il frame richiesto
            screen = self.screens[screen_name]
            screen.tkraise()
            self.current_screen = screen
            
            # Se la schermata ha un metodo di refresh, passagli lo stato aggiornato
            if hasattr(screen, "on_show"):
                screen.on_show(self.app_state)
        else:
            print(f"[ERROR] Schermata non trovata: {screen_name}")

if __name__ == "__main__":
    app = WinDeployApp()
    app.mainloop()
