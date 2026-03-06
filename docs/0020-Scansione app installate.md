<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Continuo il progetto WinDeploy agent Python. Scrivi questi due file completi:

━━━ FILE 1: system_scanner.py ━━━
 
Classe SystemScanner con metodo get_installed_apps() che ritorna una lista di dizionari:
[{ "name": "...", "version": "...", "publisher": "...", "type": "win32|store|winget",
   "uninstall_string": "...", "quiet_uninstall_string": "...", "id": "...", "size_mb": ... }]
 
Deve raccogliere app da TRE fonti:

1. Registro Windows (HKLM e HKCU):
   SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall
   SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall
   Campi: DisplayName, DisplayVersion, Publisher, UninstallString, QuietUninstallString, EstimatedSize
2. App Microsoft Store (PowerShell: Get-AppxPackage):
   Filtra solo le app utente (non di sistema).
   Usa subprocess per eseguire: powershell -Command "Get-AppxPackage | Select Name,Version,PackageFullName | ConvertTo-Json"
3. App rilevabili via winget (subprocess: winget list --output json):
   Merge con la lista esistente per arricchire le info (evita duplicati per nome)
 
FILTRAGGIO: Escludi automaticamente le app di sistema critiche che non devono mai essere rimosse:
Lista di esclusione: Microsoft Visual C++ Redistributable, .NET Runtime, Windows Terminal,
DirectX, Microsoft Edge WebView2, tutti gli aggiornamenti Windows, driver.
 
Metodo: is_system_critical(app_name) → bool
 
━━━ FILE 2: gui/screens/screen_uninstall.py ━━━
 
Schermata CustomTkinter per selezione app da disinstallare.
 
LAYOUT:

- Titolo "Seleziona le app da rimuovere"
- Sotto-titolo con contatore: "X app trovate • Y pre-selezionate"
- Barra di ricerca (filtra la lista in tempo reale)
- Filtri rapidi: bottoni "Tutte / App Store / Programmi / Pre-selezionate"
- Lista scrollabile con CheckBox per ogni app:
    [checkbox] NomeApp          versione       publisher       [badge tipo]
  Le app pre-selezionate (bloatware dal wizard) arrivano già spuntate in arancione
  Le altre sono bianche e deselezionate
- In basso: contatore "X selezionate", bottone INDIETRO e bottone CONFERMA
 
La lista viene popolata con i dati di SystemScanner in un thread separato
(mostra spinner "Scansione in corso..." durante il caricamento).
Durante la scansione la UI non si deve bloccare (usa threading + after() di Tkinter).
 
Includi anche una funzione get_selected() che ritorna la lista delle app selezionate.
Codice completo.

Ecco l'implementazione completa dei due moduli per l'agent Windows, strutturati per integrarsi perfettamente con il tuo ecosistema WinDeploy basato su React, Laravel e Python.[^1_1][^1_2][^1_3]

### Struttura e Posizionamento dei File

Prima di implementare il codice, assicurati che i file siano collocati in questo modo all'interno della directory `agent/`:

- `system_scanner.py` (nella root dell'agent, accanto a `main.py` e `config.py`)
- `gui/screens/screen_uninstall.py` (nella cartella dedicata alle UI)

***

### File 1: `system_scanner.py`

Questo modulo sfrutta la libreria `winreg` di Python per navigare le chiavi di registro di Windows, e usa `subprocess` in modalità silente (`CREATE_NO_WINDOW`) per interrogare PowerShell e Winget senza far apparire fastidiose finestre pop-up sul PC.[^1_4]

```python
import winreg
import subprocess
import json
import logging
from typing import List, Dict, Any

class SystemScanner:
    """
    Scanner for installed applications via Registry, Windows Store, and Winget.
    """
    def __init__(self):
        # Critical keywords in lowercase for the exclusion filter
        self.critical_keywords = [
            "microsoft visual c++ redistributable",
            ".net runtime",
            "windows terminal",
            "directx",
            "microsoft edge webview2",
            "update",
            "driver"
        ]

    def is_system_critical(self, app_name: str) -> bool:
        """
        Check if the app is a system critical component that should never be removed.
        """
        if not app_name:
            return True
            
        name_lower = app_name.lower()
        return any(keyword in name_lower for keyword in self.critical_keywords)

    def get_installed_apps(self) -> List[Dict[str, Any]]:
        apps_dict = {}

        # 1. Registry Apps (Win32)
        for app in self._scan_registry():
            if not self.is_system_critical(app["name"]):
                apps_dict[app["name"]] = app

        # 2. Microsoft Store Apps
        for app in self._scan_store_apps():
            if not self.is_system_critical(app["name"]):
                apps_dict[app["name"]] = app

        # 3. Winget Apps (Enrich existing or add new)
        for app in self._scan_winget():
            name = app["name"]
            if not self.is_system_critical(name):
                if name in apps_dict:
                    # Enrich existing data with Winget ID for better uninstallation
                    apps_dict[name]["id"] = app.get("id", apps_dict[name]["id"])
                    apps_dict[name]["type"] = "winget"
                else:
                    apps_dict[name] = app

        return list(apps_dict.values())

    def _scan_registry(self) -> List[Dict[str, Any]]:
        paths = [
            (winreg.HKEY_LOCAL_MACHINE, r"SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall"),
            (winreg.HKEY_LOCAL_MACHINE, r"SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall"),
            (winreg.HKEY_CURRENT_USER, r"SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall")
        ]
        
        results = []
        for hive, path in paths:
            try:
                with winreg.OpenKey(hive, path) as key:
                    num_subkeys = winreg.QueryInfoKey(key)[^1_0]
                    for i in range(num_subkeys):
                        try:
                            sub_key_name = winreg.EnumKey(key, i)
                            with winreg.OpenKey(key, sub_key_name) as sub_key:
                                display_name = self._get_reg_value(sub_key, "DisplayName")
                                if not display_name:
                                    continue
                                
                                uninstall_string = self._get_reg_value(sub_key, "UninstallString", "")
                                quiet_uninstall_string = self._get_reg_value(sub_key, "QuietUninstallString", "")
                                
                                if not uninstall_string and not quiet_uninstall_string:
                                    continue
                                
                                size_kb = self._get_reg_value(sub_key, "EstimatedSize", 0)
                                size_mb = round(size_kb / 1024, 2) if isinstance(size_kb, int) else 0.0

                                results.append({
                                    "name": display_name,
                                    "version": self._get_reg_value(sub_key, "DisplayVersion", ""),
                                    "publisher": self._get_reg_value(sub_key, "Publisher", ""),
                                    "type": "win32",
                                    "uninstall_string": uninstall_string,
                                    "quiet_uninstall_string": quiet_uninstall_string,
                                    "id": sub_key_name,
                                    "size_mb": size_mb
                                })
                        except OSError:
                            continue
            except FileNotFoundError:
                continue
        return results

    def _get_reg_value(self, key, value_name: str, default: Any = None) -> Any:
        try:
            return winreg.QueryValueEx(key, value_name)[^1_0]
        except OSError:
            return default

    def _scan_store_apps(self) -> List[Dict[str, Any]]:
        results = []
        # Filter NonRemovable and System signature directly in PowerShell
        cmd = 'Get-AppxPackage | Where-Object { $_.SignatureKind -ne "System" -and $_.NonRemovable -eq $false } | Select-Object Name,Version,PackageFullName | ConvertTo-Json -Compress'
        try:
            process = subprocess.run(
                ["powershell", "-NoProfile", "-Command", cmd], 
                capture_output=True, text=True, check=True, creationflags=subprocess.CREATE_NO_WINDOW
            )
            output = process.stdout.strip()
            if not output:
                return results
                
            apps = json.loads(output)
            # PowerShell ConvertTo-Json returns a dict if there's only 1 item, so we cast it to list
            if isinstance(apps, dict):
                apps = [apps]
                
            for app in apps:
                results.append({
                    "name": app.get("Name", ""),
                    "version": app.get("Version", ""),
                    "publisher": "Microsoft Store",
                    "type": "store",
                    "uninstall_string": f"Remove-AppxPackage -Package {app.get('PackageFullName', '')}",
                    "quiet_uninstall_string": f"Remove-AppxPackage -Package {app.get('PackageFullName', '')}",
                    "id": app.get("PackageFullName", ""),
                    "size_mb": 0.0
                })
        except Exception as e:
            logging.error(f"Error scanning Store apps: {e}")
            
        return results

    def _scan_winget(self) -> List[Dict[str, Any]]:
        results = []
        try:
            # Force accept agreements to prevent subprocess hanging
            process = subprocess.run(
                ["winget", "list", "--accept-source-agreements", "--output", "json"], 
                capture_output=True, text=True, creationflags=subprocess.CREATE_NO_WINDOW
            )
            
            if process.returncode == 0 and process.stdout:
                try:
                    apps = json.loads(process.stdout)
                    # Depending on winget version, JSON payload might be wrapped in "Data"
                    if isinstance(apps, dict) and "Data" in apps:
                        apps = apps["Data"]
                        
                    for app in apps:
                        results.append({
                            "name": app.get("Name", ""),
                            "version": app.get("Version", ""),
                            "publisher": app.get("Publisher", ""),
                            "type": "winget",
                            "uninstall_string": f"winget uninstall --id \"{app.get('Id', '')}\" --silent",
                            "quiet_uninstall_string": f"winget uninstall --id \"{app.get('Id', '')}\" --silent",
                            "id": app.get("Id", ""),
                            "size_mb": 0.0
                        })
                except json.JSONDecodeError:
                    logging.warning("Winget output is not valid JSON. Possibly unsupported Winget version.")
        except Exception as e:
            logging.error(f"Error scanning Winget: {e}")
            
        return results
```


***

### File 2: `gui/screens/screen_uninstall.py`

Questo file implementa un `CTkScrollableFrame` per gestire centinaia di righe senza bloccare la finestra. La classe estrae dinamicamente i valori `.get()` direttamente dai widget CheckBox creati nel loop, una best practice standard di CustomTkinter per i frame dinamici.[^1_5][^1_6]

```python
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
```


***

### L'Avvocato del Diavolo: Analisi Critica, Rischi e Trade-off

Dal punto di vista sistemistico ed enterprise, questo design è rapido da costruire ma soffre di criticità occulte che si manifesteranno non appena tenterai di scalare il deployment a flussi aziendali non presidiati.

- **Limiti e fallimenti occulti di Winget JSON**: Molte release stabili di Winget attualmente integrate in Windows 11 non supportano ancora in modo affidabile il flag `--output json` al di fuori di alcuni comandi di ricerca/preview. C'è il rischio altissimo che il processo di parsing si interrompa sollevando una `JSONDecodeError` e saltando tutto l'inventario Winget. Il fallback consigliato per scenari di produzione è interfacciarsi con Winget usando il modulo COM nativo o `Get-WinGetPackage` via PowerShell.
- **Blocco dell'UI di Unistall (Ghosting)**: Usare `QuietUninstallString` o `UninstallString` sui classici programmi Win32 è un campo minato. Moltissimi vendor (es. driver stampanti o gestionali legacy) creano stringhe di unistall che ignorano i flag `/S` o `/qn` nel registro. Se l'agent Python esegue questi comandi in loop nella `ScreenProgress`, una GUI invisibile di disinstallazione rimarrà appesa nel limbo in background, bloccando l'esecuzione dell'agent WinDeploy per ore.
- **App Provisionate vs App Utente**: Con `Get-AppxPackage` rimuovi l'app solo per l'utente locale appena creato. Quando in azienda accederà l'utente Active Directory o un secondo dipendente, le app (es. Xbox, Candy Crush) verranno ri-iniettate istantaneamente da Windows. Per rendere l'azione scalabile ed eliminare la root cause, il codice deve richiamare la rimozione a monte usando il comando PowerShell `Remove-AppxProvisionedPackage -Online`.[^1_7][^1_8]
- **Filtri di sicurezza "Greedy"**: L'implementazione del metodo `is_system_critical` con blocco a substring e lower-case (es. la stringa `update`) è intrinsecamente pericolosa. Se un software aziendale si chiama "Lenovo System Updater", verrà bloccato. La prassi corretta in ambito Endpoint Management (es. Intune) prevede la whitelisting degli id di pacchetto (`PackageFullName` o `Publisher`) piuttosto che una soft-matching dei nomi.
<span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_19][^1_20][^1_21][^1_22][^1_23][^1_24][^1_25][^1_26][^1_27][^1_28][^1_29][^1_30][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: 0019-Struttura-agent.md

[^1_2]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^1_3]: 0001-projectdescription.txt

[^1_4]: https://stackoverflow.com/questions/27967795/how-do-i-check-if-an-application-is-installed-in-windows-using-python

[^1_5]: https://customtkinter.tomschimansky.com/tutorial/scrollable-frames/

[^1_6]: https://customtkinter.tomschimansky.com/tutorial/frames/

[^1_7]: https://stackoverflow.com/questions/58730646/select-string-doesnt-show-all-matches-with-get-appxpackage

[^1_8]: https://www.scribd.com/document/863119411/Boost-Pc

[^1_9]: 0017-motorpage.md

[^1_10]: 0016-wizards.md

[^1_11]: 0015-dashboard.md

[^1_12]: 0014-Login.md

[^1_13]: 0013-SetupprogettoReact.md

[^1_14]: 0012-apiendpointwindows.md

[^1_15]: 0011-controllercrud.md

[^1_16]: 0010-autenticazione.md

[^1_17]: 0009-scaffoldprogetto.md

[^1_18]: 0008-db.md

[^1_19]: 0007-cheatsheet.md

[^1_20]: 0006-viteconfigjs.md

[^1_21]: 0005-filedotenv.md

[^1_22]: 0004-Strutturacartelle.md

[^1_23]: 0003-setupiniziale.md

[^1_24]: 0002-ricerca-iniziale.md

[^1_25]: https://customtkinter.tomschimansky.com/tutorial/grid-system/

[^1_26]: https://stackoverflow.com/questions/78290384/how-to-make-scrollable-dropdown-list-in-combobox-customtkinter

[^1_27]: https://github.com/TomSchimansky/CustomTkinter/wiki/CTkScrollableFrame

[^1_28]: https://dev.to/devasservice/customtkinter-a-complete-tutorial-4527

[^1_29]: https://www.youtube.com/watch?v=N89Ha6IqxKc

[^1_30]: https://www.reddit.com/r/learnpython/comments/flzfyy/uninstall_windows_programs_with_python/

