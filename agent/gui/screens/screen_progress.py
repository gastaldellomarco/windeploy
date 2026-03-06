import customtkinter as ctk
import threading
import time
from datetime import datetime

# We dynamically import the controllers
from installer import Installer
from system_config import SystemConfig
from config import COLORS
from api_client import APIClient

class ScreenProgress(ctk.CTkFrame):
    def __init__(self, master=None, parent=None, controller=None, wizard_config=None, on_complete=None, on_error=None):
        # Support both old (parent/controller) and new (master/callbacks) initialization styles
        super().__init__(master or parent, fg_color="transparent")
        
        self.controller = controller  # For old-style backwards compatibility
        self.wizard_config = wizard_config or {}
        self.on_complete = on_complete
        self.on_error = on_error
        
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
        
        # Auto-start execution if wizard_config is provided (new-style callbacks)
        if wizard_config:
            self.after(100, self._auto_start_execution)

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

    def on_show(self, app_state: dict):
        """When the ScreenProgress is shown, if app_state includes data start execution."""
        wizard_config = app_state.get('wizard_config')
        execution_log_id = app_state.get('execution_log_id')
        token = app_state.get('auth_token')
        if wizard_config and execution_log_id:
            api_client = APIClient()
            if token:
                api_client.set_token(token)

            # Determine apps to remove from wizard_config extras
            apps_to_remove = wizard_config.get('uninstall', [])
            self.start_execution(wizard_config, apps_to_remove, api_client, execution_log_id)

    def _queue_action(self, action_type, *args):
        """ Generic queue helper to push updates to main thread """
        self.gui_queue.append((action_type, *args))

    def _auto_start_execution(self):
        """Auto-start execution when instantiated with new-style callbacks."""
        if self.wizard_config and not self.is_running:
            apps_to_remove = self.wizard_config.get('bloatware_to_remove', [])
            api_client = APIClient()
            execution_log_id = self.wizard_config.get('wizard_code', '')
            self.start_execution(self.wizard_config, apps_to_remove, api_client, execution_log_id)

    def _process_queue(self):
        """ Loop executed in the MAIN THREAD to update CTk objects securely """
        while self.gui_queue:
            action = self.gui_queue.pop(0)
            action_type = action[0]
            
            if action_type == "log":
                self.log_textbox.configure(state="normal")
                self.log_textbox.insert("end", action[1] + "\n")
                self.log_textbox.see("end") # Auto-scroll to bottom
                self.log_textbox.configure(state="disabled")
                
            elif action_type == "step":
                key, status, message = action[1], action[2], action[3]
                icon_char = {"in_progress": "🔄", "success": "✅", "error": "❌"}.get(status, "⏳")
                self.step_icons[key].configure(text=icon_char)
                
                if message:
                    base_text = self.step_labels[key].cget("text").split(" (")[0]
                    self.step_labels[key].configure(text=f"{base_text} ({message})")
                    
            elif action_type == "progress":
                percent = action[1]
                self.progress_bar.set(percent / 100.0)
                self.percent_label.configure(text=f"{int(percent)}%")
                
            elif action_type == "finish":
                self.is_running = False
                
                # Generate HTML report and save it
                from report_generator import ReportGenerator
                from pathlib import Path
                import os
                
                report_path = None
                try:
                    # Prepare report data
                    report_data = {
                        "tecnico": "Agent",
                        "data_ora": datetime.now().isoformat(),
                        "codice_wizard": self.wizard_config.get("wizard_code", ""),
                        "durata": "~0 minuti",  # TODO: Track actual duration
                        "pc": {
                            "nome_originale": "N/D",
                            "nome_nuovo": self.wizard_config.get("pc_name", "N/D"),
                            "cpu": "N/D",
                            "ram_gb": "N/D",
                            "disco_gb": "N/D",
                            "windows": "Windows 10/11",
                        },
                        "steps": [],
                        "software_installati": self.wizard_config.get("software", []),
                        "app_rimosse": self.wizard_config.get("bloatware_to_remove", []),
                        "power_plan": self.wizard_config.get("power_plan", {}),
                        "agent_version": "1.0.0",
                    }
                    
                    # Generate HTML report
                    html_content = ReportGenerator.generate(report_data)
                    
                    # Save to %PROGRAMDATA%\WinDeploy\reports\
                    reports_dir = Path(os.environ.get('PROGRAMDATA', 'C:\\ProgramData')) / 'WinDeploy' / 'reports'
                    reports_dir.mkdir(parents=True, exist_ok=True)
                    
                    report_filename = f"report_{self.wizard_config.get('wizard_code', 'unknown')}_{datetime.now().strftime('%Y%m%d_%H%M%S')}.html"
                    report_path = str(reports_dir / report_filename)
                    
                    with open(report_path, 'w', encoding='utf-8') as f:
                        f.write(html_content)
                        
                except Exception as e:
                    print(f"[WARN] Impossibile generare report: {e}")
                
                # Build result dict from execution context
                result = {
                    "success": True,
                    "steps_ok": 6,  # TODO: Track this from execution
                    "steps_total": 6,
                    "steps_failed": 0,
                    "duration_seconds": 0,
                    "wizard_code": self.wizard_config.get("wizard_code", ""),
                    "report_path": report_path,
                }
                
                # Call new-style callback if available, else fall back to old-style navigation
                if self.on_complete:
                    self.on_complete(result)
                elif self.controller:
                    self.controller.navigate("ScreenComplete", {"result": result})
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
                # Construct the correct method name: apply_power_plan or apply_extras
                method_name = f"apply_{step_key}_plan" if step_key == "power" else f"apply_{step_key}"
                method = getattr(sys_config, method_name, None)
                if method and callable(method):
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
