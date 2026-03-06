# agent/installer.py
import logging
import subprocess
import os
import requests

class Installer:
    def __init__(self):
        self.timeout_uninstall = 120
        self.timeout_install = 300
        self.logger = logging.getLogger('windeploy.agent.installer')

    def _run_cmd(self, cmd, description: str, timeout: int) -> bool:
        self.logger.info(f"[INIZIO] {description}")
        try:
            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=timeout,
                check=False,
                creationflags=subprocess.CREATE_NO_WINDOW
            )
            if result.returncode == 0:
                self.logger.info(f"[OK] {description}: returncode=0")
                if result.stdout.strip():
                    self.logger.debug(f"[STDOUT] {result.stdout.strip()}")
                return True
            else:
                self.logger.warning(f"[FAIL] {description}: returncode={result.returncode}")
                if result.stderr.strip():
                    self.logger.warning(f"[STDERR] {result.stderr.strip()}")
                if result.stdout.strip():
                    self.logger.debug(f"[STDOUT] {result.stdout.strip()}")
                return False
                
        except subprocess.TimeoutExpired:
            self.logger.error(f"[TIMEOUT] {description}: superati {timeout} secondi")
            return False
        except FileNotFoundError as e:
            self.logger.error(f"[NOT FOUND] Comando non trovato in {description}: {e}")
            return False
        except Exception as e:
            self.logger.error(f"[ERRORE INATTESO] {description}: {e}", exc_info=True)
            return False

    def uninstall_app(self, app: dict) -> bool:
        app_name = app.get("name", "App sconosciuta")
        
        if app.get("is_store_app") and app.get("PackageFullName"):
            cmd = ["powershell", "-NoProfile", "-Command", f"Remove-AppxPackage -Package '{app['PackageFullName']}' -ErrorAction Stop"]
            if self._run_cmd(cmd, f"Disinstallazione Store App: {app_name}", self.timeout_uninstall):
                return True

        if app.get("QuietUninstallString"):
            if self._run_cmd(app["QuietUninstallString"], f"Disinstallazione Quiet: {app_name}", self.timeout_uninstall):
                return True
                
        if app.get("UninstallString"):
            cmd_str = app["UninstallString"]
            cmd_lower = cmd_str.lower()
            if "msiexec" in cmd_lower and "/qn" not in cmd_lower:
                cmd_str += " /qn"
            elif "/s" not in cmd_lower and "/silent" not in cmd_lower:
                cmd_str += " /S"
                
            if self._run_cmd(cmd_str, f"Disinstallazione Standard: {app_name}", self.timeout_uninstall):
                return True
                
        return False

    def install_winget(self, package_id: str) -> bool:
        cmd = ["winget", "install", "--id", package_id, "--silent", "--accept-package-agreements", "--accept-source-agreements"]
        description = f"Installazione winget: {package_id}"
        self.logger.info(f"[INIZIO] {description}")
        
        try:
            result = subprocess.run(
                cmd, capture_output=True, text=True,
                timeout=self.timeout_install, check=False, creationflags=subprocess.CREATE_NO_WINDOW
            )
            
            stdout_clean = result.stdout.strip() if result.stdout else ""
            stderr_clean = result.stderr.strip() if result.stderr else ""
            
            if result.returncode == 0:
                if "Successfully installed" in stdout_clean or "installato con successo" in stdout_clean:
                    self.logger.info(f"[OK] {description}: Installato correttamente")
                elif "already installed" in stdout_clean or "già installato" in stdout_clean:
                    self.logger.info(f"[OK] {description}: Già installato (saltato)")
                else:
                    self.logger.info(f"[OK] {description}: returncode=0")
                    
                if stdout_clean:
                    self.logger.debug(f"[STDOUT] {stdout_clean}")
                return True
            else:
                if "No package found" in stdout_clean or "Nessun pacchetto" in stdout_clean:
                    self.logger.warning(f"[FAIL] {description}: Pacchetto inesistente su repository winget")
                else:
                    self.logger.warning(f"[FAIL] {description}: returncode={result.returncode}")
                
                if stderr_clean:
                    self.logger.warning(f"[STDERR] {stderr_clean}")
                if stdout_clean:
                    self.logger.debug(f"[STDOUT] {stdout_clean}")
                return False

        except subprocess.TimeoutExpired:
            self.logger.error(f"[TIMEOUT] {description}: superati {self.timeout_install} secondi")
            return False
        except Exception as e:
            self.logger.error(f"[ERRORE INATTESO] {description}: {e}", exc_info=True)
            return False

    def install_file(self, file_path: str, silent_args: str = "/S") -> bool:
        if not os.path.exists(file_path):
            self.logger.error(f"[NOT FOUND] Installer non trovato su disco: {file_path}")
            return False
            
        cmd_str = f'"{file_path}" {silent_args}'
        if file_path.lower().endswith(".msi"):
            cmd_str = f'msiexec /i "{file_path}" /qn /norestart'
            
        return self._run_cmd(cmd_str, f"Installazione binario locale: {os.path.basename(file_path)}", self.timeout_install)

    def download_and_install(self, url: str, name: str) -> bool:
        self.logger.info(f"[INIZIO] Download software custom: {name}")
        try:
            temp_dir = os.environ.get("TEMP", "C:\\Temp")
            file_path = os.path.join(temp_dir, name)
            
            response = requests.get(url, stream=True, timeout=60)
            response.raise_for_status()
            
            with open(file_path, 'wb') as f:
                for chunk in response.iter_content(chunk_size=8192):
                    f.write(chunk)
                    
            self.logger.info(f"[OK] Download completato: {file_path}")
            success = self.install_file(file_path)
            
            if os.path.exists(file_path):
                os.remove(file_path)
                
            return success
        except Exception as e:
            self.logger.error(f"[ERRORE] download_and_install fallito: {e}", exc_info=True)
            return False
