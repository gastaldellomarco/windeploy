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
                    num_subkeys = winreg.QueryInfoKey(key)[0]
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
            return winreg.QueryValueEx(key, value_name)[0]
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
