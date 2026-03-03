# File: screen_uninstall.py
# Path: windeploy\agent\gui\screens\screen_uninstall.py

import customtkinter as ctk
import threading
from system_scanner import SystemScanner
from config import COLORS

class ScreenUninstall(ctk.CTkFrame):
    def __init__(self, parent, controller):
        super().__init__(parent, fg_color="transparent")
        self.controller = controller
        self.pack_propagate(False)
        self.scanner = SystemScanner()
        
        self.all_apps = []
        self.checkboxes = [] # List of tuples: (CTkCheckBox instance, app_data dict)
        self.current_filter = "Tutte"
        self.search_query = ""
        self.preselected_bloatware = []
        
        self._build_ui()

    def _build_ui(self):
        # Header
        self.header_frame = ctk.CTkFrame(self, fg_color="transparent")
        self.header_frame.pack(fill="x", pady=(20, 10))
        
        self.title_label = ctk.CTkLabel(self.header_frame, text="Seleziona le app da rimuovere", 
                                        font=ctk.CTkFont(size=24, weight="bold"), text_color=COLORS["text_main"])
        self.title_label.pack()
        
        self.subtitle_label = ctk.CTkLabel(self.header_frame, text="In attesa di scansione...", 
                                           font=ctk.CTkFont(size=14), text_color=COLORS["text_muted"])
        self.subtitle_label.pack()

        # Tools: Search & Filters
        self.tools_frame = ctk.CTkFrame(self, fg_color="transparent")
        self.tools_frame.pack(fill="x", padx=20, pady=(0, 10))
        
        self.search_entry = ctk.CTkEntry(self.tools_frame, placeholder_text="Cerca app...", 
                                         width=250, fg_color=COLORS["bg_main"], border_color=COLORS["border"])
        self.search_entry.pack(side="left", padx=(0, 15))
        self.search_entry.bind("<KeyRelease>", self.on_search)
        
        # Filter buttons
        self.filter_buttons = {}
        filters = ["Tutte", "App Store", "Programmi", "Pre-selezionate"]
        
        for f in filters:
            btn = ctk.CTkButton(self.tools_frame, text=f, width=100, 
                                fg_color=COLORS["bg_main"] if f != "Tutte" else COLORS["primary"],
                                hover_color=COLORS["primary_hover"],
                                command=lambda f_type=f: self.set_filter(f_type))
            btn.pack(side="left", padx=5)
            self.filter_buttons[f] = btn

        # Scrollable List Container
        self.scroll_frame = ctk.CTkScrollableFrame(self, fg_color=COLORS["bg_card"], corner_radius=10)
        self.scroll_frame.pack(fill="both", expand=True, padx=20, pady=5)
        
        self.spinner_label = ctk.CTkLabel(self.scroll_frame, text="Scansione in corso...\nAttendere prego, potrebbe richiedere alcuni secondi.", 
                                          font=ctk.CTkFont(size=16), text_color=COLORS["text_muted"])

        # Footer
        self.footer_frame = ctk.CTkFrame(self, fg_color="transparent")
        self.footer_frame.pack(fill="x", padx=20, pady=20)
        
        self.count_label = ctk.CTkLabel(self.footer_frame, text="0 selezionate", 
                                        font=ctk.CTkFont(size=16, weight="bold"), text_color=COLORS["text_main"])
        self.count_label.pack(side="left")
        
        self.btn_confirm = ctk.CTkButton(self.footer_frame, text="CONFERMA", width=150, height=45,
                                         fg_color=COLORS["primary"], hover_color=COLORS["primary_hover"],
                                         font=ctk.CTkFont(weight="bold"), state="disabled",
                                         command=self.on_confirm)
        self.btn_confirm.pack(side="right", padx=(15, 0))
        
        self.btn_back = ctk.CTkButton(self.footer_frame, text="INDIETRO", width=150, height=45,
                                      fg_color="transparent", border_width=1, border_color=COLORS["border"],
                                      hover_color=COLORS["bg_card"], text_color=COLORS["text_main"],
                                      command=self.on_back)
        self.btn_back.pack(side="right")

    def on_show(self, app_state):
        # Read bloatware list from the central App State (passed from wizard API)
        wizard_config = app_state.get("wizard_config", {})
        self.preselected_bloatware = wizard_config.get("bloatware", [])
        
        self._clear_list()
        self.spinner_label.pack(pady=100)
        self.btn_confirm.configure(state="disabled")
        
        # Multithreading to avoid freezing CustomTkinter GUI
        threading.Thread(target=self._scan_thread, daemon=True).start()

    def _scan_thread(self):
        apps = self.scanner.get_installed_apps()
        # Always use .after() to pass data back to the main UI thread
        self.after(0, self._on_scan_complete, apps)

    def _on_scan_complete(self, apps):
        self.all_apps = apps
        self.btn_confirm.configure(state="normal")
        self.render_list()

    def set_filter(self, filter_type):
        self.current_filter = filter_type
        for name, btn in self.filter_buttons.items():
            if name == filter_type:
                btn.configure(fg_color=COLORS["primary"])
            else:
                btn.configure(fg_color=COLORS["bg_main"])
        self.render_list()

    def on_search(self, event):
        self.search_query = self.search_entry.get().lower()
        self.render_list()

    def _clear_list(self):
        for widget in self.scroll_frame.winfo_children():
            widget.destroy()
        self.checkboxes.clear()

    def render_list(self):
        self._clear_list()
        
        total_found = len(self.all_apps)
        preset_count = 0
        
        for app in self.all_apps:
            # Check bloatware match
            is_preset = any(b.lower() in app["name"].lower() for b in self.preselected_bloatware) if self.preselected_bloatware else False
            if is_preset:
                preset_count += 1
                
            # Filters
            if self.current_filter == "App Store" and app["type"] != "store": continue
            if self.current_filter == "Programmi" and app["type"] not in ["win32", "winget"]: continue
            if self.current_filter == "Pre-selezionate" and not is_preset: continue
            
            if self.search_query and self.search_query not in app["name"].lower() and self.search_query not in app["publisher"].lower():
                continue

            # App Row layout
            row = ctk.CTkFrame(self.scroll_frame, fg_color="transparent")
            row.pack(fill="x", pady=2)
            
            # Orange for preset, default primary otherwise
            cb_color = "#F97316" if is_preset else COLORS["primary"]
            
            # Checkbox automatically stores its state (1 or 0) internally
            cb = ctk.CTkCheckBox(row, text=app["name"], 
                                 fg_color=cb_color, hover_color=cb_color,
                                 command=self.update_selection_count, width=350,
                                 font=ctk.CTkFont(weight="bold" if is_preset else "normal"))
            if is_preset:
                cb.select()
                
            cb.pack(side="left", padx=(10, 5))
            self.checkboxes.append((cb, app))
            
            # Badges and Labels
            type_color = COLORS["primary"] if app["type"] == "store" else COLORS["text_muted"]
            type_lbl = ctk.CTkLabel(row, text=app["type"].upper(), width=70, 
                                    fg_color=COLORS["bg_main"], corner_radius=6, 
                                    text_color=type_color, font=ctk.CTkFont(size=11, weight="bold"))
            type_lbl.pack(side="right", padx=10)

            truncated_pub = (app["publisher"][:20] + '..') if len(app["publisher"]) > 20 else app["publisher"]
            pub_lbl = ctk.CTkLabel(row, text=truncated_pub, width=150, anchor="w", text_color=COLORS["text_muted"])
            pub_lbl.pack(side="right", padx=10)

            ver_lbl = ctk.CTkLabel(row, text=app["version"], width=100, anchor="w", text_color=COLORS["text_muted"])
            ver_lbl.pack(side="right", padx=10)

        self.subtitle_label.configure(text=f"{total_found} app trovate • {preset_count} pre-selezionate")
        self.update_selection_count()

    def update_selection_count(self):
        count = len(self.get_selected())
        self.count_label.configure(text=f"{count} selezionate")

    def get_selected(self):
        return [app for cb, app in self.checkboxes if cb.get() == 1]

    def on_confirm(self):
        selected_apps = self.get_selected()
        self.controller.app_state["apps_to_uninstall"] = selected_apps
        self.controller.navigate("ScreenProgress")

    def on_back(self):
        self.controller.navigate("ScreenOverview")
