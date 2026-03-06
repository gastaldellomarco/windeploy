# agent/system_config.py
import logging
import subprocess

class SystemConfig:
    def __init__(self):
        self.logger = logging.getLogger('windeploy.agent.system_config')
    
    def _run_subprocess(self, cmd, description: str, timeout: int = 300) -> bool:
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
            self.logger.error(f"[TIMEOUT] {description}: superati {timeout} sec")
            return False
        except FileNotFoundError as e:
            self.logger.error(f"[NOT FOUND] Comando non trovato in {description}: {e}")
            return False
        except Exception as e:
            self.logger.error(f"[ERRORE INATTESO] {description}: {e}", exc_info=True)
            return False

    def _run_ps(self, cmd_str: str, description: str) -> bool:
        cmd = ["powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", cmd_str]
        return self._run_subprocess(cmd, description)

    def rename_pc(self, new_name: str) -> bool:
        ps_cmd = f"Rename-Computer -NewName '{new_name}' -Force"
        return self._run_ps(ps_cmd, f"Rinomina PC a: {new_name}")

    def create_admin_user(self, username: str, password: str) -> bool:
        description = f"Creazione utente admin: {username} (password: [REDACTED])"
        self.logger.info(f"[INIZIO] {description}")
        
        # Escape single quotes in username and password for PowerShell string literals
        username_escaped = username.replace("'", "''")
        password_escaped = password.replace("'", "''")
        
        # Use SecureString to avoid exposing password in process arguments
        ps_script = f"""
$securePassword = ConvertTo-SecureString '{password_escaped}' -AsPlainText -Force
New-LocalUser -Name '{username_escaped}' -Password $securePassword -FullName '{username_escaped}' -Description 'WinDeploy Local Admin'
Add-LocalGroupMember -Group 'Administrators' -Member '{username_escaped}'
"""
        try:
            cmd = ["powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", ps_script]
            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=300,
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
            self.logger.error(f"[TIMEOUT] {description}: superati 300 secondi")
            return False
        except Exception as e:
            self.logger.error(f"[ERRORE INATTESO] {description}: {e}", exc_info=True)
            return False

    def remove_setup_account(self) -> bool:
        ps_cmd = (
            "$CurrentUser = $env:USERNAME; "
            "$Users = Get-LocalUser | Where-Object { $_.Enabled -eq $true -and $_.Name -ne 'Administrator' -and $_.Name -ne $CurrentUser }; "
            "if ($Users.Count -gt 0) { Remove-LocalUser -Name $Users[0].Name }"
        )
        return self._run_ps(ps_cmd, "Rimozione account di setup iniziale Microsoft/OOBE")

    def apply_power_plan(self, plan_config: dict) -> bool:
        plan_type = plan_config.get("type", "preset")
        success = True
        
        if plan_type == "preset" and plan_config.get("guid"):
            success &= self._run_subprocess(["powercfg", "/setactive", plan_config["guid"]], f"Applicazione Power Plan Guid: {plan_config['guid']}")
        elif plan_type == "custom":
            for setting, value in plan_config.get("settings", {}).items():
                success &= self._run_subprocess(["powercfg", "/change", setting, str(value)], f"Modifica Powercfg: {setting}={value}")
        return success

    def apply_extras(self, extras: dict) -> bool:
        success = True
        if "timezone" in extras:
            success &= self._run_subprocess(["tzutil", "/s", extras["timezone"]], f"Impostazione fuso orario: {extras['timezone']}")
        if "language" in extras:
            success &= self._run_ps(f"Set-WinUserLanguageList -LanguageList '{extras['language']}' -Force", f"Impostazione lingua: {extras['language']}")
        if "wallpaper" in extras:
            path = extras["wallpaper"]
            success &= self._run_subprocess(["reg", "add", r"HKCU\Control Panel\Desktop", "/v", "Wallpaper", "/t", "REG_SZ", "/d", path, "/f"], "Impostazione Wallpaper RegKey")
            self._run_subprocess(["RUNDLL32.EXE", "user32.dll,UpdatePerUserSystemParameters", "1", "True"], "Aggiornamento Desktop")
        if "windows_update" in extras:
            opt = {"manual": "2", "download_only": "3", "automatic": "4"}.get(extras["windows_update"], "3")
            success &= self._run_subprocess(["reg", "add", r"HKLM\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU", "/v", "AUOptions", "/t", "REG_DWORD", "/d", opt, "/f"], f"Policy WU AUOptions={opt}")
            success &= self._run_subprocess(["reg", "add", r"HKLM\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU", "/v", "NoAutoUpdate", "/t", "REG_DWORD", "/d", "0", "/f"], "Policy WU NoAutoUpdate=0")
        return success
