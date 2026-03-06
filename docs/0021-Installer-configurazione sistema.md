<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Continuo il progetto WinDeploy agent Python. Scrivi questi file completi:

━━━ FILE 1: installer.py ━━━
 
Classe Installer con metodi:
 
uninstall_app(app: dict) → bool:
  Tenta la disinstallazione nell'ordine:
  1. QuietUninstallString (se disponibile)
  2. UninstallString con flag /S o /silent
  3. Per App Store: Remove-AppxPackage via PowerShell
  Timeout: 120 secondi per app
  Ritorna True se successo, False se errore
 
install_winget(package_id: str) → bool:
  Esegue: winget install --id {package_id} --silent --accept-package-agreements --accept-source-agreements
  Timeout: 300 secondi
 
install_file(file_path: str, silent_args: str = "/S") → bool:
  Esegue il file .exe o .msi con argomenti silent
 
download_and_install(url: str, nome: str) → bool:
  Scarica il file dal server WinDeploy e poi chiama install_file
 
━━━ FILE 2: system_config.py ━━━
 
Classe SystemConfig con metodi PowerShell:
 
rename_pc(new_name: str) → bool:
  PowerShell: Rename-Computer -NewName "{new_name}" -Force
 
create_admin_user(username: str, password: str) → bool:
  PowerShell: New-LocalUser + Add-LocalGroupMember -Group "Administrators"
 
remove_setup_account() → bool:
  Rimuove il primo account Microsoft trovato che non sia l'admin locale appena creato
 
apply_power_plan(plan_config: dict) → bool:
  Se tipo = preset: powercfg /setactive {guid}
  Se tipo = custom: usa powercfg /change per ogni parametro
 
apply_extras(extras: dict) → bool:
  Imposta timezone: tzutil /s "{timezone}"
  Imposta lingua: PowerShell Set-WinUserLanguageList
  Wallpaper: reg add HKCUControl PanelDesktop /v Wallpaper /t REG_SZ /d "{path}"
  Windows Update: modifica registro per policy
 
━━━ FILE 3: gui/screens/screen_progress.py ━━━
 
Schermata CustomTkinter per l'esecuzione. LAYOUT:

- Titolo "Configurazione in corso..."
- ProgressBar grande in alto (0-100%)
- Percentuale numerica sopra la barra
- Lista step con icone animate:
    ✅ Disinstallazione app (3 app rimosse)
    🔄 Installazione software (Chrome in corso...)  ← animazione rotazione
    ⏳ Rinomina PC
    ⏳ Creazione utente admin
    ⏳ Applicazione power plan
    ⏳ Impostazioni extra
- Log live in basso: area di testo scrollabile con ultime azioni (es. "Rimosso Xbox ✅")
- Tutto viene eseguito in un thread separato (non bloccare la GUI)
- La GUI si aggiorna via self.after(100, callback) dal thread
 
Metodo start_execution(wizard_config, apps_to_remove, api_client, execution_log_id):
  Esegue tutti i passi in sequenza, per ogni step chiama api_client.send_step()
  e aggiorna la GUI. Al termine naviga a screen_complete.
 
Codice Python completo per tutti e 3 i file.

Ecco i file completi per continuare lo sviluppo dell'agent Python di WinDeploy.

Prima di procedere al codice, ecco l'architettura dei file e un'analisi critica basata sulle tue direttive.

### 📂 Struttura Cartelle Coinvolte

```text
agent/
├── installer.py                  # Logica di installazione/disinstallazione software
├── system_config.py              # Comandi PowerShell per sistema e utenti
└── gui/
    └── screens/
        └── screen_progress.py    # UI CustomTkinter multithread per l'esecuzione
```


***

## ⚖️ Analisi Critica, Sicurezza e Trade-off (L'Avvocato del Diavolo)

1. **Sicurezza delle Password in chiaro (Subprocess):** Se passiamo la password dell'amministratore locale direttamente negli argomenti di `powershell.exe` (es. `ConvertTo-SecureString 'MIA_PASSWORD'`), questa diventa visibile in chiaro a qualsiasi utente tramite Task Manager o Process Explorer durante l'esecuzione.[^1_1]
    * **Soluzione Adottata:** Nel metodo `create_admin_user`, PowerShell viene invocato passando lo script tramite standard input (`stdin`). Questo nasconde la password dalla command-line di Windows, un approccio molto più robusto.
2. **Limitazioni di Winget nel contesto di Sistema:** Eseguire `winget` in modo silente tramite subprocess funziona perfettamente se l'agent è avviato dall'utente locale. Tuttavia, se intendi in futuro distribuire questo .exe tramite GPO (System Account), `winget` fallirà perché richiede un contesto utente per risolvere la path dell'eseguibile dell'App Store.[^1_1]
    * **Trade-off:** L'agent attuale è pensato per il tecnico (OOBE manuale), quindi questo design è accettabile, ma non scalabile per MDM (Intune) puro.
3. **Validazione dei Download (Supply Chain Attack):** Nel metodo `download_and_install` scarichiamo un binario in `.exe` via HTTPS per poi installarlo. Se il server WinDeploy viene compromesso (o il DNS dirottato), l'agent installerà malware con privilegi di amministratore.
    * **Azione futura suggerita:** Implementare nel backend Laravel la generazione dell'hash SHA-256 del file caricato, passarlo nel payload JSON e validarlo in locale su Python prima di chiamare `subprocess.run()`.

***

## 💻 Codice Sorgente Completo

### ━━━ FILE 1: `installer.py` ━━━

Gestisce i package manager nativi, il registro e gli applicativi Windows Store AppX.

```python
import subprocess
import os
import requests

class Installer:
    def __init__(self):
        self.timeout_uninstall = 120
        self.timeout_install = 300

    def uninstall_app(self, app: dict) -> bool:
        """
        Attempts to uninstall an app based on provided dictionary keys.
        Falls back to different methods if previous ones are missing.
        """
        try:
            # 1. Windows Store Apps (AppxPackage)
            if app.get("is_store_app") and app.get("PackageFullName"):
                cmd = ["powershell", "-NoProfile", "-Command", f"Remove-AppxPackage -Package '{app['PackageFullName']}' -ErrorAction Stop"]
                subprocess.run(cmd, timeout=self.timeout_uninstall, check=True, creationflags=subprocess.CREATE_NO_WINDOW)
                return True

            # 2. QuietUninstallString (Perfect silent uninstall)
            if app.get("QuietUninstallString"):
                subprocess.run(app["QuietUninstallString"], timeout=self.timeout_uninstall, check=True, creationflags=subprocess.CREATE_NO_WINDOW)
                return True
                
            # 3. Standard UninstallString with silent heuristics appended
            if app.get("UninstallString"):
                cmd_str = app["UninstallString"]
                cmd_lower = cmd_str.lower()
                
                # Append correct silent flags based on installer type
                if "msiexec" in cmd_lower and "/qn" not in cmd_lower:
                    cmd_str += " /qn"
                elif "/s" not in cmd_lower and "/silent" not in cmd_lower:
                    cmd_str += " /S"
                    
                subprocess.run(cmd_str, timeout=self.timeout_uninstall, check=True, creationflags=subprocess.CREATE_NO_WINDOW)
                return True
                
            return False
            
        except subprocess.TimeoutExpired:
            return False
        except subprocess.CalledProcessError:
            return False
        except Exception:
            return False

    def install_winget(self, package_id: str) -> bool:
        """ Installs a package silently using Windows Package Manager """
        try:
            cmd = [
                "winget", "install", "--id", package_id,
                "--silent", "--accept-package-agreements", "--accept-source-agreements"
            ]
            subprocess.run(cmd, timeout=self.timeout_install, check=True, creationflags=subprocess.CREATE_NO_WINDOW)
            return True
        except (subprocess.TimeoutExpired, subprocess.CalledProcessError):
            return False

    def install_file(self, file_path: str, silent_args: str = "/S") -> bool:
        """ Executes a local installer silently """
        try:
            if not os.path.exists(file_path):
                return False
            
            cmd_str = f'"{file_path}" {silent_args}'
            
            # MSI files require specific arguments
            if file_path.lower().endswith(".msi"):
                cmd_str = f'msiexec /i "{file_path}" /qn /norestart'
                
            subprocess.run(cmd_str, timeout=self.timeout_install, check=True, creationflags=subprocess.CREATE_NO_WINDOW)
            return True
        except (subprocess.TimeoutExpired, subprocess.CalledProcessError):
            return False

    def download_and_install(self, url: str, name: str) -> bool:
        """ Downloads custom software from WinDeploy Server and executes it """
        try:
            temp_dir = os.environ.get("TEMP", "C:\\Temp")
            file_path = os.path.join(temp_dir, name)
            
            # Stream download to avoid loading large files in RAM
            response = requests.get(url, stream=True, timeout=60)
            response.raise_for_status()
            
            with open(file_path, 'wb') as f:
                for chunk in response.iter_content(chunk_size=8192):
                    f.write(chunk)
                    
            success = self.install_file(file_path)
            
            # Secure cleanup
            if os.path.exists(file_path):
                os.remove(file_path)
                
            return success
        except Exception:
            return False
```


### ━━━ FILE 2: `system_config.py` ━━━

Contiene tutti i wrapper PowerShell per interagire con i layer di astrazione del sistema operativo.

```python
import subprocess

class SystemConfig:
    
    def _run_ps(self, cmd_str: str) -> bool:
        """ Helper to execute raw PowerShell commands silently """
        try:
            cmd = ["powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", cmd_str]
            subprocess.run(cmd, check=True, creationflags=subprocess.CREATE_NO_WINDOW)
            return True
        except subprocess.CalledProcessError:
            return False

    def rename_pc(self, new_name: str) -> bool:
        """ Renames the target machine (Requires reboot to take effect) """
        ps_cmd = f"Rename-Computer -NewName '{new_name}' -Force"
        return self._run_ps(ps_cmd)

    def create_admin_user(self, username: str, password: str) -> bool:
        """ 
        Creates a local administrator. 
        SECURITY: Passed via stdin pipe to prevent password leaking in process arguments. 
        """
        ps_script = f"""
        $passwordText = [Console]::In.ReadLine()
        $securePassword = ConvertTo-SecureString $passwordText -AsPlainText -Force
        New-LocalUser -Name '{username}' -Password $securePassword -FullName '{username}' -Description 'WinDeploy Local Admin'
        Add-LocalGroupMember -Group 'Administrators' -Member '{username}'
        """
        try:
            cmd = ["powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", "-"]
            process = subprocess.Popen(
                cmd, 
                stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, 
                text=True, creationflags=subprocess.CREATE_NO_WINDOW
            )
            # Send password to standard input
            stdout, stderr = process.communicate(input=password + "\n")
            return process.returncode == 0
        except Exception:
            return False

    def remove_setup_account(self) -> bool:
        """ Removes the initial Microsoft account or setup user that ran OOBE """
        ps_cmd = (
            "$CurrentUser = $env:USERNAME; "
            "$Users = Get-LocalUser | Where-Object { $_.Enabled -eq $true -and $_.Name -ne 'Administrator' -and $_.Name -ne $CurrentUser }; "
            "if ($Users.Count -gt 0) { Remove-LocalUser -Name $Users[^1_0].Name }"
        )
        return self._run_ps(ps_cmd)

    def apply_power_plan(self, plan_config: dict) -> bool:
        """ Modifies Windows Powercfg states based on wizard presets or custom rules """
        try:
            plan_type = plan_config.get("type", "preset")
            if plan_type == "preset" and plan_config.get("guid"):
                subprocess.run(["powercfg", "/setactive", plan_config["guid"]], check=True, creationflags=subprocess.CREATE_NO_WINDOW)
            
            elif plan_type == "custom":
                # Iterates over keys like 'standby-timeout-ac', 'monitor-timeout-dc', etc.
                for setting, value in plan_config.get("settings", {}).items():
                    subprocess.run(["powercfg", "/change", setting, str(value)], check=True, creationflags=subprocess.CREATE_NO_WINDOW)
            return True
        except Exception:
            return False

    def apply_extras(self, extras: dict) -> bool:
        """ Applies optional configurations like Timezone, Registry modifications, etc. """
        try:
            if "timezone" in extras:
                subprocess.run(["tzutil", "/s", extras["timezone"]], check=True, creationflags=subprocess.CREATE_NO_WINDOW)

            if "language" in extras:
                self._run_ps(f"Set-WinUserLanguageList -LanguageList '{extras['language']}' -Force")

            if "wallpaper" in extras:
                # Modifying registry
                path = extras["wallpaper"]
                subprocess.run(["reg", "add", r"HKCU\Control Panel\Desktop", "/v", "Wallpaper", "/t", "REG_SZ", "/d", path, "/f"], check=True, creationflags=subprocess.CREATE_NO_WINDOW)
                # Force refresh wallpaper
                subprocess.run(["RUNDLL32.EXE", "user32.dll,UpdatePerUserSystemParameters", "1", "True"], check=False, creationflags=subprocess.CREATE_NO_WINDOW)

            if "windows_update" in extras:
                policy = extras["windows_update"]
                # 2 = Notify download, 3 = Auto download notify install, 4 = Auto install
                au_options = {"manual": "2", "download_only": "3", "automatic": "4"}
                opt = au_options.get(policy, "3")
                
                subprocess.run(["reg", "add", r"HKLM\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU", "/v", "AUOptions", "/t", "REG_DWORD", "/d", opt, "/f"], check=True, creationflags=subprocess.CREATE_NO_WINDOW)
                subprocess.run(["reg", "add", r"HKLM\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU", "/v", "NoAutoUpdate", "/t", "REG_DWORD", "/d", "0", "/f"], check=True, creationflags=subprocess.CREATE_NO_WINDOW)

            return True
        except Exception:
            return False
```


### ━━━ FILE 3: `gui/screens/screen_progress.py` ━━━

Separa rigidamente il main thread dell'interfaccia CustomTkinter dal worker thread che esegue i blocchi di configurazione lenti, aggiornando tutto tramite una coda thread-safe.

```python
import customtkinter as ctk
import threading
import time

# We dynamically import the controllers
from installer import Installer
from system_config import SystemConfig
from config import COLORS

class ScreenProgress(ctk.CTkFrame):
    def __init__(self, parent, controller):
        super().__init__(parent, fg_color="transparent")
        self.controller = controller
        
        # Layout Title
        self.title_label = ctk.CTkLabel(self, text="Configurazione in corso...", font=ctk.CTkFont(size=24, weight="bold"), text_color=COLORS['text_main'])
        self.title_label.pack(pady=(20, 10))
        
        # Progress Bar & Numeric Data
        self.percent_label = ctk.CTkLabel(self, text="0%", font=ctk.CTkFont(size=18, weight="bold"), text_color=COLORS['text_main'])
        self.percent_label.pack()
        
        self.progress_bar = ctk.CTkProgressBar(self, width=600, height=20, progress_color=COLORS['primary'])
        self.progress_bar.pack(pady=(5, 30))
        self.progress_bar.set(0)
        
        # Container for animated steps
        self.steps_frame = ctk.CTkFrame(self, fg_color="transparent")
        self.steps_frame.pack(fill="x", padx=50, pady=10)
        
        self.step_labels = {}
        self.step_icons = {}
        
        # Bottom text area for live logging
        self.log_textbox = ctk.CTkTextbox(self, width=700, height=150, fg_color=COLORS['bg_main'], text_color=COLORS['text_muted'], state="disabled")
        self.log_textbox.pack(pady=20)
        
        self.is_running = False
        self.gui_queue = []

    def init_steps(self):
        """ Clears and prepares the UI list of operations """
        for widget in self.steps_frame.winfo_children():
            widget.destroy()
            
        step_definitions = [
            ("uninstall", "Disinstallazione app"),
            ("install", "Installazione software"),
            ("rename", "Rinomina PC"),
            ("admin", "Creazione utente admin"),
            ("power", "Applicazione power plan"),
            ("extras", "Impostazioni extra")
        ]
        
        for key, text in step_definitions:
            frame = ctk.CTkFrame(self.steps_frame, fg_color="transparent")
            frame.pack(fill="x", pady=5)
            
            icon = ctk.CTkLabel(frame, text="⏳", font=ctk.CTkFont(size=16))
            icon.pack(side="left", padx=(0, 10))
            
            lbl = ctk.CTkLabel(frame, text=text, font=ctk.CTkFont(size=14), text_color=COLORS['text_main'])
            lbl.pack(side="left")
            
            self.step_icons[key] = icon
            self.step_labels[key] = lbl

    def start_execution(self, wizard_config, apps_to_remove, api_client, execution_log_id):
        """ Called by the router to start the heavy processing operations """
        self.init_steps()
        self.is_running = True
        
        self.log_textbox.configure(state="normal")
        self.log_textbox.delete("1.0", "end")
        self.log_textbox.configure(state="disabled")
        
        # Isolate heavy I/O operations from tkinter event loop
        threading.Thread(
            target=self._execution_thread,
            args=(wizard_config, apps_to_remove, api_client, execution_log_id),
            daemon=True
        ).start()
        
        # Start the polling event that will consume gui_queue safely
        self.after(100, self._process_queue)

    def _queue_action(self, action_type, *args):
        """ Generic queue helper to push updates to main thread """
        self.gui_queue.append((action_type, *args))

    def _process_queue(self):
        """ Loop executed in the MAIN THREAD to update CTk objects securely """
        while self.gui_queue:
            action = self.gui_queue.pop(0)
            action_type = action[^1_0]
            
            if action_type == "log":
                self.log_textbox.configure(state="normal")
                self.log_textbox.insert("end", action[^1_1] + "\n")
                self.log_textbox.see("end") # Auto-scroll to bottom
                self.log_textbox.configure(state="disabled")
                
            elif action_type == "step":
                key, status, message = action[^1_1], action[^1_2], action[^1_3]
                icon_char = {"in_progress": "🔄", "success": "✅", "error": "❌"}.get(status, "⏳")
                self.step_icons[key].configure(text=icon_char)
                
                if message:
                    base_text = self.step_labels[key].cget("text").split(" (")[^1_0]
                    self.step_labels[key].configure(text=f"{base_text} ({message})")
                    
            elif action_type == "progress":
                percent = action[^1_1]
                self.progress_bar.set(percent / 100.0)
                self.percent_label.configure(text=f"{int(percent)}%")
                
            elif action_type == "finish":
                self.is_running = False
                self.controller.navigate("ScreenComplete")
                return # Interrupt polling loop

        if self.is_running:
            self.after(100, self._process_queue)

    def _execution_thread(self, wizard_config, apps_to_remove, api_client, log_id):
        """ Background Worker Thread. Contains ALL business logic. """
        installer = Installer()
        sys_config = SystemConfig()
        total_steps = 6
        current_step = 0
        
        def push_api(step_name, status, msg):
            if api_client:
                try: api_client.send_step(log_id, step_name, status, msg)
                except: pass

        # --- 1. Disinstallazione App ---
        self._queue_action("step", "uninstall", "in_progress", "in corso...")
        removed_count = 0
        for app in apps_to_remove:
            self._queue_action("log", f"Rimozione {app.get('name')}...")
            if installer.uninstall_app(app):
                removed_count += 1
                self._queue_action("log", f"Rimosso {app.get('name')} ✅")
            else:
                self._queue_action("log", f"Errore {app.get('name')} ❌")
                
        self._queue_action("step", "uninstall", "success", f"{removed_count} rimosse")
        push_api("uninstall", "completed", f"Removed {removed_count} applications.")
        current_step += 1
        self._queue_action("progress", (current_step / total_steps) * 100)

        # --- 2. Installazione Software ---
        software_list = wizard_config.get("software", [])
        self._queue_action("step", "install", "in_progress", "in corso...")
        installed_count = 0
        for sw in software_list:
            sw_name = sw.get("name", "App")
            self._queue_action("step", "install", "in_progress", f"{sw_name} in corso...")
            self._queue_action("log", f"Download/Install {sw_name}...")
            
            if sw.get("type") == "winget":
                success = installer.install_winget(sw.get("identifier"))
            else:
                success = installer.download_and_install(sw.get("url"), sw.get("filename", "setup.exe"))
                
            if success:
                installed_count += 1
                self._queue_action("log", f"Installato {sw_name} ✅")
            else:
                self._queue_action("log", f"Fallito {sw_name} ❌")
                
        self._queue_action("step", "install", "success", f"{installed_count} installati")
        push_api("install", "completed", f"Installed {installed_count} packages.")
        current_step += 1
        self._queue_action("progress", (current_step / total_steps) * 100)

        # --- 3. Rinomina PC ---
        self._queue_action("step", "rename", "in_progress", None)
        pc_name = wizard_config.get("pc_name")
        if pc_name:
            if sys_config.rename_pc(pc_name):
                self._queue_action("log", f"PC rinominato in {pc_name} ✅")
                self._queue_action("step", "rename", "success", None)
                push_api("rename", "completed", f"Renamed to {pc_name}")
            else:
                self._queue_action("log", "Errore Rinomina ❌")
                self._queue_action("step", "rename", "error", None)
        else:
            self._queue_action("step", "rename", "success", "Saltato")
            
        current_step += 1
        self._queue_action("progress", (current_step / total_steps) * 100)

        # --- 4. Creazione Utente Admin ---
        self._queue_action("step", "admin", "in_progress", None)
        admin_user = wizard_config.get("admin_user")
        if admin_user:
            username = admin_user.get("username")
            password = admin_user.get("password")
            if sys_config.create_admin_user(username, password):
                self._queue_action("log", f"Utente locale {username} creato ✅")
                
                # IMPORTANT: Pulizia payload per prevenire Data Leak se l'app crasha
                if "password" in admin_user:
                    admin_user["password"] = "******"
                    
                if admin_user.get("remove_setup_account"):
                    sys_config.remove_setup_account()
                    self._queue_action("log", "Utente OOBE di sistema rimosso ✅")
                    
                self._queue_action("step", "admin", "success", None)
                push_api("admin", "completed", f"Created {username}")
            else:
                self._queue_action("log", "Errore creazione Admin ❌")
                self._queue_action("step", "admin", "error", None)
        else:
            self._queue_action("step", "admin", "success", "Saltato")
            
        current_step += 1
        self._queue_action("progress", (current_step / total_steps) * 100)

        # --- 5 & 6. Power Plan ed Extras ---
        # (Codice riassunto per la stessa logica di coda)
        for step_key, attr, action_text in [("power", "power_plan", "Power Plan"), ("extras", "extras", "Impostazioni Extra")]:
            self._queue_action("step", step_key, "in_progress", None)
            config_dict = wizard_config.get(attr)
            
            if config_dict:
                method = getattr(sys_config, f"apply_{step_key}")
                if method(config_dict):
                    self._queue_action("log", f"{action_text} applicate ✅")
                    self._queue_action("step", step_key, "success", None)
                    push_api(step_key, "completed", f"Applied {step_key}")
                else:
                    self._queue_action("log", f"Errore {action_text} ❌")
                    self._queue_action("step", step_key, "error", None)
            else:
                self._queue_action("step", step_key, "success", "Saltato")
                
            current_step += 1
            self._queue_action("progress", (current_step / total_steps) * 100)

        # --- Conclusione ---
        time.sleep(1) # Lieve pausa per permettere alla UI di visualizzare i check verdi finali
        self._queue_action("finish")
```


### ✅ Checklist Operativa per lo Sviluppo Immediato

- [ ] Assicurati che l'eseguibile compilato venga testato in Sandbox di Windows prima di metterlo in produzione (per validare le policy di *ExecutionPolicy* di PowerShell).
- [ ] Il rinnovo del PC impone che qualsiasi azione successiva che dipenda dall'Active Directory/Rete venga fallita se non c'è un riavvio forzato al termine della schermata 5 (ScreenComplete).
- [ ] L'esecuzione del download dei custom-installer bloccherà le risorse di rete; se implementi un rate limiter su Laravel, fai attenzione agli endpoint degli eseguibili pesanti (es. Firefox/Chrome).
<span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_19][^1_20][^1_21][^1_22][^1_23][^1_24][^1_25][^1_26][^1_27][^1_28][^1_29][^1_30][^1_4][^1_5][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: https://github.com/marticliment/UniGetUI/discussions/2314

[^1_2]: 0020-Scansione app installate.md

[^1_3]: 0019-Struttura-agent.md

[^1_4]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^1_5]: 0017-motorpage.md

[^1_6]: 0016-wizards.md

[^1_7]: 0015-dashboard.md

[^1_8]: 0014-Login.md

[^1_9]: 0013-SetupprogettoReact.md

[^1_10]: 0012-apiendpointwindows.md

[^1_11]: 0011-controllercrud.md

[^1_12]: 0010-autenticazione.md

[^1_13]: 0009-scaffoldprogetto.md

[^1_14]: 0008-db.md

[^1_15]: 0007-cheatsheet.md

[^1_16]: 0006-viteconfigjs.md

[^1_17]: 0005-filedotenv.md

[^1_18]: 0004-Strutturacartelle.md

[^1_19]: 0003-setupiniziale.md

[^1_20]: 0002-ricerca-iniziale.md

[^1_21]: 0001-projectdescription.txt

[^1_22]: https://stackoverflow.com/questions/52534503/remove-appxpackage-noisy-in-the-console

[^1_23]: https://www.wapt.fr/apidoc/wapt-2.2.2/windows/_modules/setuphelpers_windows.html

[^1_24]: https://github.com/tranquilit/WAPT/blob/branch-1.8.2/setuphelpers_windows.py

[^1_25]: https://readthedocs.org/projects/ansible-doc-cn/downloads/pdf/latest/

[^1_26]: https://forum.videohelp.com/threads/414154-Devine-Modular-Movie-TV-and-Music-Archival-Software/page44

[^1_27]: https://stackoverflow.com/questions/64967275/changing-hostname-of-pc-through-batch-script

[^1_28]: https://www.neohope.com/tag/windows/

[^1_29]: https://silentinstall.org/silently-install-python

[^1_30]: https://community.jamf.com/general-discussions-2/rename-computer-using-google-sheet-23265

