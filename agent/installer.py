# File: installer.py
# Path: windeploy\agent\installer.py

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
