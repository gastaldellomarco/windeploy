# File: system_config.py
# Path: windeploy\agent\system_config.py

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
            "if ($Users.Count -gt 0) { Remove-LocalUser -Name $Users[0].Name }"
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
