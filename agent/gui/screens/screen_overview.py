# agent/gui/screens/screen_overview.py
import customtkinter as ctk

# Fallback robusto per i colori: mappa le costanti dirette al dict COLORS se non esistono globalmente
try:
    from config import DARK_BG, ACCENT, TEXT_MAIN, TEXT_MUTED, WARNING_COLOR, SUCCESS_COLOR, ERROR_COLOR, BORDER_COLOR, PRIMARY_HOVER
except ImportError:
    from config import COLORS
    DARK_BG = COLORS.get("bg_main", "#111827")
    ACCENT = COLORS.get("primary", "#2563EB")
    PRIMARY_HOVER = COLORS.get("primary_hover", "#1D4ED8")
    TEXT_MAIN = COLORS.get("text_main", "#F9FAFB")
    TEXT_MUTED = COLORS.get("text_muted", "#9CA3AF")
    SUCCESS_COLOR = COLORS.get("success", "#10B981")
    ERROR_COLOR = COLORS.get("error", "#EF4444")
    BORDER_COLOR = COLORS.get("border", "#374151")
    WARNING_COLOR = "#EAB308"  # Giallo warning standard Tailwind


class ScreenOverview(ctk.CTkFrame):
    def __init__(self, master, wizard_config: dict, on_start: callable, on_back: callable):
        super().__init__(master, fg_color="transparent")
        self.wizard_config = wizard_config
        self.on_start = on_start
        self.on_back = on_back
        
        # Configurazione layout a griglia base
        self.grid_columnconfigure(0, weight=1)
        self.grid_rowconfigure(2, weight=1)  # Spazio espandibile per il contenuto
        
        # --- Header ---
        self.title_label = ctk.CTkLabel(
            self, 
            text="Riepilogo configurazione", 
            font=ctk.CTkFont(size=24, weight="bold"),
            text_color=TEXT_MAIN
        )
        self.title_label.grid(row=0, column=0, pady=(20, 5))
        
        self.subtitle_label = ctk.CTkLabel(
            self, 
            text="Verifica i dati prima di avviare — le operazioni non sono reversibili", 
            font=ctk.CTkFont(size=14, weight="bold"),
            text_color=WARNING_COLOR
        )
        self.subtitle_label.grid(row=1, column=0, pady=(0, 20))
        
        # --- Layout Centrato a due colonne ---
        self.content_frame = ctk.CTkFrame(self, fg_color="transparent")
        self.content_frame.grid(row=2, column=0, sticky="nsew", padx=40)
        self.content_frame.grid_columnconfigure(0, weight=1)
        self.content_frame.grid_columnconfigure(1, weight=1)
        self.content_frame.grid_rowconfigure(0, weight=1)
        self.content_frame.grid_rowconfigure(1, weight=1)
        
        # 1. Sezione Identità PC (Sinistra Alto)
        self.identity_frame = ctk.CTkFrame(self.content_frame, fg_color=DARK_BG, border_color=BORDER_COLOR, border_width=1)
        self.identity_frame.grid(row=0, column=0, sticky="nsew", padx=10, pady=10)
        
        ctk.CTkLabel(self.identity_frame, text="Identità PC", font=ctk.CTkFont(size=16, weight="bold"), text_color=ACCENT).pack(anchor="w", padx=15, pady=(15, 10))
        
        # Utilizzo safe access con fallback per prevenire KeyError
        pc_name = self.wizard_config.get("pc_name", "Non specificato")
        admin_user = self.wizard_config.get("admin_user", {}).get("username", "Non specificato")
        domain = self.wizard_config.get("domain", "WORKGROUP")
        
        self._add_info_row(self.identity_frame, "Nome PC:", pc_name)
        self._add_info_row(self.identity_frame, "Utente admin:", admin_user)
        self._add_info_row(self.identity_frame, "Dominio/WG:", domain)
        
        # 2. Sezione Operazioni di sistema (Sinistra Basso)
        self.sys_frame = ctk.CTkFrame(self.content_frame, fg_color=DARK_BG, border_color=BORDER_COLOR, border_width=1)
        self.sys_frame.grid(row=1, column=0, sticky="nsew", padx=10, pady=10)
        
        ctk.CTkLabel(self.sys_frame, text="Operazioni di sistema", font=ctk.CTkFont(size=16, weight="bold"), text_color=ACCENT).pack(anchor="w", padx=15, pady=(15, 10))
        
        power_plan = self.wizard_config.get("power_plan", "Balanced")
        bloatware_list = self.wizard_config.get("bloatware_to_remove", [])
        bloatware_text = f"{len(bloatware_list)} app selezionate per la rimozione"
        
        self._add_info_row(self.sys_frame, "Power plan:", power_plan)
        self._add_info_row(self.sys_frame, "Bloatware:", bloatware_text)
        
        extras = self.wizard_config.get("extras", {})
        extras_frame = ctk.CTkFrame(self.sys_frame, fg_color="transparent")
        extras_frame.pack(fill="x", padx=15, pady=5)
        
        if not extras:
            ctk.CTkLabel(extras_frame, text="Nessun extra selezionato", text_color=TEXT_MUTED).pack(anchor="w")
        else:
            ctk.CTkLabel(extras_frame, text="Extras:", font=ctk.CTkFont(weight="bold"), text_color=TEXT_MUTED).pack(anchor="w")
            for key, value in extras.items():
                icon = "✓" if value else "✗"
                color = SUCCESS_COLOR if value else ERROR_COLOR
                row = ctk.CTkFrame(extras_frame, fg_color="transparent")
                row.pack(fill="x", padx=10, pady=2)
                ctk.CTkLabel(row, text=icon, text_color=color, font=ctk.CTkFont(weight="bold")).pack(side="left")
                ctk.CTkLabel(row, text=str(key), text_color=TEXT_MAIN).pack(side="left", padx=5)
        
        # 3. Sezione Software da installare (Destra Altezza Piena)
        self.software_frame = ctk.CTkFrame(self.content_frame, fg_color=DARK_BG, border_color=BORDER_COLOR, border_width=1)
        self.software_frame.grid(row=0, column=1, rowspan=2, sticky="nsew", padx=10, pady=10)
        
        ctk.CTkLabel(self.software_frame, text="Software da installare", font=ctk.CTkFont(size=16, weight="bold"), text_color=ACCENT).pack(anchor="w", padx=15, pady=(15, 10))
        
        self.software_scroll = ctk.CTkScrollableFrame(self.software_frame, fg_color="transparent")
        self.software_scroll.pack(fill="both", expand=True, padx=10, pady=(0, 15))
        
        software_list = self.wizard_config.get("software_list", [])
        if not software_list:
            ctk.CTkLabel(self.software_scroll, text="Nessun software selezionato", text_color=TEXT_MUTED).pack(pady=20)
        else:
            for sw in software_list:
                nome_sw = sw.get("name", str(sw)) if isinstance(sw, dict) else str(sw)
                sw_row = ctk.CTkFrame(self.software_scroll, fg_color="transparent")
                sw_row.pack(fill="x", pady=2)
                ctk.CTkLabel(sw_row, text="✓", text_color=SUCCESS_COLOR, font=ctk.CTkFont(weight="bold")).pack(side="left", padx=(5, 10))
                ctk.CTkLabel(sw_row, text=nome_sw, text_color=TEXT_MAIN).pack(side="left")

        # --- Footer Bottoni ---
        self.buttons_frame = ctk.CTkFrame(self, fg_color="transparent")
        self.buttons_frame.grid(row=3, column=0, pady=30)
        
        self.btn_back = ctk.CTkButton(
            self.buttons_frame,
            text="← Indietro",
            fg_color=BORDER_COLOR,
            hover_color=TEXT_MUTED,
            text_color=TEXT_MAIN,
            width=150,
            height=40,
            font=ctk.CTkFont(weight="bold"),
            command=self.on_back
        )
        self.btn_back.pack(side="left", padx=10)
        
        self.btn_start = ctk.CTkButton(
            self.buttons_frame,
            text="▶ Avvia configurazione",
            fg_color=ACCENT,
            hover_color=PRIMARY_HOVER,
            text_color=TEXT_MAIN,
            width=200,
            height=40,
            font=ctk.CTkFont(weight="bold"),
            command=self._handle_start
        )
        self.btn_start.pack(side="left", padx=10)

    def _add_info_row(self, parent, label_text, value_text):
        """Helper interno per renderizzare righe chiave/valore in modo uniforme."""
        row = ctk.CTkFrame(parent, fg_color="transparent")
        row.pack(fill="x", padx=15, pady=5)
        ctk.CTkLabel(row, text=label_text, text_color=TEXT_MUTED, width=120, anchor="w").pack(side="left")
        ctk.CTkLabel(row, text=str(value_text), text_color=TEXT_MAIN, anchor="w").pack(side="left", fill="x", expand=True)

    def _handle_start(self):
        """Previene il double-click disabilitando il tasto e avvia il callback."""
        self.btn_start.configure(state="disabled")
        self.btn_back.configure(state="disabled")
        self.on_start()
