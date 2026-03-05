"""agent/api_client.py
Small class-based HTTP client for the agent UI.

Provides a thin wrapper around requests so screens can delegate network
calls and receive the raw Response object for status-code-level handling.
"""
from typing import Optional
import requests
import json
import socket
import os
import platform
import shutil

try:
    import psutil
except Exception:
    psutil = None


class APIClient:
    """Minimal HTTP client used by the GUI.

    Contract (authenticate_wizard):
    - inputs: codicewizard (str), macaddress (str)
    - output: requests.Response (raw response)
    - errors: raises requests exceptions (ConnectionError, Timeout, etc.)
    """

    def __init__(self, base_url: Optional[str] = None, timeout: Optional[int] = None):
        # Import config lazily to avoid circular import problems during module
        # import time when UI modules import this client.
        try:
            from agent.config import API_URL as _API_URL, REQUESTS_TIMEOUT as _REQUESTS_TIMEOUT
        except Exception:
            _API_URL = None
            _REQUESTS_TIMEOUT = None

        self.base_url = base_url or _API_URL
        self.timeout = timeout or _REQUESTS_TIMEOUT
        self.session = requests.Session()
        # Always request JSON from the API endpoints to avoid HTML responses
        self.session.headers.update({"Accept": "application/json"})
        self.token = None

    def set_token(self, token: str):
        """Attach a Bearer token to be used for subsequent requests."""
        self.token = token
        self.session.headers.update({"Authorization": f"Bearer {token}"})

    def send_step(self, execution_log_id: int, step_name: str, status: str, message: str = None) -> requests.Response:
        """POST /agent/step to update execution progress.

        Payload shape expected by backend:
        {
          "execution_log_id": <int>,
          "step": { "name": <str>, "status": <str>, "message": <str> }
        }
        """
        payload = {
            "execution_log_id": execution_log_id,
            "step": {
                "nome": step_name,
                "status": status,
                "message": message,
            }
        }
        return self.session.post(f"{self.base_url}/agent/step", json=payload, timeout=self.timeout)

    def _get_disk_gb(self):
        try:
            total, used, free = shutil.disk_usage('/')
            return round(total / (1024 ** 3), 1)
        except Exception:
            return None

    def _get_ram_gb(self):
        try:
            if psutil:
                return round(psutil.virtual_memory().total / (1024 ** 3), 1)
            # fallback: try to read from environment or return None
            return None
        except Exception:
            return None

    def _get_cpu_info(self):
        try:
            if psutil:
                return psutil.cpu_count(logical=False) or psutil.cpu_count()
            # fallback to platform processor info
            return platform.processor() or None
        except Exception:
            return None

    def start_execution(self, wizard_config: dict, token: Optional[str] = None) -> requests.Response:
        """POST /agent/start including pc_info payload.

        Builds a `pc_info` object with hostname, cpu, ram, disk, windowsversion.
        Returns the raw requests.Response so caller can inspect status and body.
        """
        # Normalize types to what backend validation expects:
        # - cpu: string
        # - ram: integer (GB)
        # - disco: integer (GB)
        raw_cpu = self._get_cpu_info()
        raw_ram = self._get_ram_gb()
        raw_disco = self._get_disk_gb()

        cpu = str(raw_cpu) if raw_cpu is not None else None
        try:
            ram = int(raw_ram) if raw_ram is not None else None
        except Exception:
            ram = None
        try:
            disco = int(raw_disco) if raw_disco is not None else None
        except Exception:
            disco = None

        pc_info = {
            "nome_originale": socket.gethostname() or os.environ.get("COMPUTERNAME", "PC-NON-SPECIFICATO"),
            "cpu": cpu,
            "ram": ram,
            "disco": disco,
            "windows_version": platform.release(),
        }

        # backend expects `pc_info` snake_case key
        payload = {"pc_info": pc_info}

        # Debug: print the payload that will be sent to /agent/start so we can
        # verify the presence of pcinfo.nomeoriginale (helps debug 422 validation).
        try:
            print(f"POST /agent/start payload: {json.dumps(payload, ensure_ascii=False)}", flush=True)
        except Exception:
            # best-effort logging; don't fail the request if printing fails
            try:
                print("POST /agent/start payload: <unserializable payload>", flush=True)
            except Exception:
                pass

        if token:
            self.set_token(token)

        return self.session.post(f"{self.base_url}/agent/start", json=payload, timeout=self.timeout)

    def authenticate_wizard(self, codicewizard: str, macaddress: str) -> requests.Response:
        """POST /agent/auth with JSON payload and return the raw Response.

        This method intentionally returns the raw requests.Response so the
        caller can inspect status_code, headers and body as needed.
        """
        # The backend expects snake_case keys: codice_wizard and mac_address
        payload = {
            "codice_wizard": codicewizard,
            "mac_address": macaddress,
        }
        return self.session.post(f"{self.base_url}/agent/auth", json=payload, timeout=self.timeout)
