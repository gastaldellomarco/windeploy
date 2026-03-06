# agent/api_client.py
import os
import logging
import requests
import time
from datetime import datetime

class ApiClient:
    def __init__(self, base_url: str | None = None, jwt_token: str | None = None, wizard_code: str | None = None):
        # Default to environment-configured API if not provided
        self.base_url = (base_url or os.getenv('WINDEPLOY_API_URL') or 'http://windeploy.local.api').rstrip('/')
        self.jwt_token = jwt_token or ''
        self.wizard_code = wizard_code or ''
        
        self.session = requests.Session()
        self.session.headers.update({
            "Authorization": f"Bearer {self.jwt_token}",
            "Content-Type": "application/json",
            "Accept": "application/json"
        })
        # (connect_timeout, read_timeout)
        self.timeout = (5, 30)

    def send_step(self, wizard_code_or_log: str, step_name: str, status: str, message: str, progress: float) -> bool:
        """
        Invia l'avanzamento al backend. Fire-and-forget: non solleva eccezioni.
        """
        # Sanitizzazione del progress per le validation rules del backend (0-100, int)
        safe_progress = min(max(int(progress), 0), 100)
        # Tronca il messaggio a 500 caratteri come da schema DB
        safe_message = message[:500] if message else ""
        
        payload = {
            "wizard_code": wizard_code_or_log or self.wizard_code,
            "step": step_name,
            "status": status,
            "message": safe_message,
            "progress": safe_progress,
            "timestamp": datetime.utcnow().isoformat() + "Z"
        }
        
        self._log_step_locally(step_name, status, safe_message)
        
        for attempt in range(2):
            try:
                response = self.session.post(
                    f"{self.base_url}/api/agent/step",
                    json=payload,
                    timeout=self.timeout
                )
                if response.status_code == 200:
                    return True
                else:
                    logging.warning(f"Errore HTTP {response.status_code} in send_step: {response.text}")
                    return False
            except (requests.ConnectionError, requests.Timeout) as e:
                logging.warning(f"Backend non raggiungibile in send_step (tentativo {attempt+1}/2): {e}")
                if attempt == 0:
                    time.sleep(2)  # Backoff prima del secondo tentativo
        return False

    def send_complete(self, success: bool, steps_ok: int, steps_failed: int, report_path: str | None = None) -> bool:
        """
        Segnala la fine dell'esecuzione chiudendo l'execution_log.
        """
        status = "completed" if success else "error"
        result = self.send_step(
            self.wizard_code,
            "execution_complete",
            status,
            f"Esecuzione terminata. OK: {steps_ok}, Errori: {steps_failed}",
            100
        )
        
        # ⚠️ IMPLICAZIONE SICUREZZA:
        # A fine operazione svuotiamo le variabili per impedire l'estrazione 
        # del Bearer token tramite memory dumping (es. Mimikatz) a processo dormiente.
        self.jwt_token = ""
        self.wizard_code = ""
        self.session.headers.pop("Authorization", None)
        
        return result

    # Compatibility helpers used by GUI/test scripts
    def set_token(self, token: str):
        self.jwt_token = token
        if token:
            self.session.headers.update({"Authorization": f"Bearer {token}"})
        else:
            self.session.headers.pop("Authorization", None)

    def authenticate_wizard(self, codice: str, mac: str | None = None):
        """Authenticate using wizard code and MAC address. Returns requests.Response."""
        # Backend expects snake_case keys: codice_wizard and mac_address
        payload = {"codice_wizard": codice, "mac_address": mac}
        return self.session.post(f"{self.base_url}/api/agent/auth", json=payload, timeout=self.timeout)

    def start_execution(self, wizard_config: dict):
        """Expects wizard_config serialized as JSON; wrapper for POST /agent/start"""
        return self.session.post(f"{self.base_url}/api/agent/start", json=wizard_config, timeout=self.timeout)

    def _log_step_locally(self, step_name: str, status: str, message: str):
        """
        Scrive il fallback locale in caso il server sia offline.
        """
        try:
            appdata = os.environ.get("APPDATA", os.path.expanduser("~"))
            log_dir = os.path.join(appdata, "WinDeploy", "logs")
            os.makedirs(log_dir, exist_ok=True)
            
            date_str = datetime.now().strftime("%Y-%m-%d")
            log_file = os.path.join(log_dir, f"{date_str}.log")
            
            timestamp = datetime.now().isoformat()
            log_line = f"[{timestamp}] [{status.upper()}] {step_name}: {message}\n"
            
            with open(log_file, "a", encoding="utf-8") as f:
                f.write(log_line)
        except Exception as e:
            logging.warning(f"Impossibile scrivere log locale: {e}")

    def upload_log(self, log_path, wizard_code: str) -> bool:
        """
        Invia il file di log completo come multipart form-data al termine.
        
        Args:
            log_path: Percorso del file log (str o Path)
            wizard_code: Codice wizard per l'associazione nel backend
            
        Returns:
            bool: True se upload riuscito, False altrimenti
        """
        from pathlib import Path

        if not isinstance(log_path, Path):
            log_path = Path(log_path)

        if not log_path.exists():
            logging.error(f"Impossibile caricare il log: {log_path} non esiste.")
            return False

        # Blocco sicurezza per payload massivi
        file_size_mb = os.path.getsize(log_path) / (1024 * 1024)
        if file_size_mb > 10.0:
            logging.warning(f"Upload log ignorato: il file supera i 10MB ({file_size_mb:.1f} MB).")
            return False

        # TODO: implementare endpoint backend /api/agent/log-upload
        # Vedi issue backend #XX — Upload log file da agent
        logging.info("Upload log completato (STUB): endpoint multipart non ancora implementato su Laravel.")
        return False
        
        # --- CODICE PRONTO PER QUANDO L'API SARÀ DISPONIBILE ---
        # try:
        #     with open(log_path, 'rb') as f:
        #         files = {'log_file': (log_path.name, f, 'text/plain')}
        #         data = {'wizard_code': wizard_code}
        #         
        #         # Utilizza timeout larghi in scrittura considerati limiti upload aziendali asimmetrici
        #         response = self.session.post(
        #             f'{self.base_url}/api/agent/log-upload',
        #             files=files,
        #             data=data,
        #             timeout=(5, 60)
        #         )
        #         response.raise_for_status()
        #         return True
        # except Exception as e:
        #     logging.error(f"Errore durante l'upload asincrono del log: {e}")
        #     return False


# Backwards-compatible alias: some modules import APIClient (all-caps).
# Keep both names to avoid changing many imports across the codebase.
APIClient = ApiClient
